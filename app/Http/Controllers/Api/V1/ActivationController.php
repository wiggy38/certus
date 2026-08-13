<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ErrorCodes;
use App\Models\Activation;
use App\Models\ActivationHistorique;
use App\Models\AuditLog;
use App\Models\BlacklistAntirejeu;
use App\Models\BlacklistFingerprint;
use App\Models\Licence;
use App\Services\CleService;
use App\Services\SignalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Activation des licences Experto par les machines clientes — Parcours 2.
 *
 * Routes :
 *   POST  /api/v1/licences/activer                              → activer()    [api.key CLIENT]
 *   GET   /api/v1/licences/{licence_id}/activations             → index()      [api.key.admin]
 *   PATCH /api/v1/licences/{licence_id}/activations/{id}        → update()     [api.key.admin]
 *   GET   /api/v1/licences/{licence_id}/historique-activations  → historique() [api.key.admin]
 */
class ActivationController extends BaseApiController
{
    // ----------------------------------------------------------------
    // Modules disponibles par type de licence
    // ----------------------------------------------------------------

    private const MODULES = [
        Licence::TYPE_STARTER    => ['SYCEBNL'],
        Licence::TYPE_STANDARD   => ['SYCEBNL', 'GSTOCK'],
        Licence::TYPE_PRO        => ['SYCEBNL', 'GSTOCK', 'GPAY', 'VPN'],
        Licence::TYPE_ENTERPRISE => ['SYCEBNL', 'GSTOCK', 'GPAY', 'VPN'],
        Licence::TYPE_EVAL       => ['SYCEBNL'],
    ];

    public function __construct(
        private readonly CleService    $cleService,
        private readonly SignalService $signalService,
    ) {}

    // ================================================================
    // POST /api/v1/licences/activer — Parcours 2
    // ================================================================

