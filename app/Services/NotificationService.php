<?php

namespace App\Services;

use App\Models\Licence;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notifications email envoyées aux administrateurs et organisations.
 *
 * Tous les envois sont journalisés sur le canal certus.
 * En développement (MAIL_MAILER=log), les emails sont écrits dans les logs.
 */
class NotificationService
{
    private string $adminEmail;

    public function __construct()
    {
        $this->adminEmail = (string) config('certus.admin_email', config('mail.from.address'));
    }

    // ----------------------------------------------------------------
    // Alertes sécurité
    // ----------------------------------------------------------------

    /**
     * Alerte l'administrateur qu'un signal de piratage a été détecté.
     *
     * @param string $signal  Constante ErrorCodes::SIGNAL_*
     * @param array  $contexte Données de contexte (licence_id, fingerprint…)
     */
    public function alerterPiratage(string $signal, array $contexte): void
    {
        Log::channel('certus')->critical("ALERTE PIRATAGE [{$signal}]", $contexte);

        // TODO: envoyer un Mailable dédié à $this->adminEmail
        // Mail::to($this->adminEmail)->send(new AlertePiratageEmail($signal, $contexte));
    }

    // ----------------------------------------------------------------
    // Notifications licences
    // ----------------------------------------------------------------

    /**
     * Notifie l'organisation que sa licence expire dans $joursRestants jours.
     */
    public function notifierExpirationProche(Licence $licence, int $joursRestants): void
    {
        Log::channel('certus')->info("Licence #{$licence->id} expire dans {$joursRestants} jour(s).", [
            'licence_id'      => $licence->id,
            'organisation_id' => $licence->organisation_id,
            'date_expiration' => $licence->date_expiration?->toDateString(),
        ]);

        // TODO: Mail::to($licence->organisation->email)->send(new ExpirationProcheEmail($licence, $joursRestants));
    }

    /**
     * Confirme une nouvelle activation à l'organisation.
     */
    public function notifierActivation(Licence $licence, string $fingerprintHash, string $hostname): void
    {
        Log::channel('certus')->info("Nouvelle activation — Licence #{$licence->id}.", [
            'licence_id'      => $licence->id,
            'organisation_id' => $licence->organisation_id,
            'hostname'        => $hostname,
            'fingerprint_prefix' => substr($fingerprintHash, 0, 8) . '…',
        ]);

        // TODO: Mail::to($licence->organisation->email)->send(new NouvelleActivationEmail($licence, $hostname));
    }

    /**
     * Informe l'organisation qu'une de ses licences a été révoquée.
     */
    public function notifierRevocation(Licence $licence, string $raison): void
    {
        Log::channel('certus')->warning("Licence #{$licence->id} révoquée.", [
            'licence_id'      => $licence->id,
            'organisation_id' => $licence->organisation_id,
            'raison'          => $raison,
        ]);

        // TODO: Mail::to($licence->organisation->email)->send(new LicenceRevoqueeEmail($licence, $raison));
    }
}
