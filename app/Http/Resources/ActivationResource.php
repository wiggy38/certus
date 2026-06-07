<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforme un modèle Activation en tableau JSON API.
 * Le fingerprint_hash complet n'est jamais retourné — seul un préfixe de 8 chars
 * est exposé pour faciliter l'identification sans exposer l'empreinte complète.
 */
class ActivationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'licence_id'          => $this->licence_id,
            'licence'             => new LicenceResource($this->whenLoaded('licence')),
            'fingerprint_prefix'  => substr($this->fingerprint_hash, 0, 8) . '…',
            'ip_address'          => $this->ip_address,
            'hostname'            => $this->hostname,
            'statut'              => $this->statut,
            'dernier_signal'      => $this->dernier_signal?->toIso8601String(),
            'silencieuse'         => $this->estSilencieuse(),
            'metadata'            => $this->metadata,
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
