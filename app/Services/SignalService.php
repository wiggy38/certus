<?php

namespace App\Services;

use App\Constants\ErrorCodes;
use App\Models\Activation;
use App\Models\ActivationHistorique;
use App\Models\BlacklistAntirejeu;
use App\Models\BlacklistFingerprint;
use App\Models\Licence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Détection des 4 signaux d'anomalie lors des activations Experto.
 *
 * Chaque signal surveille un vecteur d'abus différent :
 *
 *   Signal 1 — QUOTA_POSTES    : nb_activations_actives >= nb_postes
 *              → Bloque (403), INSERT blacklist_fingerprints(signal=1)
 *
 *   Signal 2 — RAFALE          : > 5 activations sur la dernière heure
 *              → N'INTERDIT PAS l'activation, UPDATE tentatives_suspectes+1
 *
 *   Signal 3 — MULTI_LICENCES  : fingerprint ACTIVE sur 2+ licences différentes
 *              → Bloque (403), INSERT blacklist_fingerprints(signal=3)
 *
 *   Signal 4 — CLE_PARTAGEE    : anti_rejeu déjà blacklisté + fingerprint inconnu
 *              → Bloque (403), INSERT blacklist_fingerprints(signal=4)
 *                             + INSERT blacklist_antirejeu
 *
 * Point d'entrée : verifierTousLesSignaux() — appelé avant chaque activation.
 */
class SignalService
{
    /** Nombre maximum d'activations en 1 heure avant que Signal 2 se déclenche. */
    private const SEUIL_RAFALE_HEURE = 5;

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    // ================================================================
    // Point d'entrée principal
    // ================================================================

    /**
     * Exécute les 4 vérifications dans l'ordre de criticité décroissante.
     * Les signaux bloquants (1, 3, 4) stoppent la chaîne immédiatement.
     * Le signal 2 laisse l'activation continuer mais enregistre l'anomalie.
     *
     * @param string $licenceId  Licence à vérifier
     * @param string $fingerprint SHA-256 de la machine (64 hex)
     * @param string $antiRejeu  Jeton CHAR(2) de la licence
     *
     * @return array{
     *   signal:      int,      — 0 = aucun, 1-4 = numéro du signal déclenché
     *   bloque:      bool,     — true si l'activation doit être refusée
     *   message:     string,   — message descriptif à logger / retourner
     *   code_erreur: ?string   — constante ErrorCodes ou null si non bloquant
     * }
     */
    public function verifierTousLesSignaux(
        string $licenceId,
        string $fingerprint,
        string $antiRejeu,
    ): array {
        $licence = Licence::findOrFail($licenceId);

        // ── Signal 4 : clé partagée (priorité maximale) ──────────────
        if ($this->detecterSignal4($licenceId, $fingerprint, $antiRejeu)) {
            $this->bloquerFingerprint(
                $fingerprint,
                $licenceId,
                BlacklistFingerprint::SIGNAL_CLE_PARTAGEE,
                'Clé partagée : anti_rejeu connu + fingerprint inconnu',
            );
            $this->bloquerAntiRejeu($antiRejeu, $licenceId);
            $this->notifications->alerterPiratage('SIGNAL_4_CLE_PARTAGEE', [
                'licence_id'         => $licenceId,
                'fingerprint_prefix' => substr($fingerprint, 0, 8) . '…',
                'anti_rejeu'         => $antiRejeu,
            ]);

            return $this->resultat(4, true, 'Partage de clé détecté : anti-rejeu connu avec empreinte inconnue.', ErrorCodes::CLE_PARTAGEE_DETECTEE);
        }

        // ── Signal 3 : fingerprint actif sur plusieurs licences ───────
        if ($this->detecterSignal3($fingerprint)) {
            $this->bloquerFingerprint(
                $fingerprint,
                $licenceId,
                BlacklistFingerprint::SIGNAL_MULTI_LICENCES,
                'Empreinte active sur plusieurs licences simultanément',
            );
            $this->notifications->alerterPiratage('SIGNAL_3_MULTI_LICENCES', [
                'licence_id'         => $licenceId,
                'fingerprint_prefix' => substr($fingerprint, 0, 8) . '…',
            ]);

            return $this->resultat(3, true, 'Empreinte machine active sur plusieurs licences simultanément.', ErrorCodes::FINGERPRINT_MULTI_LICENCES);
        }

        // ── Signal 1 : quota de postes atteint ───────────────────────
        if ($this->detecterSignal1($licence)) {
            $this->bloquerFingerprint(
                $fingerprint,
                $licenceId,
                BlacklistFingerprint::SIGNAL_QUOTA_POSTES,
                "Quota de {$licence->nb_postes} poste(s) atteint",
            );
            $this->notifications->alerterPiratage('SIGNAL_1_QUOTA_POSTES', [
                'licence_id' => $licenceId,
                'nb_postes'  => $licence->nb_postes,
            ]);

            return $this->resultat(1, true, "Quota de postes ({$licence->nb_postes}) atteint : activation refusée.", ErrorCodes::QUOTA_POSTES_ATTEINT);
        }

        // ── Signal 2 : rafale (avertissement, non bloquant) ───────────
        if ($this->detecterSignal2($licenceId)) {
            DB::table('licences')
                ->where('licence_id', $licenceId)
                ->increment('tentatives_suspectes');

            $this->notifications->alerterPiratage('SIGNAL_2_RAFALE', [
                'licence_id' => $licenceId,
                'seuil'      => self::SEUIL_RAFALE_HEURE,
            ]);

            return $this->resultat(2, false, "Rafale d'activations détectée : tentative suspecte enregistrée.", ErrorCodes::SIGNAL_RAFALE);
        }

        return $this->resultat(0, false, 'Aucune anomalie détectée.', null);
    }

