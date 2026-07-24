<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $sujet ?? 'Notification Certus' }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333333; }
        .wrapper { width: 100%; padding: 24px 0; background-color: #f4f4f4; }
        .container { max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background-color: {{ $couleur ?? '#2c3e50' }}; padding: 24px 32px; }
        .header h1 { margin: 0; color: #ffffff; font-size: 20px; font-weight: bold; letter-spacing: 0.5px; }
        .header p { margin: 6px 0 0; color: rgba(255,255,255,0.80); font-size: 12px; }
        .body { padding: 28px 32px; }
        .body p { line-height: 1.6; margin: 0 0 12px; }
        .alert-box { border-left: 4px solid {{ $couleur ?? '#2c3e50' }}; background: #fafafa; padding: 14px 18px; margin: 18px 0; border-radius: 0 4px 4px 0; }
        .alert-box p { margin: 0; font-size: 13px; color: #555; line-height: 1.55; }
        table.details { width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 13px; }
        table.details th { text-align: left; padding: 8px 12px; background: #f0f0f0; color: #555; font-weight: normal; width: 40%; border-bottom: 1px solid #e0e0e0; }
        table.details td { padding: 8px 12px; color: #222; border-bottom: 1px solid #e8e8e8; }
        .btn { display: inline-block; margin-top: 18px; padding: 11px 24px; background-color: {{ $couleur ?? '#2c3e50' }}; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: bold; }
        .footer { padding: 18px 32px; background: #f9f9f9; border-top: 1px solid #eeeeee; font-size: 11px; color: #aaa; text-align: center; line-height: 1.5; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; color: #fff; background-color: {{ $couleur ?? '#2c3e50' }}; margin-left: 6px; vertical-align: middle; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">
        <div class="header">
            <h1>@yield('titre')</h1>
            <p>Certus — Système de gestion des licences Experto &middot; {{ $horodatage }}</p>
        </div>
        <div class="body">
            @yield('contenu')

            @isset($portalUrl)
            <a class="btn" href="{{ $portalUrl }}">Ouvrir le portail administrateur</a>
            @endisset
        </div>
        <div class="footer">
            Ce message est généré automatiquement par <strong>Certus</strong> — ExpertoSoft.<br>
            Ne pas répondre à cet email. Pour toute question, connectez-vous au portail administrateur.
        </div>
    </div>
</div>
</body>
</html>
