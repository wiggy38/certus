<?php

namespace App\Http\Middleware;

use App\Constants\ErrorCodes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint l'accès aux clés API de niveau ADMIN.
 *
 * Doit être chaîné APRÈS ApiKeyMiddleware, qui injecte 'apiKeyNiveau'
 * dans $request->attributes. Retourne 403 si le niveau est CLIENT.
 *
 * Usage :
 *   Route::middleware(['api.key', 'api.key.admin'])->group(...)
 */
class ApiKeyAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $niveau = $request->attributes->get('apiKeyNiveau');

        if ($niveau === null) {
            return $this->erreur(
                ErrorCodes::API_KEY_MANQUANTE,
                'Authentification requise. Chaînez api.key avant api.key.admin.',
                401,
            );
        }

        if ($niveau !== 'ADMIN') {
            return $this->erreur(
                ErrorCodes::API_KEY_INSUFFISANTE,
                'Accès refusé : droits ADMIN requis pour cette ressource.',
                403,
            );
        }

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
