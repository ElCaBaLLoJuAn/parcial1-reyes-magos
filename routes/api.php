<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaravanaController;
use App\Http\Controllers\ParadaController;
use Illuminate\Support\Facades\Route;

/*
| Rutas -> Controladores -> Servicios -> Repositorios.
| 'rol:JEFE_CARAVANA' = middleware AutenticarJwt (401 sin token / 403 rol distinto).
| Todas las rutas quedan bajo el prefijo /api.
*/

Route::post('/auth/login', [AuthController::class, 'login']);

// Públicas
Route::get('/caravanas', [CaravanaController::class, 'index']);
Route::get('/caravanas/{id}', [CaravanaController::class, 'show'])->whereNumber('id');
Route::get('/caravanas/{caravanaId}/paradas', [ParadaController::class, 'index'])->whereNumber('caravanaId');
Route::get('/caravanas/{caravanaId}/itinerario', [ParadaController::class, 'itinerario'])->whereNumber('caravanaId'); // extra
Route::patch('/paradas/{id}/estado', [ParadaController::class, 'cambiarEstado'])->whereNumber('id');

// Protegidas: rol JEFE_CARAVANA
Route::middleware('rol:JEFE_CARAVANA')->group(function () {
    Route::post('/caravanas', [CaravanaController::class, 'store']);
    Route::put('/caravanas/{id}', [CaravanaController::class, 'update'])->whereNumber('id');
    Route::patch('/caravanas/{id}/estado', [CaravanaController::class, 'cambiarEstado'])->whereNumber('id');
    Route::post('/caravanas/{caravanaId}/paradas', [ParadaController::class, 'store'])->whereNumber('caravanaId');
    Route::delete('/paradas/{id}', [ParadaController::class, 'destroy'])->whereNumber('id');
});
