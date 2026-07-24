<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ErrorCodes;
use App\Models\AuditLog;
use App\Models\Licence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Surveillance des anomalies et alertes de piratage — Parcours 5.
 * Toutes les routes nécessitent le rôle ADMIN.
 *
 * Routes :
 *   GET   /api/v1/monitoring/alertes              → alertes()
 *   PATCH /api/v1/licences/{licence_id}/reset-alertes → resetAlertes()
 */
class MonitoringController extends BaseApiController
{
    /**
     * Licences présentant des tentatives suspectes, enrichies avec org_nom
     * et le dernier signal enregistré dans audit_log.
     *
     * Triées par tentatives_suspectes DESC.
     */
    public function alertes(Request $request): JsonResponse
    {
        // Sous-requêtes pour récupérer le dernier signal depuis audit_log
        $dernierSignalAction = DB::raw(
            "(SELECT action FROM audit_log
              WHERE audit_log.licence_id = licences.licence_id
              AND action IN (
                  '" . AuditLog::ACTION_TENTATIVE_REJETEE . "',
                  '" . AuditLog::ACTION_PIRATAGE_DETECTE  . "'
              )
              ORDER BY horodatage DESC LIMIT 1) AS dernier_signal"
        );

        $dernierSignalHorodatage = DB::raw(
            "(SELECT horodatage FROM audit_log
              WHERE audit_log.licence_id = licences.licence_id
              AND action IN (
                  '" . AuditLog::ACTION_TENTATIVE_REJETEE . "',
                  '" . AuditLog::ACTION_PIRATAGE_DETECTE  . "'
              )
              ORDER BY horodatage DESC LIMIT 1) AS dernier_signal_le"
        );

        $alertes = Licence::query()
            ->select([
                'licences.licence_id',
                'licences.org_id',
                'licences.statut',
                'licences.type_licence',
                'licences.tentatives_suspectes',
                'organisations.nom AS org_nom',
                $dernierSignalAction,
                $dernierSignalHorodatage,
            ])
            ->join('organisations', 'licences.org_id', '=', 'organisations.org_id')
            ->where('licences.tentatives_suspectes', '>', 0)
            ->orderByDesc('licences.tentatives_suspectes')
            ->get();

        return $this->succes(
            $alertes->map(fn ($row) => [
                'licence_id'           => $row->licence_id,
                'org_id'               => $row->org_id,
                'org_nom'              => $row->org_nom,
                'statut'               => $row->statut,
                'type_licence'         => $row->type_licence,
                'tentatives_suspectes' => $row->tentatives_suspectes,
                'dernier_signal'       => $row->dernier_signal,
                'dernier_signal_le'    => $row->dernier_signal_le,
            ])->values()->all(),
            [
                'total'      => $alertes->count(),
                'horodatage' => now()->toIso8601String(),
            ],
        );
    }

    /**
     * Remet à zéro le compteur tentatives_suspectes d'une licence.
     * Un motif est obligatoire pour traçabilité.
     */
    public function resetAlertes(Request $request, string $licence_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'motif' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->erreur(
                ErrorCodes::VALIDATION_ECHOUEE,
                'Le champ motif est obligatoire.',
                ['erreurs' => $validator->errors()->toArray()],
                422,
            );
        }

        $licence = Licence::find($licence_id);

        if ($licence === null) {
            return $this->erreur(
                ErrorCodes::LICENCE_INTROUVABLE,
                "Licence {$licence_id} introuvable.",
                [],
                404,
            );
        }

        $motif  = $validator->validated()['motif'];
        $acteur = $this->acteurCourant($request);

        DB::table('licences')
            ->where('licence_id', $licence_id)
            ->update(['tentatives_suspectes' => 0]);

        AuditLog::enregistrer(
            licenceId: $licence_id,
            action:    AuditLog::ACTION_RESET_ALERTES,
            acteur:    $acteur,
            ipSource:  $request->ip(),
            detail:    [
                'motif'                   => $motif,
                'ancienne_valeur'         => $licence->tentatives_suspectes,
            ],
        );

        return $this->succes([
            'licence_id'           => $licence_id,
            'tentatives_suspectes' => 0,
        ]);
    }
}
