<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforme un modèle Organisation en tableau JSON API.
 * La clé api_key est toujours exclue (définie en $hidden sur le modèle).
 */
class OrganisationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'nom'         => $this->nom,
            'email'       => $this->email,
            'telephone'   => $this->telephone,
            'adresse'     => $this->adresse,
            'pays'        => $this->pays,
            'statut'      => $this->statut,
            'role'        => $this->api_key_role,
            'licences_count' => $this->whenCounted('licences'),
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
