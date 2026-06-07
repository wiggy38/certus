<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Journal immuable de toutes les actions liées aux activations.
 * Append-only : aucun enregistrement n'est modifié ou supprimé (piste d'audit).
 *
 * @property int    $id
 * @property int    $licence_id
 * @property int|null $activation_id
 * @property string $action          activation | revocation | heartbeat | signal_piratage
 * @property string $fingerprint_hash
 * @property string $ip_address
 * @property array|null $details     Détails contextuels JSON
 * @property \Carbon\Carbon $survenu_le
 */
class ActivationHistorique extends Model
{
    public $timestamps = false;

    protected $table = 'activation_historiques';

    protected $fillable = [
        'licence_id',
        'activation_id',
        'action',
        'fingerprint_hash',
        'ip_address',
        'details',
        'survenu_le',
    ];

    protected $casts = [
        'details'    => 'array',
        'survenu_le' => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    public function licence(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Licence::class);
    }

    public function activation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Activation::class);
    }
}
