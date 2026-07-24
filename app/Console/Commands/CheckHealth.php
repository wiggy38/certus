<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckHealth extends Command
{
    protected $signature   = 'certus:check-health';
    protected $description = 'Vérifie l\'état du système Certus (DB, tables, JWT)';

    private const TABLES_REQUISES = [
        'organisations',
        'licences',
        'activations',
        'activation_historique',
        'audit_log',
        'blacklist_fingerprints',
        'blacklist_antirejeu',
        'api_keys',
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Certus — Health Check</> ' . now()->toIso8601String());
        $this->newLine();

        $checks = [];
        $echecGlobal = false;

        // 1. Connexion DB
        $dbConnecte = false;
        try {
            DB::connection()->getPdo();
            $version    = DB::selectOne('SELECT VERSION() AS v')->v;
            $dbConnecte = true;
            $checks[]   = $this->ligne('OK', 'Connexion DB', $version);
        } catch (\Throwable $e) {
            $checks[]    = $this->ligne('FAIL', 'Connexion DB', $e->getMessage());
            $echecGlobal = true;
        }

        // 2. Tables présentes (ignorées si la DB est inaccessible)
        foreach (self::TABLES_REQUISES as $table) {
            if (! $dbConnecte) {
                $checks[] = $this->ligne('SKIP', "Table : {$table}", 'DB inaccessible');
                continue;
            }
            try {
                $existe = DB::getSchemaBuilder()->hasTable($table);
            } catch (\Throwable) {
                $existe = false;
            }
            if (! $existe) {
                $echecGlobal = true;
            }
            $checks[] = $this->ligne(
                $existe ? 'OK' : 'FAIL',
                "Table : {$table}",
                $existe ? 'présente' : 'MANQUANTE',
            );
        }

        // 3. JWT_SECRET configuré
        $jwtSecret  = (string) config('jwt.secret', '');
        $jwtOk      = strlen($jwtSecret) >= 32;
        $echecGlobal = $echecGlobal || ! $jwtOk;
        $checks[]   = $this->ligne(
            $jwtOk ? 'OK' : 'FAIL',
            'JWT_SECRET',
            $jwtOk ? strlen($jwtSecret) . ' caractères' : 'NON CONFIGURÉ ou trop court',
        );

        // 4. APP_KEY configuré
        $appKey  = (string) config('app.key', '');
        $keyOk   = strlen($appKey) >= 16;
        $echecGlobal = $echecGlobal || ! $keyOk;
        $checks[] = $this->ligne(
            $keyOk ? 'OK' : 'FAIL',
            'APP_KEY',
            $keyOk ? 'configurée' : 'MANQUANTE',
        );

        // 5. Clés API présentes (au moins une)
        try {
            $nbCles  = DB::table('api_keys')->where('active', true)->count();
            $clesOk  = $nbCles > 0;
            $echecGlobal = $echecGlobal || ! $clesOk;
            $checks[] = $this->ligne(
                $clesOk ? 'OK' : 'WARN',
                'Clés API actives',
                $clesOk ? "{$nbCles} clé(s)" : 'AUCUNE clé active — seeder requis',
            );
        } catch (\Throwable) {
            $checks[] = $this->ligne('SKIP', 'Clés API actives', 'table api_keys inaccessible');
        }

        // 6. Licences actives
        try {
            $nbLicences = DB::table('licences')->where('statut', 'ACTIVE')->count();
            $checks[]   = $this->ligne('INFO', 'Licences ACTIVE', "{$nbLicences} licence(s)");
        } catch (\Throwable) {
            $checks[]   = $this->ligne('SKIP', 'Licences ACTIVE', 'table inaccessible');
        }

        // -- Affichage tableau -----------------------------------
        $this->table(
            ['Statut', 'Contrôle', 'Détail'],
            array_map(fn ($c) => [$c['statut'], $c['label'], $c['detail']], $checks),
        );

        $this->newLine();

        if ($echecGlobal) {
            $this->line('  <fg=red;options=bold>RÉSULTAT : ANOMALIE(S) DÉTECTÉE(S)</> — corriger avant le démarrage.');
            return self::FAILURE;
        }

        $this->line('  <fg=green;options=bold>RÉSULTAT : SYSTÈME OPÉRATIONNEL</>');
        return self::SUCCESS;
    }

    private function ligne(string $statut, string $label, string $detail): array
    {
        $badge = match ($statut) {
            'OK'   => '<fg=green>  OK  </>',
            'FAIL' => '<fg=red> FAIL </>',
            'WARN' => '<fg=yellow> WARN </>',
            'INFO' => '<fg=cyan> INFO </>',
            default => ' SKIP ',
        };

        return ['statut' => $badge, 'label' => $label, 'detail' => $detail];
    }
}
