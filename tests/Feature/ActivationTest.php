<?php

namespace Tests\Feature;

use App\Constants\ErrorCodes;
use App\Models\Activation;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\BlacklistAntirejeu;
use App\Models\BlacklistFingerprint;
use App\Models\Licence;
use App\Models\Organisation;
use App\Services\CleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Tests Feature pour POST /api/v1/licences/activer — Parcours 2 (Certus).
 *
 * Couverture :
 *  - Activation réussie : HTTP 200, token JWT, modules, DB cohérent
 *  - CRC invalide             → 400 CLE_INVALIDE
 *  - Licence expirée          → 403 LICENCE_EXPIREE
 *  - Fingerprint blacklisté   → 403 FINGERPRINT_BLACKLISTE
 *  - Anti-rejeu blacklisté    → 403 ANTIREJEU_BLACKLISTE
 *  - Quota postes (Signal 1)  → 403 QUOTA_POSTES_ATTEINT + blacklist
 *  - Clé partagée (Signal 4)  → 403 CLE_PARTAGEE_DETECTEE + blacklist
 *  - API key manquante        → 401
 *  - Clé CLIENT sur route ADMIN → 403 API_KEY_INSUFFISANTE
 */
class ActivationTest extends TestCase
{
    use RefreshDatabase;

    private string $clientKey;
    private string $adminKey;

    // ================================================================
    // Initialisation
    // ================================================================

    protected function setUp(): void
    {
        parent::setUp();

        // Capture tous les emails — aucun envoi SMTP réel pendant les tests
        Mail::fake();

        // Clé CLIENT — utilisée pour POST /licences/activer
        $this->clientKey = 'ck_client_test_' . str_repeat('a', 20);
        ApiKey::create([
            'cle_hash' => hash('sha256', $this->clientKey),
            'niveau'   => 'CLIENT',
            'nom'      => 'Test Client Key',
            'active'   => true,
            'cree_le'  => now(),
        ]);

        // Clé ADMIN — utilisée pour vérifier le rejet sur routes admin
        $this->adminKey = 'ck_admin_test_' . str_repeat('b', 20);
        ApiKey::create([
            'cle_hash' => hash('sha256', $this->adminKey),
            'niveau'   => 'ADMIN',
            'nom'      => 'Test Admin Key',
            'active'   => true,
            'cree_le'  => now(),
        ]);
    }

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * Crée une organisation + une licence en DB avec une clé cohérente.
     *
     * @param array $overrides  nb_postes, nb_sites, nb_projets, type_licence, date_expiration
     * @return array{
     *   organisation: Organisation,
     *   licence: Licence,
     *   cle: string,
     *   anti_rejeu: string,
     *   payload: array,
     * }
     */
    private function creerLicenceTest(array $overrides = []): array
    {
        $organisation = Organisation::create([
            'nom'           => 'Acme Corp Test',
            'email_contact' => 'test@acme.ci',
            'pays'          => 'CI',
            'cree_par'      => 'test-setup',
        ]);

        $params = [
            'org_index_b36'  => $organisation->org_index_b36,
            'nb_postes'      => $overrides['nb_postes']      ?? 3,
            'nb_sites'       => $overrides['nb_sites']       ?? 1,
            'nb_projets'     => $overrides['nb_projets']     ?? 5,
            'type_licence'   => $overrides['type_licence']   ?? Licence::TYPE_STANDARD,
            'date_expiration' => $overrides['date_expiration'] ?? '2027-06-30',
            'version_format' => 1,
        ];

        $payload = app(CleService::class)->generer($params);

        $licence = Licence::create([
            'org_id'          => $organisation->org_id,
            'type_licence'    => $params['type_licence'],
            'nb_postes'       => $params['nb_postes'],
            'nb_sites'        => $params['nb_sites'],
            'nb_projets'      => $params['nb_projets'],
            'date_expiration' => $params['date_expiration'],
            'version_format'  => $params['version_format'],
            'anti_rejeu'      => $payload['anti_rejeu'],
            'cle_hash_sha256' => $payload['cle_hash_sha256'],
            'crc_g5'          => $payload['crc_g5'],
            'cree_par'        => 'test-setup',
        ]);

        return [
            'organisation' => $organisation,
            'licence'      => $licence,
            'cle'          => $payload['cle'],
            'anti_rejeu'   => $payload['anti_rejeu'],
            'payload'      => $payload,
        ];
    }

