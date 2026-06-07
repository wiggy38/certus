<?php

namespace App\Http\Middleware;

use App\Constants\ErrorCodes;
use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint l'accès aux organisations ayant le rôle ADMIN.
 *
 * DOIT être chaîné après ApiKeyMiddleware (qui injecte l'organisation).
 *
 * Usage dans les routes :
 *   Route::middleware(['api.key', 'api.key.admin'])->group(function () { ... });
 */
class ApiKeyAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Organisation|null $organisation */
        $organisation = $request->attributes->get('organisation');

        if (! $organisation) {
            return $this->erreur(
                ErrorCodes::API_KEY_MANQUANTE,
                'Authentification requise. Chaînez api.key avant api.key.admin.',
                401,
            );
        }

        if (! $organisation->estAdmin()) {
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
