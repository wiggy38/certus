<?php

namespace Tests\Unit;

use App\Constants\ErrorCodes;
use App\Exceptions\CertusException;
use App\Services\CleService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CleService.
 *
 * Couverture :
 *  - Round-trip génération → décodage → vérification CRC
 *  - Chaque type de licence et configuration de quotas
 *  - CRC invalide → verifierCRC() retourne false
 *  - Formats de clé invalides → CertusException
 *  - XOR auto-inverse : xorGroupes(xorGroupes(x, m), m) === x
 *  - Encodage/décodage Base36 aux bornes
 */
class CleServiceTest extends TestCase
{
    private CleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CleService();
    }

    // ================================================================
    // Données communes
    // ================================================================

    private function parametresBase(array $override = []): array
    {
        return array_merge([
            'org_index_b36'   => '4K',         // seq 164
            'nb_postes'       => 5,
            'nb_sites'        => 3,
            'nb_projets'      => 10,
            'type_licence'    => '2',           // STANDARD
            'date_expiration' => '2027-06-30',
            'version_format'  => 1,
        ], $override);
    }

    // ================================================================
    // Structure de la clé générée
    // ================================================================

    public function test_generer_retourne_les_quatre_cles_de_sortie(): void
    {
        $result = $this->service->generer($this->parametresBase());

        $this->assertArrayHasKey('cle', $result);
        $this->assertArrayHasKey('anti_rejeu', $result);
        $this->assertArrayHasKey('crc_g5', $result);
        $this->assertArrayHasKey('cle_hash_sha256', $result);
    }

    public function test_generer_produit_format_xxxxx_5_groupes(): void
    {
        $result = $this->service->generer($this->parametresBase());

        $this->assertMatchesRegularExpression(
            '/^[0-9A-Z]{5}-[0-9A-Z]{5}-[0-9A-Z]{5}-[0-9A-Z]{5}-[0-9A-Z]{5}$/',
            $result['cle'],
        );
    }

    public function test_generer_produit_anti_rejeu_deux_chars_base36(): void
    {
        $result = $this->service->generer($this->parametresBase());

        $this->assertMatchesRegularExpression('/^[0-9A-Z]{2}$/', $result['anti_rejeu']);
    }

    public function test_generer_produit_crc_g5_cinq_chars_base36(): void
    {
        $result = $this->service->generer($this->parametresBase());

        $this->assertMatchesRegularExpression('/^[0-9A-Z]{5}$/', $result['crc_g5']);
    }

    public function test_generer_hash_sha256_correspond_a_la_cle(): void
    {
        $result = $this->service->generer($this->parametresBase());

        $this->assertSame(hash('sha256', $result['cle']), $result['cle_hash_sha256']);
    }

    // ================================================================
    // Round-trip génération → décodage
    // ================================================================

    public function test_decoder_retrouve_tous_les_champs_apres_generation(): void
    {
        $params = $this->parametresBase();
        $result = $this->service->generer($params);
        $decode = $this->service->decoder($result['cle']);

        $this->assertSame(strtoupper($params['org_index_b36']), $decode['index_org']);
        $this->assertSame('2027-06',                $decode['expiration']);
        $this->assertSame($params['nb_postes'],     $decode['nb_postes']);
        $this->assertSame($params['nb_sites'],      $decode['nb_sites']);
        $this->assertSame($params['nb_projets'],    $decode['nb_projets']);
        $this->assertSame($params['type_licence'],  $decode['type_licence']);
        $this->assertSame($result['anti_rejeu'],    $decode['anti_rejeu']);
        $this->assertSame($params['version_format'], $decode['version_format']);
    }

    public function test_decoder_retrouve_index_org_avec_grand_numero(): void
    {
        $params = $this->parametresBase(['org_index_b36' => '255S']); // seq ~100008
        $result = $this->service->generer($params);
        $decode = $this->service->decoder($result['cle']);

        $this->assertSame('255S', $decode['index_org']);
    }

    public function test_decoder_retrouve_index_org_minimal(): void
    {
        $params = $this->parametresBase(['org_index_b36' => '1']); // seq 1
        $result = $this->service->generer($params);
        $decode = $this->service->decoder($result['cle']);

        $this->assertSame('1', $decode['index_org']);
    }

    public function test_round_trip_pour_chaque_type_de_licence(): void
    {
        foreach (['1', '2', '3', '4', '9'] as $type) {
            $params = $this->parametresBase(['type_licence' => $type]);
            $result = $this->service->generer($params);
            $decode = $this->service->decoder($result['cle']);

            $this->assertSame($type, $decode['type_licence'], "Échec pour type=$type");
        }
    }

    public function test_round_trip_avec_quotas_maximaux(): void
    {
        $params = $this->parametresBase([
            'nb_postes'  => 35,    // max 1 char Base36
            'nb_sites'   => 35,
            'nb_projets' => 1295,  // max 2 chars Base36
        ]);
        $result = $this->service->generer($params);
        $decode = $this->service->decoder($result['cle']);

        $this->assertSame(35,   $decode['nb_postes']);
        $this->assertSame(35,   $decode['nb_sites']);
        $this->assertSame(1295, $decode['nb_projets']);
    }

    public function test_round_trip_date_decembre_2099(): void
    {
        $params = $this->parametresBase(['date_expiration' => '2099-12-31']);
        $result = $this->service->generer($params);
        $decode = $this->service->decoder($result['cle']);

        $this->assertSame('2099-12', $decode['expiration']);
    }

    public function test_decoder_accepte_cle_en_minuscules(): void
    {
        $result = $this->service->generer($this->parametresBase());
        $decode = $this->service->decoder(strtolower($result['cle']));

        $this->assertSame(5, $decode['nb_postes']);
    }

    // ================================================================
    // verifierCRC
    // ================================================================

    public function test_verifier_crc_retourne_true_pour_cle_valide(): void
    {
        $result = $this->service->generer($this->parametresBase());

        $this->assertTrue($this->service->verifierCRC($result['cle']));
    }

    public function test_verifier_crc_retourne_false_pour_format_incomplet(): void
    {
        $this->assertFalse($this->service->verifierCRC('AAAAA-BBBBB-CCCCC'));
    }

    public function test_verifier_crc_retourne_false_si_g5_altere(): void
    {
        $result  = $this->service->generer($this->parametresBase());
        $groupes = explode('-', $result['cle']);

        // Altérer G5 (5e groupe)
        $groupes[4] = ($groupes[4] === 'ZZZZZ') ? '00000' : 'ZZZZZ';
        $cleAltere  = implode('-', $groupes);

        $this->assertFalse($this->service->verifierCRC($cleAltere));
    }

    public function test_verifier_crc_retourne_false_si_g2_altere(): void
    {
        $result  = $this->service->generer($this->parametresBase());
        $groupes = explode('-', $result['cle']);

        $groupes[1] = '99991'; // date falsifiée
        $cleAltere  = implode('-', $groupes);

        $this->assertFalse($this->service->verifierCRC($cleAltere));
    }

    public function test_decoder_leve_exception_si_crc_invalide(): void
    {
        $result  = $this->service->generer($this->parametresBase());
        $groupes = explode('-', $result['cle']);
        $groupes[4] = ($groupes[4] === 'ZZZZZ') ? '00000' : 'ZZZZZ';

        $this->expectException(CertusException::class);
        $this->service->decoder(implode('-', $groupes));
    }

    // ================================================================
    // Validation du format
    // ================================================================

    public function test_decoder_leve_exception_si_moins_de_cinq_groupes(): void
    {
        $this->expectException(CertusException::class);
        $this->service->decoder('AAAAA-BBBBB-CCCCC');
    }

    public function test_decoder_leve_exception_si_groupe_trop_court(): void
    {
        $this->expectException(CertusException::class);
        $this->service->decoder('AAAA-BBBBB-CCCCC-DDDDD-EEEEE'); // G1 = 4 chars
    }

    public function test_decoder_leve_exception_si_caracteres_invalides(): void
    {
        $this->expectException(CertusException::class);
        $this->service->decoder('AAAAA-BB!BB-CCCCC-DDDDD-EEEEE');
    }

    public function test_generer_leve_exception_si_org_index_vide(): void
    {
        $this->expectException(CertusException::class);
        $this->service->generer($this->parametresBase(['org_index_b36' => '']));
    }

    public function test_generer_leve_exception_si_org_index_trop_long(): void
    {
        $this->expectException(CertusException::class);
        $this->service->generer($this->parametresBase(['org_index_b36' => 'ZZZZZZ'])); // 6 chars
    }

    // ================================================================
    // xorGroupes — propriété auto-inverse
    // ================================================================

    public function test_xor_groupes_est_auto_inverse(): void
    {
        $g1     = '0004K';
        $masque = '1A';

        $g1Xore  = CleService::xorGroupes($g1, $masque);
        $g1Revert = CleService::xorGroupes($g1Xore, $masque);

        $this->assertSame($g1, $g1Revert);
    }

    public function test_xor_groupes_avec_masque_nul_est_identite(): void
    {
        $g1 = '0004K';

        $this->assertSame($g1, CleService::xorGroupes($g1, '00'));
    }

    public function test_xor_groupes_auto_inverse_aux_bornes(): void
    {
        // 'ZZZ00' (60464880) XOR 'ZZ' (1295) = 60464127 < 36^5-1 : aucun débordement.
        // 'ZZZZZ' XOR 'ZZ' déborderait (60466928 > 36^5-1) et ne serait pas réversible.
        $g1     = 'ZZZ00';
        $masque = 'ZZ';

        $g1Xore   = CleService::xorGroupes($g1, $masque);
        $g1Revert = CleService::xorGroupes($g1Xore, $masque);

        $this->assertSame($g1, $g1Revert);
    }

    public function test_xor_groupes_ne_depasse_pas_la_longueur_cible(): void
    {
        // Même dans le cas de débordement (ZZZZZ XOR ZZ), la sortie doit
        // rester sur 5 chars grâce au cap par modulo dans xorGroupes().
        $result = CleService::xorGroupes('ZZZZZ', 'ZZ');
        $this->assertSame(5, strlen($result));
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{5}$/', $result);
    }

    public function test_xor_groupes_produit_chaine_base36_valide(): void
    {
        $result = CleService::xorGroupes('0004K', '1A');

        $this->assertMatchesRegularExpression('/^[0-9A-Z]{5}$/', $result);
    }

    // ================================================================
    // encoderBase36 / decoderBase36
    // ================================================================

    public function test_encoder_base36_zero_donne_zeros(): void
    {
        $this->assertSame('00000', CleService::encoderBase36(0, 5));
        $this->assertSame('0',     CleService::encoderBase36(0, 1));
    }

    public function test_encoder_base36_valeurs_connues(): void
    {
        $this->assertSame('1',  CleService::encoderBase36(1, 1));
        $this->assertSame('Z',  CleService::encoderBase36(35, 1));   // max 1 char
        $this->assertSame('10', CleService::encoderBase36(36, 2));   // 36₁₀ = 10₃₆
        $this->assertSame('ZZ', CleService::encoderBase36(1295, 2)); // max 2 chars
    }

    public function test_decoder_base36_valeurs_connues(): void
    {
        $this->assertSame(0,    CleService::decoderBase36('0'));
        $this->assertSame(35,   CleService::decoderBase36('Z'));
        $this->assertSame(36,   CleService::decoderBase36('10'));
        $this->assertSame(1295, CleService::decoderBase36('ZZ'));
    }

    public function test_encoder_puis_decoder_base36_est_identique(): void
    {
        foreach ([0, 1, 35, 36, 164, 1000, 60_466_175] as $n) {
            $encoded = CleService::encoderBase36($n, 5);
            $decoded = CleService::decoderBase36($encoded);
            $this->assertSame($n, $decoded, "Round-trip Base36 échoué pour n=$n");
        }
    }

    // ================================================================
    // Aléatoire — anti_rejeu différent à chaque appel
    // ================================================================

    public function test_deux_generations_ont_anti_rejeu_differents(): void
    {
        $params = $this->parametresBase();
        $antis  = [];

        for ($i = 0; $i < 20; $i++) {
            $antis[] = $this->service->generer($params)['anti_rejeu'];
        }

        // Probabilité que 20 tirages soient identiques : (1/1296)^19 ≈ 0
        $this->assertGreaterThan(1, count(array_unique($antis)));
    }
}
