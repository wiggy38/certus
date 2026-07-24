<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ErrorCodes;
use App\Models\AuditLog;
use App\Models\BlacklistFingerprint;
use App\Models\Licence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Gestion de la liste noire des fingerprints machines — Parcours 5.
 * Toutes les routes nécessitent le rôle ADMIN.
 *
 * Routes :
 *   POST  /api/v1/blacklist/fingerprints  → store()
 *   GET   /api/v1/blacklist/fingerprints  → index()
 */
class BlacklistController extends BaseApiController
{
    /**
     * Ajoute manuellement un fingerprint en liste noire (action admin).
     *
     * Insère une entrée dans blacklist_fingerprints avec bloque_par = nom de la clé API,
     * puis trace l'action dans audit_log.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fingerprint' => ['required', 'string', 'size:64', 'regex:/^[0-9a-fA-F]{64}$/'],
            'licence_id'  => ['required', 'string', 'exists:licences,licence_id'],
            'signal'      => ['required', 'integer', 'in:1,2,3,4'],
            'motif'       => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->erreur(
                ErrorCodes::VALIDATION_ECHOUEE,
                'Données invalides.',
                ['erreurs' => $validator->errors()->toArray()],
                422,
            );
        }

        $donnees  = $validator->validated();
        $acteur   = $this->acteurCourant($request);

        $entree = BlacklistFingerprint::enregistrer(
            fingerprint: strtolower($donnees['fingerprint']),
            licenceId:   $donnees['licence_id'],
            signal:      (int) $donnees['signal'],
            bloquePar:   $acteur,
            motif:       $donnees['motif'] ?? null,
        );

        AuditLog::enregistrer(
            licenceId: $donnees['licence_id'],
            action:    AuditLog::ACTION_BLOCAGE_FINGERPRINT_MANUEL,
            acteur:    $acteur,
            ipSource:  $request->ip(),
            detail:    [
                'fingerprint_prefix' => substr($donnees['fingerprint'], 0, 8) . '…',
                'signal'             => $donnees['signal'],
                'motif'              => $donnees['motif'] ?? null,
            ],
        );

        return $this->cree(['blacklist_id' => $entree->blacklist_id]);
    }

    /**
     * Liste paginée des fingerprints en liste noire.
     *
     * Filtres (query params) :
     *   licence_id — restreint à une licence
     *   signal     — filtre par type de signal (1, 3 ou 4)
     *   depuis     — entrées à partir de cette date/heure (ISO 8601 ou YYYY-MM-DD)
     *   page       — page courante (défaut : 1)
     *   limite     — entrées par page (défaut : 50, max : 200)
     */
    public function index(Request $request): JsonResponse
    {
        $query = BlacklistFingerprint::query()->orderByDesc('horodatage');

        if ($request->filled('licence_id')) {
            $query->where('licence_id', $request->query('licence_id'));
        }

        if ($request->filled('signal')) {
            $query->where('signal', (int) $request->query('signal'));
        }

        if ($request->filled('depuis')) {
            $query->where('horodatage', '>=', $request->query('depuis'));
        }

        $limite = min((int) $request->query('limite', 50), 200);
        $page   = max((int) $request->query('page', 1), 1);

        $paginator = $query->paginate($limite, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(fn (BlacklistFingerprint $bf) => [
            'blacklist_id' => $bf->blacklist_id,
            'fingerprint'  => $bf->fingerprint,
            'licence_id'   => $bf->licence_id,
            'signal'       => $bf->signal,
            'signal_libelle' => BlacklistFingerprint::libelleSignal($bf->signal),
            'motif'        => $bf->motif,
            'bloque_par'   => $bf->bloque_par,
            'horodatage'   => $bf->horodatage?->toIso8601String(),
        ])->values()->all();

        return $this->succes($items, [
            'total'  => $paginator->total(),
            'page'   => $paginator->currentPage(),
            'limite' => $paginator->perPage(),
        ]);
    }
}