    /**
     * Retourne un SHA-256 hex déterministe de 64 chars à partir d'une graine.
     * Permet d'avoir des fingerprints valides, lisibles et reproductibles.
     */
    private function fingerprint(string $seed): string
    {
        return hash('sha256', 'certus-test-machine:' . $seed);
    }

    /**
     * Envoie une requête POST /api/v1/licences/activer avec la clé CLIENT par défaut.
     * Passer apiKey=null pour omettre l'en-tête X-API-Key.
     */
    private function postActiver(array $data, string|false|null $apiKey = 'default'): TestResponse
    {
        $headers = [];
        if ($apiKey === 'default') {
            $headers['X-API-Key'] = $this->clientKey;
        } elseif ($apiKey !== null && $apiKey !== false) {
            $headers['X-API-Key'] = $apiKey;
        }
        // null / false → pas de header (test d'auth manquante)

        return $this->postJson('/api/v1/licences/activer', $data, $headers);
    }

    // ================================================================
    // ✓ Activation réussie
    // ================================================================

    public function test_activation_reussie(): void
    {
        $ctx = $this->creerLicenceTest(['type_licence' => Licence::TYPE_STANDARD, 'nb_postes' => 3]);
        $fp  = $this->fingerprint('machine-a');

        $response = $this->postActiver([
            'cle'         => $ctx['cle'],
            'fingerprint' => $fp,
            'version_app' => '11.2.1',
        ]);

        // ── Réponse HTTP ────────────────────────────────────────────
        $response->assertStatus(200)
                 ->assertJsonPath('statut', 'OK')
                 ->assertJsonPath('data.type_licence', Licence::TYPE_STANDARD)
                 ->assertJsonPath('data.modules', ['SYCEBNL', 'GSTOCK'])
                 ->assertJsonStructure(['data' => [
                     'licence_id', 'org_nom', 'type_licence', 'type_libelle',
                     'nb_postes', 'nb_sites', 'nb_projets',
                     'date_expiration', 'modules', 'token_local',
                 ]]);

        // token_local = JWT valide (3 segments base64url séparés par des points)
        $tokenLocal = $response->json('data.token_local');
        $this->assertIsString($tokenLocal);
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/',
            $tokenLocal,
            'token_local doit être un JWT HS256 (3 segments base64url)',
        );

        // ── Base de données ─────────────────────────────────────────
        $this->assertDatabaseCount('activations', 1);
        $this->assertDatabaseHas('activations', [
            'licence_id'  => $ctx['licence']->licence_id,
            'fingerprint' => $fp,
            'statut'      => Activation::STATUT_ACTIVE,
            'version_app' => '11.2.1',
        ]);

        $this->assertDatabaseHas('licences', [
            'licence_id'     => $ctx['licence']->licence_id,
            'nb_activations' => 1,
        ]);

