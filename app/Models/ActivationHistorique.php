<?php

namespace App\Models;

use App\Constants\ErrorCodes;
use App\Exceptions\CertusException;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal immuable des événements d'activation — INSERT ONLY.
 *
 * Cette table constitue la piste d'audit des activations Experto.
 * Aucune modification ni suppression n'est autorisée après insertion.
 *
 * GARANTIE D'IMMUTABILITÉ : update() et delete() lèvent une CertusException.
 * Utiliser uniquement ::create() ou le raccourci ::enregistrer().
 *
 * @property int    $histo_id
 * @property int    $activation_id     FK → activations.activation_id
 * @property string $licence_id        FK → licences.licence_id
 * @property string $evenement         ACTIVATION | DESACTIVATION | REACTIVATION | EXPIRATION
 * @property string $acteur            Initiateur de l'événement (user id, 'systeme', 'api'…)
 * @property string|null $ip_source
 * @property string|null $motif
 * @property \Carbon\Carbon $horodatage
 */
class ActivationHistorique extends Model
{
    // ----------------------------------------------------------------
    // Configuration Eloquent
    // ----------------------------------------------------------------

    protected $table   = 'activation_historique';
    protected $primaryKey = 'histo_id';
    public $timestamps = false;    // horodatage géré manuellement

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
    // PROTECTION INSERT-ONLY — Surcharge des méthodes de mutation
    // ----------------------------------------------------------------

    /**
     * Interdit toute modification d'un enregistrement existant.
     *
     * @throws CertusException 405 à chaque appel sur un enregistrement existant
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new CertusException(
                ErrorCodes::ERREUR_INTERNE,
                'activation_historique est INSERT-ONLY : la modification est interdite. '
                . 'Utilisez ::enregistrer() pour ajouter un événement.',
                ['histo_id' => $this->histo_id],
                405,
            );
        }

        return parent::save($options);
    }

    /**
     * Interdit la mise à jour par appel direct à update().
     *
     * @throws CertusException 405
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new CertusException(
            ErrorCodes::ERREUR_INTERNE,
            'activation_historique est INSERT-ONLY : update() est interdit.',
            ['histo_id' => $this->histo_id ?? null],
            405,
        );
    }

    /**
     * Interdit la suppression par appel direct à delete().
     *
     * @throws CertusException 405
     */
    public function delete(): bool|null
    {
        throw new CertusException(
            ErrorCodes::ERREUR_INTERNE,
            'activation_historique est INSERT-ONLY : delete() est interdit.',
            ['histo_id' => $this->histo_id ?? null],
            405,
        );
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
     * C'est la SEULE méthode autorisée pour écrire dans cette table.
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

        // $histo->exists est false → notre save() passe en parent::save() (insert)
        $histo->save();

        return $histo;
    }
}
