<?php

use App\Http\Controllers\Api\ConsultaPersonaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Consulta de personas para sistemas externos
|--------------------------------------------------------------------------
|
| Hoy la consume Cuenta_facil al crear la ficha de un contratista. El aliado
| sale del usuario dueño del token, no de la URL.
|
| El throttle no es decorativo: detrás de este endpoint hay una llamada al
| operador de planilla, y una tanda sin freno vence la sesión de Enlace y
| deja fallando todo lo demás (pasó el 2026-08-04 con el barrido de clientes).
|
*/
Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->get('/v1/personas/{tipoDoc}/{cedula}', [ConsultaPersonaController::class, 'mostrar'])
    ->where(['tipoDoc' => '[A-Za-z]{2,3}', 'cedula' => '[0-9]{4,15}'])
    ->name('api.personas.mostrar');
