@extends('emails.certus.layout')

@section('titre')
    &#9888; Signal 2 — Rafale d'activations détectée
@endsection

@section('contenu')
    <p>Une activité suspecte a été détectée sur une licence : un nombre anormal de tentatives d'activation a été enregistré en moins d'une heure.</p>

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
        @isset($contexte['nb_tentatives'])
        <tr>
            <th>Tentatives / heure</th>
            <td><strong>{{ $contexte['nb_tentatives'] }}</strong> (seuil : {{ $contexte['seuil'] ?? 5 }})</td>
        </tr>
        @endisset
        @isset($contexte['tentatives_suspectes_total'])
        <tr>
            <th>Total cumulé</th>
            <td>{{ $contexte['tentatives_suspectes_total'] }} tentative(s) suspecte(s)</td>
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
        L'activation a été autorisée cette fois. Si ce comportement persiste, envisagez de suspendre ou révoquer la licence.
    </p>
@endsection
