<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ErrorCodes;
use App\Models\Activation;
use App\Models\ActivationHistorique;
use App\Models\AuditLog;
use App\Models\BlacklistAntirejeu;
use App\Models\Licence;
use App\Models\Organisation;
use App\Services\CleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Émission et cycle de vie des licences Experto — Parcours 1.
 *
 * Routes (protégées par api.key.admin) :
 *   POST  /api/v1/licences                           → store()
 *   GET   /api/v1/licences/{licence_id}              → show()
 *   POST  /api/v1/licences/{licence_id}/revoquer     → revoquer()
 *   GET   /api/v1/licences/{licence_id}/audit        → audit()
 */
class LicenceController extends BaseApiController
{
    public function __construct(private readonly CleService $cleService) {}

    /**
    * Émet une nouvelle licence et génère la clé XXXXX-XXXXX-XXXXX-XXXXX-XXXXX.
    *
    * Flux :
    *   1. Valider les champs
    *   2. Charger l'organisation → récupérer org_index_b36 pour G1 de la clé
    *   3. Appeler CleService::generer() → {cle, anti_rejeu, crc_g5, cle_hash_sha256}
    *   4. INSERT licences — licence_id généré par le modèle, anti_rejeu du CleService
    *   5. INSERT audit_log ACTION_GENERATION
    *   6. Retourner 201 {licence_id, cle, anti_rejeu} — cle_hash_sha256 NON exposé
    */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'org_id'          => ['required', 'string', 'exists:organisations,org_id'],
            'type_licence'    => ['required', 'string', 'in:1,2,3,4,9'],
            'nb_postes'       => ['required', 'integer', 'min:0'],
            'nb_sites'        => ['required', 'integer', 'min:0'],
            'nb_projets'      => ['required', 'integer', 'min:0'],
            'date_expiration' => ['required', 'date', 'after:today'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return $this->erreur(
                ErrorCodes::VALIDATION_ECHOUEE,
                'Données invalides.',
                ['erreurs' => $validator->errors()->toArray()],
                422,
            );
        }

        $donnees = $validator->validated();
        $acteur  = $this->acteurCourant($request);

        $organisation = Organisation::find($donnees['org_id']);
        if ($organisation === null) {
            return $this->erreur(
                ErrorCodes::ORGANISATION_INTROUVABLE,
                "Organisation {$donnees['org_id']} introuvable.",
                [],
                404,
            );
        }

        $payload = $this->cleService->generer([
            'org_index_b36'  => $organisation->org_index_b36,
            'nb_postes'      => $donnees['nb_postes'],
            'nb_sites'       => $donnees['nb_sites'],
            'nb_projets'     => $donnees['nb_projets'],
            'type_licence'   => $donnees['type_licence'],
            'date_expiration' => $donnees['date_expiration'],
            'version_format' => 1,
        ]);

        $licence = Licence::create([
            'org_id'          => $organisation->org_id,
            'type_licence'    => $donnees['type_licence'],
            'nb_postes'       => $donnees['nb_postes'],
            'nb_sites'        => $donnees['nb_sites'],
            'nb_projets'      => $donnees['nb_projets'],
            'date_expiration' => $donnees['date_expiration'],
            'version_format'  => 1,
            'anti_rejeu'      => $payload['anti_rejeu'],   // synchronisé avec la clé
            'cle_hash_sha256' => $payload['cle_hash_sha256'],
            'crc_g5'          => $payload['crc_g5'],
            'notes'           => $donnees['notes'] ?? null,
            'cree_par'        => $acteur,
        ]);

        AuditLog::enregistrer(
            licenceId: $licence->licence_id,
            action:    AuditLog::ACTION_GENERATION,
            acteur:    $acteur,
            ipSource:  $request->ip(),
            detail:    [
                'org_id'       => $organisation->org_id,
                'type_licence' => $licence->type_libelle,
                'nb_postes'    => $licence->nb_postes,
                'nb_sites'     => $licence->nb_sites,
                'nb_projets'   => $licence->nb_projets,
                'expiration'   => $licence->date_expiration?->toDateString(),
            ],
        );

