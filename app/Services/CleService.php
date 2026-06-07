<?php

namespace App\Services;

use App\Constants\ErrorCodes;
use App\Exceptions\CertusException;

/**
 * Génération, encodage et vérification des clés de licence Experto.
 *
 * FORMAT CLÉ : XXXXX-XXXXX-XXXXX-XXXXX-XXXXX (25 chars Base36 + 4 tirets = 29 chars)
 *
 * Structure des groupes :
 *   G1 (5) : org_index_b36 paddé XORé avec G5[0..1]      — identifiant organisation masqué
 *   G2 (5) : AAMM + version_format                        — ex: "27061" = juin 2027, v1
 *   G3 (5) : postes(1) + sites(1) + mode(C) + BDD(H) + 0 — quotas machine
 *   G4 (5) : projets(2) + type_licence(1) + anti_rejeu(2) — quotas projet + jeton
 *   G5 (5) : CRC32(G1_brut+G2+G3+G4) % 36^5 en Base36    — checksum intégrité
 *
 * Toutes les méthodes d'encodage/décodage sont statiques pour faciliter
 * les tests unitaires sans instanciation.
 */
class CleService
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /** Modulo Base36 pour tenir en 5 chars : 36^5 = 60 466 176 */
    private const CRC_MOD = 60_466_176;

    /** Caractère fixe pour le mode produit en G3. */
    private const G3_MODE = 'C';

    /** Caractère fixe pour le type de BDD en G3. */
    private const G3_BDD = 'H';

    // ================================================================
    // API publique
    // ================================================================

    /**
     * Génère une clé de licence Experto et les métadonnées associées.
     *
     * @param array{
     *   org_index_b36:  string,
     *   nb_postes:      int,
     *   nb_sites:       int,
     *   nb_projets:     int,
     *   type_licence:   string,
     *   date_expiration:string,
     *   version_format: int
     * } $params
     *
     * @return array{
     *   cle:             string,   — clé formatée XXXXX-XXXXX-XXXXX-XXXXX-XXXXX
     *   anti_rejeu:      string,   — jeton CHAR(2) à stocker en DB
     *   crc_g5:          string,   — groupe G5 (5 chars) à stocker en DB
     *   cle_hash_sha256: string    — SHA-256 de la clé à stocker en DB
     * }
     *
     * @throws CertusException si un paramètre est hors-plage
     */
    public function generer(array $params): array
    {
        $orgIndex      = strtoupper((string) ($params['org_index_b36'] ?? ''));
        $nbPostes      = (int) ($params['nb_postes']      ?? 0);
        $nbSites       = (int) ($params['nb_sites']       ?? 0);
        $nbProjets     = (int) ($params['nb_projets']     ?? 0);
        $typeLicence   = (string) ($params['type_licence'] ?? '1');
        $dateExp       = (string) ($params['date_expiration'] ?? '');
        $versionFormat = (int) ($params['version_format'] ?? 1);

        if (strlen($orgIndex) === 0 || strlen($orgIndex) > 5) {
            throw new CertusException(
                ErrorCodes::CLE_FORMAT_INVALIDE,
                'org_index_b36 invalide : 1 à 5 chars Base36 attendus.',
            );
        }

        [$yearStr, $monthStr] = array_pad(explode('-', $dateExp), 3, '01');

        // ── G1 brut : org_index paddé à 5 chars ──────────────────────
        $g1Brut = str_pad($orgIndex, 5, '0', STR_PAD_LEFT);

        // ── G2 : AAMM + version_format ───────────────────────────────
        $g2 = sprintf('%02d%02d%d', (int) $yearStr % 100, (int) $monthStr, $versionFormat);

        // ── G3 : postes(1) + sites(1) + C + H + 0 ───────────────────
        $g3 = self::encoderBase36($nbPostes, 1)
            . self::encoderBase36($nbSites, 1)
            . self::G3_MODE
            . self::G3_BDD
            . '0';

        // ── G4 : projets(2) + type(1) + anti_rejeu(2) ───────────────
        $antiRejeu = self::genererAntiRejeu();
        $g4 = self::encoderBase36($nbProjets, 2)
            . $typeLicence
            . $antiRejeu;

        // ── G5 : CRC32(G1_brut+G2+G3+G4) mod 36^5 ──────────────────
        $g5 = self::calculerG5($g1Brut, $g2, $g3, $g4);

        // ── G1 final : G1_brut XOR G5[0..1] ─────────────────────────
        $g1Final = self::xorGroupes($g1Brut, substr($g5, 0, 2));

        // ── Assemblage ────────────────────────────────────────────────
        $cle = strtoupper("{$g1Final}-{$g2}-{$g3}-{$g4}-{$g5}");

        return [
            'cle'             => $cle,
            'anti_rejeu'      => $antiRejeu,
            'crc_g5'          => $g5,
            'cle_hash_sha256' => hash('sha256', $cle),
        ];
    }

    /**
     * Décode une clé de licence et retourne ses composantes.
     * Vérifie le CRC avant de retourner les données.
     *
     * @return array{
     *   index_org:      string,   — org_index_b36 (sans zéros de remplissage)
     *   expiration:     string,   — 'YYYY-MM' (année + mois, sans le jour)
     *   nb_postes:      int,
     *   nb_sites:       int,
     *   nb_projets:     int,
     *   type_licence:   string,   — '1'|'2'|'3'|'4'|'9'
     *   anti_rejeu:     string,   — CHAR(2) [A-Z0-9]
     *   version_format: int
     * }
     *
     * @throws CertusException si le format ou le CRC est invalide
     */
    public function decoder(string $cle): array
    {
        $groupes = $this->parseGroupes($cle);
        [$g1Final, $g2, $g3, $g4, $g5] = $groupes;

        // Récupérer G1_brut en annulant le XOR avec G5[0..1]
        $g1Brut = self::xorGroupes($g1Final, substr($g5, 0, 2));

        // Vérifier le CRC avant toute utilisation des données
        $g5Attendu = self::calculerG5($g1Brut, $g2, $g3, $g4);
        if (! hash_equals($g5Attendu, $g5)) {
            throw CertusException::securite(
                ErrorCodes::CLE_CRC_INVALIDE,
                'CRC de la clé invalide : intégrité compromise.',
            );
        }

        // ── Décoder G2 : AAMM + version ─────────────────────────────
        $yearShort     = (int) substr($g2, 0, 2);
        $month         = (int) substr($g2, 2, 2);
        $versionFormat = (int) $g2[4];
        $year          = 2000 + $yearShort;

        // ── Décoder G3 ───────────────────────────────────────────────
        $nbPostes = self::decoderBase36($g3[0]);
        $nbSites  = self::decoderBase36($g3[1]);

        // ── Décoder G4 ───────────────────────────────────────────────
        $nbProjets   = self::decoderBase36(substr($g4, 0, 2));
        $typeLicence = $g4[2];
        $antiRejeu   = substr($g4, 3, 2);

        // Retirer les zéros de remplissage de G1_brut pour retrouver l'index
        $indexOrg = ltrim($g1Brut, '0') ?: '0';

        return [
            'index_org'      => $indexOrg,
            'expiration'     => sprintf('%04d-%02d', $year, $month),
            'nb_postes'      => $nbPostes,
            'nb_sites'       => $nbSites,
            'nb_projets'     => $nbProjets,
            'type_licence'   => $typeLicence,
            'anti_rejeu'     => $antiRejeu,
            'version_format' => $versionFormat,
        ];
    }

    /**
     * Vérifie le CRC d'une clé sans lever d'exception.
     * Recalcule G5 à partir de G1_brut+G2+G3+G4 et compare à G5 reçu.
     */
    public function verifierCRC(string $cle): bool
    {
        try {
            $groupes = $this->parseGroupes($cle);
        } catch (CertusException) {
            return false;
        }

        [$g1Final, $g2, $g3, $g4, $g5] = $groupes;
        $g1Brut    = self::xorGroupes($g1Final, substr($g5, 0, 2));
        $g5Attendu = self::calculerG5($g1Brut, $g2, $g3, $g4);

        return hash_equals($g5Attendu, $g5);
    }

    // ================================================================
    // Méthodes statiques publiques — utilisables dans les tests
    // ================================================================

    /**
     * Encode un entier en Base36 sur $longueur caractères (paddé à gauche).
     *
     * @param int $n        Valeur entière >= 0
     * @param int $longueur Nombre de caractères souhaité
     */
    public static function encoderBase36(int $n, int $longueur): string
    {
        if ($n < 0) {
            $n = 0;
        }

        $encoded = strtoupper(base_convert((string) $n, 10, 36));

        return str_pad($encoded, $longueur, '0', STR_PAD_LEFT);
    }

    /**
     * Décode une chaîne Base36 en entier.
     */
    public static function decoderBase36(string $s): int
    {
        return (int) base_convert(strtolower(trim($s)), 36, 10);
    }

    /**
     * Applique un XOR Base36 entre $g1 et $masque.
     *
     * Le XOR est appliqué sur les valeurs entières :
     *   result = encoderBase36( decoderBase36($g1) XOR decoderBase36($masque), len($g1) )
     *
     * Propriété : xorGroupes(xorGroupes($g1, $m), $m) === $g1 (auto-inverse).
     *
     * @param string $g1     Chaîne Base36 de longueur quelconque
     * @param string $masque Chaîne Base36 (longueur quelconque)
     */
    public static function xorGroupes(string $g1, string $masque): string
    {
        $len       = strlen($g1);
        $g1Int     = self::decoderBase36($g1);
        $masqueInt = self::decoderBase36($masque);
        $xored     = $g1Int ^ $masqueInt;

        // XOR of two integers can exceed 36^len (not a power-of-2 base).
        // Cap to representable range: this only triggers for org sequences near
        // the absolute maximum (~60M orgs) and breaks reversibility in that case.
        // For all realistic sequences (< 10^6), the XOR never overflows.
        $maxRange = (int) pow(36, $len);

        return self::encoderBase36($xored % $maxRange, $len);
    }

    // ================================================================
    // Helpers privés
    // ================================================================

    /**
     * Parse et normalise les 5 groupes d'une clé.
     *
     * @return array{string, string, string, string, string}
     * @throws CertusException si la structure est invalide
     */
    private function parseGroupes(string $cle): array
    {
        $cle     = strtoupper(trim($cle));
        $groupes = explode('-', $cle);

        if (count($groupes) !== 5) {
            throw new CertusException(
                ErrorCodes::CLE_FORMAT_INVALIDE,
                "Format de clé invalide : 5 groupes séparés par '-' attendus, "
                . count($groupes) . ' reçus.',
            );
        }

        foreach ($groupes as $i => $g) {
            if (strlen($g) !== 5) {
                throw new CertusException(
                    ErrorCodes::CLE_FORMAT_INVALIDE,
                    "Groupe G" . ($i + 1) . " invalide : 5 chars attendus, " . strlen($g) . " reçus.",
                );
            }
            if (! preg_match('/^[0-9A-Z]{5}$/', $g)) {
                throw new CertusException(
                    ErrorCodes::CLE_FORMAT_INVALIDE,
                    "Groupe G" . ($i + 1) . " invalide : caractères Base36 [0-9A-Z] attendus.",
                );
            }
        }

        return $groupes;
    }

    /**
     * Calcule G5 = CRC32(G1_brut + G2 + G3 + G4) mod 36^5 encodé en Base36 (5 chars).
     * Utilise la valeur absolue de crc32() pour éviter les entiers négatifs.
     */
    private static function calculerG5(string $g1Brut, string $g2, string $g3, string $g4): string
    {
        $crcVal = abs(crc32($g1Brut . $g2 . $g3 . $g4)) % self::CRC_MOD;

        return self::encoderBase36($crcVal, 5);
    }

    /**
     * Génère un jeton anti-rejeu de 2 caractères Base36 aléatoires.
     * Utilise random_bytes() pour la qualité cryptographique.
     */
    private static function genererAntiRejeu(): string
    {
        $bytes = random_bytes(2);

        return self::ALPHABET[ord($bytes[0]) % 36]
             . self::ALPHABET[ord($bytes[1]) % 36];
    }
}