    // ================================================================
    // Détecteurs individuels (publics pour tests unitaires)
    // ================================================================

    /**
     * Signal 1 — Le nombre d'activations ACTIVE dépasse le quota de postes.
     *
     * Un fingerprint déjà ACTIVE pour cette licence est toujours compté
     * (le heartbeat ne crée pas de nouvelle activation).
     */
    public function detecterSignal1(Licence $licence): bool
    {
        $nbActives = Activation::where('licence_id', $licence->licence_id)
            ->where('statut', Activation::STATUT_ACTIVE)
            ->count();

        $depasse = $nbActives >= $licence->nb_postes;

        if ($depasse) {
            Log::channel('certus')->warning('Signal 1 — Quota postes dépassé', [
                'licence_id' => $licence->licence_id,
                'nb_postes'  => $licence->nb_postes,
                'nb_actives' => $nbActives,
            ]);
        }

        return $depasse;
    }

    /**
     * Signal 2 — Plus de 5 nouvelles activations en 1 heure sur la même licence.
     * Non bloquant : l'activation est autorisée si le quota (Signal 1) n'est pas atteint.
     * Incrémente licences.tentatives_suspectes en cas de déclenchement.
     *
     * Source : activation_historique (evenement = ACTIVATION, horodatage < 1 h).
     */
    public function detecterSignal2(string $licenceId): bool
    {
        $nbRecentes = ActivationHistorique::where('licence_id', $licenceId)
            ->where('evenement', ActivationHistorique::EVT_ACTIVATION)
            ->where('horodatage', '>=', now()->subHour())
            ->count();

        $rafale = $nbRecentes > self::SEUIL_RAFALE_HEURE;

        if ($rafale) {
            Log::channel('certus')->warning('Signal 2 — Rafale activations détectée', [
                'licence_id'  => $licenceId,
                'nb_recentes' => $nbRecentes,
                'seuil'       => self::SEUIL_RAFALE_HEURE,
            ]);
        }

        return $rafale;
    }

    /**
     * Signal 3 — Le fingerprint est ACTIVE sur au moins 2 licences différentes.
     * Indique qu'une même machine tente d'activer plusieurs licences simultanément.
     */
    public function detecterSignal3(string $fingerprint): bool
    {
        $nbLicences = Activation::where('fingerprint', $fingerprint)
            ->where('statut', Activation::STATUT_ACTIVE)
            ->distinct('licence_id')
            ->count('licence_id');

        $multiLicences = $nbLicences >= 2;

        if ($multiLicences) {
            Log::channel('certus')->warning('Signal 3 — Fingerprint multi-licences', [
                'fingerprint_prefix' => substr($fingerprint, 0, 8) . '…',
                'nb_licences_actives' => $nbLicences,
            ]);
        }

        return $multiLicences;
    }

