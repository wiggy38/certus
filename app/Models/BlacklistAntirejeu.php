<?php

namespace App\Models;

use App\Models\Traits\ReadOnlyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Liste noire des jetons anti-rejeu consommés — INSERT ONLY.
 *
 * Chaque licence porte un jeton anti_rejeu CHAR(2) généré à l'émission.
 * Lors d'une vérification d'activation, si ce jeton est présent dans
 * cette table avec un horodatage de moins de 5 minutes, la requête est
 * rejetée (Signal 3 — REJEU).
 *
 * Purge : une tâche planifiée peut supprimer les entrées plus vieilles que
 * 5 minutes sans compromettre la sécurité (leur TTL est déjà dépassé).
 *
 * @property int         $bl_id
 * @property string      $anti_rejeu  CHAR(2) [A-Z0-9] issu de licences.anti_rejeu
 * @property string      $licence_id  FK → licences.licence_id
 * @property string|null $motif
 * @property string      $bloque_par  'SYSTEME' ou identifiant admin
 * @property \Carbon\Carbon $horodatage
 */
class BlacklistAntirejeu extends Model
{
    use ReadOnlyModel;

    // ----------------------------------------------------------------
    // Configuration Eloquent
    // ----------------------------------------------------------------

    protected $table      = 'blacklist_antirejeu';
    protected $primaryKey = 'bl_id';
    public $timestamps    = false;

    /** Durée de vie du blocage en minutes (TTL anti-replay). */
    const TTL_MINUTES = 5;

    protected $fillable = [
        'anti_rejeu',
        'licence_id',
        'motif',
        'bloque_par',
        'horodatage',
    ];

    protected $casts = [
        'bl_id'      => 'integer',
        'horodatage' => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Boot : horodatage automatique + bloque_par par défaut
    // ----------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (BlacklistAntirejeu $bl): void {
            if (empty($bl->horodatage)) {
                $bl->horodatage = now();
            }
            if (empty($bl->bloque_par)) {
                $bl->bloque_par = 'SYSTEME';
            }
        });
    }

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    /** Licence dont le jeton anti-rejeu a été consommé. */
    public function licence(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Licence::class, 'licence_id', 'licence_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    /**
     * Entrées encore dans la fenêtre de protection (< TTL_MINUTES minutes).
     * Un jeton détecté par ce scope déclenche le Signal 3 (REJEU).
     */
    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('horodatage', '>=', now()->subMinutes(self::TTL_MINUTES));
    }

    // ----------------------------------------------------------------
    // Méthodes statiques métier
    // ----------------------------------------------------------------

    /**
     * Indique si un jeton anti-rejeu est bloqué pour une licence donnée.
     * Utilisé par SignalService::detecterRejeu().
     *
     * @param string $antiRejeu Jeton CHAR(2) de la licence
     * @param string $licenceId Licence concernée
     */
    public static function estBloque(string $antiRejeu, string $licenceId): bool
    {
        return static::where('anti_rejeu', $antiRejeu)
            ->where('licence_id', $licenceId)
            ->where('horodatage', '>=', now()->subMinutes(self::TTL_MINUTES))
            ->exists();
    }

    // ----------------------------------------------------------------
    // Fabrique — point d'entrée unique pour l'écriture
    // ----------------------------------------------------------------

    /**
     * Enregistre un jeton anti-rejeu comme consommé/bloqué.
     * Appelé par SignalService lors de la détection d'un rejeu.
     *
     * @param string      $antiRejeu Jeton CHAR(2) [A-Z0-9] de la licence
     * @param string      $licenceId Licence dont le jeton a été rejoué
     * @param string      $bloquePar 'SYSTEME' ou identifiant de l'initiateur
     * @param string|null $motif     Contexte du blocage (optionnel)
     */
    public static function enregistrer(
        string  $antiRejeu,
        string  $licenceId,
        string  $bloquePar = 'SYSTEME',
        ?string $motif = null,
    ): self {
        $bl = new self([
            'anti_rejeu' => $antiRejeu,
            'licence_id' => $licenceId,
            'motif'      => $motif,
            'bloque_par' => $bloquePar,
            'horodatage' => now(),
        ]);

        $bl->save();

        return $bl;
    }
}
