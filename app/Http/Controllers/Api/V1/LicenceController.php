<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ErrorCodes;
use App\Http\Resources\LicenceResource;
use App\Models\Licence;
use App\Models\Organisation;
use App\Services\CleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Émission et gestion du cycle de vie des licences Experto.
 *
 * Routes :
 *   GET    /api/v1/licences                    → index()      [api.key]
 *   POST   /api/v1/licences                    → store()      [api.key.admin]
 *   GET    /api/v1/licences/{id}               → show()       [api.key]
 *   POST   /api/v1/licences/{id}/suspendre     → suspendre()  [api.key.admin]
 *   POST   /api/v1/licences/{id}/revoquer      → revoquer()   [api.key.admin]
 */
class LicenceController extends BaseApiController
{
    public function __construct(private readonly CleService $cleService) {}

    /**
     * Liste les licences de l'organisation appelante (CLIENT)
     * ou toutes les licences (ADMIN).
     */
    public function index(Request $request): JsonResponse
    {
        $organisation = $this->organisationCourante($request);

        $query = $organisation->estAdmin()
            ? Licence::with('organisation')
            : Licence::where('organisation_id', $organisation->id);

        $paginator = $query->paginate($request->integer('par_page', 20));

        return $this->liste(
            LicenceResource::collection($paginator),
            $this->metaPagination($paginator),
        );
    }

    /** Émet une nouvelle licence pour une organisation. */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organisation_id' => ['required', 'integer', 'exists:organisations,id'],
            'produit'         => ['required', 'string', 'max:100'],
            'version'         => ['required', 'string', 'max:20'],
            'type'            => ['required', 'in:standard,premium,entreprise'],
            'date_debut'      => ['required', 'date'],
            'date_expiration' => ['required', 'date', 'after:date_debut'],
            'max_activations' => ['required', 'integer', 'min:1'],
            'metadata'        => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return $this->erreur(
                ErrorCodes::VALIDATION_ECHOUEE,
                'Données invalides.',
                ['erreurs' => $validator->errors()->toArray()],
            );
        }

        $donnees = $validator->validated();

        // Génération de la clé signée
        $cle = $this->cleService->generer([
            'organisation_id' => $donnees['organisation_id'],
            'produit'         => $donnees['produit'],
            'version'         => $donnees['version'],
            'type'            => $donnees['type'],
            'date_expiration' => $donnees['date_expiration'],
            'max_activations' => $donnees['max_activations'],
        ]);

        $licence = Licence::create(array_merge($donnees, [
            'cle'                => $cle,
            'statut'             => 'active',
            'activations_count'  => 0,
        ]));

        // Retourne la clé en clair une seule fois à l'émission
        $data = (new LicenceResource($licence))->toArray($request);
        $data['cle'] = $cle;

        return $this->cree($data, ['message' => 'Licence émise. La clé ne sera plus retournée après cette réponse.']);
    }

    /** Détail d'une licence. */
    public function show(Request $request, int $id): JsonResponse
    {
        $licence = Licence::with('organisation')->findOrFail($id);
        return $this->succes(new LicenceResource($licence));
    }

    /** Suspend une licence (désactive sans révoquer). */
    public function suspendre(Request $request, int $id): JsonResponse
    {
        $licence = Licence::findOrFail($id);

        if (! in_array($licence->statut, ['active'])) {
            return $this->erreur(
                ErrorCodes::LICENCE_INVALIDE,
                "Impossible de suspendre une licence avec le statut «{$licence->statut}».",
            );
        }

        $licence->update(['statut' => 'suspendue']);

        return $this->succes(
            new LicenceResource($licence),
            ['message' => "Licence #{$id} suspendue."],
        );
    }

    /** Révoque définitivement une licence. */
    public function revoquer(Request $request, int $id): JsonResponse
    {
        $licence = Licence::findOrFail($id);

        if ($licence->statut === 'revoquee') {
            return $this->erreur(
                ErrorCodes::LICENCE_REVOQUEE,
                "La licence #{$id} est déjà révoquée.",
            );
        }

        $licence->update(['statut' => 'revoquee']);

        // TODO: révoquer toutes les activations actives liées
        // TODO: notifier l'organisation via NotificationService

        return $this->succes(
            new LicenceResource($licence),
            ['message' => "Licence #{$id} révoquée définitivement."],
        );
    }
}
