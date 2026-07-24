@extends('emails.certus.layout')

@section('titre')
    &#128683; Signal 3 — Empreinte machine multi-licences
@endsection

@section('contenu')
    <p>Une tentative d'activation a été <strong>refusée</strong>. La même empreinte machine est active sur plusieurs licences distinctes.</p>

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
        @isset($contexte['nb_licences_actives'])
        <tr>
            <th>Licences simultanées</th>
            <td>{{ $contexte['nb_licences_actives'] }} licences actives avec ce fingerprint</td>
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
        Ce comportement indique une possible réutilisation d'image système ou de machine virtuelle clonée. Vérifiez l'ensemble des licences associées à cet appareil.
    </p>
@endsection
