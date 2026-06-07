<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Licence logicielle Experto émise pour une organisation.
 *
 * @property int    $id
 * @property int    $organisation_id
 * @property string $cle              Clé de licence (chiffrée, cachée en sérialisation)
 * @property string $produit          ex: "Experto"
 * @property string $version          ex: "3.0"
 * @property string $type             standard | premium | entreprise
 * @property string $statut           active | suspendue | expiree | revoquee
 * @property \Carbon\Carbon $date_debut
 * @property \Carbon\Carbon $date_expiration
 * @property int    $max_activations
 * @property int    $activations_count
 * @property string|null $fingerprint_hash  SHA-256 verrouillé à la première activation
 * @property array|null  $metadata          Options JSON supplémentaires
 */
class Licence extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organisation_id',
        'cle',
        'produit',
        'version',
        'type',
        'statut',
        'date_debut',
        'date_expiration',
        'max_activations',
        'activations_count',
        'fingerprint_hash',
        'metadata',
    ];

    protected $hidden = ['cle'];

    protected $casts = [
        'date_debut'        => 'date',
        'date_expiration'   => 'date',
        'metadata'          => 'array',
        'activations_count' => 'integer',
        'max_activations'   => 'integer',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    public function organisation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function activations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Activation::class);
    }

    public function historique(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ActivationHistorique::class);
    }

    // ----------------------------------------------------------------
    // Méthodes métier
    // ----------------------------------------------------------------

    /** Vrai si la licence est active et dans sa période de validité. */
    public function isActive(): bool
    {
        return $this->statut === 'active'
            && now()->between($this->date_debut, $this->date_expiration);
    }

    /** Vrai si le quota d'activations n'est pas atteint. */
    public function quotaDisponible(): bool
    {
        return $this->activations_count < $this->max_activations;
    }

    /** Nombre de jours restants avant expiration (négatif si expirée). */
    public function joursRestants(): int
    {
        return (int) now()->diffInDays($this->date_expiration, false);
    }
}
