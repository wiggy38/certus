<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Activation;
use App\Models\AuditLog;
use App\Models\Licence;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tableau de bord, audit et signaux de piratage pour les administrateurs.
 * Toutes les routes nécessitent le rôle ADMIN.
 *
 * Routes :
 *   GET /api/v1/monitoring/tableau-de-bord   → tableauDeBord()
 *   GET /api/v1/monitoring/audit             → audit()
 *   GET /api/v1/monitoring/signaux           → signaux()
 */
class MonitoringController extends BaseApiController
{
    /**
     * Vue d'ensemble du système : compteurs clés en temps réel.
     */
    public function tableauDeBord(Request $request): JsonResponse
    {
        return $this->succes([
            'organisations' => [
                'total'     => Organisation::count(),
                'actives'   => Organisation::where('statut', 'actif')->count(),
            ],
            'licences' => [
                'actives'          => Licence::where('statut', 'active')->count(),
                'suspendues'       => Licence::where('statut', 'suspendue')->count(),
                'expirees'         => Licence::where('statut', 'expiree')->count(),
                'revoquees'        => Licence::where('statut', 'revoquee')->count(),
                'expirent_30_jours' => Licence::where('statut', 'active')
                    ->whereBetween('date_expiration', [now(), now()->addDays(30)])
                    ->count(),
            ],
            'activations' => [
                'actives'   => Activation::where('statut', 'active')->count(),
                'revoquees' => Activation::where('statut', 'revoquee')->count(),
            ],
            'horodatage' => now()->toIso8601String(),
        ]);
    }

    /**
     * Journal d'audit paginé (toutes les actions API tracées).
     */
    public function audit(Request $request): JsonResponse
    {
        $paginator = AuditLog::with('organisation')
            ->when($request->filled('organisation_id'), fn ($q) =>
                $q->where('organisation_id', $request->integer('organisation_id'))
            )
            ->when($request->filled('action'), fn ($q) =>
                $q->where('action', 'like', '%' . $request->input('action') . '%')
            )
            ->when($request->filled('depuis'), fn ($q) =>
                $q->where('survenu_le', '>=', $request->input('depuis'))
            )
            ->latest('survenu_le')
            ->paginate($request->integer('par_page', 50));

        return $this->liste($paginator->items(), $this->metaPagination($paginator));
    }

    /**
     * Signaux de piratage récents (100 derniers).
     * Filtrés depuis audit_logs où l'action commence par 'SIGNAL_'.
     */
    public function signaux(Request $request): JsonResponse
    {
        $signaux = AuditLog::where('action', 'like', 'SIGNAL_%')
            ->latest('survenu_le')
            ->limit(100)
            ->get();

        return $this->succes($signaux, [
            'total'         => $signaux->count(),
            'horodatage'    => now()->toIso8601String(),
        ]);
    }
}
