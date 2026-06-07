<?php

use Illuminate\Support\Facades\Route;

Route::get('/status', function () {
    return response()->json([
        'status'  => 'ok',
        'app'     => config('app.name'),
        'version' => 'v1',
        'time'    => now()->toIso8601String(),
    ]);
});
