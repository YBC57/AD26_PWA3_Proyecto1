<?php

use App\Http\Controllers\EventoPuertaController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [EventoPuertaController::class, 'index'])
        ->name('dashboard');

    Route::get('eventos-puerta', [EventoPuertaController::class, 'index'])
        ->name('eventos_puerta.index');

    Route::get('eventos-puerta/{eventoPuerta}', [EventoPuertaController::class, 'show'])
        ->name('eventos_puerta.show');

    Route::post('eventos-puerta', [EventoPuertaController::class, 'store'])
        ->name('eventos_puerta.store');
});

require __DIR__.'/settings.php';
