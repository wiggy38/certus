<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Clé API Certus — authentification des consommateurs de l'API.
 *
 * Jamais stockée en clair : seul le hash SHA-256 de la valeur brute est persisté.
 * Pour vérifier une clé reçue dans X-API-Key :
 *   ApiKey::verifier($cleEnClair)
 *
 * @property int    $id
 * @property string $cle_hash   SHA-256 de la clé brute
 * @property string $niveau     ADMIN | CLIENT
 * @property string $nom        Libellé descriptif
 * @property bool   $active
 * @property \Carbon\Carbon $cree_le
 */
class ApiKey extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'cle_hash',
        'niveau',
        'nom',
        'active',
        'cree_le',
    ];

    protected $casts = [
        'active'  => 'boolean',
        'cree_le' => 'datetime',
    ];

    /**
     * Recherche une clé active par valeur brute (X-API-Key header).
     * Retourne null si absente, inconnue, ou désactivée.
     */
    public static function verifier(string $cleEnClair): ?self
    {
        return static::query()
            ->where('cle_hash', hash('sha256', $cleEnClair))
            ->where('active', true)
            ->first();
    }

    public function estAdmin(): bool
    {
        return $this->niveau === 'ADMIN';
    }
}
