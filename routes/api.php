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
| Middleware :
|   api.key       → vérifie X-API-Key (toutes les routes protégées)
|   api.key.admin → restreint aux clés de rôle ADMIN
|
*/

// Route de santé publique (pas d'auth requise)
Route::get('/status', function () {
    return response()->json([
        'statut'  => 'OK',
        'data'    => [
            'app'     => config('app.name'),
            'version' => 'v1',
            'time'    => now()->toIso8601String(),
        ],
        'meta'    => [],
    ]);
});

// ----------------------------------------------------------------
// Routes protégées par clé API (CLIENT + ADMIN)
// ----------------------------------------------------------------
Route::middleware('api.key')->group(function () {

    // Licences — lecture accessible à tous
    Route::get('/licences', [LicenceController::class, 'index']);
    Route::get('/licences/{id}', [LicenceController::class, 'show']);
    Route::get('/licences/{licenceId}/activations', [ActivationController::class, 'index']);

    // Activations — opérations machine
    Route::post('/activations', [ActivationController::class, 'activer']);
    Route::post('/activations/{id}/heartbeat', [ActivationController::class, 'heartbeat']);
    Route::delete('/activations/{id}', [ActivationController::class, 'revoquer']);

    // Vérification blacklist (lecture, accessible sans rôle admin)
    Route::get('/blacklist/verifier', [BlacklistController::class, 'verifier']);

    // ----------------------------------------------------------------
    // Routes réservées aux ADMIN
    // ----------------------------------------------------------------
    Route::middleware('api.key.admin')->group(function () {

        // Organisations
        Route::apiResource('organisations', OrganisationController::class);

        // Licences — écriture
        Route::post('/licences', [LicenceController::class, 'store']);
        Route::post('/licences/{id}/suspendre', [LicenceController::class, 'suspendre']);
        Route::post('/licences/{id}/revoquer', [LicenceController::class, 'revoquer']);

        // Blacklist fingerprints
        Route::get('/blacklist/fingerprints', [BlacklistController::class, 'index']);
        Route::post('/blacklist/fingerprints', [BlacklistController::class, 'ajouter']);
        Route::delete('/blacklist/fingerprints/{id}', [BlacklistController::class, 'retirer']);

        // Monitoring
        Route::get('/monitoring/tableau-de-bord', [MonitoringController::class, 'tableauDeBord']);
        Route::get('/monitoring/audit', [MonitoringController::class, 'audit']);
        Route::get('/monitoring/signaux', [MonitoringController::class, 'signaux']);
    });
});
