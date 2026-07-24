<?php

use App\Http\Controllers\Api\V1\ActivationController;
use App\Http\Controllers\Api\V1\BlacklistController;
use App\Http\Controllers\Api\V1\LicenceController;
use App\Http\Controllers\Api\V1\MonitoringController;
use App\Http\Controllers\Api\V1\OrganisationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Certus v1 — Base URL : https://certus.expertosoft.com/api/v1
|--------------------------------------------------------------------------
|
| Authentification : header X-API-Key (table api_keys)
|   api.key       → présence + validité de la clé (tout niveau)
|   api.key.admin → niveau ADMIN requis (chaîné après api.key)
|
*/

// Route de santé publique — pas d'authentification requise
Route::get('/status', function () {
    return response()->json([
        'statut' => 'OK',
        'data'   => [
            'app'     => config('app.name'),
            'version' => 'v1',
            'time'    => now()->toIso8601String(),
        ],
        'meta'   => [],
    ]);
});

Route::middleware('api.key')->group(function () {

    // ----------------------------------------------------------------
    // P1 — Routes réservées aux clés ADMIN
    // ----------------------------------------------------------------
    Route::middleware('api.key.admin')->group(function () {

        // Organisations
        Route::post('/organisations', [OrganisationController::class, 'store']);
        Route::get('/organisations/{org_id}', [OrganisationController::class, 'show']);

        // Licences — émission et cycle de vie
        Route::post('/licences', [LicenceController::class, 'store']);
        Route::get('/licences/{licence_id}', [LicenceController::class, 'show']);
        Route::post('/licences/{licence_id}/revoquer', [LicenceController::class, 'revoquer']);

        // Activations — consultation et modification
        Route::get('/licences/{licence_id}/activations', [ActivationController::class, 'index']);
        Route::patch('/licences/{licence_id}/activations/{activation_id}', [ActivationController::class, 'update']);

        // Blacklist empreintes
        Route::post('/blacklist/fingerprints', [BlacklistController::class, 'store']);
        Route::get('/blacklist/fingerprints', [BlacklistController::class, 'index']);

        // Audit et historique
        Route::get('/licences/{licence_id}/audit', [LicenceController::class, 'audit']);
        Route::get('/licences/{licence_id}/historique-activations', [ActivationController::class, 'historique']);

        // Monitoring / alertes
        Route::get('/monitoring/alertes', [MonitoringController::class, 'alertes']);
        Route::patch('/licences/{licence_id}/reset-alertes', [MonitoringController::class, 'resetAlertes']);
    });

    // ----------------------------------------------------------------
    // P2 — Routes CLIENT (activation machine)
    // ----------------------------------------------------------------
    Route::post('/licences/activer', [ActivationController::class, 'activer']);
});
