<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ErrorCodes;
use App\Models\AuditLog;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Gestion des organisations clientes — Parcours 1.
 *
 * Routes (protégées par api.key.admin) :
 *   POST  /api/v1/organisations           → store()
 *   GET   /api/v1/organisations/{org_id}  → show()
 */
class OrganisationController extends BaseApiController
{
    /**
     * Crée une nouvelle organisation cliente.
     *
     * L'org_id (ex: "ORG-00001") et l'org_index_b36 (ex: "1") sont générés
     * automatiquement par le boot() du modèle Organisation de façon thread-safe.
     *
     * Retourne uniquement org_id et org_index_b36 — les autres champs sont
     * consultables via show().
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom'           => ['required', 'string', 'max:191'],
            'email_contact' => ['required', 'email', 'max:191'],
            'telephone'     => ['nullable', 'string', 'max:50'],
            'adresse'       => ['nullable', 'string', 'max:255'],
            'pays'          => ['required', 'string', 'size:2'],
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

        $organisation = Organisation::create([
            'nom'           => $donnees['nom'],
            'email_contact' => $donnees['email_contact'],
            'telephone'     => $donnees['telephone'] ?? null,
            'adresse'       => $donnees['adresse'] ?? null,
            'pays'          => strtoupper($donnees['pays']),
            'cree_par'      => $acteur,
        ]);

        AuditLog::enregistrer(
            licenceId: null,
            action:    AuditLog::ACTION_CREATION_ORGANISATION,
            acteur:    $acteur,
            ipSource:  $request->ip(),
            detail:    ['org_id' => $organisation->org_id, 'nom' => $organisation->nom],
        );

        return $this->cree([
            'org_id'        => $organisation->org_id,
            'org_index_b36' => $organisation->org_index_b36,
        ]);
    }

    /**
     * Retourne le détail d'une organisation et le nombre de ses licences.
     */
    public function show(Request $request, string $org_id): JsonResponse
    {
        $organisation = Organisation::withCount('licences')->find($org_id);

        if ($organisation === null) {
            return $this->erreur(
                ErrorCodes::ORGANISATION_INTROUVABLE,
                "Organisation {$org_id} introuvable.",
                [],
                404,
            );
        }

        return $this->succes([
            'org_id'        => $organisation->org_id,
            'org_index_b36' => $organisation->org_index_b36,
            'nom'           => $organisation->nom,
            'email_contact' => $organisation->email_contact,
            'telephone'     => $organisation->telephone,
            'adresse'       => $organisation->adresse,
            'pays'          => $organisation->pays,
            'nb_licences'   => $organisation->licences_count,
            'cree_par'      => $organisation->cree_par,
            'cree_le'       => $organisation->cree_le?->toIso8601String(),
        ]);
    }
}
