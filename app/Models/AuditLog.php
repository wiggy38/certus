<?php

namespace App\Models;

use App\Models\Traits\ReadOnlyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal immuable des actions API sur les licences Experto — INSERT ONLY.
 *
 * Chaque opération significative (activation, suspension, révocation,
 * heartbeat, détection de piratage…) doit produire une entrée via ::enregistrer().
 *
 * @property int         $log_id
 * @property string      $licence_id  FK → licences.licence_id
 * @property string      $action      Constante ACTION_* de cette classe
 * @property string      $acteur      Identifiant de l'initiateur
 * @property string|null $ip_source
 * @property string|null $detail      Contexte additionnel (JSON ou texte)
 * @property \Carbon\Carbon $horodatage
 */
class AuditLog extends Model
{
    use ReadOnlyModel;

    // ----------------------------------------------------------------
    // Configuration Eloquent
    // ----------------------------------------------------------------

    protected $table      = 'audit_log';
    protected $primaryKey = 'log_id';
    public $timestamps    = false;

    // ----------------------------------------------------------------
    // Constantes — Actions documentées (max 30 chars)
    // ----------------------------------------------------------------

    const ACTION_LICENCE_VERIFIEE   = 'LICENCE_VERIFIEE';    // GET  /licences/{id}/verifier
    const ACTION_LICENCE_ACTIVEE    = 'LICENCE_ACTIVEE';     // POST /activations
    const ACTION_LICENCE_DESACTIVEE = 'LICENCE_DESACTIVEE';  // DELETE /activations/{id}
    const ACTION_LICENCE_SUSPENDUE  = 'LICENCE_SUSPENDUE';   // POST /licences/{id}/suspendre
    const ACTION_LICENCE_REVOQUEE   = 'LICENCE_REVOQUEE';    // POST /licences/{id}/revoquer
    const ACTION_LICENCE_EXPIREE    = 'LICENCE_EXPIREE';     // scheduler
    const ACTION_HEARTBEAT          = 'HEARTBEAT';            // POST /activations/{id}/heartbeat
    const ACTION_ACTIVATION_REACTIVE = 'ACTIVATION_REACTIVE'; // reactiver()
    const ACTION_FP_BLACKLISTE      = 'FP_BLACKLISTE';       // POST /blacklist/fingerprints
    const ACTION_FP_SUPPRIME        = 'FP_SUPPRIME';         // DELETE /blacklist/fingerprints/{id}
    const ACTION_PIRATAGE_DETECTE   = 'PIRATAGE_DETECTE';    // SignalService

    protected $fillable = [
        'licence_id',
        'action',
        'acteur',
        'ip_source',
        'detail',
        'horodatage',
    ];

    protected $casts = [
        'log_id'     => 'integer',
        'horodatage' => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Boot : horodatage automatique à l'insertion
    // ----------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AuditLog $log): void {
            if (empty($log->horodatage)) {
                $log->horodatage = now();
            }
        });
    }

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    /** Licence concernée par cette entrée d'audit. */
    public function licence(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Licence::class, 'licence_id', 'licence_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    /** Entrées d'audit pour une action donnée. */
    public function scopePourAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /** Entrées d'audit sur une période donnée. */
    public function scopeDepuis(Builder $query, \DateTimeInterface $depuis): Builder
    {
        return $query->where('horodatage', '>=', $depuis);
    }

    // ----------------------------------------------------------------
    // Fabrique — point d'entrée unique pour l'écriture
    // ----------------------------------------------------------------

    /**
     * Crée et persiste une entrée d'audit.
     * Méthode à appeler depuis les contrôleurs et services.
     *
     * @param string      $licenceId  Clé de la licence concernée
     * @param string      $action     Constante ACTION_* de cette classe
     * @param string      $acteur     Id utilisateur, clé API tronquée, 'systeme'
     * @param string|null $ipSource   IP source de la requête
     * @param mixed       $detail     Données complémentaires (array JSON-encodé ou string)
     */
    public static function enregistrer(
        string $licenceId,
        string $action,
        string $acteur,
        ?string $ipSource = null,
        mixed $detail = null,
    ): self {
        $detailStr = match (true) {
            is_array($detail)  => json_encode($detail, JSON_UNESCAPED_UNICODE),
            is_string($detail) => $detail,
            default            => null,
        };

        $log = new self([
            'licence_id' => $licenceId,
            'action'     => $action,
            'acteur'     => $acteur,
            'ip_source'  => $ipSource,
            'detail'     => $detailStr,
            'horodatage' => now(),
        ]);

        $log->save();

        return $log;
    }
}