    /**
     * Signal 4 — Clé partagée : anti_rejeu déjà blacklisté + fingerprint inconnu.
     *
     * Conditions conjointes :
     *  a) L'anti_rejeu de cette licence est présent dans blacklist_antirejeu
     *     (enregistrement permanent, sans filtre TTL).
     *  b) Le fingerprint n'a jamais été vu sur cette licence
     *     (aucune activation — actuelle ou passée — avec ce fingerprint).
     *
     * Signature : quelqu'un a redistribué la clé de licence à une autre machine.
     */
    public function detecterSignal4(string $licenceId, string $fingerprint, string $antiRejeu): bool
    {
        // a) Anti-rejeu connu (vérification permanente, hors TTL)
        $antiRejeuBloque = BlacklistAntirejeu::where('anti_rejeu', $antiRejeu)
            ->where('licence_id', $licenceId)
            ->exists();

        if (! $antiRejeuBloque) {
            return false;
        }

        // b) Fingerprint inconnu pour cette licence (aucune activation, quel que soit le statut)
        $fingerprintConnu = Activation::where('licence_id', $licenceId)
            ->where('fingerprint', $fingerprint)
            ->exists();

        if ($fingerprintConnu) {
            return false;
        }

        Log::channel('certus')->warning('Signal 4 — Clé partagée détectée', [
            'licence_id'         => $licenceId,
            'fingerprint_prefix' => substr($fingerprint, 0, 8) . '…',
            'anti_rejeu'         => $antiRejeu,
        ]);

        return true;
    }

    // ================================================================
    // Actions de blocage (publiques pour appel manuel par admin)
    // ================================================================

    /**
     * Insère une entrée dans blacklist_fingerprints et journalise.
     *
     * @param string $fingerprint SHA-256 de la machine (64 hex)
     * @param string $licenceId   Licence ayant déclenché le signal
     * @param int    $signal      BlacklistFingerprint::SIGNAL_* (1, 3 ou 4)
     * @param string $motif       Description textuelle
     */
    public function bloquerFingerprint(
        string $fingerprint,
        string $licenceId,
        int $signal,
        string $motif,
    ): void {
        BlacklistFingerprint::enregistrer(
            fingerprint: $fingerprint,
            licenceId:   $licenceId,
            signal:      $signal,
            bloquePar:   'SYSTEME',
            motif:       $motif,
        );

        Log::channel('certus')->critical('Fingerprint blacklisté', [
            'licence_id'         => $licenceId,
            'fingerprint_prefix' => substr($fingerprint, 0, 8) . '…',
            'signal'             => $signal,
            'motif'              => $motif,
        ]);
    }

    /**
     * Insère une entrée dans blacklist_antirejeu et journalise.
     * Utilisé par Signal 4 pour marquer la clé comme compromise.
     *
     * @param string $antiRejeu Jeton CHAR(2) [A-Z0-9] de la licence
     * @param string $licenceId Licence concernée
     */
    public function bloquerAntiRejeu(string $antiRejeu, string $licenceId): void
    {
        BlacklistAntirejeu::enregistrer(
            antiRejeu: $antiRejeu,
            licenceId: $licenceId,
            bloquePar: 'SYSTEME',
            motif:     'Clé partagée confirmée (Signal 4)',
        );

        Log::channel('certus')->critical('Anti-rejeu blacklisté', [
            'licence_id' => $licenceId,
            'anti_rejeu' => $antiRejeu,
        ]);
    }

    // ================================================================
    // Helper privé
    // ================================================================

    /**
     * Construit le tableau de résultat uniforme retourné par verifierTousLesSignaux().
     */
    private function resultat(int $signal, bool $bloque, string $message, ?string $codeErreur): array
    {
        return [
            'signal'      => $signal,
            'bloque'      => $bloque,
            'message'     => $message,
            'code_erreur' => $codeErreur,
        ];
    }
}
