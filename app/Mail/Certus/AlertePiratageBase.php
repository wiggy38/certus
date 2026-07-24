<?php

namespace App\Mail\Certus;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Classe de base pour les alertes de piratage Certus.
 *
 * Toutes les alertes sont mises en file sur la queue "certus-notifications"
 * pour ne pas bloquer la réponse API lors de la détection d'un signal.
 *
 * Sous-classes :
 *   AlertePiratageSignal1 — Quota postes dépassé
 *   AlertePiratageSignal2 — Rafale d'activations
 *   AlertePiratageSignal3 — Fingerprint multi-licences
 *   AlertePiratageSignal4 — Clé partagée détectée
 *
 * Le tableau $contexte expose au moins : licence_id, org_nom, horodatage.
 * Des clés supplémentaires varient selon le signal.
 */
abstract class AlertePiratageBase extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Nombre de tentatives de re-envoi en cas d'échec. */
    public int $tries = 3;

    /** Délai en secondes entre deux tentatives (exponentiel : 60, 120, 240). */
    public int $backoff = 60;

    /**
     * @param array $contexte Données de contexte du signal (licence_id, org_nom, etc.)
     */
    public function __construct(
        public readonly array $contexte,
    ) {
        $this->onQueue('certus-notifications');
    }
}
