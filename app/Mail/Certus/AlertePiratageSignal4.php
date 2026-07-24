<?php

namespace App\Mail\Certus;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email d'alerte — Signal 4 : Clé de licence partagée détectée.
 *
 * Envoyé à l'administrateur quand un anti-rejeu déjà blacklisté
 * est présenté par une machine inconnue — signe que la clé a été
 * redistribuée à un tiers.
 * L'activation est refusée, le fingerprint ET l'anti-rejeu sont blacklistés.
 *
 * Contexte attendu : licence_id, org_nom, fingerprint_prefix, anti_rejeu, horodatage.
 */
class AlertePiratageSignal4 extends AlertePiratageBase
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[CERTUS ALERTE] Signal 4 — Partage de clé de licence détecté',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.certus.signal4',
            with: [
                'couleur'           => '#8e44ad',
                'portalUrl'         => config('certus.portal_url'),
                'horodatage'        => $this->contexte['horodatage'] ?? now()->format('d/m/Y à H:i:s'),
                'descriptionSignal' => 'Un anti-rejeu déjà enregistré en liste noire a été présenté par une empreinte machine inconnue. Cela indique que la clé de licence a probablement été partagée à un tiers non autorisé. L\'activation est refusée, l\'empreinte et l\'anti-rejeu ont été blacklistés.',
            ],
        );
    }
}
