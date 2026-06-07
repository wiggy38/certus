<?php

namespace App\Models;

use App\Models\Traits\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal immuable des événements d'activation — INSERT ONLY.
 *
 * Cette table constitue la piste d'audit des activations Experto.
 * Aucune modification ni suppression n'est autorisée après insertion.
 *
 * La protection INSERT-ONLY est assurée par le trait ReadOnlyModel.
 * Seul point d'écriture autorisé : ::enregistrer().
 *
 * @property int         $histo_id
 * @property int         $activation_id  FK → activations.activation_id
 * @property string      $licence_id     FK → licences.licence_id
 * @property string      $evenement      ACTIVATION|DESACTIVATION|REACTIVATION|EXPIRATION
 * @property string      $acteur         Initiateur de l'événement
 * @property string|null $ip_source
 * @property string|null $motif
 * @property \Carbon\Carbon $horodatage
 */
class ActivationHistorique extends Model
{
    use ReadOnlyModel;

    // ----------------------------------------------------------------
    // Configuration Eloquent
    // ----------------------------------------------------------------

    protected $table      = 'activation_historique';
    protected $primaryKey = 'histo_id';
    public $timestamps    = false;

    // ----------------------------------------------------------------
    // Constantes — Événements possibles
    // ----------------------------------------------------------------

    const EVT_ACTIVATION    = 'ACTIVATION';
    const EVT_DESACTIVATION = 'DESACTIVATION';
    const EVT_REACTIVATION  = 'REACTIVATION';
    const EVT_EXPIRATION    = 'EXPIRATION';

    const EVENEMENTS = [
        self::EVT_ACTIVATION,
        self::EVT_DESACTIVATION,
        self::EVT_REACTIVATION,
        self::EVT_EXPIRATION,
    ];

    protected $fillable = [
        'activation_id',
        'licence_id',
        'evenement',
        'acteur',
        'ip_source',
        'motif',
        'horodatage',
    ];

    protected $casts = [
        'histo_id'      => 'integer',
        'activation_id' => 'integer',
        'horodatage'    => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Boot : horodatage automatique à l'insertion
    // ----------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ActivationHistorique $histo): void {
            if (empty($histo->horodatage)) {
                $histo->horodatage = now();
            }
        });
    }

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    /** Activation concernée par cet événement. */
    public function activation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Activation::class, 'activation_id', 'activation_id');
    }

    /** Licence concernée par cet événement. */
    public function licence(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Licence::class, 'licence_id', 'licence_id');
    }

    // ----------------------------------------------------------------
    // Fabrique — point d'entrée unique pour l'écriture
    // ----------------------------------------------------------------

    /**
     * Crée et persiste un enregistrement d'historique.
     *
     * @param int         $activationId
     * @param string      $licenceId
     * @param string      $evenement   Constante EVT_* de cette classe
     * @param string      $acteur      Initiateur : user id, 'systeme', clé API tronquée…
     * @param string|null $ipSource    IP source de l'événement
     * @param string|null $motif       Raison textuelle (optionnelle)
     */
    public static function enregistrer(
        int     $activationId,
        string  $licenceId,
        string  $evenement,
        string  $acteur,
        ?string $ipSource = null,
        ?string $motif = null,
    ): self {
        $histo = new self([
            'activation_id' => $activationId,
            'licence_id'    => $licenceId,
            'evenement'     => $evenement,
            'acteur'        => $acteur,
            'ip_source'     => $ipSource,
            'motif'         => $motif,
            'horodatage'    => now(),
        ]);

        // $histo->exists === false → ReadOnlyModel::save() délègue à parent::save() (INSERT)
        $histo->save();

        return $histo;
    }
}
