<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Organisation cliente d'Experto.
 * Chaque organisation possède une clé API et un rôle (CLIENT ou ADMIN).
 *
 * @property int    $id
 * @property string $nom
 * @property string $email
 * @property string|null $telephone
 * @property string|null $adresse
 * @property string|null $pays
 * @property string $statut         actif | suspendu | expire
 * @property string $api_key        Clé d'authentification API (confidentielle)
 * @property string $api_key_role   CLIENT | ADMIN
 * @property string|null $notes
 */
class Organisation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'email',
        'telephone',
        'adresse',
        'pays',
        'statut',
        'api_key',
        'api_key_role',
        'notes',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    public function licences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Licence::class);
    }

    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // ----------------------------------------------------------------
    // Accesseurs
    // ----------------------------------------------------------------

    public function estActif(): bool
    {
        return $this->statut === 'actif';
    }

    public function estAdmin(): bool
    {
        return $this->api_key_role === 'ADMIN';
    }
}
