<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ErrorCodes;
use App\Models\BlacklistFingerprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Gestion de la liste noire des fingerprints machines.
 * Toutes les routes nécessitent le rôle ADMIN.
 *
 * Routes :
 *   GET    /api/v1/blacklist/fingerprints           → index()
 *   POST   /api/v1/blacklist/fingerprints           → ajouter()
 *   DELETE /api/v1/blacklist/fingerprints/{id}      → retirer()
 *   GET    /api/v1/blacklist/verifier               → verifier()
 */
class BlacklistController extends BaseApiController
{
    /** Liste paginée des fingerprints en liste noire. */
    public function index(Request $request): JsonResponse
    {
        $paginator = BlacklistFingerprint::actifs()
            ->latest()
            ->paginate($request->integer('par_page', 50));

        return $this->liste($paginator->items(), $this->metaPagination($paginator));
    }

    /** Ajoute un fingerprint à la liste noire. */
    public function ajouter(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fingerprint_hash' => ['required', 'regex:/^[a-f0-9]{64}$/i', 'unique:blacklist_fingerprints,fingerprint_hash'],
            'raison'           => ['required', 'string', 'max:255'],
            'expire_le'        => ['nullable', 'date', 'after:now'],
        ]);

        if ($validator->fails()) {
            return $this->erreur(
                ErrorCodes::VALIDATION_ECHOUEE,
                'Données invalides.',
                ['erreurs' => $validator->errors()->toArray()],
            );
        }

        $organisation = $this->organisationCourante($request);

        $entree = BlacklistFingerprint::create(array_merge(
            $validator->validated(),
            [
                'bloque_le'   => now(),
                'ajoute_par'  => (string) $organisation->id,
            ],
        ));

        return $this->cree($entree, ['message' => 'Fingerprint ajouté à la liste noire.']);
    }

    /** Retire (expire immédiatement) un fingerprint de la liste noire. */
    public function retirer(Request $request, int $id): JsonResponse
    {
        $entree = BlacklistFingerprint::findOrFail($id);
        $entree->update(['expire_le' => now()]);

        return $this->succes(null, ['message' => "Fingerprint #{$id} retiré de la liste noire."]);
    }

    /**
     * Vérifie si un fingerprint donné est actuellement sur liste noire.
     * Utilisé par le logiciel Experto avant une tentative d'activation.
     */
    public function verifier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'fingerprint_hash' => ['required', 'regex:/^[a-f0-9]{64}$/i'],
        ]);

        if ($validator->fails()) {
            return $this->erreur(
                ErrorCodes::FINGERPRINT_INVALIDE,
                'Hash SHA-256 valide requis (64 caractères hexadécimaux).',
            );
        }

        $hash   = $request->query('fingerprint_hash');
        $bloque = BlacklistFingerprint::where('fingerprint_hash', $hash)->actifs()->exists();

        return $this->succes(['bloque' => $bloque]);
    }
}
