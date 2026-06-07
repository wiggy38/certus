<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller de base pour toutes les routes API v1 de Certus.
 *
 * Convention de réponse imposée :
 *   { "statut": "OK"|"ERREUR", "data": {}, "meta": {} }
 *
 * Tous les controllers Api/V1 étendent cette classe.
 */
abstract class BaseApiController extends Controller
{
    // ----------------------------------------------------------------
    // Helpers de réponse
    // ----------------------------------------------------------------

    /** Réponse succès avec une ressource ou un tableau. */
    protected function succes(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'statut' => 'OK',
            'data'   => $data,
            'meta'   => $meta,
        ], $status);
    }

    /** Réponse succès pour une collection paginée. */
    protected function liste(mixed $collection, array $meta = []): JsonResponse
    {
        return response()->json([
            'statut' => 'OK',
            'data'   => $collection,
            'meta'   => $meta,
        ]);
    }

    /** Réponse d'erreur métier structurée. */
    protected function erreur(
        string $codeErreur,
        string $message,
        array $metaSup = [],
        int $status = 422,
    ): JsonResponse {
        return response()->json([
            'statut' => 'ERREUR',
            'data'   => null,
            'meta'   => array_merge([
                'code_erreur' => $codeErreur,
                'message'     => $message,
            ], $metaSup),
        ], $status);
    }

    /** Réponse 201 Created avec la ressource créée. */
    protected function cree(mixed $data, array $meta = []): JsonResponse
    {
        return $this->succes($data, $meta, 201);
    }

    // ----------------------------------------------------------------
    // Helpers de contexte
    // ----------------------------------------------------------------

    /**
     * Retourne l'organisation authentifiée injectée par ApiKeyMiddleware.
     * Lever une exception si appelé sur une route non protégée.
     */
    protected function organisationCourante(Request $request): Organisation
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        if (! $organisation instanceof Organisation) {
            throw new \LogicException('organisationCourante() appelé sans le middleware api.key.');
        }

        return $organisation;
    }

    /** Construit les métadonnées de pagination à partir d'un LengthAwarePaginator. */
    protected function metaPagination(\Illuminate\Pagination\LengthAwarePaginator $paginator): array
    {
        return [
            'total'        => $paginator->total(),
            'par_page'     => $paginator->perPage(),
            'page_courante' => $paginator->currentPage(),
            'derniere_page' => $paginator->lastPage(),
        ];
    }
}
