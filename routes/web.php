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

        // Cotizaciones y Prospectos
        Route::post('cotizaciones/{id}/gestion', [\App\Http\Controllers\Admin\CotizacionController::class, 'registrarGestion'])->name('cotizaciones.gestion');
        Route::post('cotizaciones/{id}/cotizar', [\App\Http\Controllers\Admin\CotizacionController::class, 'cotizar'])->name('cotizaciones.cotizar');
        Route::post('cotizaciones/{id}/convertir', [\App\Http\Controllers\Admin\CotizacionController::class, 'convertirACliente'])->name('cotizaciones.convertir');
        Route::get('cotizaciones/{id}/pdf', [\App\Http\Controllers\Admin\CotizacionController::class, 'descargarPdf'])->name('cotizaciones.pdf');
        Route::resource('cotizaciones', \App\Http\Controllers\Admin\CotizacionController::class);

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
        Route::get('contratos/api/calcular-retiro/{contrato}', [\App\Http\Controllers\Admin\ContratoController::class, 'apiCalcularRetiro'])->name('contratos.calcular_retiro');
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

        // Planillas de Pago SS — mapeo visual de coordenadas
        $opf = \App\Http\Controllers\Admin\OperadorPlanillaFormularioController::class;
        Route::get ('configuracion/operadores/{operador}/formulario',     [$opf, 'editor'])  ->name('configuracion.operadores.formulario');
        Route::get ('configuracion/operadores/{operador}/formulario/pdf', [$opf, 'verPdf'])  ->name('configuracion.operadores.formulario.vpdf');
        Route::post('configuracion/operadores/{operador}/formulario',     [$opf, 'guardar']) ->name('configuracion.operadores.formulario.guardar');
        Route::post('configuracion/operadores/{operador}/formulario/pdf', [$opf, 'subirPdf'])->name('configuracion.operadores.formulario.pdf');
        Route::get ('configuracion/operadores/datos-ejemplo',             [$opf, 'obtenerDatosEjemplo'])->name('configuracion.operadores.ejemplo');

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
            Route::get('empresa/{id}/exportar',         [$fc, 'exportarEmpresaExcel'])->name('empresa.exportar');
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
            Route::match(['get', 'post'], 'cuenta-cobro', [$fc, 'cuentaCobroPreview'])->name('cuenta_cobro.preview');
            Route::post('contrato/{contrato}/retiro-pendiente', [$fc, 'guardarRetiroPendiente'])->name('contrato.retiro_pendiente');


            // ── Cobros adicionales por empresa (parafiscales, pendientes, etc.) ──
            Route::get( 'empresa/{empresaId}/cobros-adicionales',        [$fc, 'cobrosAdicionalesIndex'])  ->name('cobros_adicionales.index');
            Route::post('empresa/{empresaId}/cobros-adicionales',        [$fc, 'cobrosAdicionalesStore'])  ->name('cobros_adicionales.store');
            Route::delete('cobros-adicionales/{cobroId}',                [$fc, 'cobrosAdicionalesDestroy'])->name('cobros_adicionales.destroy');

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
            Route::get('/certificado-pdf',      [$pp, 'descargarCertificadoPdf'])->name('certificado_pdf');
            Route::patch('/n-plano',            [$pp, 'actualizarNPlano']) ->name('n_plano.update');
            Route::patch('/mover-masivo',       [$pp, 'moverPlanoMasivo']) ->name('mover_masivo');
            Route::patch('/{id}/mover',         [$pp, 'moverPlano'])       ->name('mover');
            Route::post('/confirmar-pago',      [$pp, 'confirmarPago'])    ->name('confirmar_pago');
            Route::get('/api/razon/{id}',       [$pp, 'apiRazonSocial'])   ->name('api.razon');
            Route::get('/api/resumen',           [$pp, 'apiResumenPlanos']) ->name('api.resumen');

            // Envío de planillas por WhatsApp
            Route::get('/envio-planillas',                     [$pp, 'enviosPlanillaIndex'])->name('envio_planillas');
            Route::get('/envio-planillas/api',                 [$pp, 'enviosPlanillaApi'])->name('envio_planillas.api');
            Route::post('/envio-planillas/enviar',             [$pp, 'enviosPlanillaEnviar'])->name('envio_planillas.enviar');
            Route::post('/envio-planillas/{detalleId}/reenviar', [$pp, 'enviosPlanillaReenviar'])->name('envio_planillas.reenviar');
            Route::get('/envio-planillas/historial',           [$pp, 'enviosPlanillaHistorial'])->name('envio_planillas.historial');
            Route::get('/envio-planillas/{loteId}/detalle',    [$pp, 'enviosPlanillaLoteDetalle'])->name('envio_planillas.lote_detalle');
            Route::post('/envio-planillas/crear-plantilla',    [$pp, 'enviosPlanillaCrearPlantilla'])->name('envio_planillas.crear_plantilla');
        });

        // ── Cobros ───────────────────────────────────────────────────────
        Route::prefix('cobros')->name('cobros.')->group(function () {
            $cb = \App\Http\Controllers\Admin\CobrosController::class;
            // Individuales
            Route::get('/',                          [$cb, 'index'])                  ->name('index');
            Route::get('/exportar',                  [$cb, 'exportar'])               ->name('exportar');
            Route::get('/whatsapp/previsualizar',         [$cb, 'vistaPrevisualizarWhatsApp']) ->name('whatsapp.previsualizar');
            Route::post('/whatsapp/prueba',               [$cb, 'enviarPruebaWhatsApp'])        ->name('whatsapp.prueba');
            Route::post('/whatsapp/enviar-filtro',        [$cb, 'enviarFiltroWhatsApp'])        ->name('whatsapp.enviar_filtro');
            Route::get('/whatsapp/historial',             [$cb, 'historialEnviosWhatsApp'])     ->name('whatsapp.historial');
            Route::get('/whatsapp/{loteId}/reporte',      [$cb, 'reporteLoteWhatsApp'])         ->name('whatsapp.reporte');
            Route::post('/whatsapp/{loteId}/reintentar',  [$cb, 'reintentarLoteWhatsApp'])      ->name('whatsapp.reintentar');
            Route::post('/{contratoId}/llamada',     [$cb, 'registrarLlamada'])       ->name('llamada.store');
            Route::get('/{contratoId}/llamadas',     [$cb, 'historialLlamadas'])      ->name('llamadas');
            // Empresas
            Route::get('/empresas',                  [$cb, 'empresas'])               ->name('empresas');
            Route::get('/empresas/whatsapp/previsualizar', [$cb, 'previsualizarWhatsAppEmpresas'])->name('empresas.whatsapp.previsualizar');
            Route::post('/empresas/whatsapp/enviar',        [$cb, 'enviarWhatsAppEmpresas'])->name('empresas.whatsapp.enviar');
            Route::post('/empresa/{id}/llamada',     [$cb, 'registrarLlamadaEmpresa'])->name('empresa.llamada.store');
            Route::get('/empresa/{id}/llamadas',     [$cb, 'historialEmpresa'])       ->name('empresa.llamadas');
            Route::patch('/empresa/{id}/encargado',  [$cb, 'asignarEncargado'])       ->name('empresa.encargado');
        });

        // ── Informes (admin + superadmin; financiero también para contador) ──
        Route::prefix('informes')->name('informes.')->group(function () {
            $ic = \App\Http\Controllers\Admin\InformeController::class;
            Route::get('/',                       [$ic, 'hub'])                  ->name('hub');
            Route::get('/consolidado-mensual',     [$ic, 'consolidadoMensual'])   ->name('consolidado_mensual');
            Route::get('/consolidado-mensual/detalle', [$ic, 'consolidadoMensualDetalle'])->name('consolidado_mensual_detalle');
            Route::get('/consolidado-mensual/whatsapp-detalle', [$ic, 'consolidadoMensualWhatsapp'])->name('consolidado_mensual_whatsapp');
            Route::get('/brynex-cobros',           [$ic, 'brynexCobros'])         ->name('brynex_cobros');
            Route::get('/brynex-cobros/{cobro}/pdf', [$ic, 'brynexCobroPdf'])       ->name('brynex_cobros.pdf');
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
            Route::post('/',                        [$ac, 'store'])            ->name('store');
            Route::post('/distribuir',              [$ac, 'storeDistribuido']) ->name('distribuir');
            Route::get('/informe',                  [$ac, 'informe'])          ->name('informe');
            Route::get('/{id}/recibo',              [$ac, 'reciboAnticipo'])   ->name('recibo');
            Route::post('/{id}/anular',             [$ac, 'anular'])           ->name('anular');
            Route::post('/{id}/devolver',           [$ac, 'devolver'])         ->name('devolver');
            Route::delete('/{id}',                  [$ac, 'destroy'])          ->name('destroy');
            // APIs para modal facturar
            Route::get('/api/contrato/{id}',        [$ac, 'porContrato'])      ->name('api.contrato');
            Route::get('/api/empresa/{id}',         [$ac, 'porEmpresa'])       ->name('api.empresa');
            Route::get('/api/contratos-empresa/{id}', [$ac, 'contratosEmpresa'])->name('api.contratos_empresa');
            Route::get('/api/cliente/{cedula}',     [$ac, 'porCliente'])       ->name('api.cliente');
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

        // Copias de Seguridad (Backups)
        $bbc = \App\Http\Controllers\BrynexBackupController::class;
        Route::get('/backups',           [$bbc, 'backups'])        ->name('backups');
        Route::get('/backups/descargar', [$bbc, 'descargarBackup'])->name('backups.descargar');
        Route::post('/backups/crear',    [$bbc, 'crearBackupManual'])->name('backups.crear');


        // ── Módulo Consumo & Cobros ───────────────────────────────────────────
        $cx = \App\Http\Controllers\BrynexConsumoController::class;
        Route::get('consumo',                                    [$cx, 'index'])             ->name('consumo.index');
        Route::get('consumo/contabilidad',                       [$cx, 'contabilidad'])      ->name('consumo.contabilidad');
        Route::get('consumo/{aliado}/{mes}/{anio}',              [$cx, 'show'])              ->name('consumo.show');
        Route::post('consumo/{aliado}/{mes}/{anio}/cerrar',      [$cx, 'cerrar'])            ->name('consumo.cerrar');
        Route::get('consumo/{aliado}/modulos',                   [$cx, 'modulosAliado'])     ->name('consumo.modulos');
        Route::put('consumo/{aliado}/modulos',                   [$cx, 'actualizarModulos']) ->name('consumo.modulos.update');
        Route::post('consumo/cobros/{cobro}/pago',               [$cx, 'registrarPago'])     ->name('consumo.pago');
        Route::get('consumo/cobros/{cobro}/pdf',                 [$cx, 'descargarPdf'])      ->name('consumo.pdf');
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
        Route::post('/consignacion/{csId}/no-aparece',          [$cd, 'noApareceConsignacion'])->name('consignacion.no-aparece');
        Route::delete('/consignacion/{csId}/anular-prestamo',   [$cd, 'anularConsignacionPrestamo'])->name('consignacion.anular-prestamo');
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
        Route::get('/{contrato}/historial',        [$ac, 'historial'])->name('historial');
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
        Route::get('chat',                           [$chat, 'index'])                   ->name('chat.index');
        Route::get('chat/{id}',                      [$chat, 'show'])                    ->name('chat.show');
        Route::get('chat/{id}/api-mensajes',         [$chat, 'apiMensajes'])             ->name('chat.api_mensajes');
        Route::get('chat/{id}/api-sidebar',          [$chat, 'apiConversacionSidebar'])  ->name('chat.api_sidebar');
        Route::post('chat/{id}/mensaje',             [$chat, 'enviarMensaje'])           ->name('chat.mensaje');
        Route::patch('chat/{id}/asignar',            [$chat, 'asignar'])                 ->name('chat.asignar');
        Route::patch('chat/{id}/cerrar',             [$chat, 'cerrar'])                  ->name('chat.cerrar');
        Route::patch('chat/{id}/leer',               [$chat, 'marcarLeido'])             ->name('chat.leer');
        Route::get('chat/media/{mensajeId}',         [$chat, 'descargarMedia'])          ->name('chat.media');
        Route::get('api/no-leidos',                  [$chat, 'apiNoLeidos'])             ->name('api.no_leidos');

        // ── Plantillas (admin del aliado) ─────────────────────────────────────
        Route::get('plantillas',                 [$plantilla, 'index'])          ->name('plantillas.index');
        Route::get('plantillas/crear',           [$plantilla, 'create'])         ->name('plantillas.create');
        Route::post('plantillas',                [$plantilla, 'store'])          ->name('plantillas.store');
        Route::get('plantillas/importar',        [$plantilla, 'vistaImportar'])  ->name('plantillas.importar');
        Route::post('plantillas/importar',       [$plantilla, 'procesarImportar'])->name('plantillas.importar.store');
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
        Route::get('configuracion/global',       [$config, 'editGlobal'])        ->name('config.global');
        Route::post('configuracion/global',      [$config, 'updateGlobal'])       ->name('config.global.update');
        Route::get('configuracion/{id}/editar',  [$config, 'edit'])              ->name('config.edit');
        Route::put('configuracion/{id}',         [$config, 'update'])            ->name('config.update');
        Route::get('configuracion/{id}/switch-and-go', [$config, 'switchAndGo'])->name('config.switch_and_go');
        Route::post('configuracion/{id}/copiar-plantilla', [$config, 'copiarPlantillaGlobal'])->name('config.copiar_plantilla');
        Route::post('configuracion/{id}/sincronizar-meta', [$config, 'sincronizarPlantillasMeta'])->name('config.sincronizar_meta');
        Route::post('configuracion/verificar',   [$config, 'verificarWebhook'])  ->name('config.verificar');
    });

    // ============================================
    // MÓDULO FINANZAS PERSONALES (BD separada)
    // ============================================
    Route::prefix('finanzas')->name('finanzas.')->middleware(['finanzas.access'])->group(function () {
        $ns = \App\Http\Controllers\Finanzas\FinanzasDashboardController::class;
        $ent = \App\Http\Controllers\Finanzas\EntradaController::class;
        $gas = \App\Http\Controllers\Finanzas\GastoController::class;
        $pre = \App\Http\Controllers\Finanzas\PrestamoController::class;
        $inv = \App\Http\Controllers\Finanzas\InversionController::class;
        $pat = \App\Http\Controllers\Finanzas\PatrimonioController::class;
        $pro = \App\Http\Controllers\Finanzas\ProyectoController::class;
        $cta = \App\Http\Controllers\Finanzas\CuentaController::class;

        // Dashboard
        Route::get('/', [$ns, 'index'])->name('dashboard');

        // Dashboard API — endpoints AJAX para carga progresiva
        Route::prefix('api')->name('api.')->group(function () use ($ns) {
            Route::get('/resumen',     [$ns, 'apiResumen'])->name('resumen');
            Route::get('/evolucion',   [$ns, 'apiEvolucion'])->name('evolucion');
            Route::get('/consolidado', [$ns, 'apiConsolidado'])->name('consolidado');
            Route::get('/cuentas',     [$ns, 'apiCuentas'])->name('cuentas');
            Route::get('/alertas',     [$ns, 'apiAlertas'])->name('alertas');
        });

        // Cuentas / Bolsillos
        Route::get('/cuentas',                   [$cta, 'index'])->name('cuentas.index');
        Route::post('/cuentas',                  [$cta, 'store'])->name('cuentas.store');
        Route::put('/cuentas/{cuenta}',          [$cta, 'update'])->name('cuentas.update');
        Route::delete('/cuentas/{cuenta}',       [$cta, 'destroy'])->name('cuentas.destroy');
        Route::post('/cuentas/transferir',       [$cta, 'transferir'])->name('cuentas.transferir');
        Route::delete('/cuentas/transferencia/{id}', [$cta, 'eliminarTransferencia'])->name('cuentas.transferencia.destroy');

        // Entradas / Fuentes
        Route::get('/entradas',                  [$ent, 'index'])->name('entradas.index');
        Route::post('/entradas',                 [$ent, 'store'])->name('entradas.store');
        Route::get('/entradas/detalle-esporadico', [$ent, 'getDetalleEsporadico'])->name('entradas.detalle-esporadico');
        Route::put('/entradas/esporadico/{id}',   [$ent, 'updateEsporadico'])->name('entradas.update-esporadico');
        Route::delete('/entradas/esporadico/{id}', [$ent, 'destroyEsporadico'])->name('entradas.destroy-esporadico');
        Route::get('/fuentes',                   [$ent, 'fuentesIndex'])->name('fuentes.index');
        Route::post('/fuentes',                  [$ent, 'fuenteStore'])->name('fuentes.store');
        Route::put('/fuentes/{fuente}',          [$ent, 'fuenteUpdate'])->name('fuentes.update');
        Route::delete('/fuentes/{fuente}',       [$ent, 'fuenteDestroy'])->name('fuentes.destroy');

        // App Líderes / Otras App
        Route::get('/app-lideres',               [\App\Http\Controllers\Finanzas\AppLiderController::class, 'index'])->name('app-lideres.index');
        Route::post('/app-lideres/aliados',      [\App\Http\Controllers\Finanzas\AppLiderController::class, 'storeAliado'])->name('app-lideres.store-aliado');
        Route::post('/app-lideres/update',       [\App\Http\Controllers\Finanzas\AppLiderController::class, 'updateAliado'])->name('app-lideres.update-aliado');
        Route::post('/app-lideres/save-cell',    [\App\Http\Controllers\Finanzas\AppLiderController::class, 'saveCell'])->name('app-lideres.save-cell');
        Route::post('/app-lideres/recibos',      [\App\Http\Controllers\Finanzas\AppLiderController::class, 'registrarRecibo'])->name('app-lideres.registrar-recibo');
        Route::delete('/app-lideres/recibos/{id}', [\App\Http\Controllers\Finanzas\AppLiderController::class, 'deleteRecibo'])->name('app-lideres.delete-recibo');
        Route::get('/app-lideres/recibos/{id}/soporte', [\App\Http\Controllers\Finanzas\AppLiderController::class, 'descargarSoporte'])->name('app-lideres.descargar-soporte');

        // Brynex Aliados
        Route::get('/brynex-aliados',               [\App\Http\Controllers\Finanzas\BrynexAliadoController::class, 'index'])->name('brynex-aliados.index');
        Route::post('/brynex-aliados/aliados',      [\App\Http\Controllers\Finanzas\BrynexAliadoController::class, 'storeAliado'])->name('brynex-aliados.store-aliado');
        Route::post('/brynex-aliados/update',       [\App\Http\Controllers\Finanzas\BrynexAliadoController::class, 'updateAliado'])->name('brynex-aliados.update-aliado');
        Route::post('/brynex-aliados/save-cell',    [\App\Http\Controllers\Finanzas\BrynexAliadoController::class, 'saveCell'])->name('brynex-aliados.save-cell');
        Route::post('/brynex-aliados/recibos',      [\App\Http\Controllers\Finanzas\BrynexAliadoController::class, 'registrarRecibo'])->name('brynex-aliados.registrar-recibo');
        Route::delete('/brynex-aliados/recibos/{id}', [\App\Http\Controllers\Finanzas\BrynexAliadoController::class, 'deleteRecibo'])->name('brynex-aliados.delete-recibo');
        Route::get('/brynex-aliados/recibos/{recibo}/soporte', [\App\Http\Controllers\Finanzas\BrynexAliadoController::class, 'descargarSoporte'])->name('brynex-aliados.descargar-soporte');

        // Gastos / Categorías
        Route::get('/gastos',                    [$gas, 'index'])->name('gastos.index');
        Route::post('/gastos',                   [$gas, 'store'])->name('gastos.store');
        Route::put('/gastos/{gasto}',            [$gas, 'update'])->name('gastos.update');
        Route::delete('/gastos/{gasto}',         [$gas, 'destroy'])->name('gastos.destroy');
        Route::get('/gastos/informe',            [$gas, 'informe'])->name('gastos.informe');
        Route::get('/gastos/{gasto}/soporte',    [$gas, 'descargarSoporte'])->name('gastos.descargar-soporte');
        Route::get('/categorias',                [$gas, 'categoriasIndex'])->name('categorias.index');
        Route::post('/categorias',               [$gas, 'categoriaStore'])->name('categorias.store');
        Route::put('/categorias/{cat}',          [$gas, 'categoriaUpdate'])->name('categorias.update');
        Route::delete('/categorias/{cat}',       [$gas, 'categoriaDestroy'])->name('categorias.destroy');

        // Préstamos
        Route::get('/prestamos',                 [$pre, 'index'])->name('prestamos.index');
        Route::get('/prestamos/crear',           [$pre, 'create'])->name('prestamos.create');
        Route::post('/prestamos',                [$pre, 'store'])->name('prestamos.store');
        Route::get('/prestamos/{prestamo}',      [$pre, 'show'])->name('prestamos.show');
        Route::get('/prestamos/{prestamo}/edit', [$pre, 'edit'])->name('prestamos.edit');
        Route::put('/prestamos/{prestamo}',      [$pre, 'update'])->name('prestamos.update');
        Route::post('/prestamos/{prestamo}/pago',         [$pre, 'registrarPago'])->name('prestamos.pago');
        Route::post('/prestamos/{prestamo}/anexar',       [$pre, 'anexarValor'])->name('prestamos.anexar');
        Route::post('/prestamos/{prestamo}/liquidar',     [$pre, 'liquidarMes'])->name('prestamos.liquidar');
        Route::post('/prestamos/{prestamo}/whatsapp',     [$pre, 'enviarWhatsapp'])->name('prestamos.whatsapp');
        Route::post('/prestamos/{prestamo}/toggle-alertas',[$pre, 'toggleAlertas'])->name('prestamos.toggle-alertas');
        Route::post('/prestamos/{prestamo}/castigar',       [$pre, 'castigar'])->name('prestamos.castigar');
        Route::post('/prestamos/{prestamo}/reactivar',      [$pre, 'reactivar'])->name('prestamos.reactivar');
        Route::get('/cuenta-corriente',          [$pre, 'cuentaCorriente'])->name('prestamos.cuenta-corriente');
        Route::post('/prestamos-movimiento/{movimiento}', [$pre, 'updateMovimiento'])->name('prestamos.movimiento.update');
        Route::delete('/prestamos-movimiento/{movimiento}', [$pre, 'destroyMovimiento'])->name('prestamos.movimiento.destroy');
        Route::get('/prestamos/{prestamo}/soporte', [$pre, 'descargarSoporte'])->name('prestamos.descargar-soporte');
        Route::get('/prestamos-movimiento/{movimiento}/soporte', [$pre, 'descargarSoporteMovimiento'])->name('prestamos.movimiento.descargar-soporte');

        // Inversiones
        Route::get('/inversiones',               [$inv, 'index'])->name('inversiones.index');
        Route::post('/inversiones',              [$inv, 'store'])->name('inversiones.store');
        Route::put('/inversiones/{inv}',         [$inv, 'update'])->name('inversiones.update');
        Route::delete('/inversiones/{inv}',      [$inv, 'destroy'])->name('inversiones.destroy');
        Route::get('/inversiones/precio-usdt',   [$inv, 'precioUsdt'])->name('inversiones.precio-usdt');

        // Patrimonio
        Route::get('/patrimonio',                [$pat, 'index'])->name('patrimonio.index');
        Route::post('/patrimonio',               [$pat, 'store'])->name('patrimonio.store');
        Route::get('/patrimonio/{pat}',          [$pat, 'show'])->name('patrimonio.show');
        Route::put('/patrimonio/{pat}',          [$pat, 'update'])->name('patrimonio.update');
        Route::post('/patrimonio/{pat}/gasto',   [$pat, 'agregarGasto'])->name('patrimonio.gasto');

        // Proyectos
        Route::get('/proyectos',                 [$pro, 'index'])->name('proyectos.index');
        Route::post('/proyectos',                [$pro, 'store'])->name('proyectos.store');
        Route::get('/proyectos/{proyecto}',      [$pro, 'show'])->name('proyectos.show');
        Route::put('/proyectos/{proyecto}',      [$pro, 'update'])->name('proyectos.update');
        Route::post('/proyectos/{proyecto}/movimiento', [$pro, 'agregarMovimiento'])->name('proyectos.movimiento');
        Route::delete('/proyectos-movimiento/{movimiento}', [$pro, 'eliminarMovimiento'])->name('proyectos.movimiento.destroy');
        Route::put('/proyectos-movimiento/{movimiento}', [$pro, 'actualizarMovimiento'])->name('proyectos.movimiento.update');
    });
});

