<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Nécessaire pour MariaDB avec utf8mb4 et clés primaires sur varchar(255)
        Schema::defaultStringLength(191);

        $this->configurerRateLimiting();
    }

    private function configurerRateLimiting(): void
    {
        // 100 req/min par clé API — identifiant = hash de la clé brute
        RateLimiter::for('api_certus', function (Request $request) {
            $cle = $request->header('X-API-Key', $request->ip());

            return Limit::perMinute(100)
                ->by(hash('sha256', $cle))
                ->response(function () {
                    return response()->json([
                        'statut' => 'ERREUR',
                        'data'   => null,
                        'meta'   => [
                            'code_erreur' => 'TROP_DE_REQUETES',
                            'message'     => 'Limite de 100 requêtes par minute atteinte.',
                        ],
                    ], 429);
                });
        });
    }
}
