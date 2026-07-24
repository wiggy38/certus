<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Initialise deux clés API pour Certus :
 *   - 1 clé ADMIN (gestion complète)
 *   - 1 clé CLIENT (activation machine uniquement)
 *
 * Les valeurs brutes sont affichées UNE SEULE FOIS ici.
 * Seul le hash SHA-256 est persisté en base — jamais la valeur brute.
 */
class ApiKeySeeder extends Seeder
{
    public function run(): void
    {
        $keys = [
            [
                'niveau' => 'ADMIN',
                'nom'    => 'Clé Admin — ExpertoSoft',
                'brut'   => 'ck_admin_' . Str::random(32),
            ],
            [
                'niveau' => 'CLIENT',
                'nom'    => 'Clé Client — Experto (démo)',
                'brut'   => 'ck_client_' . Str::random(32),
            ],
        ];

        $this->command?->newLine();
        $this->command?->line('┌─────────────────────────────────────────────────────────────────┐');
        $this->command?->line('│           CERTUS — Clés API générées (valeurs brutes)           │');
        $this->command?->line('│      Conservez ces valeurs : elles ne seront plus affichées.    │');
        $this->command?->line('├─────────────────────────────────────────────────────────────────┤');

        foreach ($keys as $entry) {
            ApiKey::create([
                'cle_hash' => hash('sha256', $entry['brut']),
                'niveau'   => $entry['niveau'],
                'nom'      => $entry['nom'],
                'active'   => true,
                'cree_le'  => now(),
            ]);

            $this->command?->line(sprintf(
                '│  %-8s │ %s',
                $entry['niveau'],
                $entry['brut'],
            ));
        }

        $this->command?->line('└─────────────────────────────────────────────────────────────────┘');
        $this->command?->newLine();
        $this->command?->warn('  Header HTTP : X-API-Key: <valeur ci-dessus>');
        $this->command?->newLine();
    }
}
