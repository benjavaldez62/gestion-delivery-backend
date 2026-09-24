<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\EstadoPagosController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Rutas para categorías
Route::post('/categorias', [CategoriaController::class, 'store']);
Route::get('/categorias', [CategoriaController::class, 'index']);
Route::get('/categorias/{id}', [CategoriaController::class, 'show']);
Route::put('/categorias/{id}', [CategoriaController::class, 'update']);
Route::delete('/categorias/{id}', [CategoriaController::class, 'destroy']);
Route::put('/categorias/{id}/restore', [CategoriaController::class, 'restore']);


//Rutas para MetodoPago
Route::get('/metodos-pago', [MetodoPagoController::class, 'index']);
Route::post('/metodos-pago', [MetodoPagoController::class, 'store']);
Route::get('/metodos-pago/{id}', [MetodoPagoController::class, 'show']);
Route::put('/metodos-pago/{id}', [MetodoPagoController::class, 'update']);
Route::delete('/metodos-pago/{id}', [MetodoPagoController::class, 'destroy']);
Route::put('/metodos-pago/{id}/restore', [MetodoPagoController::class, 'restore']);

//Rutas para EstadoPagos
Route::get('/estado-pagos', [EstadoPagosController::class, 'index']);
Route::post('/estado-pagos', [EstadoPagosController::class, 'store']);
Route::get('/estado-pagos/{id}', [EstadoPagosController::class, 'show']);
Route::put('/estado-pagos/{id}', [EstadoPagosController::class, 'update']);
Route::delete('/estado-pagos/{id}', [EstadoPagosController::class, 'destroy']);
Route::put('/estado-pagos/{id}/restore', [EstadoPagosController::class, 'restore']);
