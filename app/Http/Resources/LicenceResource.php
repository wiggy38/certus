<?php

namespace App\Http\Resources;

use App\Models\Licence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforme un modèle Licence en tableau JSON API.
 *
 * cle_hash_sha256 et anti_rejeu ne sont jamais exposés dans les réponses API.
 */
class LicenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'licence_id'            => $this->licence_id,
            'org_id'                => $this->org_id,
            'organisation'          => new OrganisationResource($this->whenLoaded('organisation')),

            // Type
            'type_licence'          => $this->type_licence,
            'type_libelle'          => $this->type_libelle,   // accessor → "STANDARD"

            // Quotas
            'nb_postes'             => $this->nb_postes,
            'nb_sites'              => $this->nb_sites,
            'nb_projets'            => $this->nb_projets,

            // Validité
            'date_emission'         => $this->date_emission?->toDateString(),
            'date_expiration'       => $this->date_expiration?->toDateString(),
            'jours_restants'        => $this->joursRestants(),
            'is_expired'            => $this->is_expired,     // accessor
            'version_format'        => $this->version_format,

            // Cycle de vie
            'statut'                => $this->statut,
            'nb_activations'        => $this->nb_activations,
            'tentatives_suspectes'  => $this->tentatives_suspectes,

            'notes'                 => $this->notes,

            // Traçabilité
            'cree_par'              => $this->cree_par,
            'cree_le'               => $this->cree_le?->toIso8601String(),
            'modifie_le'            => $this->modifie_le?->toIso8601String(),
            'modifie_par'           => $this->modifie_par,
        ];
    }
}
