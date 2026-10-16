<?php

use App\Http\Controllers\Api\V1\EventoPuertaController;
use App\Http\Middleware\EnsureDispositivoPuedeReportar;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:60,1', EnsureDispositivoPuedeReportar::class])->group(function () {
    Route::post('puertas/{puerta}/eventos', [EventoPuertaController::class, 'store'])
        ->name('api.v1.puertas.eventos.store');
});