    /**
    * Active une licence Experto sur une machine cliente.
    *
    * Flux de sécurité (ordre strict) :
    *   1.  Validation champs d'entrée
    *   2.  Normalisation + vérification longueur de la clé (25 chars sans tirets)
    *   3.  Vérification CRC32 via CleService::verifierCRC()
    *   4.  Décodage payload + vérification date d'expiration
    *   5.  Vérification blacklist_antirejeu (anti_rejeu de G4)
    *   6.  Vérification blacklist_fingerprints
    *   7.  Lookup licence par SHA-256(clé normalisée avec tirets)
    *   8.  Vérification statut = ACTIVE
    *   9.  Vérification des 4 signaux d'anomalie (SignalService)
    *   10. Transaction atomique : INSERT activations + historique + audit_log, UPDATE nb_activations
    *   11. Génération token JWT local (HS256, signé avec APP_KEY)
    *   12. Retour 200 {licence_id, org_nom, type_licence, …, modules[], token_local}
    *
    * Toutes les tentatives (succès ET échecs) sont loggées dans audit_log.
    */
    public function activer(Request $request): JsonResponse
    {
        // ── Étape 1 : validation ─────────────────────────────────────
        $validator = Validator::make($request->all(), [
            'cle'         => ['required', 'string'],
            'fingerprint' => ['required', 'string', 'size:64', 'regex:/^[0-9a-fA-F]{64}$/'],
            'version_app' => ['nullable', 'string', 'max:30'],
        ]);

        if ($validator->fails()) {
            return $this->erreur(
                ErrorCodes::VALIDATION_ECHOUEE,
                'Données invalides.',
                ['erreurs' => $validator->errors()->toArray()],
                422,
            );
        }

        $donnees     = $validator->validated();
        $fingerprint = strtolower($donnees['fingerprint']);
        $acteur      = $this->acteurCourant($request);

        // ── Étape 2 : normalisation + longueur ───────────────────────
        $cleBrute   = strtoupper(trim($donnees['cle']));
        $sansTirets = str_replace('-', '', $cleBrute);

        if (strlen($sansTirets) !== 25 || ! preg_match('/^[0-9A-Z]{25}$/', $sansTirets)) {
            $this->loggerRejet(null, $fingerprint, 'Format clé invalide', $acteur, $request);

            return $this->erreur(ErrorCodes::CLE_INVALIDE, 'Format de clé invalide (25 chars Base36 attendus).', [], 400);
        }

        // Clé normalisée avec tirets (XXXXX-XXXXX-XXXXX-XXXXX-XXXXX)
        $cleNormalisee = implode('-', str_split($sansTirets, 5));

        // ── Étape 3 : vérification CRC32 ────────────────────────────
        if (! $this->cleService->verifierCRC($cleNormalisee)) {
            $this->loggerRejet(null, $fingerprint, 'CRC invalide', $acteur, $request);

            return $this->erreur(ErrorCodes::CLE_INVALIDE, 'Clé de licence corrompue (CRC invalide).', [], 400);
        }

        // ── Étape 4 : décodage + expiration ──────────────────────────
        $payload = $this->cleService->decoder($cleNormalisee);

        // expiration = 'YYYY-MM' → valide jusqu'à la fin du mois
        [$year, $month] = explode('-', $payload['expiration']);
        $dateExpiration = Carbon::createFromDate((int) $year, (int) $month, 1)->endOfMonth()->startOfDay();

        if ($dateExpiration->isPast()) {
            $this->loggerRejet(null, $fingerprint, 'Licence expirée (clé)', $acteur, $request);

            return $this->erreur(ErrorCodes::LICENCE_EXPIREE, 'La clé de licence est expirée.', [], 403);
        }

        $antiRejeu = $payload['anti_rejeu'];

        // ── Étape 5 : blacklist anti-rejeu (fenêtre TTL 5 min) ──────────
        // Bloque les rejeux à court terme (même requête envoyée deux fois).
        // La détection de partage de clé à long terme (Signal 4) est gérée
        // à l'étape 9 via SignalService, qui interroge la blacklist sans TTL.
        if (BlacklistAntirejeu::where('anti_rejeu', $antiRejeu)->actifs()->exists()) {
            $this->loggerRejet(null, $fingerprint, 'Anti-rejeu blacklisté (fenêtre TTL)', $acteur, $request);

            return $this->erreur(ErrorCodes::ANTIREJEU_BLACKLISTE, 'Cette clé est révoquée ou compromise.', [], 403);
        }

        // ── Étape 6 : blacklist fingerprint ──────────────────────────
        if (BlacklistFingerprint::where('fingerprint', $fingerprint)->exists()) {
            $this->loggerRejet(null, $fingerprint, 'Fingerprint blacklisté', $acteur, $request);

            return $this->erreur(ErrorCodes::FINGERPRINT_BLACKLISTE, 'Cette machine est bannie.', [], 403);
        }

        // ── Étape 7 : lookup licence par SHA-256 ────────────────────
        $cleHash = hash('sha256', $cleNormalisee);
        $licence = Licence::with('organisation')->where('cle_hash_sha256', $cleHash)->first();

        if ($licence === null) {
            $this->loggerRejet(null, $fingerprint, 'Licence introuvable (hash SHA-256)', $acteur, $request);

            return $this->erreur(ErrorCodes::LICENCE_INCONNUE, 'Licence inconnue.', [], 404);
        }

        // ── Étape 8 : statut ACTIVE ───────────────────────────────────
        if ($licence->statut !== Licence::STATUT_ACTIVE) {
            $this->loggerRejet($licence->licence_id, $fingerprint, "Statut {$licence->statut}", $acteur, $request);

            return $this->erreur(ErrorCodes::LICENCE_REVOQUEE, 'La licence n\'est pas active.', [], 403);
        }

        // ── Étape 9 : signaux d'anomalie ─────────────────────────────
        $signal = $this->signalService->verifierTousLesSignaux(
            licenceId:   $licence->licence_id,
            fingerprint: $fingerprint,
            antiRejeu:   $antiRejeu,
        );

        if ($signal['bloque']) {
            $this->loggerRejet(
                $licence->licence_id,
                $fingerprint,
                "Signal {$signal['signal']} : {$signal['message']}",
                $acteur,
                $request,
            );

            return $this->erreur($signal['code_erreur'], $signal['message'], [], 403);
        }

        // Signal 2 (rafale) : non bloquant, on continue
        $modules = self::MODULES[$licence->type_licence] ?? ['SYCEBNL'];

        // ── Étape 10 : transaction atomique ─────────────────────────
        $activation = DB::transaction(function () use ($licence, $fingerprint, $donnees, $acteur, $request): Activation {
            $activation = Activation::create([
                'licence_id'    => $licence->licence_id,
                'fingerprint'   => $fingerprint,
                'statut'        => Activation::STATUT_ACTIVE,
                'nom_poste'     => null,
                'ip_activation' => $request->ip(),
                'version_app'   => $donnees['version_app'] ?? null,
            ]);

            ActivationHistorique::enregistrer(
                activationId: $activation->activation_id,
                licenceId:    $licence->licence_id,
                evenement:    ActivationHistorique::EVT_ACTIVATION,
                acteur:       $acteur,
                ipSource:     $request->ip(),
                motif:        null,
            );

            DB::table('licences')
                ->where('licence_id', $licence->licence_id)
                ->increment('nb_activations');

            AuditLog::enregistrer(
                licenceId: $licence->licence_id,
                action:    AuditLog::ACTION_LICENCE_ACTIVEE,
                acteur:    $acteur,
                ipSource:  $request->ip(),
                detail:    [
                    'fingerprint_prefix' => substr($fingerprint, 0, 8) . '…',
                    'version_app'        => $donnees['version_app'] ?? null,
                    'activation_id'      => $activation->activation_id,
                ],
            );

            return $activation;
        });

        // ── Étape 11 : token JWT local ───────────────────────────────
        $tokenLocal = $this->genererTokenLocal($licence, $fingerprint, $modules);

        // ── Étape 12 : réponse 200 ───────────────────────────────────
        return $this->succes([
            'licence_id'      => $licence->licence_id,
            'org_nom'         => $licence->organisation?->nom,
            'type_licence'    => $licence->type_licence,
            'type_libelle'    => $licence->type_libelle,
            'nb_postes'       => $licence->nb_postes,
            'nb_sites'        => $licence->nb_sites,
            'nb_projets'      => $licence->nb_projets,
            'date_expiration' => $licence->date_expiration?->toDateString(),
            'modules'         => $modules,
            'token_local'     => $tokenLocal,
        ]);
    }

