<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Liste noire des fingerprints machines bannis.
 * Un fingerprint blacklisté ne peut plus activer aucune licence.
 *
 * @property int    $id
 * @property string $fingerprint_hash  SHA-256 de la machine bannie
 * @property string $raison
 * @property \Carbon\Carbon $bloque_le
 * @property \Carbon\Carbon|null $expire_le  null = ban permanent
 * @property string $ajoute_par        Identifiant admin ou 'systeme'
 */
class BlacklistFingerprint extends Model
{
    protected $table = 'blacklist_fingerprints';

    protected $fillable = [
        'fingerprint_hash',
        'raison',
        'bloque_le',
        'expire_le',
        'ajoute_par',
    ];

    protected $casts = [
        'bloque_le'  => 'datetime',
        'expire_le'  => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Méthodes métier
    // ----------------------------------------------------------------

    /** Vrai si le ban est toujours en vigueur. */
    public function estActif(): bool
    {
        return is_null($this->expire_le) || $this->expire_le->isFuture();
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeActifs(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expire_le')->orWhere('expire_le', '>', now());
        });
    }
}
