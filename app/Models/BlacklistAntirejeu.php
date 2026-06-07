<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Store des nonces utilisés pour la protection anti-rejeu.
 * Chaque nonce est conservé jusqu'à son expiration (TTL ~5 min).
 * Si un nonce est rejoué avant expiration → Signal 3 (SIGNAL_REJEU).
 *
 * Append-only : les entrées expirées sont purgées par une commande scheduled.
 *
 * @property int    $id
 * @property string $nonce            Identifiant unique de la requête (UUID ou HMAC)
 * @property string $fingerprint_hash Machine source de la requête
 * @property \Carbon\Carbon $expire_le  TTL glissant (now + 5 minutes)
 * @property \Carbon\Carbon $created_at
 */
class BlacklistAntirejeu extends Model
{
    protected $table = 'blacklist_antirejeu';

    const UPDATED_AT = null;

    protected $fillable = [
        'nonce',
        'fingerprint_hash',
        'expire_le',
    ];

    protected $casts = [
        'expire_le'  => 'datetime',
        'created_at' => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    /** Nonces encore valides (non expirés). */
    public function scopeValides(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('expire_le', '>', now());
    }
}
