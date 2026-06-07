<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ErrorCodes;
use App\Exceptions\CertusException;
use App\Http\Resources\ActivationResource;
use App\Models\Activation;
use App\Models\ActivationHistorique;
use App\Models\Licence;
use App\Services\CleService;
use App\Services\FingerprintService;
use App\Services\SignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Activation, heartbeat et révocation des instances machines.
 *
 * Routes :
 *   POST   /api/v1/activations                     → activer()    [api.key]
 *   POST   /api/v1/activations/{id}/heartbeat      → heartbeat()  [api.key]
 *   DELETE /api/v1/activations/{id}                → revoquer()   [api.key]
 *   GET    /api/v1/licences/{licenceId}/activations → index()     [api.key]
 */
class ActivationController extends BaseApiController
{
    public function __construct(
        private readonly CleService         $cleService,
        private readonly FingerprintService $fingerprintService,
        private readonly SignalService      $signalService,
    ) {}

    /**
     * Active une licence sur une machine cliente.
     *
     * Flux de sécurité :
     *   1. Validation des champs
     *   2. Décodage et vérification de la clé (Signal 4)
     *   3. Vérification du fingerprint (format + blacklist)
     *   4. Vérification du nonce anti-rejeu (Signal 3)
     *   5. Chargement de la licence depuis la BDD
     *   6. Détection de clonage (Signal 1)
     *   7. Vérification du quota
     *   8. Création de l'activation (transaction)
     */
    public function activer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cle'              => ['required', 'string'],
            'fingerprint_hash' => ['required', 'string'],
            'nonce'            => ['required', 'string', 'min:16', 'max:128'],
            'hostname'         => ['nullable', 'string', 'max:191'],
            'metadata'         => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return $this->erreur(
                ErrorCodes::VALIDATION_ECHOUEE,
                'Données invalides.',
                ['erreurs' => $validator->errors()->toArray()],
            );
        }

        $donnees          = $validator->validated();
        $fingerprintHash  = $donnees['fingerprint_hash'];
        $nonce            = $donnees['nonce'];

        try {
            // Étape 2 : décodage clé → récupération de l'organisation_id et du produit
            $payload = $this->cleService->decoder($donnees['cle']);

            // Étape 3 : validation fingerprint
            $this->fingerprintService->valider($fingerprintHash);
            $this->fingerprintService->verifierBlacklist($fingerprintHash);

            // Étape 4 : anti-rejeu
            $this->signalService->detecterRejeu($nonce, $fingerprintHash);

            // Étape 5 : licence en BDD
            $licence = Licence::where('organisation_id', $payload['organisation_id'])
                ->where('produit', $payload['produit'])
                ->where('statut', 'active')
                ->first();

            if (! $licence) {
                return $this->erreur(ErrorCodes::LICENCE_INTROUVABLE, 'Licence active introuvable.', [], 404);
            }

            if (! $licence->isActive()) {
                return $this->erreur(ErrorCodes::LICENCE_EXPIREE, 'La licence est expirée ou hors période.', [], 403);
            }

            // Étape 6 : détection clonage
            $this->signalService->detecterClonage($fingerprintHash, $licence->id);

            // Étape 7 : quota
            if (! $licence->quotaDisponible()) {
                return $this->erreur(ErrorCodes::LICENCE_QUOTA_DEPASSE, 'Quota maximum d\'activations atteint.', [], 403);
            }

            // Étape 8 : création atomique
            $activation = DB::transaction(function () use ($licence, $fingerprintHash, $donnees, $request) {
                $activation = Activation::create([
                    'licence_id'       => $licence->id,
                    'fingerprint_hash' => $fingerprintHash,
                    'ip_address'       => $request->ip(),
                    'hostname'         => $donnees['hostname'] ?? null,
                    'statut'           => 'active',
                    'dernier_signal'   => now(),
                    'metadata'         => $donnees['metadata'] ?? null,
                ]);

                $licence->increment('activations_count');

                ActivationHistorique::create([
                    'licence_id'       => $licence->id,
                    'activation_id'    => $activation->id,
                    'action'           => 'activation',
                    'fingerprint_hash' => $fingerprintHash,
                    'ip_address'       => $request->ip(),
                    'details'          => ['hostname' => $donnees['hostname'] ?? null],
                    'survenu_le'       => now(),
                ]);

                return $activation;
            });

        } catch (CertusException $e) {
            return $this->erreur($e->getCodeErreur(), $e->getMessage(), $e->getContexte(), $e->getHttpStatus());
        }

        return $this->cree(
            new ActivationResource($activation),
            ['message' => 'Licence activée avec succès.'],
        );
    }

    /**
     * Signal de vie périodique envoyé par le logiciel installé.
     * Met à jour dernier_signal et vérifie que la licence est toujours valide.
     */
    public function heartbeat(Request $request, int $activationId): JsonResponse
    {
        $activation = Activation::with('licence')->findOrFail($activationId);

        if (! $activation->estActive()) {
            return $this->erreur(ErrorCodes::ACTIVATION_IMPOSSIBLE, 'Activation révoquée.', [], 403);
        }

        if (! $activation->licence->isActive()) {
            return $this->erreur(ErrorCodes::LICENCE_EXPIREE, 'La licence associée est expirée.', [], 403);
        }

        $activation->update(['dernier_signal' => now()]);

        return $this->succes(
            new ActivationResource($activation),
            ['prochaine_verification' => now()->addMinutes(30)->toIso8601String()],
        );
    }

    /** Révoque une activation (libère le slot sur la licence). */
    public function revoquer(Request $request, int $activationId): JsonResponse
    {
        $activation = Activation::with('licence')->findOrFail($activationId);

        if (! $activation->estActive()) {
            return $this->erreur(ErrorCodes::ACTIVATION_IMPOSSIBLE, "L'activation est déjà révoquée.");
        }

        DB::transaction(function () use ($activation, $request) {
            $activation->update(['statut' => 'revoquee']);
            $activation->licence->decrement('activations_count');

            ActivationHistorique::create([
                'licence_id'       => $activation->licence_id,
                'activation_id'    => $activation->id,
                'action'           => 'revocation',
                'fingerprint_hash' => $activation->fingerprint_hash,
                'ip_address'       => $request->ip(),
                'details'          => [],
                'survenu_le'       => now(),
            ]);
        });

        return $this->succes(
            new ActivationResource($activation->fresh()),
            ['message' => "Activation #{$activationId} révoquée."],
        );
    }

    /** Liste les activations d'une licence. */
    public function index(Request $request, int $licenceId): JsonResponse
    {
        $licence     = Licence::findOrFail($licenceId);
        $activations = $licence->activations()->get();

        return $this->liste(ActivationResource::collection($activations), [
            'total' => $activations->count(),
        ]);
    }
}
