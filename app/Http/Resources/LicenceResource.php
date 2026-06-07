<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforme un modèle Licence en tableau JSON API.
 * La clé brute ($cle) est toujours exclue (définie en $hidden sur le modèle).
 */
class LicenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'organisation_id'    => $this->organisation_id,
            'organisation'       => new OrganisationResource($this->whenLoaded('organisation')),
            'produit'            => $this->produit,
            'version'            => $this->version,
            'type'               => $this->type,
            'statut'             => $this->statut,
            'date_debut'         => $this->date_debut?->toDateString(),
            'date_expiration'    => $this->date_expiration?->toDateString(),
            'jours_restants'     => $this->date_expiration
                ? (int) now()->diffInDays($this->date_expiration, false)
                : null,
            'max_activations'    => $this->max_activations,
            'activations_count'  => $this->activations_count,
            'quota_disponible'   => $this->quotaDisponible(),
            'metadata'           => $this->metadata,
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
