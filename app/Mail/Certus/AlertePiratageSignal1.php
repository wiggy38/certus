<?php

namespace App\Mail\Certus;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email d'alerte — Signal 1 : Quota de postes dépassé.
 *
 * Envoyé à l'administrateur quand le nombre d'activations actives
 * atteint ou dépasse le quota de postes de la licence.
 * Le fingerprint incriminé est ajouté en blacklist (signal=1).
 *
 * Contexte attendu : licence_id, org_nom, nb_postes, horodatage.
 */
class AlertePiratageSignal1 extends AlertePiratageBase
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[CERTUS ALERTE] Signal 1 — Quota de postes dépassé',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.certus.signal1',
            with: [
                'couleur'           => '#e67e22',
                'portalUrl'         => config('certus.portal_url'),
                'horodatage'        => $this->contexte['horodatage'] ?? now()->format('d/m/Y à H:i:s'),
                'descriptionSignal' => 'Le nombre d\'activations actives a atteint ou dépassé le quota de postes autorisés pour cette licence. L\'activation a été refusée et l\'empreinte machine a été blacklistée.',
            ],
        );
    }
}