    // ================================================================
    // Routes ADMIN — Parcours 1 (stubs + index)
    // ================================================================

    /** Liste les activations d'une licence (ADMIN). */
    public function index(Request $request, string $licence_id): JsonResponse
    {
        $licence = Licence::find($licence_id);

        if ($licence === null) {
            return $this->erreur(ErrorCodes::LICENCE_INTROUVABLE, "Licence {$licence_id} introuvable.", [], 404);
        }

        $activations = Activation::where('licence_id', $licence_id)
            ->orderByDesc('activation_id')
            ->get();

        return $this->succes(
            $activations->map(fn (Activation $a) => [
                'activation_id' => $a->activation_id,
                'fingerprint'   => $a->fingerprint,
                'statut'        => $a->statut,
                'nom_poste'     => $a->nom_poste,
                'ip_activation' => $a->ip_activation,
                'version_app'   => $a->version_app,
            ])->values()->all(),
            ['total' => $activations->count()],
        );
    }

    /**
     * Désactive un poste (passe l'activation en INACTIVE) — action admin.
     *
     * Le client envoie statut = "DESACTIVEE" pour déclencher la désactivation.
     * En DB, le statut devient INACTIVE (via Activation::desactiver()).
     *
     * Flux :
     *   1. Valider statut(= DESACTIVEE) et motif(nullable)
     *   2. Charger la licence et l'activation (qui doit appartenir à cette licence)
     *   3. Vérifier que l'activation est ACTIVE
     *   4. Désactiver (UPDATE statut = INACTIVE + INSERT activation_historique EVT_DESACTIVATION)
     *   5. Décrémenter licences.nb_activations
     *   6. Retourner {activation_id, statut, slots_disponibles}
     */
    public function update(Request $request, string $licence_id, int $activation_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'statut' => ['required', 'string', 'in:DESACTIVEE'],
            'motif'  => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->erreur(
                ErrorCodes::VALIDATION_ECHOUEE,
                'Données invalides.',
                ['erreurs' => $validator->errors()->toArray()],
                422,
            );
        }

        $licence = Licence::find($licence_id);

        if ($licence === null) {
            return $this->erreur(ErrorCodes::LICENCE_INTROUVABLE, "Licence {$licence_id} introuvable.", [], 404);
        }

        $activation = Activation::where('activation_id', $activation_id)
            ->where('licence_id', $licence_id)
            ->first();

        if ($activation === null) {
            return $this->erreur(ErrorCodes::ACTIVATION_INTROUVABLE, "Activation {$activation_id} introuvable pour cette licence.", [], 404);
        }

        if (! $activation->estActive()) {
            return $this->erreur(
                ErrorCodes::ACTIVATION_IMPOSSIBLE,
                "L'activation {$activation_id} n'est pas dans l'état ACTIVE (statut actuel : {$activation->statut}).",
                [],
                409,
            );
        }

        $motif  = $validator->validated()['motif'] ?? null;
        $acteur = $this->acteurCourant($request);