        return $this->cree([
            'licence_id' => $licence->licence_id,
            'cle'        => $payload['cle'],
            'anti_rejeu' => $payload['anti_rejeu'],
        ]);
    }

    /**
     * Retourne tous les champs d'une licence, sauf cle_hash_sha256.
     */
    public function show(Request $request, string $licence_id): JsonResponse
    {
        $licence = Licence::with('organisation')->find($licence_id);

        if ($licence === null) {
            return $this->erreur(
                ErrorCodes::LICENCE_INTROUVABLE,
                "Licence {$licence_id} introuvable.",
                [],
                404,
            );
        }

        return $this->succes([
            'licence_id'           => $licence->licence_id,
            'org_id'               => $licence->org_id,
            'type_licence'         => $licence->type_licence,
            'type_libelle'         => $licence->type_libelle,
            'nb_postes'            => $licence->nb_postes,
            'nb_sites'             => $licence->nb_sites,
            'nb_projets'           => $licence->nb_projets,
            'date_emission'        => $licence->date_emission?->toDateString(),
            'date_expiration'      => $licence->date_expiration?->toDateString(),
            'version_format'       => $licence->version_format,
            'anti_rejeu'           => $licence->anti_rejeu,
            'crc_g5'               => $licence->crc_g5,
            'statut'               => $licence->statut,
            'nb_activations'       => $licence->nb_activations,
            'tentatives_suspectes' => $licence->tentatives_suspectes,
            'notes'                => $licence->notes,
            'cree_par'             => $licence->cree_par,
            'cree_le'              => $licence->cree_le?->toIso8601String(),
            'modifie_le'           => $licence->modifie_le?->toIso8601String(),
            'modifie_par'          => $licence->modifie_par,
            'organisation'         => [
                'org_id' => $licence->organisation?->org_id,
                'nom'    => $licence->organisation?->nom,
                'pays'   => $licence->organisation?->pays,
            ],
        ]);
    }

    /**
     * Révoque définitivement une licence.
     *
     * Flux atomique (transaction DB) :
     *   1. Valider motif
     *   2. Passer licence → REVOQUEE
     *   3. Passer toutes les activations ACTIVE → REVOQUEE
     *   4. INSERT activation_historique EVT_EXPIRATION pour chaque activation fermée
     *   5. INSERT blacklist_antirejeu (anti_rejeu de la licence)
     *   6. INSERT audit_log ACTION_REVOCATION
     *
     * Retourne le nombre d'activations fermées pour permettre un audit côté appelant.
     */
    public function revoquer(Request $request, string $licence_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->erreur(
                ErrorCodes::VALIDATION_ECHOUEE,
                'Données invalides.',
                ['erreurs' => $validator->errors()->toArray()],
                422,
            );
        }

        $licence = Licence::find($licence_id);

        if ($licence === null) {
            return $this->erreur(
                ErrorCodes::LICENCE_INTROUVABLE,
                "Licence {$licence_id} introuvable.",
                [],
                404,
            );
        }

        if ($licence->statut === Licence::STATUT_REVOQUEE) {
            return $this->erreur(
                ErrorCodes::LICENCE_REVOQUEE,
                "La licence {$licence_id} est déjà révoquée.",
                [],
                409,
            );
        }

        $motif  = $validator->validated()['motif'] ?? null;
        $acteur = $this->acteurCourant($request);

        $activationsClosees = DB::transaction(function () use ($licence, $motif, $acteur, $request): int {
            $licence->update(['statut' => Licence::STATUT_REVOQUEE]);

            $activations = Activation::where('licence_id', $licence->licence_id)
                ->where('statut', Activation::STATUT_ACTIVE)
                ->get();

            foreach ($activations as $activation) {
                $activation->update(['statut' => Activation::STATUT_REVOQUEE]);

                ActivationHistorique::enregistrer(
                    activationId: $activation->activation_id,
                    licenceId:    $licence->licence_id,
                    evenement:    ActivationHistorique::EVT_EXPIRATION,
                    acteur:       $acteur,
                    ipSource:     $request->ip(),
                    motif:        $motif ?? 'Révocation de la licence',
                );
            }

            BlacklistAntirejeu::enregistrer(
                antiRejeu: $licence->anti_rejeu,
                licenceId: $licence->licence_id,
                bloquePar: $acteur,
                motif:     $motif ?? 'Révocation de la licence',
            );

            AuditLog::enregistrer(
                licenceId: $licence->licence_id,
                action:    AuditLog::ACTION_REVOCATION,
                acteur:    $acteur,
                ipSource:  $request->ip(),
                detail:    [
                    'motif'              => $motif,
                    'activations_closes' => $activations->count(),
                ],
            );

            return $activations->count();
        });

        return $this->succes([
            'licence_id'         => $licence->licence_id,
            'statut'             => $licence->fresh()->statut,
            'activations_closes' => $activationsClosees,
        ]);
    }

    /**
     * Retourne le journal d'audit paginé d'une licence.
     *
     * Paramètres query :
     *   action  — filtre sur la colonne action (exact match)
     *   depuis  — entrées à partir de cette date/heure (ISO 8601 ou YYYY-MM-DD)
     *   jusqu   — entrées jusqu'à cette date/heure
     *   page    — page courante (défaut : 1)
     *   limite  — entrées par page (défaut : 50, max : 200)
     */
    public function audit(Request $request, string $licence_id): JsonResponse
    {
        $licence = Licence::find($licence_id);

        if ($licence === null) {
            return $this->erreur(
                ErrorCodes::LICENCE_INTROUVABLE,
                "Licence {$licence_id} introuvable.",
                [],
                404,
            );
        }

        $limite = min((int) $request->query('limite', 50), 200);
        $page   = max((int) $request->query('page', 1), 1);

        $query = AuditLog::where('licence_id', $licence_id);

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('depuis')) {
            $query->where('horodatage', '>=', $request->query('depuis'));
        }

        if ($request->filled('jusqu')) {
            $query->where('horodatage', '<=', $request->query('jusqu'));
        }

        $paginator = $query
            ->orderByDesc('horodatage')
            ->paginate($limite, ['*'], 'page', $page);

        return $this->succes($paginator->items(), [
            'total'  => $paginator->total(),
            'page'   => $paginator->currentPage(),
            'limite' => $paginator->perPage(),
        ]);
    }
}
