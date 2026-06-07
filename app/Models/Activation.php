<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Instance active d'une licence Experto sur une machine cliente.
 *
 * Un enregistrement = une machine identifiée par son fingerprint SHA-256.
 * La traçabilité temporelle (qui a activé, quand, depuis quelle IP)
 * est déléguée à ActivationHistorique (INSERT-ONLY).
 *
 * @property int    $activation_id      PK auto-increment
 * @property string $licence_id         FK → licences.licence_id
 * @property string $fingerprint        SHA-256 de la machine (64 hex)
 * @property string $statut             ACTIVE | INACTIVE | EXPIREE | REVOQUEE
 * @property string|null $nom_poste     Nom de la machine / poste de travail
 * @property string|null $ip_activation IPv4 ou IPv6 au moment de l'activation
 * @property string|null $version_app   Version d'Experto installée sur le poste
 */
class Activation extends Model
{
    use HasFactory;

    // ----------------------------------------------------------------
    // Configuration Eloquent
    // ----------------------------------------------------------------

    protected $primaryKey = 'activation_id';
    public $timestamps    = false;     // pas de created_at / updated_at dans le schéma

    // ----------------------------------------------------------------
    // Constantes — Statuts d'activation
    // ----------------------------------------------------------------

    const STATUT_ACTIVE   = 'ACTIVE';
    const STATUT_INACTIVE = 'INACTIVE';     // désactivée manuellement
    const STATUT_EXPIREE  = 'EXPIREE';      // licence associée expirée
    const STATUT_REVOQUEE = 'REVOQUEE';     // révoquée par un administrateur

    const STATUTS = [
        self::STATUT_ACTIVE,
        self::STATUT_INACTIVE,
        self::STATUT_EXPIREE,
        self::STATUT_REVOQUEE,
    ];

    protected $fillable = [
        'licence_id',
        'fingerprint',
        'statut',
        'nom_poste',
        'ip_activation',
        'version_app',
    ];

    protected $casts = [
        'activation_id' => 'integer',
    ];

    // ----------------------------------------------------------------
    // Boot
    // ----------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Activation $activation): void {
            if (empty($activation->statut)) {
                $activation->statut = self::STATUT_ACTIVE;
            }
        });
    }

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    /** Licence à laquelle cette activation est rattachée. */
    public function licence(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Licence::class, 'licence_id', 'licence_id');
    }

    /** Historique complet des événements sur cette activation. */
    public function historique(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ActivationHistorique::class, 'activation_id', 'activation_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    /**
     * Activations dont le statut est ACTIVE.
     *
     * Usage : Activation::active()->get()
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_ACTIVE);
    }

    /**
     * Activations appartenant à une licence donnée.
     *
     * Usage : Activation::parLicence('LIC-0000000001')->active()->get()
     */
    public function scopeParLicence(Builder $query, string $licenceId): Builder
    {
        return $query->where('licence_id', $licenceId);
    }

    /**
     * Activations correspondant à un fingerprint donné.
     * Utile pour détecter le multi-instance (même machine, même licence).
     */
    public function scopeParFingerprint(Builder $query, string $fingerprint): Builder
    {
        return $query->where('fingerprint', $fingerprint);
    }

    // ----------------------------------------------------------------
    // Méthodes métier
    // ----------------------------------------------------------------

    /** Vrai si l'activation est dans l'état ACTIVE. */
    public function estActive(): bool
    {
        return $this->statut === self::STATUT_ACTIVE;
    }

    /**
     * Désactive cette instance et journalise l'événement.
     * Met à jour le statut puis crée une entrée dans activation_historique.
     */
    public function desactiver(string $acteur, ?string $motif = null, ?string $ipSource = null): void
    {
        $this->update(['statut' => self::STATUT_INACTIVE]);

        ActivationHistorique::enregistrer(
            activationId: $this->activation_id,
            licenceId:    $this->licence_id,
            evenement:    ActivationHistorique::EVT_DESACTIVATION,
            acteur:       $acteur,
            ipSource:     $ipSource,
            motif:        $motif,
        );
    }

    /**
     * Réactive une activation précédemment désactivée.
     */
    public function reactiver(string $acteur, ?string $motif = null, ?string $ipSource = null): void
    {
        $this->update(['statut' => self::STATUT_ACTIVE]);

        ActivationHistorique::enregistrer(
            activationId: $this->activation_id,
            licenceId:    $this->licence_id,
            evenement:    ActivationHistorique::EVT_REACTIVATION,
            acteur:       $acteur,
            ipSource:     $ipSource,
            motif:        $motif,
        );
    }
}
