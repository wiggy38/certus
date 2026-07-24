<?php

namespace App\Http\Middleware;

use App\Constants\ErrorCodes;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie la présence et la validité du header X-API-Key.
 *
 * En cas de succès, deux attributs sont injectés dans la requête :
 *   $request->attributes->get('apiKey')       → instance ApiKey (model)
 *   $request->attributes->get('apiKeyNiveau') → 'ADMIN' | 'CLIENT'
 *
 * Ce middleware doit être appliqué AVANT api.key.admin.
 */
class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $cleEnClair = $request->header('X-API-Key');

        if (empty($cleEnClair)) {
            return $this->erreur(
                ErrorCodes::API_KEY_MANQUANTE,
                "L'en-tête X-API-Key est requis.",
                401,
            );
        }

        $apiKey = ApiKey::verifier($cleEnClair);

        if ($apiKey === null) {
            return $this->erreur(
                ErrorCodes::API_KEY_INVALIDE,
                'Clé API invalide ou désactivée.',
                401,
            );
        }

        $request->attributes->set('apiKey', $apiKey);
        $request->attributes->set('apiKeyNiveau', $apiKey->niveau);

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
