<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;


// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });


Route::post('login', [ApiController::class, 'login']);
Route::post('refresh', [ApiController::class, 'refreshToken']);

Route::get('paquetes_cantidad', [ApiController::class, 'paquetes_cantidad']);
Route::get('paquetes', [ApiController::class, 'paquetes']);
Route::get('paquetes_transito' , [ApiController::class, 'paquetesEnTransito']);
Route::get('cliente', [ApiController::class, 'cliente']);
Route::get('sucursales', [ApiController::class, 'sucursales']);

Route::get('direccion_miami', [ApiController::class, 'direccion_miami']);
Route::get('horario_retiro', [ApiController::class, 'horario_retiro']);
Route::get('obtener_direcciones', [ApiController::class, 'obtenerDirecciones']);

Route::post('crear_pedido', [ApiController::class, 'crear_pedido']);

Route::post('consultar_disponibilidad', [ApiController::class, 'consultar_disponibilidad']);
Route::post('obtener_pago' , [ApiController::class, 'obtener_pago']);