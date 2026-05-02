<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AlidoSelectorController;

// ─── Rutas públicas ────────────────────────────────────────────────────────
Route::get('/',      [LoginController::class, 'showLogin'])->name('login');
Route::get('/login', [LoginController::class, 'showLogin']);
Route::post('/login',  [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ─── CSRF token fresco (puede llamarse sin auth, pero solo desde session activa) ──
// El JS lo usa para renovar el token antes de peticiones PATCH/POST críticas.
Route::get('/csrf-token', function () {
    return response()->json([
        'token' => csrf_token(),
    ]);
})->name('csrf.token')->middleware('web');


// ─── Rutas protegidas ──────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Selector de aliado (solo usuarios BryNex)
    Route::get('/seleccionar-aliado',  [AlidoSelectorController::class, 'index'])->name('aliado.selector');
    Route::post('/seleccionar-aliado', [AlidoSelectorController::class, 'seleccionar'])->name('aliado.seleccionar');
    Route::post('/cambiar-aliado',     [AlidoSelectorController::class, 'cambiar'])->name('aliado.cambiar');

    // Dashboard principal
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // ─── Panel Administración ──────────────────────────────────────
    Route::prefix('admin')->name('admin.')->group(function () {

        // Aliados (solo superadmin)
        Route::resource('aliados', \App\Http\Controllers\Admin\AlidoController::class)
             ->except(['show']);
        Route::patch('aliados/{id}/restore', [\App\Http\Controllers\Admin\AlidoController::class, 'restore'])
             ->name('aliados.restore');

        // Usuarios (superadmin + admin)
        Route::resource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class)
             ->except(['show']);
        Route::patch('usuarios/{id}/restore', [\App\Http\Controllers\Admin\UsuarioController::class, 'restore'])
             ->name('usuarios.restore');

        // Asesores (superadmin + admin + usuario)
        Route::get('asesores/reporte-mensual', [\App\Http\Controllers\Admin\AsesorController::class, 'reporteMensual'])
             ->name('asesores.reporte_mensual');
        Route::resource('asesores', \App\Http\Controllers\Admin\AsesorController::class)->parameters(['asesores' => 'asesor']);
        Route::patch('asesores/{id}/restore', [\App\Http\Controllers\Admin\AsesorController::class, 'restore'])
             ->name('asesores.restore');
        Route::post('asesores/{asesor}/comisiones', [\App\Http\Controllers\Admin\AsesorController::class, 'registrarComision'])
             ->name('asesores.comisiones.store');
        Route::patch('comisiones/{comision}/pagar', [\App\Http\Controllers\Admin\AsesorController::class, 'marcarPagada'])
             ->name('asesores.comisiones.pagar');

        // Clientes (todos los roles con acceso)
        Route::get('clientes/buscar-cedula', [\App\Http\Controllers\Admin\ClienteController::class, 'buscarPorCedula'])
             ->name('clientes.buscar_cedula');
        Route::resource('clientes', \App\Http\Controllers\Admin\ClienteController::class)
             ->parameters(['clientes' => 'cliente'])
             ->except(['show', 'destroy']);

        // Beneficiarios
        Route::get('clientes/{cedula}/beneficiarios',  [\App\Http\Controllers\Admin\BeneficiarioController::class, 'index'])->name('clientes.beneficiarios.index');
        Route::post('clientes/{cedula}/beneficiarios', [\App\Http\Controllers\Admin\BeneficiarioController::class, 'store'])->name('clientes.beneficiarios.store');
        Route::put('beneficiarios/{id}',    [\App\Http\Controllers\Admin\BeneficiarioController::class, 'update'])->name('beneficiarios.update');
        Route::delete('beneficiarios/{id}', [\App\Http\Controllers\Admin\BeneficiarioController::class, 'destroy'])->name('beneficiarios.destroy');

        // Documentos del cliente
        Route::get('clientes/{cedula}/documentos',  [\App\Http\Controllers\Admin\DocumentoClienteController::class, 'index'])->name('clientes.documentos.index');
        Route::post('clientes/{cedula}/documentos', [\App\Http\Controllers\Admin\DocumentoClienteController::class, 'store'])->name('clientes.documentos.store');
        Route::get('documentos/{id}/descargar',     [\App\Http\Controllers\Admin\DocumentoClienteController::class, 'download'])->name('documentos.download');
        Route::delete('documentos/{id}',            [\App\Http\Controllers\Admin\DocumentoClienteController::class, 'destroy'])->name('documentos.destroy');

        // Claves de acceso (cliente y razón social)
        $cac = \App\Http\Controllers\Admin\ClaveAccesoController::class;
        Route::get('clave-accesos',                        [$cac, 'index'])             ->name('clave_accesos.index');
        Route::get('clave-accesos/razon-social/{id}',      [$cac, 'indexRazonSocial'])  ->name('clave_accesos.razon_social');
        Route::get('clave-accesos/empresa/{id}',           [$cac, 'indexEmpresa'])      ->name('clave_accesos.empresa');
        Route::post('clave-accesos',                       [$cac, 'store'])             ->name('clave_accesos.store');
        Route::put('clave-accesos/{id}',                   [$cac, 'update'])            ->name('clave_accesos.update');
        Route::delete('clave-accesos/{id}',                [$cac, 'destroy'])           ->name('clave_accesos.destroy');

        // Bitácora (solo superadmin)
        Route::get('bitacora', [\App\Http\Controllers\Admin\BitacoraController::class, 'index'])->name('bitacora.index');

        // Contratos
        Route::resource('contratos', \App\Http\Controllers\Admin\ContratoController::class)
             ->parameters(['contratos' => 'contrato'])
             ->except(['show', 'destroy']);
        Route::patch('contratos/{contrato}/retirar',     [\App\Http\Controllers\Admin\ContratoController::class, 'retirar'])->name('contratos.retirar');
        // APIs reactivas del cotizador
        Route::post('contratos/api/cotizar',             [\App\Http\Controllers\Admin\ContratoController::class, 'cotizar'])->name('contratos.cotizar');
        Route::get('contratos/api/tarifas',              [\App\Http\Controllers\Admin\ContratoController::class, 'tarifasPorPlan'])->name('contratos.tarifas');
        Route::patch('contratos/api/radicado/{id}',      [\App\Http\Controllers\Admin\ContratoController::class, 'actualizarRadicado'])->name('contratos.radicado.update');

        // Configuración del aliado (tarifas, admon, ARL)
        Route::get('configuracion',            [\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'hub'])  ->name('configuracion.hub');
        Route::get('configuracion/parametros', [\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'index'])->name('configuracion.index');
        Route::post('configuracion/parametros',[\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'store'])->name('configuracion.store');
        // Cuentas bancarias
        Route::get('configuracion/cuentas',        [\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'cuentas'])      ->name('configuracion.cuentas');
        Route::post('configuracion/cuentas',       [\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'storeCuenta']) ->name('configuracion.cuentas.store');
        Route::patch('configuracion/cuentas/{id}', [\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'updateCuenta'])->name('configuracion.cuentas.update');
        Route::delete('configuracion/cuentas/{id}',[\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'destroyCuenta'])->name('configuracion.cuentas.destroy');
        // Configuración de modalidades → planes
        $mc = \App\Http\Controllers\Admin\ModalidadConfigController::class;
        Route::get('configuracion/modalidades',          [$mc, 'index'])        ->name('configuracion.modalidades');
        Route::post('configuracion/modalidades',          [$mc, 'guardar'])      ->name('configuracion.modalidades.guardar');
        Route::patch('configuracion/modalidades/{id}/toggle', [$mc, 'toggleActivo']) ->name('configuracion.modalidades.toggle');

        // Configuración de operadores de planilla SS por aliado
        $opc = \App\Http\Controllers\Admin\OperadorPlanillaConfigController::class;
        Route::get('configuracion/operadores-planilla',              [$opc, 'index'])         ->name('configuracion.operadores.index');
        Route::patch('configuracion/operadores-planilla/{id}/toggle',[$opc, 'toggle'])        ->name('configuracion.operadores.toggle');
        Route::post('configuracion/operadores-planilla/orden',       [$opc, 'guardarOrden'])  ->name('configuracion.operadores.orden');

        // CRUD de Razones Sociales (empresas de afiliación) por aliado
        $rsc = \App\Http\Controllers\Admin\RazonSocialController::class;
        Route::get( 'configuracion/razones-sociales',              [$rsc, 'index'])       ->name('configuracion.razones.index');
        Route::get( 'configuracion/razones-sociales/crear',        [$rsc, 'create'])      ->name('configuracion.razones.create');
        Route::post('configuracion/razones-sociales',              [$rsc, 'store'])       ->name('configuracion.razones.store');
        Route::get( 'configuracion/razones-sociales/{id}/editar',  [$rsc, 'edit'])        ->name('configuracion.razones.edit');
        Route::put( 'configuracion/razones-sociales/{id}',         [$rsc, 'update'])      ->name('configuracion.razones.update');
        Route::delete('configuracion/razones-sociales/{id}',       [$rsc, 'destroy'])     ->name('configuracion.razones.destroy');
        Route::patch('configuracion/razones-sociales/{id}/estado', [$rsc, 'toggleEstado'])->name('configuracion.razones.estado');

        // Formularios EPS — mapeo visual de coordenadas
        $ef = \App\Http\Controllers\Admin\EpsFormularioController::class;
        Route::get ('configuracion/eps/{eps}/formulario',      [$ef, 'editor'])   ->name('configuracion.eps.formulario');
        Route::get ('configuracion/eps/{eps}/formulario/pdf',  [$ef, 'verPdf'])   ->name('configuracion.eps.formulario.vpdf');
        Route::post('configuracion/eps/{eps}/formulario',      [$ef, 'guardar'])  ->name('configuracion.eps.formulario.guardar');
        Route::post('configuracion/eps/{eps}/formulario/pdf',  [$ef, 'subirPdf']) ->name('configuracion.eps.formulario.pdf');

        // API utilitaria: ciudades por departamento (para selects dinámicos)
        Route::get('api/departamentos/{id}/ciudades', function ($id) {
            return \App\Models\Ciudad::where('departamento_id', $id)
                ->orderBy('nombre')
                ->get(['id', 'nombre']);
        })->name('api.ciudades');

        // ─── Facturación ──────────────────────────────────────────────
        Route::prefix('facturacion')->name('facturacion.')->group(function () {
            $fc = \App\Http\Controllers\Admin\FacturacionController::class;
            Route::get('/',                             [$fc, 'index'])             ->name('index');
            Route::get('empresa/{id}',                  [$fc, 'empresa'])           ->name('empresa');
            Route::get('empresa/{id}/historial',        [$fc, 'historialEmpresa'])  ->name('empresa.historial');
            Route::get('empresa/{id}/editar',           [$fc, 'editEmpresa'])       ->name('empresa.edit');
            Route::put('empresa/{id}/editar',           [$fc, 'updateEmpresa'])     ->name('empresa.update');
            Route::post('facturar',                     [$fc, 'facturar'])          ->name('facturar');
            Route::post('abonar/{id}',                  [$fc, 'abonar'])            ->name('abonar');
            Route::get('recibo/{id}',                   [$fc, 'recibo'])            ->name('recibo');
            Route::get('recibo-abono/{id}',             [$fc, 'reciboAbono'])       ->name('recibo-abono');
            Route::get('api/saldo/{cedula}',            [$fc, 'saldoCliente'])      ->name('api.saldo');
            Route::get('api/mes-pagado/{contratoId}',   [$fc, 'mesPagado'])         ->name('api.mes_pagado');
            Route::get('api/plano/{razon_social_id}',   [$fc, 'planoActual'])       ->name('api.plano');
            Route::get('api/saldos-contratos',          [$fc, 'saldosContratos'])   ->name('api.saldos_contratos');
            Route::delete('{id}/anular',                [$fc, 'anular'])            ->name('anular');
            Route::get('historial/{cedula}',            [$fc, 'historial'])         ->name('historial');
            Route::get('anuladas',                      [$fc, 'anuladas'])          ->name('anuladas');
            Route::post('{id}/restaurar',               [$fc, 'restaurar'])         ->name('restaurar');
            // Imágenes de consignaciones
            Route::post('consignacion/{id}/imagen',     [$fc, 'subirImagenConsignacion'])->name('consignacion.imagen.subir');
            Route::get('consignacion/{id}/imagen',      [$fc, 'verImagenConsignacion'])  ->name('consignacion.imagen.ver');
            // Otro ingreso (trámites: traslado EPS, inclusión beneficiarios, etc.)
            Route::post('otro-ingreso',                 [$fc, 'facturarOtroIngreso'])    ->name('otro_ingreso.store');
            // Cuenta de Cobro
            Route::post('cuenta-cobro',                 [$fc, 'cuentaCobroPreview'])     ->name('cuenta_cobro.preview');
        });

        // ── Planos (Pago Planillas SS) ────────────────────────────────────
        Route::prefix('planos')->name('planos.')->group(function () {
            $pp = \App\Http\Controllers\Admin\PlanoPagoController::class;
            Route::get('/',                     [$pp, 'index'])            ->name('index');
            Route::get('/descargar',            [$pp, 'descargar'])        ->name('descargar');
            Route::patch('/n-plano',            [$pp, 'actualizarNPlano']) ->name('n_plano.update');
            Route::patch('/{id}/mover',         [$pp, 'moverPlano'])       ->name('mover');
            Route::post('/confirmar-pago',      [$pp, 'confirmarPago'])    ->name('confirmar_pago');
            Route::get('/api/razon/{id}',       [$pp, 'apiRazonSocial'])   ->name('api.razon');
        });

        // ── Cobros ───────────────────────────────────────────────────────
        Route::prefix('cobros')->name('cobros.')->group(function () {
            $cb = \App\Http\Controllers\Admin\CobrosController::class;
            // Individuales
            Route::get('/',                          [$cb, 'index'])                  ->name('index');
            Route::post('/{contratoId}/llamada',     [$cb, 'registrarLlamada'])       ->name('llamada.store');
            Route::get('/{contratoId}/llamadas',     [$cb, 'historialLlamadas'])      ->name('llamadas');
            // Empresas
            Route::get('/empresas',                  [$cb, 'empresas'])               ->name('empresas');
            Route::post('/empresa/{id}/llamada',     [$cb, 'registrarLlamadaEmpresa'])->name('empresa.llamada.store');
            Route::get('/empresa/{id}/llamadas',     [$cb, 'historialEmpresa'])       ->name('empresa.llamadas');
            Route::patch('/empresa/{id}/encargado',  [$cb, 'asignarEncargado'])       ->name('empresa.encargado');
        });

        // ── Informes (admin + superadmin; financiero también para contador) ──
        Route::prefix('informes')->name('informes.')->group(function () {
            $ic = \App\Http\Controllers\Admin\InformeController::class;
            Route::get('/',                       [$ic, 'hub'])                  ->name('hub');
            Route::get('/clientes-activos',       [$ic, 'clientesActivos'])      ->name('clientes_activos');
            Route::get('/por-razon-social',       [$ic, 'porRazonSocial'])       ->name('por_razon_social');
            Route::get('/afiliaciones-retiros',   [$ic, 'afiliacionesRetiros'])  ->name('afiliaciones_retiros');
            Route::get('/empresas-clientes',      [$ic, 'empresasClientes'])     ->name('empresas_clientes');
            Route::get('/por-entidades',          [$ic, 'porEntidades'])         ->name('por_entidades');
            Route::get('/retirados-mes',          [$ic, 'retiradosMes'])         ->name('retirados_mes');
            Route::get('/incapacidades',          [$ic, 'resumenIncapacidades']) ->name('incapacidades');
            Route::get('/tareas',                 [$ic, 'resumenTareas'])        ->name('tareas');
            Route::get('/financiero',             [$ic, 'estadoFinanciero'])     ->name('financiero');
            Route::get('/financiero/bancos',      [$ic, 'financieroBancos'])     ->name('financiero.bancos');
            Route::get('/financiero/auditar-planilla', [$ic, 'auditarPlanilla']) ->name('financiero.auditar_planilla');
            Route::get('/financiero/ss-planillas',     [$ic, 'ssPlanillas'])     ->name('financiero.ss_planillas');
        });

        // ── Préstamos / Cartera ──────────────────────────────────────────
        Route::prefix('prestamos')->name('prestamos.')->group(function () {
            $pc = \App\Http\Controllers\Admin\PrestamosController::class;
            Route::get('/',                  [$pc, 'index'])              ->name('index');
            Route::get('/api/pendientes',    [$pc, 'apiPendientes'])      ->name('api.pendientes');
            Route::get('/{id}',              [$pc, 'show'])               ->name('show');
            Route::post('/{id}/abonar',      [$pc, 'abonar'])             ->name('abonar');
            Route::post('/{id}/condonar',    [$pc, 'condonar'])           ->name('condonar');
            Route::post('/{id}/gestion',     [$pc, 'registrarGestion'])   ->name('gestion.store');
            Route::get('/{id}/gestiones',    [$pc, 'historialGestiones']) ->name('gestiones');
        });

    });

    // ── BryNex Global (solo usuarios es_brynex) ──────────────────────────
    Route::prefix('brynex')->name('brynex.')->group(function () {
        $bx = \App\Http\Controllers\BrynexController::class;
        Route::get('/',         [$bx, 'hub'])          ->name('hub');
        Route::get('/accesos',  [$bx, 'accesos'])       ->name('accesos');
        Route::post('/accesos', [$bx, 'toggleAcceso'])  ->name('accesos.toggle');
    });


    // ── Cuadre Diario ────────────────────────────────────────────────

    Route::prefix('cuadre-diario')->name('admin.cuadre-diario.')->group(function () {
        $cd = \App\Http\Controllers\Admin\CuadreDiarioController::class;
        Route::get('/',                          [$cd, 'index'])                ->name('index');
        Route::post('/abrir',                    [$cd, 'abrir'])                ->name('abrir');
        Route::get('/consolidado',               [$cd, 'consolidado'])          ->name('consolidado');
        Route::get('/bancos',                    [$cd, 'bancos'])               ->name('bancos');
        Route::delete('/gasto/{gastoId}',        [$cd, 'eliminarGasto'])        ->name('gasto.destroy');
        Route::post('/gasto/{gastoId}/imagen',               [$cd, 'subirImagenGasto'])       ->name('gasto.imagen');
        Route::post('/consignacion/{csId}/imagen',            [$cd, 'subirImagenConsignacion'])->name('consignacion.imagen');
        Route::post('/consignacion/{csId}/confirmar',          [$cd, 'confirmarConsignacion'])->name('consignacion.confirmar');
        Route::patch('/consignacion/{csId}/confirmar/reversar', [$cd, 'reversarConsignacion']) ->name('consignacion.reversar');
        Route::get('/{id}',                      [$cd, 'ver'])                  ->name('ver');
        Route::post('/{id}/gasto',               [$cd, 'registrarGasto'])       ->name('gasto.store');
        Route::post('/{id}/cerrar',              [$cd, 'cerrar'])               ->name('cerrar');
    });

    // ── Caja Menor ───────────────────────────────────────────────────
    Route::prefix('caja-menor')->name('admin.caja-menor.')->group(function () {
        $cm = \App\Http\Controllers\Admin\CajaMenorController::class;
        Route::get('/',    [$cm, 'index'])->name('index');
        Route::post('/',   [$cm, 'store'])->name('store');
    });

    // -- Afiliaciones
    Route::prefix('admin/afiliaciones')->name('admin.afiliaciones.')->group(function () {
        $ac = \App\Http\Controllers\Admin\AfiliacionController::class;
        $fc = \App\Http\Controllers\Admin\FormularioEpsController::class;
        Route::get('/',                            [$ac, 'index'])   ->name('index');
        Route::get('/exportar',                    [$ac, 'exportar'])->name('exportar');
        Route::get('/{contrato}/formulario/eps',       [$fc, 'vista'])   ->name('formulario.eps');
        Route::get('/{contrato}/formulario/eps/raw',   [$fc, 'generar']) ->name('formulario.eps.raw');
        Route::post('/{contrato}/formulario/eps/firma',[$fc, 'guardarFirma'])->name('formulario.eps.firma');
    });

    // ── Tareas ───────────────────────────────────────────────────────────────
    Route::prefix('admin/tareas')->name('admin.tareas.')->group(function () {
        $tc = \App\Http\Controllers\Admin\TareaController::class;
        Route::get('/',                        [$tc, 'index'])              ->name('index');
        Route::post('/',                       [$tc, 'store'])              ->name('store');
        Route::get('/reporte',                 [$tc, 'reporte'])            ->name('reporte');
        Route::put('/{id}',                    [$tc, 'update'])             ->name('update');
        Route::delete('/{id}',                 [$tc, 'destroy'])            ->name('destroy');
        Route::get('/{id}/show',               [$tc, 'show'])               ->name('show');
        Route::post('/{id}/gestion',           [$tc, 'gestion'])            ->name('gestion');
        Route::patch('/{id}/trasladar',        [$tc, 'trasladar'])          ->name('trasladar');
        Route::patch('/{id}/cerrar',           [$tc, 'cerrar'])             ->name('cerrar');
        Route::post('/{id}/documento',         [$tc, 'subirDocumento'])     ->name('documento.store');
        Route::get('/documento/{docId}',       [$tc, 'descargarDocumento']) ->name('documento.download');
        Route::get('/api/clientes',            [$tc, 'buscarCliente'])      ->name('api.clientes');
        Route::get('/api/contratos',           [$tc, 'contratosPorCedula']) ->name('api.contratos');
    });

    // ── Incapacidades ────────────────────────────────────────────────────────
    Route::prefix('admin/incapacidades')->name('admin.incapacidades.')->group(function () {
        $ic = \App\Http\Controllers\Admin\IncapacidadController::class;
        Route::get('/',                        [$ic, 'index'])             ->name('index');
        Route::post('/',                       [$ic, 'store'])             ->name('store');
        Route::put('/{id}',                    [$ic, 'update'])            ->name('update');
        Route::delete('/{id}',                 [$ic, 'destroy'])           ->name('destroy');
        Route::get('/{id}/show',               [$ic, 'show'])              ->name('show');
        Route::post('/{id}/gestion',           [$ic, 'storeGestion'])      ->name('gestion.store');
        Route::post('/{id}/documento',         [$ic, 'storeDocumento'])    ->name('documento.store');
        Route::get('/documento/{docId}',       [$ic, 'descargarDocumento'])->name('documento.download');
        Route::post('/{id}/pago',              [$ic, 'registrarPago'])     ->name('pago.store');
        Route::get('/api/calcular/{id}',       [$ic, 'calcularValor'])     ->name('api.calcular');
        Route::get('/api/clientes',            [$ic, 'apiClientes'])       ->name('api.clientes');
        Route::get('/api/contratos',           [$ic, 'apiContratos'])      ->name('api.contratos');
    });

    // -- Radicados
    Route::prefix('admin/radicados')->name('admin.radicados.')->group(function () {
        $rc = \App\Http\Controllers\Admin\RadicadoController::class;
        Route::patch('{id}',             [$rc, 'update'])             ->name('update');
        Route::post('{id}/pdf',          [$rc, 'subirPdf'])           ->name('pdf');
        Route::get('{id}/pdf/descargar', [$rc, 'descargarPdf'])       ->name('pdf.download');
        Route::patch('{id}/enviado',     [$rc, 'marcarEnviado'])      ->name('enviado');
        Route::get('{id}/bitacora',      [$rc, 'bitacora'])           ->name('bitacora');
        Route::get('{id}/documentos',    [$rc, 'documentosCotizante'])->name('documentos');
    });
});

