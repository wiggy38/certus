<?php

namespace App\Mail\Certus;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email d'alerte — Signal 2 : Rafale d'activations suspecte.
 *
 * Envoyé à l'administrateur quand plus de 5 tentatives d'activation
 * sont détectées en moins d'une heure sur la même licence.
 * L'activation n'est PAS bloquée, mais tentatives_suspectes est incrémenté.
 *
 * Contexte attendu : licence_id, org_nom, seuil, horodatage.
 */
class AlertePiratageSignal2 extends AlertePiratageBase
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[CERTUS ALERTE] Signal 2 — Rafale d\'activations détectée',
        );
    }

    public function content(): Content
    {
        $seuil = $this->contexte['seuil'] ?? 5;

        return new Content(
            view: 'emails.certus.signal2',
            with: [
                'couleur'           => '#f39c12',
                'portalUrl'         => config('certus.portal_url'),
                'horodatage'        => $this->contexte['horodatage'] ?? now()->format('d/m/Y à H:i:s'),
                'descriptionSignal' => "Plus de {$seuil} tentatives d'activation ont été enregistrées en moins d'une heure. L'activation a été autorisée mais le compteur de tentatives suspectes a été incrémenté. Surveillez cette licence.",
            ],
        );
    }
}
