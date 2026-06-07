<?php

namespace App\Http\Middleware;

use App\Constants\ErrorCodes;
use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie la présence et la validité du header X-API-Key.
 * Injecte l'organisation authentifiée dans les attributs de la requête.
 *
 * Usage dans les routes :
 *   Route::middleware('api.key')->group(function () { ... });
 */
class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (empty($apiKey)) {
            return $this->erreur(
                ErrorCodes::API_KEY_MANQUANTE,
                "L'en-tête X-API-Key est requis.",
                401,
            );
        }

        $organisation = Organisation::query()
            ->where('api_key', $apiKey)
            ->where('statut', 'actif')
            ->first();

        if (! $organisation) {
            return $this->erreur(
                ErrorCodes::API_KEY_INVALIDE,
                'Clé API invalide ou organisation inactive.',
                401,
            );
        }

        // Rend l'organisation accessible dans tous les controllers via
        // $request->attributes->get('organisation')
        $request->attributes->set('organisation', $organisation);

        return $next($request);
    }

    private function erreur(string $code, string $message, int $status): Response
    {
        return response()->json([
            'statut' => 'ERREUR',
            'data'   => null,
            'meta'   => [
                'code_erreur' => $code,
                'message'     => $message,
            ],
        ], $status);
    }
}
