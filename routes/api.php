<?php

use App\Http\Controllers\AdicionalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\EstadoPagosController;
use App\Http\Controllers\EstadoPedidoController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PedidoItemController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rutas para Adicionales
Route::get('/adicionales', [AdicionalController::class, 'index']);
Route::post('/adicionales', [AdicionalController::class, 'store']);
Route::get('/adicionales/{id}', [AdicionalController::class, 'show']);
Route::put('/adicionales/{id}', [AdicionalController::class, 'update']);
Route::delete('/adicionales/{id}', [AdicionalController::class, 'destroy']);
Route::put('/adicionales/{id}/restore', [AdicionalController::class, 'restore']);
Route::get('/adicionales-desactivados', [AdicionalController::class, 'indexDesactivados']);

// Rutas para Authentication
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Rutas para Categorías
Route::post('/categorias', [CategoriaController::class, 'store']);
Route::get('/categorias', [CategoriaController::class, 'index']);
Route::get('/categorias/{id}', [CategoriaController::class, 'show']);
Route::put('/categorias/{id}', [CategoriaController::class, 'update']);
Route::delete('/categorias/{id}', [CategoriaController::class, 'destroy']);
Route::put('/categorias/{id}/restore', [CategoriaController::class, 'restore']);
Route::get('/categorias-desactivadas', [CategoriaController::class, 'indexDesactivadas']);

// Rutas para Clientes
Route::get('/clientes', [ClienteController::class, 'index']);
Route::post('/clientes', [ClienteController::class, 'store']);
Route::get('/clientes/{id}', [ClienteController::class, 'show']);
Route::put('/clientes/{id}', [ClienteController::class, 'update']);
Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);
Route::put('/clientes/{id}/restore', [ClienteController::class, 'restore']);
Route::get('/clientes-desactivados', [ClienteController::class, 'indexDesactivados']);

// Rutas para Comprobantes
Route::get('/comprobantes', [ComprobanteController::class, 'index']);
Route::post('/comprobantes', [ComprobanteController::class, 'store']);
Route::get('/comprobantes/{id}', [ComprobanteController::class, 'show']);
Route::delete('/comprobantes/{id}', [ComprobanteController::class, 'destroy']);
Route::put('/comprobantes/{id}/restore', [ComprobanteController::class, 'restore']);
Route::get('/comprobantes-desactivados', [ComprobanteController::class, 'indexDesactivados']);

// Rutas para EstadoPagos
Route::get('/estado-pagos', [EstadoPagosController::class, 'index']);
Route::post('/estado-pagos', [EstadoPagosController::class, 'store']);
Route::get('/estado-pagos/{id}', [EstadoPagosController::class, 'show']);
Route::put('/estado-pagos/{id}', [EstadoPagosController::class, 'update']);
Route::delete('/estado-pagos/{id}', [EstadoPagosController::class, 'destroy']);
Route::put('/estado-pagos/{id}/restore', [EstadoPagosController::class, 'restore']);
Route::get('/estado-pagos-desactivados', [EstadoPagosController::class, 'indexDesactivados']);

// Rutas para EstadoPedido
Route::get('/estado-pedidos', [EstadoPedidoController::class, 'index']);
Route::post('/estado-pedidos', [EstadoPedidoController::class, 'store']);
Route::get('/estado-pedidos/{id}', [EstadoPedidoController::class, 'show']);
Route::put('/estado-pedidos/{id}', [EstadoPedidoController::class, 'update']);
Route::delete('/estado-pedidos/{id}', [EstadoPedidoController::class, 'destroy']);
Route::put('/estado-pedidos/{id}/restore', [EstadoPedidoController::class, 'restore']);
Route::get('/estado-pedidos-desactivados', [EstadoPedidoController::class, 'indexDesactivados']);

// Rutas para MetodoPago
Route::get('/metodos-pago', [MetodoPagoController::class, 'index']);
Route::post('/metodos-pago', [MetodoPagoController::class, 'store']);
Route::get('/metodos-pago/{id}', [MetodoPagoController::class, 'show']);
Route::put('/metodos-pago/{id}', [MetodoPagoController::class, 'update']);
Route::delete('/metodos-pago/{id}', [MetodoPagoController::class, 'destroy']);
Route::put('/metodos-pago/{id}/restore', [MetodoPagoController::class, 'restore']);
Route::get('/metodos-pago-desactivados', [MetodoPagoController::class, 'indexDesactivados']);

// Rutas para Pagos
Route::get('/pagos', [PagoController::class, 'index']);
Route::post('/pagos', [PagoController::class, 'store']);
Route::get('/pagos/{id}', [PagoController::class, 'show']);
Route::put('/pagos/{id}', [PagoController::class, 'update']);
Route::delete('/pagos/{id}', [PagoController::class, 'destroy']);
Route::put('/pagos/{id}/restore', [PagoController::class, 'restore']);
Route::get('/pagos-desactivados', [PagoController::class, 'indexDesactivados']);

// Rutas para Pedidos
Route::get('/pedidos', [PedidoController::class, 'index']);
Route::post('/pedidos', [PedidoController::class, 'store']);
Route::get('/pedidos/{id}', [PedidoController::class, 'show']);
Route::put('/pedidos/{id}', [PedidoController::class, 'update']);
Route::delete('/pedidos/{id}', [PedidoController::class, 'destroy']);
Route::put('/pedidos/{id}/restore', [PedidoController::class, 'restore']);
Route::get('/pedidos-desactivados', [PedidoController::class, 'indexDesactivados']);

// Rutas para PedidoItem
Route::get('/pedido-items', [PedidoItemController::class, 'index']);
Route::post('/pedidos/{pedido}/items', [PedidoItemController::class, 'store']);
Route::get('/pedido-items/{pedidoItem}', [PedidoItemController::class, 'show']);
Route::put('/pedido-items/{pedidoItem}', [PedidoItemController::class, 'update']);
Route::delete('/pedido-items/{pedidoItem}', [PedidoItemController::class, 'destroy']);
Route::get('/pedido-items-desactivados', [PedidoItemController::class, 'indexDesactivados']);

// Rutas para Productos
Route::get('/productos', [ProductoController::class, 'index']); 
Route::post('/productos', [ProductoController::class, 'store']);
Route::get('/productos/{id}', [ProductoController::class, 'show']);
Route::put('/productos/{id}', [ProductoController::class, 'update']);
Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);
Route::put('/productos/{id}/restore', [ProductoController::class, 'restore']);
Route::get('/productos-desactivados', [ProductoController::class, 'indexDesactivados']); 

// Rutas para Roles
Route::get('/roles', [RoleController::class, 'index']);
Route::post('/roles', [RoleController::class, 'store']);
Route::get('/roles/{id}', [RoleController::class, 'show']);
Route::put('/roles/{id}', [RoleController::class, 'update']);
Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
Route::put('/roles/{id}/restore', [RoleController::class, 'restore']);
Route::get('/roles-desactivados', [RoleController::class, 'indexDesactivados']);

// Rutas para Usuarios
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);
Route::put('/users/{id}/restore', [UserController::class, 'restore']);
Route::get('users-desactivados', [UserController::class, 'indexDesactivados']);