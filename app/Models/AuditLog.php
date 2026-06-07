<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Journal d'audit des appels API (qui a fait quoi, quand).
 * Enregistre chaque requête entrante avec son contexte.
 * Append-only : jamais modifié ni supprimé.
 *
 * @property int    $id
 * @property int|null $organisation_id
 * @property string $acteur           Clé API tronquée (8 premiers chars) ou 'systeme'
 * @property string $action           Ex: "POST /api/v1/activations"
 * @property string|null $ressource   Ex: "Licence#42" ou "Activation#7"
 * @property array|null  $payload     Corps de la requête (secrets filtrés)
 * @property int    $reponse_code     Code HTTP retourné
 * @property string $ip_address
 * @property \Carbon\Carbon $survenu_le
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'organisation_id',
        'acteur',
        'action',
        'ressource',
        'payload',
        'reponse_code',
        'ip_address',
        'survenu_le',
    ];

    protected $casts = [
        'payload'    => 'array',
        'survenu_le' => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    public function organisation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
