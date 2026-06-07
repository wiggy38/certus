<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Instance active d'une licence sur une machine cliente.
 * Chaque enregistrement représente une machine identifiée par son fingerprint SHA-256.
 *
 * @property int    $id
 * @property int    $licence_id
 * @property string $fingerprint_hash  SHA-256 de la machine
 * @property string $ip_address
 * @property string|null $hostname
 * @property string $statut           active | revoquee
 * @property \Carbon\Carbon|null $dernier_signal  Dernier heartbeat reçu
 * @property array|null $metadata     Infos OS, version logiciel, etc.
 */
class Activation extends Model
{
    use HasFactory;

    protected $fillable = [
        'licence_id',
        'fingerprint_hash',
        'ip_address',
        'hostname',
        'statut',
        'dernier_signal',
        'metadata',
    ];

    protected $casts = [
        'dernier_signal' => 'datetime',
        'metadata'       => 'array',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    public function licence(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Licence::class);
    }

    public function historique(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ActivationHistorique::class);
    }

    // ----------------------------------------------------------------
    // Méthodes métier
    // ----------------------------------------------------------------

    public function estActive(): bool
    {
        return $this->statut === 'active';
    }

    /** Vrai si aucun heartbeat reçu depuis plus de $minutes minutes. */
    public function estSilencieuse(int $minutes = 60): bool
    {
        return $this->dernier_signal !== null
            && $this->dernier_signal->diffInMinutes(now()) > $minutes;
    }
}
