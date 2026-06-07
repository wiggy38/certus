<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Organisation cliente d'Experto.
 *
 * @property string $org_id          Clé primaire alphanumérique, ex: "ORG-00471"
 * @property string $org_index_b36   Index séquentiel Base36, ex: "D3"
 * @property string $nom
 * @property string $email_contact
 * @property string|null $telephone
 * @property string|null $adresse
 * @property string $pays            Code ISO 3166-1 alpha-2, ex: "BF"
 * @property \Carbon\Carbon $cree_le
 * @property string $cree_par        Identifiant de l'auteur de la création
 *
 * @property-read int $nb_licences   Nombre de licences (accessor)
 */
class Organisation extends Model
{
    use HasFactory;

    // ----------------------------------------------------------------
    // Configuration de la clé primaire
    // ----------------------------------------------------------------

    protected $primaryKey = 'org_id';
    protected $keyType    = 'string';
    public $incrementing  = false;

    // Pas de created_at / updated_at — on utilise cree_le / cree_par
    public $timestamps = false;

    // ----------------------------------------------------------------
    // Colonnes accessibles en masse
    // ----------------------------------------------------------------

    protected $fillable = [
        'nom',
        'email_contact',
        'telephone',
        'adresse',
        'pays',
        'cree_le',
        'cree_par',
    ];

    protected $casts = [
        'cree_le' => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Boot : génération automatique de org_id et org_index_b36
    // ----------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Organisation $organisation): void {
            // Calcul du prochain numéro de séquence (thread-safe via lock BDD)
            $sequence = static::prochainSequence();

            // Format org_id : "ORG-" + numéro 5 chiffres (zéro-paddé)
            $organisation->org_id = 'ORG-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

            // Index Base36 du même numéro (ex: 471 → "D3")
            $organisation->org_index_b36 = strtoupper(base_convert((string) $sequence, 10, 36));

            // Horodatage auto si non fourni
            if (empty($organisation->cree_le)) {
                $organisation->cree_le = now();
            }
        });
    }

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    /**
     * Licences émises pour cette organisation.
     * FK : licences.org_id → organisations.org_id
     */
    public function licences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Licence::class, 'org_id', 'org_id');
    }

    // ----------------------------------------------------------------
    // Accesseurs
    // ----------------------------------------------------------------

    /**
     * Nombre total de licences de l'organisation.
     *
     * Usage : $org->nb_licences
     * Préférer withCount('licences') sur les listes pour éviter N+1.
     */
    public function getNbLicencesAttribute(): int
    {
        // Utilise le résultat de withCount() s'il est chargé, sinon requête COUNT
        return (int) ($this->attributes['licences_count'] ?? $this->licences()->count());
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    /**
     * Filtre les organisations ayant au moins une licence active.
     *
     * Note : le schéma actuel ne comporte pas de colonne `statut` sur organisations.
     * Si vous ajoutez une telle colonne, adaptez ce scope en conséquence.
     */
    public function scopeActives(Builder $query): Builder
    {
        return $query->whereHas('licences', fn (Builder $q) =>
            $q->where('statut', 'active')
        );
    }

    // ----------------------------------------------------------------
    // Helpers internes
    // ----------------------------------------------------------------

    /**
     * Calcule le prochain numéro de séquence entier en lisant le MAX
     * de la partie numérique de org_id, avec un verrou de lecture
     * pour éviter les doublons en cas d'accès concurrent.
     */
    private static function prochainSequence(): int
    {
        return (int) DB::transaction(function () {
            $max = DB::table('organisations')
                ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(org_id, 5) AS UNSIGNED)), 0) AS seq")
                ->lockForUpdate()
                ->value('seq');

            return (int) $max + 1;
        });
    }
}
