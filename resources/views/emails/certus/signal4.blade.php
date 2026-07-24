@extends('emails.certus.layout')

@section('titre')
    &#128274; Signal 4 — Partage de clé de licence détecté
@endsection

@section('contenu')
    <p>Une tentative d'activation a été <strong>refusée</strong>. Le système a confirmé que la clé de licence a vraisemblablement été partagée à un tiers non autorisé.</p>

    <div class="alert-box">
        <p>{{ $descriptionSignal }}</p>
    </div>

    <table class="details">
        <tr>
            <th>Identifiant licence</th>
            <td><strong>{{ $contexte['licence_id'] ?? '—' }}</strong></td>
        </tr>
        <tr>
            <th>Organisation</th>
            <td>{{ $contexte['org_nom'] ?? '—' }}</td>
        </tr>
        @isset($contexte['fingerprint'])
        <tr>
            <th>Empreinte machine</th>
            <td><code>{{ substr($contexte['fingerprint'], 0, 16) }}…</code> <span class="badge">Blacklistée</span></td>
        </tr>
        @endisset
        @isset($contexte['anti_rejeu'])
        <tr>
            <th>Anti-rejeu</th>
            <td><code>{{ $contexte['anti_rejeu'] }}</code> <span class="badge">Blacklisté</span></td>
        </tr>
        @endisset
        @isset($contexte['ip_source'])
        <tr>
            <th>IP source</th>
            <td>{{ $contexte['ip_source'] }}</td>
        </tr>
        @endisset
        <tr>
            <th>Date / Heure</th>
            <td>{{ $horodatage }}</td>
        </tr>
    </table>

    <p style="font-size:13px; color:#666;">
        <strong>Action recommandée :</strong> révoquez immédiatement la licence compromise et générez une nouvelle clé pour le client légitime. Envisagez une investigation sur la source de la fuite.
    </p>
@endsection
