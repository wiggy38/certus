<?php

namespace App\Http\Middleware;

use App\Constants\ErrorCodes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie la présence et la validité du header X-API-Key.
 *
 * ÉVOLUTION SCHEMA : la table `organisations` ne stocke plus les clés API.
 * Les clés API seront portées par une table dédiée `api_keys` (à créer) :
 *
 *   api_keys
 *     id           BIGINT PK
 *     org_id       VARCHAR(10) FK → organisations.org_id
 *     cle_hachee   VARCHAR(64)  -- SHA-256 de la clé en clair
 *     role         ENUM('CLIENT','ADMIN')
 *     expire_le    DATETIME NULL
 *     active       TINYINT(1) DEFAULT 1
 *
 * En attendant, ce middleware est en attente d'implémentation.
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

        // TODO: implémenter la vérification via la table `api_keys`
        //
        // $apiKeyRecord = \App\Models\ApiKey::query()
        //     ->where('cle_hachee', hash('sha256', $apiKey))
        //     ->where('active', 1)
        //     ->where(fn ($q) => $q->whereNull('expire_le')->orWhere('expire_le', '>', now()))
        //     ->with('organisation')
        //     ->first();
        //
        // if (! $apiKeyRecord) {
        //     return $this->erreur(ErrorCodes::API_KEY_INVALIDE, 'Clé API invalide.', 401);
        // }
        //
        // $request->attributes->set('organisation', $apiKeyRecord->organisation);
        // $request->attributes->set('api_key_role', $apiKeyRecord->role);

        return $this->erreur(
            ErrorCodes::API_KEY_INVALIDE,
            'Système d\'authentification par clé API non encore configuré. Table api_keys manquante.',
            501,
        );
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
