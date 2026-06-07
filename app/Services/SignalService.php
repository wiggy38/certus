<?php

namespace App\Services;

use App\Constants\ErrorCodes;
use App\Exceptions\CertusException;
use App\Models\Activation;
use App\Models\BlacklistAntirejeu;
use Illuminate\Support\Facades\Log;

/**
 * Détection des 4 signaux de piratage Certus.
 *
 * Signal 1 — CLONAGE       : même licence activée depuis un fingerprint différent
 * Signal 2 — MULTI_INSTANCE: trop d'instances simultanées (seuil configurable)
 * Signal 3 — REJEU         : nonce déjà utilisé (anti-replay attack)
 * Signal 4 — FALSIFICATION : signature de la clé ne correspond pas
 */
class SignalService
{
    /** Nombre maximum d'instances simultanées autorisées par défaut. */
    private const MAX_INSTANCES_DEFAUT = 1;

    // ----------------------------------------------------------------
    // Signal 1 : Clonage
    // ----------------------------------------------------------------

    /**
     * Détecte si une licence est utilisée depuis un fingerprint différent
     * de celui enregistré lors de la première activation.
     *
     * @throws CertusException(403) si clonage détecté
     */
    public function detecterClonage(string $fingerprintHash, int $licenceId): void
    {
        $autreActivation = Activation::query()
            ->where('licence_id', $licenceId)
            ->where('statut', 'active')
            ->whereNot('fingerprint_hash', $fingerprintHash)
            ->first();

        if ($autreActivation) {
            $this->journaliser(ErrorCodes::SIGNAL_CLONAGE, $licenceId, $fingerprintHash);
            throw CertusException::securite(
                ErrorCodes::SIGNAL_CLONAGE,
                'Tentative de clonage détectée : la licence est déjà activée sur une autre machine.',
                ['licence_id' => $licenceId],
            );
        }
    }

    // ----------------------------------------------------------------
    // Signal 2 : Multi-instance
    // ----------------------------------------------------------------

    /**
     * Vérifie que le nombre d'instances simultanées ne dépasse pas le seuil.
     *
     * @throws CertusException(403) si seuil dépassé
     */
    public function detecterMultiInstance(string $fingerprintHash, int $licenceId, int $maxInstances = self::MAX_INSTANCES_DEFAUT): void
    {
        $nbInstances = Activation::query()
            ->where('licence_id', $licenceId)
            ->where('fingerprint_hash', $fingerprintHash)
            ->where('statut', 'active')
            ->count();

        if ($nbInstances >= $maxInstances) {
            $this->journaliser(ErrorCodes::SIGNAL_MULTI_INSTANCE, $licenceId, $fingerprintHash);
            throw CertusException::securite(
                ErrorCodes::SIGNAL_MULTI_INSTANCE,
                "Nombre maximum d'instances simultanées ({$maxInstances}) atteint.",
                ['licence_id' => $licenceId, 'instances_actives' => $nbInstances],
            );
        }
    }

    // ----------------------------------------------------------------
    // Signal 3 : Anti-rejeu (nonce)
    // ----------------------------------------------------------------

    /**
     * Vérifie que le nonce n'a pas déjà été utilisé.
     * Enregistre le nonce pour la durée du TTL (5 minutes par défaut).
     *
     * @throws CertusException(403) si nonce rejoué
     */
    public function detecterRejeu(string $nonce, string $fingerprintHash, int $ttlMinutes = 5): void
    {
        $dejaUtilise = BlacklistAntirejeu::query()
            ->where('nonce', $nonce)
            ->valides()
            ->exists();

        if ($dejaUtilise) {
            $this->journaliser(ErrorCodes::SIGNAL_REJEU, null, $fingerprintHash);
            throw CertusException::securite(
                ErrorCodes::SIGNAL_REJEU,
                'Requête rejouée détectée : ce nonce a déjà été utilisé.',
                ['nonce_prefix' => substr($nonce, 0, 8) . '…'],
            );
        }

        BlacklistAntirejeu::create([
            'nonce'            => $nonce,
            'fingerprint_hash' => $fingerprintHash,
            'expire_le'        => now()->addMinutes($ttlMinutes),
        ]);
    }

    // ----------------------------------------------------------------
    // Signal 4 : Falsification
    // ----------------------------------------------------------------

    /**
     * Vérifie la signature d'une clé de licence via CleService.
     * La logique effective est déléguée à CleService::decoder().
     * Ce signal est détecté en amont, cette méthode sert à journaliser.
     *
     * @throws CertusException(403) si falsification confirmée
     */
    public function detecterFalsification(string $fingerprintHash, int $licenceId): void
    {
        $this->journaliser(ErrorCodes::SIGNAL_FALSIFICATION, $licenceId, $fingerprintHash);
        throw CertusException::securite(
            ErrorCodes::SIGNAL_FALSIFICATION,
            'Falsification de clé détectée : signature invalide.',
            ['licence_id' => $licenceId],
        );
    }

    // ----------------------------------------------------------------
    // Journalisation
    // ----------------------------------------------------------------

    private function journaliser(string $signal, ?int $licenceId, string $fingerprintHash): void
    {
        Log::channel('certus')->warning("Signal piratage [{$signal}]", [
            'signal'           => $signal,
            'licence_id'       => $licenceId,
            'fingerprint_prefix' => substr($fingerprintHash, 0, 8) . '…',
            'ip'               => request()->ip(),
        ]);
    }
}
