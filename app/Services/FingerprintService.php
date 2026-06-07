<?php

namespace App\Services;

use App\Constants\ErrorCodes;
use App\Exceptions\CertusException;
use App\Models\BlacklistFingerprint;

/**
 * Validation et gestion des fingerprints de machines clientes.
 *
 * Un fingerprint est un hash SHA-256 (64 hex) calculé côté client
 * à partir des composants matériels (CPU, MAC, hostname, OS serial…).
 * Il identifie de manière stable et unique une machine.
 */
class FingerprintService
{
    /**
     * Valide le format d'un fingerprint (SHA-256 hex attendu).
     * Lance CertusException si le format est incorrect.
     */
    public function valider(string $fingerprintHash): void
    {
        if (! preg_match('/^[a-f0-9]{64}$/i', $fingerprintHash)) {
            throw new CertusException(
                ErrorCodes::FINGERPRINT_INVALIDE,
                'Le fingerprint doit être un hash SHA-256 valide (64 hex).',
                ['fingerprint_prefix' => substr($fingerprintHash, 0, 8) . '…'],
            );
        }
    }

    /**
     * Retourne true si le fingerprint est sur la liste noire active.
     */
    public function estBlackliste(string $fingerprintHash): bool
    {
        return BlacklistFingerprint::query()
            ->where('fingerprint_hash', $fingerprintHash)
            ->actifs()
            ->exists();
    }

    /**
     * Vérifie la blacklist et lève CertusException(403) si bloqué.
     */
    public function verifierBlacklist(string $fingerprintHash): void
    {
        if ($this->estBlackliste($fingerprintHash)) {
            throw CertusException::securite(
                ErrorCodes::FINGERPRINT_BLACKLISTE,
                'Ce fingerprint est sur liste noire et ne peut plus activer de licence.',
                ['fingerprint_prefix' => substr($fingerprintHash, 0, 8) . '…'],
            );
        }
    }

    /**
     * Calcule un fingerprint SHA-256 à partir des composants machine fournis.
     * Les composants sont triés alphabétiquement puis concaténés avec '|'.
     *
     * @param array<string, string> $composants ex: ['cpu_id' => '...', 'mac' => '...']
     */
    public function calculer(array $composants): string
    {
        ksort($composants);
        $chaine = implode('|', array_map(
            fn (string $k, string $v) => "{$k}:{$v}",
            array_keys($composants),
            array_values($composants),
        ));

        return hash('sha256', $chaine);
    }
}
