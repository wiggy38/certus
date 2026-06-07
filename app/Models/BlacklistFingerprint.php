<?php

namespace App\Models;

use App\Models\Traits\ReadOnlyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Liste noire des empreintes machines bannies — INSERT ONLY.
 *
 * Un fingerprint présent dans cette table est refusé à toute activation,
 * quel que soit la licence présentée. La vérification est faite par
 * FingerprintService::verifierBlacklist().
 *
 * Le signal indique le type de piratage détecté lors du bannissement :
 *   1 = CLONAGE         (même licence active sur un autre poste)
 *   2 = MULTI_INSTANCE  (quota de postes dépassé)
 *   3 = REJEU           (nonce anti-replay consommé)
 *   4 = FALSIFICATION   (signature HMAC invalide)
 *
 * @property int         $blacklist_id
 * @property string      $fingerprint  SHA-256 de la machine (64 hex)
 * @property string      $licence_id   FK → licences.licence_id
 * @property int         $signal       1 | 2 | 3 | 4
 * @property string|null $motif
 * @property string      $bloque_par   'SYSTEME' ou identifiant admin
 * @property \Carbon\Carbon $horodatage
 */
class BlacklistFingerprint extends Model
{
    use ReadOnlyModel;

    // ----------------------------------------------------------------
    // Configuration Eloquent
    // ----------------------------------------------------------------

    protected $table      = 'blacklist_fingerprints';
    protected $primaryKey = 'blacklist_id';
    public $timestamps    = false;

    // ----------------------------------------------------------------
    // Constantes — Signaux de piratage
    // ----------------------------------------------------------------

    const SIGNAL_CLONAGE        = 1;
    const SIGNAL_MULTI_INSTANCE = 2;
    const SIGNAL_REJEU          = 3;
    const SIGNAL_FALSIFICATION  = 4;

    const SIGNAUX = [
        self::SIGNAL_CLONAGE        => 'CLONAGE',
        self::SIGNAL_MULTI_INSTANCE => 'MULTI_INSTANCE',
        self::SIGNAL_REJEU          => 'REJEU',
        self::SIGNAL_FALSIFICATION  => 'FALSIFICATION',
    ];

    protected $fillable = [
        'fingerprint',
        'licence_id',
        'signal',
        'motif',
        'bloque_par',
        'horodatage',
    ];

    protected $casts = [
        'blacklist_id' => 'integer',
        'signal'       => 'integer',
        'horodatage'   => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Boot : horodatage automatique + bloque_par par défaut
    // ----------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (BlacklistFingerprint $bf): void {
            if (empty($bf->horodatage)) {
                $bf->horodatage = now();
            }
            if (empty($bf->bloque_par)) {
                $bf->bloque_par = 'SYSTEME';
            }
        });
    }

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    /** Licence ayant déclenché le bannissement de ce fingerprint. */
    public function licence(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Licence::class, 'licence_id', 'licence_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    /** Toutes les entrées concernant un fingerprint donné. */
    public function scopePourFingerprint(Builder $query, string $fingerprint): Builder
    {
        return $query->where('fingerprint', $fingerprint);
    }

    /** Filtrer par type de signal. */
    public function scopePourSignal(Builder $query, int $signal): Builder
    {
        return $query->where('signal', $signal);
    }

    // ----------------------------------------------------------------
    // Méthodes statiques métier
    // ----------------------------------------------------------------

    /**
     * Vérifie si un fingerprint est présent dans la liste noire.
     * Délègue à FingerprintService pour les cas métier; utilisé en test unitaire.
     */
    public static function estBloque(string $fingerprint): bool
    {
        return static::where('fingerprint', $fingerprint)->exists();
    }

    /** Libellé lisible du signal donné. */
    public static function libelleSignal(int $signal): string
    {
        return self::SIGNAUX[$signal] ?? 'INCONNU';
    }

    // ----------------------------------------------------------------
    // Fabrique — point d'entrée unique pour l'écriture
    // ----------------------------------------------------------------

    /**
     * Crée et persiste un bannissement de fingerprint.
     *
     * @param string      $fingerprint SHA-256 de la machine (64 hex)
     * @param string      $licenceId   Licence qui a déclenché le signal
     * @param int         $signal      Constante SIGNAL_* de cette classe
     * @param string      $bloquePar   'SYSTEME' ou identifiant admin
     * @param string|null $motif       Description textuelle facultative
     */
    public static function enregistrer(
        string  $fingerprint,
        string  $licenceId,
        int     $signal,
        string  $bloquePar = 'SYSTEME',
        ?string $motif = null,
    ): self {
        $bf = new self([
            'fingerprint' => $fingerprint,
            'licence_id'  => $licenceId,
            'signal'      => $signal,
            'motif'       => $motif,
            'bloque_par'  => $bloquePar,
            'horodatage'  => now(),
        ]);

        $bf->save();

        return $bf;
    }
}