        $this->assertDatabaseHas('audit_log', [
            'licence_id' => $ctx['licence']->licence_id,
            'action'     => AuditLog::ACTION_LICENCE_ACTIVEE,
        ]);
    }

    // ================================================================
    // ✓ CRC invalide → 400 CLE_INVALIDE
    // ================================================================

    public function test_crc_invalide_retourne_400(): void
    {
        // Générer une clé valide et altérer G5 (checksum)
        $payload = app(CleService::class)->generer([
            'org_index_b36'   => '1',
            'nb_postes'       => 1,
            'nb_sites'        => 1,
            'nb_projets'      => 1,
            'type_licence'    => Licence::TYPE_STARTER,
            'date_expiration' => '2027-06-30',
            'version_format'  => 1,
        ]);

        $groupes = explode('-', $payload['cle']);
        $groupes[4] = ($groupes[4] === 'ZZZZZ') ? '00000' : 'ZZZZZ'; // corrompt G5
        $cleCorompue = implode('-', $groupes);

        $response = $this->postActiver([
            'cle'         => $cleCorompue,
            'fingerprint' => $this->fingerprint('machine-crc'),
        ]);

        $response->assertStatus(400)
                 ->assertJsonPath('statut', 'ERREUR')
                 ->assertJsonPath('meta.code_erreur', ErrorCodes::CLE_INVALIDE);

        // Aucune activation créée
        $this->assertDatabaseCount('activations', 0);
        // Aucun succès d'activation dans l'audit
        $this->assertDatabaseMissing('audit_log', ['action' => AuditLog::ACTION_LICENCE_ACTIVEE]);
    }

    // ================================================================
    // ✓ Licence expirée → 403 LICENCE_EXPIREE
    // ================================================================

    public function test_licence_expiree_retourne_403(): void
    {
        // La date est encodée dans la clé : CleService::generer() avec date passée
        $payload = app(CleService::class)->generer([
            'org_index_b36'   => '1',
            'nb_postes'       => 1,
            'nb_sites'        => 1,
            'nb_projets'      => 1,
            'type_licence'    => Licence::TYPE_STARTER,
            'date_expiration' => '2020-01-31', // passé
            'version_format'  => 1,
        ]);

        // Pas besoin de créer la licence en DB : l'expiration est vérifiée
        // à l'étape 4 (décodage de la clé), avant tout lookup BDD.
        $response = $this->postActiver([
            'cle'         => $payload['cle'],
            'fingerprint' => $this->fingerprint('machine-expired'),
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('statut', 'ERREUR')
                 ->assertJsonPath('meta.code_erreur', ErrorCodes::LICENCE_EXPIREE);

        $this->assertDatabaseCount('activations', 0);
    }

    // ================================================================
    // ✓ Fingerprint blacklisté → 403 FINGERPRINT_BLACKLISTE
    // ================================================================

    public function test_fingerprint_blackliste_retourne_403(): void
    {
        $ctx = $this->creerLicenceTest();
        $fp  = $this->fingerprint('machine-banned');

        BlacklistFingerprint::enregistrer(
            fingerprint: $fp,
            licenceId:   $ctx['licence']->licence_id,
            signal:      BlacklistFingerprint::SIGNAL_QUOTA_POSTES,
            bloquePar:   'test-setup',
            motif:       'Pré-banissement pour test',
        );

        $response = $this->postActiver([
            'cle'         => $ctx['cle'],
            'fingerprint' => $fp,
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('meta.code_erreur', ErrorCodes::FINGERPRINT_BLACKLISTE);

        $this->assertDatabaseCount('activations', 0);
    }

    // ================================================================
    // ✓ Anti-rejeu blacklisté → 403 ANTIREJEU_BLACKLISTE
    // ================================================================

    public function test_antirejeu_blackliste_retourne_403(): void
    {
        $ctx = $this->creerLicenceTest();

        // Insérer un enregistrement RÉCENT (< TTL 5 min) → step 5 doit le bloquer
        BlacklistAntirejeu::enregistrer(
            antiRejeu: $ctx['anti_rejeu'],
            licenceId: $ctx['licence']->licence_id,
            bloquePar: 'test-setup',
            motif:     'Anti-rejeu récent — test blocage',
        );
        // horodatage = now() (défaut dans enregistrer()), donc dans la fenêtre TTL ✓

        $response = $this->postActiver([
            'cle'         => $ctx['cle'],
            'fingerprint' => $this->fingerprint('machine-antirejeu'),
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('meta.code_erreur', ErrorCodes::ANTIREJEU_BLACKLISTE);

        $this->assertDatabaseCount('activations', 0);
    }

    // ================================================================
    // ✓ Signal 1 — quota de postes → 403 QUOTA_POSTES_ATTEINT
    // ================================================================

    public function test_quota_postes_signal_1(): void
    {
        $ctx = $this->creerLicenceTest(['nb_postes' => 2]);

        // Activer 2 fois → succès (quota = 2)
        $this->postActiver(['cle' => $ctx['cle'], 'fingerprint' => $this->fingerprint('quota-1')])
             ->assertStatus(200);
        $this->postActiver(['cle' => $ctx['cle'], 'fingerprint' => $this->fingerprint('quota-2')])
             ->assertStatus(200);

        $this->assertDatabaseCount('activations', 2);
        $this->assertDatabaseHas('licences', [
            'licence_id'     => $ctx['licence']->licence_id,
            'nb_activations' => 2,
        ]);

        // 3e tentative → Signal 1 → bloquée
        $fp3      = $this->fingerprint('quota-3');
        $response = $this->postActiver([
            'cle'         => $ctx['cle'],
            'fingerprint' => $fp3,
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('meta.code_erreur', ErrorCodes::QUOTA_POSTES_ATTEINT);

        // La 3e activation n'a pas été créée
        $this->assertDatabaseCount('activations', 2);

        // Le fingerprint de la 3e tentative est blacklisté avec signal=1
        $this->assertDatabaseHas('blacklist_fingerprints', [
            'fingerprint' => $fp3,
            'signal'      => BlacklistFingerprint::SIGNAL_QUOTA_POSTES,
            'licence_id'  => $ctx['licence']->licence_id,
        ]);
    }

    // ================================================================
    // ✓ Signal 4 — clé partagée → 403 CLE_PARTAGEE_DETECTEE
    // ================================================================

    public function test_signal_4_cle_partagee(): void
    {
        $ctx = $this->creerLicenceTest(['nb_postes' => 5]);
        $fpA = $this->fingerprint('signal4-machine-a');
        $fpB = $this->fingerprint('signal4-machine-b');

        // Étape 1 : activation légitime avec machine A → succès
        $this->postActiver(['cle' => $ctx['cle'], 'fingerprint' => $fpA])
             ->assertStatus(200);

        // Étape 2 : simuler que l'anti_rejeu a été marqué compromis (ancienne détection
        // ou révocation), avec un horodatage DÉPASSANT le TTL de 5 min.
        // → Bypasse step 5 (TTL-scoped) mais est détecté par Signal 4 (permanent).
        $bl = new BlacklistAntirejeu([
            'anti_rejeu' => $ctx['anti_rejeu'],
            'licence_id' => $ctx['licence']->licence_id,
            'bloque_par' => 'test-setup',
            'motif'      => 'Simulation clé partagée — pré-condition Signal 4',
            'horodatage' => now()->subMinutes(10), // hors TTL 5 min → step 5 laisse passer
        ]);
        $bl->save(); // INSERT autorisé (exists = false)

        // Étape 3 : machine B utilise la même clé → Signal 4
        $response = $this->postActiver([
            'cle'         => $ctx['cle'],
            'fingerprint' => $fpB,
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('meta.code_erreur', ErrorCodes::CLE_PARTAGEE_DETECTEE);

        // Machine B blacklistée avec signal=4
        $this->assertDatabaseHas('blacklist_fingerprints', [
            'fingerprint' => $fpB,
            'signal'      => BlacklistFingerprint::SIGNAL_CLE_PARTAGEE,
            'licence_id'  => $ctx['licence']->licence_id,
        ]);

        // Un 2e enregistrement anti_rejeu créé par SignalService::bloquerAntiRejeu()
        // (en plus de l'entrée de simulation insérée à l'étape 2)
        $this->assertDatabaseCount('blacklist_antirejeu', 2);

        // La 2e activation (machine B) n'a pas été créée
        $this->assertDatabaseCount('activations', 1);
    }

    // ================================================================
    // ✓ API key manquante → 401
    // ================================================================

    public function test_api_key_manquante_retourne_401(): void
    {
        $response = $this->postJson('/api/v1/licences/activer', [
            'cle'         => 'AAAAA-BBBBB-CCCCC-DDDDD-EEEEE',
            'fingerprint' => str_repeat('a', 64),
        ]);
        // Pas de header X-API-Key

        $response->assertStatus(401)
                 ->assertJsonPath('statut', 'ERREUR')
                 ->assertJsonPath('meta.code_erreur', ErrorCodes::API_KEY_MANQUANTE);
    }

    // ================================================================
    // ✓ Clé CLIENT refusée sur route ADMIN → 403
    // ================================================================

    public function test_api_key_client_ne_peut_pas_acceder_aux_routes_admin(): void
    {
        // POST /organisations est réservé ADMIN
        $response = $this->postJson(
            '/api/v1/organisations',
            [
                'nom'           => 'Org Test',
                'email_contact' => 'contact@test.ci',
                'pays'          => 'CI',
            ],
            ['X-API-Key' => $this->clientKey],
        );

        $response->assertStatus(403)
                 ->assertJsonPath('statut', 'ERREUR')
                 ->assertJsonPath('meta.code_erreur', ErrorCodes::API_KEY_INSUFFISANTE);
    }
}
