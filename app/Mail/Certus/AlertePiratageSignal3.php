<?php

namespace App\Mail\Certus;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email d'alerte — Signal 3 : Fingerprint actif sur plusieurs licences.
 *
 * Envoyé à l'administrateur quand une même empreinte machine tente
 * d'activer plusieurs licences différentes simultanément.
 * L'activation est refusée et le fingerprint est blacklisté (signal=3).
 *
 * Contexte attendu : licence_id, org_nom, fingerprint_prefix, horodatage.
 */
class AlertePiratageSignal3 extends AlertePiratageBase
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[CERTUS ALERTE] Signal 3 — Empreinte machine sur plusieurs licences',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.certus.signal3',
            with: [
                'couleur'           => '#c0392b',
                'portalUrl'         => config('certus.portal_url'),
                'horodatage'        => $this->contexte['horodatage'] ?? now()->format('d/m/Y à H:i:s'),
                'descriptionSignal' => 'La même empreinte machine (fingerprint) a été détectée comme active sur au moins deux licences différentes simultanément. L\'activation a été refusée et l\'empreinte a été blacklistée.',
            ],
        );
    }
}