        DB::transaction(function () use ($activation, $licence_id, $acteur, $motif, $request): void {
            // Désactiver l'activation + INSERT activation_historique EVT_DESACTIVATION
            $activation->desactiver($acteur, $motif, $request->ip());

            // Décrémenter nb_activations (sans aller en négatif)
            DB::table('licences')
                ->where('licence_id', $licence_id)
                ->where('nb_activations', '>', 0)
                ->decrement('nb_activations');
        });

        // Calculer les slots disponibles après mise à jour
        $nbActives = Activation::where('licence_id', $licence_id)
            ->where('statut', Activation::STATUT_ACTIVE)
            ->count();

        $slotsDisponibles = max(0, $licence->nb_postes - $nbActives);

        return $this->succes([
            'activation_id'    => $activation->activation_id,
            'statut'           => $activation->fresh()->statut,
            'slots_disponibles' => $slotsDisponibles,
        ]);
    }

    /**
     * Historique complet des événements d'activation d'une licence (ADMIN).
     * Trié par horodatage DESC, paginé.
     */
    public function historique(Request $request, string $licence_id): JsonResponse
    {
        $licence = Licence::find($licence_id);

        if ($licence === null) {
            return $this->erreur(ErrorCodes::LICENCE_INTROUVABLE, "Licence {$licence_id} introuvable.", [], 404);
        }

        $limite = min((int) $request->query('limite', 50), 200);
        $page   = max((int) $request->query('page', 1), 1);

        $paginator = ActivationHistorique::where('licence_id', $licence_id)
            ->orderByDesc('horodatage')
            ->paginate($limite, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(fn (ActivationHistorique $h) => [
            'histo_id'      => $h->histo_id,
            'activation_id' => $h->activation_id,
            'evenement'     => $h->evenement,
            'acteur'        => $h->acteur,
            'ip_source'     => $h->ip_source,
            'motif'         => $h->motif,
            'horodatage'    => $h->horodatage?->toIso8601String(),
        ])->values()->all();

        return $this->succes($items, [
            'total'  => $paginator->total(),
            'page'   => $paginator->currentPage(),
            'limite' => $paginator->perPage(),
        ]);
    }

    // ================================================================
    // Helpers privés
    // ================================================================

    /**
     * Génère un token JWT local HS256 signé avec APP_KEY.
     * Utilisé par le client WinDev pour valider la licence offline.
     *
     * Payload standard : iss, iat, exp, sub, fingerprint, type_licence,
     * nb_postes, nb_sites, nb_projets, modules.
     */
    private function genererTokenLocal(Licence $licence, string $fingerprint, array $modules): string
    {
        $b64u = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        $header = $b64u((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        $payload = $b64u((string) json_encode([
            'iss'          => 'certus',
            'iat'          => time(),
            'exp'          => $licence->date_expiration->copy()->endOfDay()->timestamp,
            'sub'          => $licence->licence_id,
            'fingerprint'  => $fingerprint,
            'type_licence' => $licence->type_licence,
            'nb_postes'    => $licence->nb_postes,
            'nb_sites'     => $licence->nb_sites,
            'nb_projets'   => $licence->nb_projets,
            'modules'      => $modules,
        ], JSON_UNESCAPED_UNICODE));

        // On dérive le secret depuis APP_KEY (Laravel le stocke en base64:...)
        $secret = base64_decode(substr((string) config('app.key'), 7)) ?: config('app.key');

        $signature = $b64u(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));

        return "{$header}.{$payload}.{$signature}";
    }

    /**
     * Enregistre un rejet d'activation dans audit_log et dans le log applicatif.
     * Loggue TOUTES les tentatives rejetées (exigence sécurité).
     *
     * @param string|null $licenceId  null si la licence n'a pas encore été identifiée
     * @param string      $fingerprint SHA-256 de la machine (64 hex)
     * @param string      $motif      Raison du rejet (pour le log interne)
     * @param string      $acteur     Nom de la clé API appelante
     */
    private function loggerRejet(
        ?string $licenceId,
        string  $fingerprint,
        string  $motif,
        string  $acteur,
        Request $request,
    ): void {
        Log::channel('certus')->warning("Activation rejetée : {$motif}", [
            'licence_id'         => $licenceId,
            'fingerprint_prefix' => substr($fingerprint, 0, 8) . '…',
            'ip'                 => $request->ip(),
        ]);

        AuditLog::enregistrer(
            licenceId: $licenceId,
            action:    AuditLog::ACTION_TENTATIVE_REJETEE,
            acteur:    $acteur,
            ipSource:  $request->ip(),
            detail:    [
                'motif'              => $motif,
                'fingerprint_prefix' => substr($fingerprint, 0, 8) . '…',
            ],
        );
    }
}
