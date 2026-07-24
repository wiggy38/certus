<?php

namespace App\Mail\Certus;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de transmission de clé de licence — envoyé à l'organisation.
 *
 * Contient la clé de licence générée et ses paramètres principaux.
 * Mis en file sur "certus-notifications" pour ne pas bloquer l'API.
 *
 * $details attendus : licence_id, org_nom, type_libelle, nb_postes,
 *                     nb_sites, nb_projets, date_expiration, horodatage.
 */
class CleGenereeEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    /**
     * @param string $cle     Clé formatée XXXXX-XXXXX-XXXXX-XXXXX-XXXXX
     * @param array  $details Métadonnées de la licence à afficher dans l'email
     */
    public function __construct(
        public readonly string $cle,
        public readonly array $details,
    ) {
        $this->onQueue('certus-notifications');
    }

    public function envelope(): Envelope
    {
        $orgNom = $this->details['org_nom'] ?? 'Votre organisation';

        return new Envelope(
            subject: "[CERTUS LICENCE] Votre clé Experto — {$orgNom}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.certus.cle_generee',
            with: [
                'couleur'   => '#27ae60',
                'portalUrl' => config('certus.portal_url'),
                'horodatage'=> $this->details['horodatage'] ?? now()->format('d/m/Y à H:i:s'),
            ],
        );
    }
}
