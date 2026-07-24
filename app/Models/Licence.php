<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Licence logicielle Experto émise pour une organisation.
 *
 * @property string $licence_id          Clé primaire, ex: "LIC-0000000001"
 * @property string $org_id              FK → organisations.org_id
 * @property string $type_licence        '1'|'2'|'3'|'4'|'9' (voir TYPES)
 * @property int    $nb_postes
 * @property int    $nb_sites
 * @property int    $nb_projets
 * @property \Carbon\Carbon $date_emission
 * @property \Carbon\Carbon $date_expiration
 * @property int    $version_format
 * @property string $anti_rejeu          Jeton 2 chars anti-replay, généré à la création
 * @property string $cle_hash_sha256     SHA-256 de la clé en clair (64 hex)
 * @property string $crc_g5              Somme de contrôle 5 chars
 * @property string $statut              ACTIVE | SUSPENDUE | EXPIREE | REVOQUEE
 * @property int    $nb_activations
 * @property int    $tentatives_suspectes
 * @property string|null $notes
 * @property string $cree_par
 * @property \Carbon\Carbon $cree_le
 * @property \Carbon\Carbon|null $modifie_le
 * @property string|null $modifie_par
 *
 * @property-read string $type_libelle   Libellé du type (accessor)
 * @property-read bool   $is_expired     Vrai si expirée (accessor)
 */
class Licence extends Model
{
    use HasFactory;

    // ----------------------------------------------------------------
    // Constantes — Statuts
    // ----------------------------------------------------------------

    const STATUT_ACTIVE    = 'ACTIVE';
    const STATUT_SUSPENDUE = 'SUSPENDUE';
    const STATUT_EXPIREE   = 'EXPIREE';
    const STATUT_REVOQUEE  = 'REVOQUEE';

    const STATUTS = [
        self::STATUT_ACTIVE,
        self::STATUT_SUSPENDUE,
        self::STATUT_EXPIREE,
        self::STATUT_REVOQUEE,
    ];

    // ----------------------------------------------------------------
    // Constantes — Types de licence
    // ----------------------------------------------------------------

    const TYPE_STARTER    = '1';
    const TYPE_STANDARD   = '2';
    const TYPE_PRO        = '3';
    const TYPE_ENTERPRISE = '4';
    const TYPE_EVAL       = '9';

    /** Libellés des types indexés par le code CHAR(1) stocké en BDD. */
    const TYPES = [
        self::TYPE_STARTER    => 'STARTER',
        self::TYPE_STANDARD   => 'STANDARD',
        self::TYPE_PRO        => 'PRO',
        self::TYPE_ENTERPRISE => 'ENTERPRISE',
        self::TYPE_EVAL       => 'EVAL',
    ];

    // ----------------------------------------------------------------
    // Configuration Eloquent
    // ----------------------------------------------------------------

    protected $primaryKey = 'licence_id';
    protected $keyType    = 'string';
    public $incrementing  = false;
    public $timestamps    = false;

    protected $fillable = [
        'org_id',
        'type_licence',
        'nb_postes',
        'nb_sites',
        'nb_projets',
        'date_emission',
        'date_expiration',
        'version_format',
        'anti_rejeu',       // CHAR(2) — passé depuis CleService::generer(), sinon généré en boot
        'cle_hash_sha256',
        'crc_g5',
        'statut',
        'nb_activations',
        'tentatives_suspectes',
        'notes',
        'cree_par',
        'cree_le',
        'modifie_le',
        'modifie_par',
    ];

    protected $casts = [
        'date_emission'         => 'date',
        'date_expiration'       => 'date',
        'cree_le'               => 'datetime',
        'modifie_le'            => 'datetime',
        'nb_postes'             => 'integer',
        'nb_sites'              => 'integer',
        'nb_projets'            => 'integer',
        'nb_activations'        => 'integer',
        'tentatives_suspectes'  => 'integer',
        'version_format'        => 'integer',
    ];

    // ----------------------------------------------------------------
    // Boot : génération automatique licence_id / anti_rejeu
    // ----------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Licence $licence): void {
            $licence->licence_id = static::prochainLicenceId();
            // Ne génère l'anti_rejeu que s'il n'a pas été fourni (ex: par CleService::generer())
            if (empty($licence->anti_rejeu)) {
                $licence->anti_rejeu = static::genererAntiRejeu();
            }

