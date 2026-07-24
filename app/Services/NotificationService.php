<?php

namespace App\Services;

use App\Mail\Certus\AlertePiratageSignal1;
use App\Mail\Certus\AlertePiratageSignal2;
use App\Mail\Certus\AlertePiratageSignal3;
use App\Mail\Certus\AlertePiratageSignal4;
use App\Mail\Certus\CleGenereeEmail;
use App\Constants\ErrorCodes;
use App\Models\Licence;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notifications email envoyées aux administrateurs et organisations.
 *
 * Tous les envois sont journalisés sur le canal certus et mis en file
 * sur la queue "certus-notifications" pour ne pas bloquer les réponses API.
 * En développement (MAIL_MAILER=log), les emails sont écrits dans les logs.
 */
class NotificationService
{
    private string $adminEmail;

    private const SIGNAL_CLASSES = [
        1 => AlertePiratageSignal1::class,
        2 => AlertePiratageSignal2::class,
        3 => AlertePiratageSignal3::class,
        4 => AlertePiratageSignal4::class,
    ];

    private const SIGNAL_LABELS = [
        1 => ErrorCodes::QUOTA_POSTES_ATTEINT,
        2 => ErrorCodes::SIGNAL_RAFALE,
        3 => ErrorCodes::FINGERPRINT_MULTI_LICENCES,
        4 => ErrorCodes::CLE_PARTAGEE_DETECTEE,
    ];

    public function __construct()
    {
        $this->adminEmail = (string) config('certus.admin_email', config('mail.from.address'));
    }

    // ----------------------------------------------------------------
    // Alertes sécurité
    // ----------------------------------------------------------------

    /**
     * Envoie une alerte de piratage pour le signal donné (1–4).
     *
     * L'email est mis en file (queue certus-notifications) pour ne pas
     * bloquer la réponse API. Le contexte est journalisé immédiatement.
     *
     * @param int   $signal  Numéro du signal détecté (1, 2, 3 ou 4)
     * @param array $contexte Données de contexte : licence_id, org_nom, horodatage, etc.
     */
    public function envoyerAlerteSignal(int $signal, array $contexte): void
    {
        $label = self::SIGNAL_LABELS[$signal] ?? "SIGNAL_{$signal}";

        Log::channel('certus')->critical("ALERTE PIRATAGE [{$label}]", $contexte);

        $classMail = self::SIGNAL_CLASSES[$signal] ?? null;
        if ($classMail === null) {
            Log::channel('certus')->error("NotificationService: signal inconnu [{$signal}]", $contexte);
            return;
        }

        Mail::to($this->adminEmail)->queue(new $classMail($contexte));
    }

    /**
     * Compatibilité descendante avec SignalService::alerterPiratage(string $signalLabel, array $contexte).
     *
     * SignalService appelle cette méthode avec la constante ErrorCodes (ex. QUOTA_POSTES_ATTEINT).
     * On convertit le label en numéro de signal et on délègue à envoyerAlerteSignal().
     *
     * @param string $signal  Constante ErrorCodes::SIGNAL_* ou QUOTA_POSTES_ATTEINT etc.
     * @param array  $contexte Données de contexte
     */
    public function alerterPiratage(string $signal, array $contexte): void
    {
        $labelToSignal = array_flip(self::SIGNAL_LABELS);
        $signalNum     = $labelToSignal[$signal] ?? null;

        if ($signalNum !== null) {
            $this->envoyerAlerteSignal($signalNum, $contexte);
            return;
        }

        // Fallback : journaliser sans Mailable pour les labels non mappés
        Log::channel('certus')->critical("ALERTE PIRATAGE [{$signal}]", $contexte);
    }

    // ----------------------------------------------------------------
    // Notifications licences
    // ----------------------------------------------------------------

    /**
     * Envoie la clé de licence générée à l'email du destinataire.
     *
     * @param string $email   Email de destination (responsable de l'organisation)
     * @param string $cle     Clé formatée XXXXX-XXXXX-XXXXX-XXXXX-XXXXX
     * @param array  $details Métadonnées : licence_id, org_nom, type_libelle, nb_postes,
     *                        nb_sites, nb_projets, date_expiration, horodatage
     */
    public function envoyerEmailGeneration(string $email, string $cle, array $details): void
    {
        Log::channel('certus')->info("Clé de licence envoyée par email.", [
            'destinataire' => $email,
            'licence_id'   => $details['licence_id'] ?? null,
            'org_nom'      => $details['org_nom'] ?? null,
        ]);

        Mail::to($email)->queue(new CleGenereeEmail($cle, $details));
    }

    /**
     * Notifie l'organisation que sa licence expire dans $joursRestants jours.
     */
    public function notifierExpirationProche(Licence $licence, int $joursRestants): void
    {
        Log::channel('certus')->info("Licence {$licence->licence_id} expire dans {$joursRestants} jour(s).", [
            'licence_id'      => $licence->licence_id,
            'organisation_id' => $licence->organisation_id,
            'date_expiration' => $licence->date_expiration?->toDateString(),
        ]);

        // TODO: Mail::to($licence->organisation->email)->queue(new ExpirationProcheEmail($licence, $joursRestants));
    }

    /**
     * Confirme une nouvelle activation à l'organisation.
     */
    public function notifierActivation(Licence $licence, string $fingerprintHash, string $hostname): void
    {
        Log::channel('certus')->info("Nouvelle activation — Licence {$licence->licence_id}.", [
            'licence_id'         => $licence->licence_id,
            'organisation_id'    => $licence->organisation_id,
            'hostname'           => $hostname,
            'fingerprint_prefix' => substr($fingerprintHash, 0, 8) . '…',
        ]);

        // TODO: Mail::to($licence->organisation->email)->queue(new NouvelleActivationEmail($licence, $hostname));
    }

    /**
     * Informe l'organisation qu'une de ses licences a été révoquée.
     */
    public function notifierRevocation(Licence $licence, string $raison): void
    {
        Log::channel('certus')->warning("Licence {$licence->licence_id} révoquée.", [
            'licence_id'      => $licence->licence_id,
            'organisation_id' => $licence->organisation_id,
            'raison'          => $raison,
        ]);

        // TODO: Mail::to($licence->organisation->email)->queue(new LicenceRevoqueeEmail($licence, $raison));
    }
}
