@extends('emails.certus.layout')

@section('titre')
    &#10003; Votre clé de licence Experto
@endsection

@section('contenu')
    <p>Bonjour,</p>

    <p>
        Votre clé de licence <strong>Experto</strong> a été générée avec succès.
        Conservez-la précieusement — elle est strictement personnelle et liée à votre organisation.
    </p>

    <div class="alert-box" style="border-color: {{ $couleur }}; background: #f0fdf4;">
        <p style="font-family: monospace; font-size: 18px; letter-spacing: 2px; color: #1a1a1a; font-weight: bold; text-align: center;">
            {{ $cle }}
        </p>
    </div>

    <table class="details">
        <tr>
            <th>Identifiant licence</th>
            <td><strong>{{ $details['licence_id'] ?? '—' }}</strong></td>
        </tr>
        <tr>
            <th>Organisation</th>
            <td>{{ $details['org_nom'] ?? '—' }}</td>
        </tr>
        @isset($details['type_libelle'])
        <tr>
            <th>Type de licence</th>
            <td>{{ $details['type_libelle'] }}</td>
        </tr>
        @endisset
        @isset($details['nb_postes'])
        <tr>
            <th>Postes autorisés</th>
            <td>{{ $details['nb_postes'] }}</td>
        </tr>
        @endisset
        @isset($details['nb_sites'])
        <tr>
            <th>Sites autorisés</th>
            <td>{{ $details['nb_sites'] }}</td>
        </tr>
        @endisset
        @isset($details['nb_projets'])
        <tr>
            <th>Projets autorisés</th>
            <td>{{ $details['nb_projets'] }}</td>
        </tr>
        @endisset
        @isset($details['date_expiration'])
        <tr>
            <th>Date d'expiration</th>
            <td>{{ $details['date_expiration'] }}</td>
        </tr>
        @endisset
        <tr>
            <th>Date de génération</th>
            <td>{{ $horodatage }}</td>
        </tr>
    </table>

    <p style="font-size:13px; color:#666;">
        Pour activer votre logiciel, saisissez cette clé dans le champ prévu lors du premier lancement d'Experto.<br>
        En cas de problème d'activation, contactez votre administrateur ou connectez-vous au portail.
    </p>
@endsection
