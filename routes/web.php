<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AlidoSelectorController;

// ─── Rutas públicas ────────────────────────────────────────────────────────
Route::get('/',      [LoginController::class, 'showLogin'])->name('login');
Route::get('/login', [LoginController::class, 'showLogin']);
Route::post('/login',  [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ─── Ruta pública: subida de documentos por token (cliente) ───────────────
// No requiere auth — solo verificación de cédula dentro del controller
Route::get( '/incapacidades/subir/{token}',  [\App\Http\Controllers\IncapacidadUploadController::class, 'show'])  ->name('incapacidades.subir');
Route::post('/incapacidades/subir/{token}',  [\App\Http\Controllers\IncapacidadUploadController::class, 'upload'])->name('incapacidades.subir.post');

// ─── Webhook público WhatsApp (Meta Cloud API) ─────────────────────────────
// No requiere auth — Meta llama directamente. Seguridad via HMAC en el controller.
Route::get( '/whatsapp/webhook', [\App\Http\Controllers\WhatsappWebhookController::class, 'verify']) ->name('whatsapp.webhook.verify');
Route::post('/whatsapp/webhook', [\App\Http\Controllers\WhatsappWebhookController::class, 'receive'])->name('whatsapp.webhook.receive');

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
        Route::get('clave-accesos/global',                 [$cac, 'vistaGlobal'])       ->name('clave_accesos.global');
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
        Route::post('contratos/{contrato}/duplicar-ir',  [\App\Http\Controllers\Admin\ContratoController::class, 'duplicarIngresoRetiro'])->name('contratos.duplicar-ir');
        // APIs reactivas del cotizador
        Route::post('contratos/api/cotizar',             [\App\Http\Controllers\Admin\ContratoController::class, 'cotizar'])->name('contratos.cotizar');
        Route::get('contratos/api/tarifas',              [\App\Http\Controllers\Admin\ContratoController::class, 'tarifasPorPlan'])->name('contratos.tarifas');
        Route::patch('contratos/api/radicado/{id}',      [\App\Http\Controllers\Admin\ContratoController::class, 'actualizarRadicado'])->name('contratos.radicado.update');

        // Configuración del aliado (tarifas, admon, ARL)
        Route::get('configuracion',            [\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'hub'])  ->name('configuracion.hub');
        Route::get('configuracion/parametros', [\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'index'])->name('configuracion.index');
        Route::post('configuracion/parametros',[\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'store'])->name('configuracion.store');
        // Cuentas bancarias
        Route::get('configuracion/cuentas',        [\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'cuentas'])              ->name('configuracion.cuentas');
        Route::post('configuracion/cuentas',       [\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'storeCuenta'])            ->name('configuracion.cuentas.store');
        Route::patch('configuracion/cuentas/{id}', [\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'updateCuenta'])           ->name('configuracion.cuentas.update');
        Route::delete('configuracion/cuentas/{id}',[\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'destroyCuenta'])          ->name('configuracion.cuentas.destroy');
        Route::patch('configuracion/cuentas/{id}/inactivar',         [\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'inactivarCuenta'])        ->name('configuracion.cuentas.inactivar');
        Route::get('configuracion/cuentas/{id}/estado-registros',    [\App\Http\Controllers\Admin\ConfiguracionAliadoController::class, 'estadoCuentaContratos']) ->name('configuracion.cuentas.estado_registros');
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
        Route::delete('configuracion/razones-sociales/{id}',              [$rsc, 'destroy'])       ->name('configuracion.razones.destroy');
        Route::patch('configuracion/razones-sociales/{id}/estado',        [$rsc, 'toggleEstado'])  ->name('configuracion.razones.estado');
        Route::patch('configuracion/razones-sociales/{id}/inactivar',     [$rsc, 'inactivar'])     ->name('configuracion.razones.inactivar');
        Route::get('configuracion/razones-sociales/{id}/estado-contratos',[$rsc, 'estadoContratos'])->name('configuracion.razones.estado_contratos');
        Route::post('configuracion/razones-sociales/{id}/sello',          [$rsc, 'subirSello'])    ->name('configuracion.razones.sello');

        // Documentos de Razones Sociales
        $rsdc = \App\Http\Controllers\Admin\RazonSocialDocumentoController::class;
        Route::post('configuracion/razones-sociales/{id}/documentos',          [$rsdc, 'store'])   ->name('configuracion.razones.documentos.store');
        Route::get('configuracion/razones-sociales/documentos/{id}/descargar', [$rsdc, 'download'])->name('configuracion.razones.documentos.download');
        Route::delete('configuracion/razones-sociales/documentos/{id}',        [$rsdc, 'destroy'])  ->name('configuracion.razones.documentos.destroy');

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
            Route::get('empresa/crear',                 [$fc, 'createEmpresa'])     ->name('empresa.create');
            Route::post('empresa',                      [$fc, 'storeEmpresa'])      ->name('empresa.store');
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
            Route::get('api/cotizacion-contrato/{id}',  [$fc, 'cotizacionContrato'])->name('api.cotizacion_contrato');
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

            // ── Facturación Electrónica (Dataico) — solo admin + superadmin ──
            $fe = \App\Http\Controllers\Admin\FacturacionElectronicaController::class;
            Route::get( 'electronica',          [$fe, 'index'])   ->name('electronica.index');
            Route::patch('electronica/marcar',  [$fe, 'marcar'])  ->name('electronica.marcar');
            Route::get(  'electronica/exportar',[$fe, 'exportar'])->name('electronica.exportar');
        });

        // ── Planos (Pago Planillas SS) ────────────────────────────────────
        Route::prefix('planos')->name('planos.')->group(function () {
            $pp = \App\Http\Controllers\Admin\PlanoPagoController::class;
            Route::get('/',                     [$pp, 'index'])            ->name('index');
            Route::get('/descargar',            [$pp, 'descargar'])         ->name('descargar');
            Route::get('/descargar-asopagos',   [$pp, 'descargarAsopagos'])->name('descargar_asopagos');
            Route::get('/descargar-miplanilla',       [$pp, 'descargarMiPlanilla'])    ->name('descargar_miplanilla');
            Route::get('/descargar-aportes-en-linea', [$pp, 'descargarAportesEnLinea'])->name('descargar_aportes_en_linea');
            Route::patch('/n-plano',            [$pp, 'actualizarNPlano']) ->name('n_plano.update');
            Route::patch('/{id}/mover',         [$pp, 'moverPlano'])       ->name('mover');
            Route::post('/confirmar-pago',      [$pp, 'confirmarPago'])    ->name('confirmar_pago');
            Route::get('/api/razon/{id}',       [$pp, 'apiRazonSocial'])   ->name('api.razon');
            Route::get('/api/resumen',           [$pp, 'apiResumenPlanos']) ->name('api.resumen');
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
            Route::get('/financiero/efectivo',     [$ic, 'financieroEfectivo'])   ->name('financiero.efectivo');

            Route::get('/financiero/auditar-planilla', [$ic, 'auditarPlanilla']) ->name('financiero.auditar_planilla');
            Route::get('/financiero/ss-planillas',     [$ic, 'ssPlanillas'])     ->name('financiero.ss_planillas');
            Route::get('/financiero/conciliacion-ss',  [$ic, 'conciliacionSS'])  ->name('financiero.conciliacion_ss');
            Route::get('/financiero/detalle-dia',      [$ic, 'detalleDia'])      ->name('financiero.detalle_dia');
            Route::get('/financiero/prestamos-mes',    [$ic, 'prestamesMes'])    ->name('financiero.prestamos_mes');
            Route::get('/financiero/gastos-detalle',   [$ic, 'gastosDetalle'])   ->name('financiero.gastos_detalle');
            Route::patch('/financiero/consignacion/{id}',  [$ic, 'editarConsignacion'])->name('financiero.consignacion.editar');
            Route::post('/financiero/consignacion/{id}/imagen', [$ic, 'subirImagenConsignacionFinanciero'])->name('financiero.consignacion.imagen');
            Route::get('/auditoria-facturas', [$ic, 'auditoriaFacturas'])->name('auditoria_facturas');

            // ── Gestión de gastos ──────────────────────────────────────────
            $ga = \App\Http\Controllers\Admin\GastoAdminController::class;
            Route::get('/gastos',              [$ga, 'index'])   ->name('gastos.index');
            Route::post('/gastos',             [$ga, 'store'])   ->name('gastos.store');
            Route::put('/gastos/{id}',         [$ga, 'update'])  ->name('gastos.update');
            Route::delete('/gastos/{id}',      [$ga, 'destroy']) ->name('gastos.destroy');
            Route::post('/gastos/{id}/imagen', [$ga, 'imagen'])  ->name('gastos.imagen');

            // ── Comisiones Asesores ──────────────────────────────────────
            $cc = \App\Http\Controllers\Admin\ComisionesController::class;
            Route::get('/comisiones',                            [$cc, 'index'])       ->name('comisiones.index');
            Route::get('/comisiones/afiliaciones',               [$cc, 'afiliaciones'])->name('comisiones.afiliaciones');
            Route::post('/comisiones/afiliaciones/{id}',         [$cc, 'distribuir'])  ->name('comisiones.distribuir');
            Route::post('/comisiones/asesores/{asesor}/pagar',   [$cc, 'pagar'])       ->name('comisiones.pagar');
        });

        // ── Anticipos (Pagos sin Factura) ────────────────────────────────
        Route::prefix('anticipos')->name('anticipos.')->group(function () {
            $ac = \App\Http\Controllers\Admin\AnticipoController::class;
            Route::post('/',                        [$ac, 'store'])       ->name('store');
            Route::get('/informe',                  [$ac, 'informe'])     ->name('informe');
            Route::post('/{id}/devolver',           [$ac, 'devolver'])    ->name('devolver');
            Route::delete('/{id}',                  [$ac, 'destroy'])     ->name('destroy');
            // APIs para modal facturar
            Route::get('/api/contrato/{id}',        [$ac, 'porContrato']) ->name('api.contrato');
            Route::get('/api/empresa/{id}',         [$ac, 'porEmpresa'])  ->name('api.empresa');
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

    // ── Gestión ARL (tipo_modalidad_id = 15)
    Route::prefix('admin/gestion-arl')->name('admin.gestion-arl.')->group(function () {
        $ga = \App\Http\Controllers\Admin\GestionArlController::class;
        Route::get('/',            [$ga, 'index'])   ->name('index');
        Route::patch('/{id}/renovar', [$ga, 'renovar'])->name('renovar');

        // ── DEBUG TEMPORAL — borrar después ──
        Route::get('/debug/{cedula}', function ($cedula) {
            $contratos = \App\Models\Contrato::where('cedula', $cedula)
                ->with(['tipoModalidad:id,tipo_modalidad,modalidad', 'razonSocial:id,razon_social', 'encargado:id,nombre'])
                ->get(['id','cedula','estado','tipo_modalidad_id','aliado_id','encargado_id','razon_social_id','fecha_ingreso','fecha_arl']);
            $user = \Illuminate\Support\Facades\Auth::user();
            return response()->json([
                'cedula_buscada'    => $cedula,
                'aliado_id_session' => session('aliado_id_activo', $user->aliado_id),
                'tu_aliado_id'      => $user->aliado_id,
                'es_brynex'         => $user->es_brynex,
                'TIPO_MODALIDAD_ARL'=> 15,
                'contratos_encontrados' => $contratos->count(),
                'contratos'         => $contratos->map(fn($c) => [
                    'id'               => $c->id,
                    'estado'           => $c->estado,
                    'tipo_modalidad_id'=> $c->tipo_modalidad_id,
                    'tipo_modalidad'   => $c->tipoModalidad?->tipo_modalidad . ' / ' . $c->tipoModalidad?->modalidad,
                    'aliado_id'        => $c->aliado_id,
                    'coincide_aliado'  => $c->aliado_id == session('aliado_id_activo', $user->aliado_id),
                    'es_tipo_15'       => $c->tipo_modalidad_id == 15,
                    'es_vigente'       => $c->estado === 'vigente',
                    'razon_social'     => $c->razonSocial?->razon_social,
                    'encargado'        => $c->encargado?->nombre,
                    'fecha_ingreso'    => $c->fecha_ingreso,
                    'fecha_arl'        => $c->fecha_arl,
                ]),
            ], 200, [], JSON_PRETTY_PRINT);
        })->name('debug');
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

    // ── Traslado Masivo de Razón Social ─────────────────────────────────────
    Route::prefix('admin/traslados-rs')->name('admin.traslados.')->middleware('role:superadmin|admin')->group(function () {
        $trs = \App\Http\Controllers\Admin\TrasladoRazonSocialController::class;
        Route::get('/',                        [$trs, 'index'])           ->name('index');
        Route::post('/validar',                [$trs, 'validar'])         ->name('validar');
        Route::post('/ejecutar',               [$trs, 'ejecutar'])        ->name('ejecutar');
        Route::post('/retiro-opcion-a',        [$trs, 'retirarOpcionA'])  ->name('retiro_a');
        Route::post('/retiro-opcion-b',        [$trs, 'retirarOpcionB'])  ->name('retiro_b');
        Route::get('/descargar-plano',         [$trs, 'descargarPlano'])  ->name('descargar_plano');
        Route::get('/descargar-excel',         [$trs, 'descargarExcel'])  ->name('descargar_excel');
        Route::get('/api/n-planos/{id}',       [$trs, 'apiNPlanosRs'])    ->name('api.n_planos');
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
        Route::get('/{id}/documentos-familia', [$ic, 'documentosFamilia']) ->name('documentos.familia');
        Route::post('/{id}/pago',              [$ic, 'registrarPago'])     ->name('pago.store');
        Route::get('/api/calcular/{id}',       [$ic, 'calcularValor'])     ->name('api.calcular');
        Route::get('/api/clientes',            [$ic, 'apiClientes'])       ->name('api.clientes');
        Route::get('/api/contratos',           [$ic, 'apiContratos'])      ->name('api.contratos');
        // Nuevas rutas
        Route::post('/{id}/link',              [$ic, 'generarLink'])       ->name('link.generar');
        Route::post('/{id}/abono',             [$ic, 'storeAbono'])        ->name('abono.store');
        Route::post('/{id}/prorroga',          [$ic, 'storeProrroga'])     ->name('prorroga.store');
        Route::get('/{id}/cuentas-rs',         [$ic, 'cuentasRazonSocial'])->name('cuentas.rs');
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

    // ─── Módulo WhatsApp ───────────────────────────────────────────────────────
    Route::prefix('admin/whatsapp')->name('admin.whatsapp.')->group(function () {
        $chat      = \App\Http\Controllers\Admin\WhatsappChatController::class;
        $plantilla = \App\Http\Controllers\Admin\WhatsappPlantillaController::class;
        $masivo    = \App\Http\Controllers\Admin\WhatsappMasivoController::class;
        $config    = \App\Http\Controllers\Admin\WhatsappConfigController::class;

        // ── Chat (todos los usuarios del aliado) ──────────────────────────────
        Route::get('chat',                       [$chat, 'index'])         ->name('chat.index');
        Route::get('chat/{id}',                  [$chat, 'show'])          ->name('chat.show');
        Route::post('chat/{id}/mensaje',         [$chat, 'enviarMensaje']) ->name('chat.mensaje');
        Route::patch('chat/{id}/asignar',        [$chat, 'asignar'])       ->name('chat.asignar');
        Route::patch('chat/{id}/cerrar',         [$chat, 'cerrar'])        ->name('chat.cerrar');
        Route::patch('chat/{id}/leer',           [$chat, 'marcarLeido'])   ->name('chat.leer');
        Route::get('chat/media/{mensajeId}',     [$chat, 'descargarMedia'])->name('chat.media');
        Route::get('api/no-leidos',              [$chat, 'apiNoLeidos'])   ->name('api.no_leidos');

        // ── Plantillas (admin del aliado) ─────────────────────────────────────
        Route::get('plantillas',                 [$plantilla, 'index'])          ->name('plantillas.index');
        Route::get('plantillas/crear',           [$plantilla, 'create'])         ->name('plantillas.create');
        Route::post('plantillas',                [$plantilla, 'store'])          ->name('plantillas.store');
        Route::get('plantillas/{id}/editar',     [$plantilla, 'edit'])           ->name('plantillas.edit');
        Route::put('plantillas/{id}',            [$plantilla, 'update'])         ->name('plantillas.update');
        Route::delete('plantillas/{id}',         [$plantilla, 'destroy'])        ->name('plantillas.destroy');
        Route::post('plantillas/sincronizar',    [$plantilla, 'sincronizar'])    ->name('plantillas.sincronizar');
        Route::get('api/plantillas-aprobadas',   [$plantilla, 'apiListarAprobadas'])->name('api.plantillas');

        // ── Envíos masivos (admin del aliado) ─────────────────────────────────
        Route::post('masivo/individual',         [$masivo, 'lanzarIndividual'])  ->name('masivo.individual');
        Route::post('masivo/empresa',            [$masivo, 'lanzarEmpresa'])     ->name('masivo.empresa');
        Route::get('masivo/historial',           [$masivo, 'historial'])         ->name('masivo.historial');
        Route::get('masivo/{id}',                [$masivo, 'detalle'])           ->name('masivo.detalle');

        // ── Configuración (solo Brynex superadmin) ────────────────────────────
        Route::get('configuracion',              [$config, 'index'])             ->name('config.index');
        Route::get('configuracion/{id}/editar',  [$config, 'edit'])              ->name('config.edit');
        Route::put('configuracion/{id}',         [$config, 'update'])            ->name('config.update');
        Route::post('configuracion/verificar',   [$config, 'verificarWebhook'])  ->name('config.verificar');
    });
});

