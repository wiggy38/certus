<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ErrorCodes;
use App\Http\Resources\OrganisationResource;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Gestion des organisations clientes.
 *
 * Routes (toutes protégées par api.key.admin sauf show) :
 *   GET    /api/v1/organisations           → index()
 *   POST   /api/v1/organisations           → store()
 *   GET    /api/v1/organisations/{id}      → show()
 *   PUT    /api/v1/organisations/{id}      → update()
 *   DELETE /api/v1/organisations/{id}      → destroy()
 */
class OrganisationController extends BaseApiController
{
    /** Liste paginée de toutes les organisations. */
    public function index(Request $request): JsonResponse
    {
        $paginator = Organisation::withCount('licences')
            ->paginate($request->integer('par_page', 20));

        return $this->liste(
            OrganisationResource::collection($paginator),
            $this->metaPagination($paginator),
        );
    }

    /** Crée une nouvelle organisation et génère sa clé API. */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom'          => ['required', 'string', 'max:191'],
            'email'        => ['required', 'email', 'unique:organisations,email'],
            'telephone'    => ['nullable', 'string', 'max:50'],
            'adresse'      => ['nullable', 'string', 'max:255'],
            'pays'         => ['nullable', 'string', 'size:2'],
            'api_key_role' => ['in:CLIENT,ADMIN'],
            'notes'        => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->erreur(
                ErrorCodes::VALIDATION_ECHOUEE,
                'Données invalides.',
                ['erreurs' => $validator->errors()->toArray()],
                422,
            );
        }

        $organisation = Organisation::create(array_merge(
            $validator->validated(),
            [
                'statut'       => 'actif',
                'api_key'      => Str::random(64),
                'api_key_role' => $request->input('api_key_role', 'CLIENT'),
            ],
        ));

        // Retourne la clé API une seule fois à la création (jamais renvoyée ensuite)
        $data = (new OrganisationResource($organisation))->toArray($request);
        $data['api_key'] = $organisation->api_key;

        return $this->cree($data, ['message' => 'Conservez cette clé API : elle ne sera plus affichée.']);
    }

    /** Détail d'une organisation. */
    public function show(Request $request, int $id): JsonResponse
    {
        $organisation = Organisation::withCount('licences')->findOrFail($id);
        return $this->succes(new OrganisationResource($organisation));
    }

    /** Met à jour les informations d'une organisation. */
    public function update(Request $request, int $id): JsonResponse
    {
        $organisation = Organisation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nom'       => ['sometimes', 'string', 'max:191'],
            'email'     => ['sometimes', 'email', 'unique:organisations,email,' . $id],
            'telephone' => ['nullable', 'string', 'max:50'],
            'adresse'   => ['nullable', 'string', 'max:255'],
            'pays'      => ['nullable', 'string', 'size:2'],
            'statut'    => ['in:actif,suspendu,expire'],
            'notes'     => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->erreur(
                ErrorCodes::VALIDATION_ECHOUEE,
                'Données invalides.',
                ['erreurs' => $validator->errors()->toArray()],
            );
        }

        $organisation->update($validator->validated());

        return $this->succes(new OrganisationResource($organisation));
    }

    /** Supprime (soft delete) une organisation. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $organisation = Organisation::findOrFail($id);
        $organisation->delete();

        return $this->succes(null, ['message' => "Organisation #{$id} supprimée."]);
    }
}
