@extends('emails.certus.layout')

@section('titre')
    &#9888; Signal 1 — Quota de postes dépassé
@endsection

@section('contenu')
    <p>Une tentative d'activation a été <strong>refusée</strong> car le quota de postes de la licence est atteint.</p>

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
        @isset($contexte['nb_postes'])
        <tr>
            <th>Quota postes</th>
            <td>{{ $contexte['nb_postes'] }} poste(s) autorisé(s)</td>
        </tr>
        @endisset
        @isset($contexte['fingerprint'])
        <tr>
            <th>Empreinte machine</th>
            <td><code>{{ substr($contexte['fingerprint'], 0, 16) }}…</code> <span class="badge">Blacklistée</span></td>
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
        Vérifiez si cette licence nécessite une extension de quota ou si des activations orphelines doivent être révoquées.
    </p>
@endsection