            if (empty($licence->cree_le)) {
                $licence->cree_le = now();
            }
            if (empty($licence->date_emission)) {
                $licence->date_emission = now()->toDateString();
            }
            if (empty($licence->statut)) {
                $licence->statut = self::STATUT_ACTIVE;
            }
        });

        static::updating(function (Licence $licence): void {
            // Horodatage automatique à chaque modification
            $licence->modifie_le = now();
        });
    }

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    /** Organisation propriétaire de la licence. */
    public function organisation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'org_id', 'org_id');
    }

    /**
     * Activations machines liées à cette licence.
     * La table activations doit avoir une colonne licence_id VARCHAR(15).
     */
    public function activations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Activation::class, 'licence_id', 'licence_id');
    }

    /**
     * Entrées d'audit liées à cette licence.
     * La table audit_logs doit avoir une colonne licence_id VARCHAR(15).
     */
    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class, 'licence_id', 'licence_id');
    }

    /**
     * Fingerprints en liste noire associés à cette licence.
     * La table blacklist_fingerprints doit avoir une colonne licence_id VARCHAR(15).
     */
    public function blacklistFingerprints(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BlacklistFingerprint::class, 'licence_id', 'licence_id');
    }

    /**
     * Nonces anti-rejeu liés à cette licence.
     * La table blacklist_antirejeu doit avoir une colonne licence_id VARCHAR(15).
     */
    public function blacklistAntirejeu(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BlacklistAntirejeu::class, 'licence_id', 'licence_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    /**
     * Licences actuellement valides : statut ACTIVE + date_expiration future.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('statut', self::STATUT_ACTIVE)
            ->where('date_expiration', '>=', now()->toDateString());
    }

    /**
     * Licences ayant enregistré au moins une tentative suspecte.
     * Utilisé pour le tableau de bord de monitoring.
     */
    public function scopeSuspectes(Builder $query, int $seuil = 1): Builder
    {
        return $query->where('tentatives_suspectes', '>=', $seuil);
    }

    // ----------------------------------------------------------------
    // Accesseurs
    // ----------------------------------------------------------------

    /**
     * Libellé lisible du type de licence.
     *
     * Usage : $licence->type_libelle  →  "STANDARD"
     */
    public function getTypeLibelleAttribute(): string
    {
        return self::TYPES[$this->type_licence] ?? 'INCONNU';
    }

    /**
     * Indique si la licence est expirée (statut ou date_expiration dépassée).
     *
     * Usage : $licence->is_expired  →  true | false
     */
    public function getIsExpiredAttribute(): bool
    {
        if ($this->statut === self::STATUT_EXPIREE) {
            return true;
        }

        return $this->date_expiration !== null
            && $this->date_expiration->isPast();
    }

    // ----------------------------------------------------------------
    // Méthodes métier
    // ----------------------------------------------------------------

    /** Nombre de jours restants avant expiration (négatif si déjà expirée). */
    public function joursRestants(): int
    {
        return (int) now()->diffInDays($this->date_expiration, false);
    }

    /** Vrai si la licence est utilisable (active + non expirée). */
    public function estValide(): bool
    {
        return $this->statut === self::STATUT_ACTIVE && ! $this->is_expired;
    }

    // ----------------------------------------------------------------
    // Helpers internes
    // ----------------------------------------------------------------

    /**
     * Génère le prochain licence_id séquentiel de façon thread-safe.
     * Format : "LIC-" + 10 chiffres zéro-paddés → "LIC-0000000001" (14 chars < 15)
     */
    private static function prochainLicenceId(): string
    {
        return (string) DB::transaction(function () {
            $max = DB::table('licences')
                ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(licence_id, 5) AS UNSIGNED)), 0) AS seq")
                ->lockForUpdate()
                ->value('seq');

            $seq = (int) $max + 1;
            return 'LIC-' . str_pad((string) $seq, 10, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Génère un jeton anti-rejeu de 2 caractères [A-Z0-9].
     * Ce code est unique par émission de licence, pas globalement.
     */
    private static function genererAntiRejeu(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return $alphabet[random_int(0, 35)] . $alphabet[random_int(0, 35)];
    }
}
