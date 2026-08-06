<?php

namespace App\Services\Exportacion;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Qué se entrega cuando un aliado se va, y con qué forma.
 *
 * Tres reglas gobiernan este archivo:
 *
 *  1. **Informes, no tablas.** Cada archivo es un concepto de negocio aplanado,
 *     no el volcado de una tabla. `Afiliaciones` trae persona, empresa, EPS,
 *     ARL y salario en la misma fila. El aliado se lleva su información; no se
 *     lleva el modelo relacional con el que la operamos.
 *
 *  2. **Nombres, nunca ids ni códigos internos.** Todo `*_id` se resuelve al
 *     nombre de la entidad. El único número que cruza archivos es el
 *     consecutivo de la entrega — ver ContextoExportacion.
 *
 *  3. **Cabeceras en español, con espacios y tildes.** `Primer Apellido`, no
 *     `primer_apellido`. El nombre de la columna es la mitad del esquema.
 *
 * Lo que NO sale, y por qué:
 *  - Catálogos del sistema (eps, arls, pensiones, cajas, ciudades, planes,
 *    modalidades): las entidades salen por nombre dentro de cada informe.
 *  - `planos`: son filas derivadas de contratos + facturas y son nuestra forma
 *    de armar la PILA. Si el aliado necesita el histórico de planillas se le
 *    entrega el TXT oficial, que es formato público del gobierno.
 *  - `ruaf_consultas`, `adres_chequeos`: caché de integraciones externas.
 *  - `operadores_credenciales`, `whatsapp_*`, `ia_*`, `brynex_*`: credenciales,
 *    integraciones y cobros nuestros.
 *  - Columnas de proceso interno: `id_legacy`, `np`, `n_plano`, `token_subida`,
 *    `fe_marcada*`, `retiro_pendiente_*`, `dist_*`, rutas de archivos.
 */
class InformesAliado
{
    /** Nombre del usuario del sistema, resuelto por id. */
    private const USUARIO = 'users';

    /**
     * @return array<int, array{
     *     archivo: string,
     *     titulo: string,
     *     descripcion: string,
     *     columnas: array<string, string|\Closure>,
     *     fuentes: array<int, array{builder: \Closure, id: string}>
     * }>
     */
    public function todos(): array
    {
        return [
            $this->personas(),
            $this->beneficiarios(),
            $this->empresasClientes(),
            $this->razonesSociales(),
            $this->afiliaciones(),
            $this->facturacion(),
            $this->pagosRecibidos(),
            $this->gestionesCobro(),
            $this->incapacidades(),
            $this->gestionesIncapacidades(),
            $this->tramites(),
            $this->movimientosTramites(),
            $this->tareas(),
            $this->gestionesTareas(),
            $this->prospectos(),
            $this->usuariosYAsesores(),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 1. Personas
    // ══════════════════════════════════════════════════════════════════════

    private function personas(): array
    {
        return [
            'archivo' => '01_Personas',
            'titulo' => 'Personas',
            'descripcion' => 'Datos de cada persona registrada: identificación, contacto, ubicación y entidades de seguridad social actuales.',
            'columnas' => [
                'Tipo de Documento' => 'tipo_doc',
                'Documento' => 'cedula',
                'Primer Nombre' => 'primer_nombre',
                'Segundo Nombre' => 'segundo_nombre',
                'Primer Apellido' => 'primer_apellido',
                'Segundo Apellido' => 'segundo_apellido',
                'Nombre Completo' => fn ($f) => $this->nombre($f),
                'Género' => 'genero',
                'Fecha de Nacimiento' => fn ($f) => $this->fecha($f->fecha_nacimiento),
                'Fecha de Expedición del Documento' => fn ($f) => $this->fecha($f->fecha_expedicion),
                'RH' => 'rh',
                'Grupo Sisbén' => 'sisben',
                'Teléfono' => 'telefono',
                'Celular' => 'celular',
                'Correo' => 'correo',
                'Departamento' => 'departamento',
                'Municipio' => 'municipio',
                'Dirección de Vivienda' => 'direccion_vivienda',
                'Dirección de Cobro' => 'direccion_cobro',
                'Barrio' => 'barrio',
                'EPS' => 'eps',
                'Fondo de Pensión' => 'pension',
                'IPS' => 'ips',
                'Urgencias' => 'urgencias',
                'Ocupación' => 'ocupacion',
                'Referido Por' => 'referido',
                'Aplica IVA' => fn ($f) => $this->si($f->iva),
                'Saldo en Deuda' => fn ($f) => $this->dinero($f->deuda),
                'Fecha Probable de Pago' => fn ($f) => $this->fecha($f->fecha_probable_pago),
                'Modo Probable de Pago' => 'modo_probable_pago',
                'Observación' => 'observacion',
                'Observación de Llamada' => 'observacion_llamada',
                'Fecha de Registro' => fn ($f) => $this->fechaHora($f->created_at),
            ],
            'fuentes' => [[
                'id' => 'clientes.id',
                'builder' => fn (int $a) => DB::table('clientes')
                    ->leftJoin('departamentos', 'departamentos.id', '=', 'clientes.departamento_id')
                    ->leftJoin('ciudades', 'ciudades.id', '=', 'clientes.municipio_id')
                    ->leftJoin('eps', 'eps.id', '=', 'clientes.eps_id')
                    ->leftJoin('pensiones', 'pensiones.id', '=', 'clientes.pension_id')
                    ->where('clientes.aliado_id', $a)
                    ->select([
                        'clientes.id as id',
                        'clientes.tipo_doc', 'clientes.cedula',
                        'clientes.primer_nombre', 'clientes.segundo_nombre',
                        'clientes.primer_apellido', 'clientes.segundo_apellido',
                        'clientes.genero', 'clientes.sisben', 'clientes.fecha_nacimiento',
                        'clientes.fecha_expedicion', 'clientes.rh', 'clientes.telefono',
                        'clientes.celular', 'clientes.correo', 'clientes.direccion_vivienda',
                        'clientes.direccion_cobro', 'clientes.barrio', 'clientes.ips',
                        'clientes.urgencias', 'clientes.iva', 'clientes.ocupacion',
                        'clientes.referido', 'clientes.observacion', 'clientes.observacion_llamada',
                        'clientes.deuda', 'clientes.fecha_probable_pago',
                        'clientes.modo_probable_pago', 'clientes.created_at',
                        'departamentos.nombre as departamento',
                        'ciudades.nombre as municipio',
                        'eps.nombre as eps',
                        'pensiones.razon_social as pension',
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 2. Beneficiarios
    // ══════════════════════════════════════════════════════════════════════

    private function beneficiarios(): array
    {
        return [
            'archivo' => '02_Beneficiarios',
            'titulo' => 'Beneficiarios',
            'descripcion' => 'Beneficiarios reportados por cada titular.',
            'columnas' => [
                'Documento del Titular' => 'cc_cliente',
                'Nombre del Titular' => fn ($f) => $this->limpiar($f->titular),
                'Tipo de Documento' => 'tipo_doc',
                'Documento del Beneficiario' => 'n_documento',
                'Nombres del Beneficiario' => 'nombres',
                'Parentesco' => 'parentesco',
                'Fecha de Nacimiento' => fn ($f) => $this->fecha($f->fecha_nacimiento),
                'Fecha de Expedición del Documento' => fn ($f) => $this->fecha($f->fecha_expedicion),
                'Fecha de Ingreso' => fn ($f) => $this->fecha($f->fecha_ingreso),
                'Observación' => 'observacion',
                'Fecha de Registro' => fn ($f) => $this->fechaHora($f->created_at),
            ],
            'fuentes' => [[
                'id' => 'beneficiarios.id',
                'builder' => fn (int $a) => DB::table('beneficiarios')
                    ->leftJoin('clientes', function ($j) {
                        // (cedula, aliado_id) es índice único en clientes: el
                        // join no multiplica filas.
                        $j->on('clientes.cedula', '=', 'beneficiarios.cc_cliente')
                            ->on('clientes.aliado_id', '=', 'beneficiarios.aliado_id');
                    })
                    ->where('beneficiarios.aliado_id', $a)
                    ->select([
                        'beneficiarios.id as id',
                        'beneficiarios.cc_cliente', 'beneficiarios.tipo_doc',
                        'beneficiarios.n_documento', 'beneficiarios.nombres',
                        'beneficiarios.parentesco', 'beneficiarios.fecha_nacimiento',
                        'beneficiarios.fecha_expedicion', 'beneficiarios.fecha_ingreso',
                        'beneficiarios.observacion', 'beneficiarios.created_at',
                        DB::raw($this->sqlNombre('clientes').' as titular'),
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 3. Empresas cliente
    // ══════════════════════════════════════════════════════════════════════

    private function empresasClientes(): array
    {
        return [
            'archivo' => '03_Empresas_Clientes',
            'titulo' => 'Empresas cliente',
            'descripcion' => 'Empresas a las que se les factura el servicio.',
            'columnas' => [
                'NIT' => 'nit',
                'Empresa' => 'empresa',
                'Contacto' => 'contacto',
                'Teléfono' => 'telefono',
                'Celular' => 'celular',
                'Correo' => 'correo',
                'Dirección' => 'direccion',
                'Actividad Económica' => 'actividad_economica',
                'Cliente Desde' => fn ($f) => $this->fecha($f->cliente_de),
                'Tipo de Facturación' => 'tipo_facturacion',
                'Aplica IVA' => fn ($f) => $this->si($f->iva),
                'Asesor' => 'asesor',
                'Encargado' => 'encargado',
                'Observación' => 'observacion',
                'Fecha de Registro' => fn ($f) => $this->fechaHora($f->created_at),
            ],
            'fuentes' => [[
                'id' => 'empresas.id',
                'builder' => fn (int $a) => DB::table('empresas')
                    ->leftJoin('asesores', 'asesores.id', '=', 'empresas.asesor_id')
                    ->leftJoin(self::USUARIO.' as u_enc', 'u_enc.id', '=', 'empresas.encargado_id')
                    ->where('empresas.aliado_id', $a)
                    ->select([
                        'empresas.id as id',
                        'empresas.nit', 'empresas.empresa', 'empresas.contacto',
                        'empresas.telefono', 'empresas.celular', 'empresas.direccion',
                        'empresas.correo', 'empresas.actividad_economica',
                        'empresas.cliente_de', 'empresas.tipo_facturacion', 'empresas.iva',
                        'empresas.observacion', 'empresas.created_at',
                        'asesores.nombre as asesor',
                        'u_enc.nombre as encargado',
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 4. Razones sociales
    // ══════════════════════════════════════════════════════════════════════

    private function razonesSociales(): array
    {
        return [
            'archivo' => '04_Razones_Sociales',
            'titulo' => 'Razones sociales',
            'descripcion' => 'Razones sociales bajo las que se afilia y se paga la planilla.',
            'columnas' => [
                'NIT' => 'nit',
                'Dígito de Verificación' => 'dv',
                'Razón Social' => 'razon_social',
                'Estado' => 'estado',
                'Plan' => 'plan',
                'Es Independiente' => fn ($f) => $this->si($f->es_independiente),
                'Dirección' => 'direccion',
                'Teléfonos' => 'telefonos',
                'Correos' => 'correos',
                'Actividad Económica' => 'actividad_economica',
                'Objeto Social' => 'objeto_social',
                'Salario Mínimo' => fn ($f) => $this->dinero($f->salario_minimo),
                'ARL' => 'arl',
                'Caja de Compensación' => 'caja',
                'Representante Legal' => 'nombre_rep',
                'Documento del Representante' => 'cedula_rep',
                'Fecha de Constitución' => fn ($f) => $this->fecha($f->fecha_constitucion),
                'Fecha Límite de Pago' => fn ($f) => $this->fecha($f->fecha_limite_pago),
                'Día Hábil de Pago' => 'dia_habil',
                'Forma de Presentación' => 'forma_presentacion',
                'Sucursal' => 'nombre_sucursal',
                'Encargado' => 'encargado',
                'Observación' => 'observacion',
            ],
            'fuentes' => [[
                'id' => 'razones_sociales.id',
                'builder' => fn (int $a) => DB::table('razones_sociales')
                    ->leftJoin('arls', 'arls.nit', '=', 'razones_sociales.arl_nit')
                    ->leftJoin('cajas', 'cajas.nit', '=', 'razones_sociales.caja_nit')
                    ->leftJoin(self::USUARIO.' as u_enc', 'u_enc.id', '=', 'razones_sociales.encargado_id')
                    ->where('razones_sociales.aliado_id', $a)
                    ->select([
                        'razones_sociales.id as id',
                        'razones_sociales.nit', 'razones_sociales.dv',
                        'razones_sociales.razon_social', 'razones_sociales.estado',
                        'razones_sociales.plan', 'razones_sociales.es_independiente',
                        'razones_sociales.direccion', 'razones_sociales.telefonos',
                        'razones_sociales.correos', 'razones_sociales.actividad_economica',
                        'razones_sociales.objeto_social', 'razones_sociales.salario_minimo',
                        'razones_sociales.nombre_rep', 'razones_sociales.cedula_rep',
                        'razones_sociales.fecha_constitucion', 'razones_sociales.fecha_limite_pago',
                        'razones_sociales.dia_habil', 'razones_sociales.forma_presentacion',
                        'razones_sociales.nombre_sucursal', 'razones_sociales.observacion',
                        DB::raw('COALESCE(arls.nombre_arl, arls.razon_social) as arl'),
                        'cajas.nombre as caja',
                        'u_enc.nombre as encargado',
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 5. Afiliaciones (contratos aplanados)
    // ══════════════════════════════════════════════════════════════════════

    private function afiliaciones(): array
    {
        return [
            'archivo' => '05_Afiliaciones',
            'titulo' => 'Afiliaciones',
            'descripcion' => 'Una fila por afiliación: la persona, su empresa, sus entidades, su salario y las fechas del vínculo.',
            'columnas' => [
                'Documento' => 'cedula',
                'Tipo de Documento' => 'tipo_doc',
                'Nombre Completo' => fn ($f) => $this->nombre($f),
                'Celular' => 'celular',
                'Correo' => 'correo',
                'Razón Social' => 'razon_social',
                'NIT Razón Social' => 'nit_razon_social',
                'Estado' => 'estado',
                'Plan' => 'plan',
                'Modalidad' => 'modalidad',
                'Cargo' => 'cargo',
                'Actividad Económica' => 'actividad_economica',
                'Código CIIU' => 'codigo_ciiu',
                'Salario' => fn ($f) => $this->dinero($f->salario),
                'IBC' => fn ($f) => $this->dinero($f->ibc),
                'EPS' => 'eps',
                'Fondo de Pensión' => 'pension',
                'ARL' => 'arl',
                'Nivel de Riesgo ARL' => 'n_arl',
                'Cobertura ARL' => 'arl_modo',
                'NIT Cotizante ARL' => 'arl_nit_cotizante',
                'Caja de Compensación' => 'caja',
                'Porcentaje Caja' => 'porcentaje_caja',
                'Fecha de Ingreso' => fn ($f) => $this->fecha($f->fecha_ingreso),
                'Fecha de Retiro' => fn ($f) => $this->fecha($f->fecha_retiro),
                'Fecha de Vigencia ARL' => fn ($f) => $this->fecha($f->fecha_arl),
                'Motivo de Afiliación' => 'motivo_afiliacion',
                'Motivo de Retiro' => 'motivo_retiro',
                'Valor Administración' => fn ($f) => $this->dinero($f->administracion),
                'Administración del Asesor' => fn ($f) => $this->dinero($f->admon_asesor),
                'Costo de Afiliación' => fn ($f) => $this->dinero($f->costo_afiliacion),
                'Seguro' => fn ($f) => $this->dinero($f->seguro),
                'Asesor' => 'asesor',
                'Encargado' => 'encargado',
                'Envío de Planilla' => 'envio_planilla',
                'Fecha Probable de Pago' => fn ($f) => $this->fecha($f->fecha_probable_pago),
                'Modo Probable de Pago' => 'modo_probable_pago',
                'Observación' => 'observacion',
                'Observación de Afiliación' => 'observacion_afiliacion',
                'Observación de Llamada' => 'observacion_llamada',
                'Fecha de Registro' => fn ($f) => $this->fechaHora($f->created_at),
            ],
            'fuentes' => [[
                'id' => 'contratos.id',
                'builder' => fn (int $a) => DB::table('contratos')
                    ->leftJoin('clientes', function ($j) {
                        $j->on('clientes.cedula', '=', 'contratos.cedula')
                            ->on('clientes.aliado_id', '=', 'contratos.aliado_id');
                    })
                    ->leftJoin('razones_sociales', 'razones_sociales.id', '=', 'contratos.razon_social_id')
                    ->leftJoin('planes_contrato', 'planes_contrato.id', '=', 'contratos.plan_id')
                    ->leftJoin('tipo_modalidad', 'tipo_modalidad.id', '=', 'contratos.tipo_modalidad_id')
                    ->leftJoin('eps', 'eps.id', '=', 'contratos.eps_id')
                    ->leftJoin('pensiones', 'pensiones.id', '=', 'contratos.pension_id')
                    ->leftJoin('arls', 'arls.id', '=', 'contratos.arl_id')
                    ->leftJoin('cajas', 'cajas.id', '=', 'contratos.caja_id')
                    ->leftJoin('actividades_economicas', 'actividades_economicas.id', '=', 'contratos.actividad_economica_id')
                    ->leftJoin('motivos_afiliacion', 'motivos_afiliacion.id', '=', 'contratos.motivo_afiliacion_id')
                    ->leftJoin('motivos_retiro', 'motivos_retiro.id', '=', 'contratos.motivo_retiro_id')
                    ->leftJoin('asesores', 'asesores.id', '=', 'contratos.asesor_id')
                    ->leftJoin(self::USUARIO.' as u_enc', 'u_enc.id', '=', 'contratos.encargado_id')
                    ->where('contratos.aliado_id', $a)
                    ->select([
                        'contratos.id as id',
                        'contratos.cedula', 'contratos.estado', 'contratos.cargo',
                        'contratos.salario', 'contratos.ibc', 'contratos.n_arl',
                        'contratos.arl_modo', 'contratos.arl_nit_cotizante',
                        'contratos.porcentaje_caja', 'contratos.fecha_ingreso',
                        'contratos.fecha_retiro', 'contratos.fecha_arl',
                        'contratos.administracion', 'contratos.admon_asesor',
                        'contratos.costo_afiliacion', 'contratos.seguro',
                        'contratos.envio_planilla', 'contratos.fecha_probable_pago',
                        'contratos.modo_probable_pago', 'contratos.observacion',
                        'contratos.observacion_afiliacion', 'contratos.observacion_llamada',
                        'contratos.created_at',
                        'clientes.tipo_doc', 'clientes.celular', 'clientes.correo',
                        'clientes.primer_nombre', 'clientes.segundo_nombre',
                        'clientes.primer_apellido', 'clientes.segundo_apellido',
                        'razones_sociales.razon_social',
                        'razones_sociales.nit as nit_razon_social',
                        'planes_contrato.nombre as plan',
                        'tipo_modalidad.tipo_modalidad as modalidad',
                        'eps.nombre as eps',
                        'pensiones.razon_social as pension',
                        DB::raw('COALESCE(arls.nombre_arl, arls.razon_social) as arl'),
                        'cajas.nombre as caja',
                        'actividades_economicas.nombre as actividad_economica',
                        'actividades_economicas.codigo_ciiu',
                        'motivos_afiliacion.nombre as motivo_afiliacion',
                        'motivos_retiro.nombre as motivo_retiro',
                        'asesores.nombre as asesor',
                        'u_enc.nombre as encargado',
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 6. Facturación
    // ══════════════════════════════════════════════════════════════════════

    private function facturacion(): array
    {
        return [
            'archivo' => '06_Facturacion',
            'titulo' => 'Facturación',
            'descripcion' => 'Todas las facturas del periodo histórico, con el desglose de cada concepto. Las anuladas van incluidas con Estado = ANULADA.',
            'columnas' => [
                'Consecutivo' => fn ($f, $ctx) => $ctx->asignar('factura', $f->id),
                'Número de Factura' => 'numero_factura',
                'Tipo' => 'tipo',
                'Mes' => 'mes',
                'Año' => 'anio',
                'Documento' => 'cedula',
                'Nombre Completo' => fn ($f) => $this->nombre($f),
                'Razón Social' => 'razon_social',
                'Empresa' => 'empresa',
                'Estado' => fn ($f) => $f->deleted_at ? 'ANULADA' : $f->estado,
                'Fecha de Pago' => fn ($f) => $this->fecha($f->fecha_pago),
                'Forma de Pago' => 'forma_pago',
                'Días Cotizados' => 'dias_cotizados',
                'Valor EPS' => fn ($f) => $this->dinero($f->v_eps),
                'Valor ARL' => fn ($f) => $this->dinero($f->v_arl),
                'Valor Pensión' => fn ($f) => $this->dinero($f->v_afp),
                'Valor Caja' => fn ($f) => $this->dinero($f->v_caja),
                'Total Seguridad Social' => fn ($f) => $this->dinero($f->total_ss),
                'Administración' => fn ($f) => $this->dinero($f->admon),
                'Administración del Asesor' => fn ($f) => $this->dinero($f->admin_asesor),
                'Seguro' => fn ($f) => $this->dinero($f->seguro),
                'Afiliación' => fn ($f) => $this->dinero($f->afiliacion),
                'Mensajería' => fn ($f) => $this->dinero($f->mensajeria),
                'Otros' => fn ($f) => $this->dinero($f->otros),
                'IVA' => fn ($f) => $this->dinero($f->iva),
                'Mora' => fn ($f) => $this->dinero($f->mora),
                'Anticipo Aplicado' => fn ($f) => $this->dinero($f->anticipo_aplicado),
                'Total' => fn ($f) => $this->dinero($f->total),
                'Valor Consignado' => fn ($f) => $this->dinero($f->valor_consignado),
                'Valor en Efectivo' => fn ($f) => $this->dinero($f->valor_efectivo),
                'Saldo al Próximo Periodo' => fn ($f) => $this->dinero($f->saldo_proximo),
                'Es Préstamo' => fn ($f) => $this->si($f->es_prestamo),
                'Valor del Préstamo' => fn ($f) => $this->dinero($f->valor_prestamo),
                'Cobro de Retiro' => fn ($f) => $this->dinero($f->retiro),
                'Comisión del Asesor' => fn ($f) => $this->dinero($f->c_asesor),
                'Utilidad' => fn ($f) => $this->dinero($f->c_utilidad),
                'Descripción del Trámite' => 'descripcion_tramite',
                'Facturado Por' => 'facturado_por',
                'Anulada Por' => 'anulada_por',
                'Motivo de Anulación' => 'motivo_anulacion',
                'Observación' => 'observacion',
                'Observación de la Factura' => 'obs_factura',
                'Fecha de Registro' => fn ($f) => $this->fechaHora($f->created_at),
            ],
            'fuentes' => [[
                'id' => 'facturas.id',
                'builder' => fn (int $a) => DB::table('facturas')
                    ->leftJoin('clientes', function ($j) {
                        $j->on('clientes.cedula', '=', 'facturas.cedula')
                            ->on('clientes.aliado_id', '=', 'facturas.aliado_id');
                    })
                    ->leftJoin('razones_sociales', 'razones_sociales.id', '=', 'facturas.razon_social_id')
                    ->leftJoin('empresas', 'empresas.id', '=', 'facturas.empresa_id')
                    ->leftJoin(self::USUARIO.' as u_fac', 'u_fac.id', '=', 'facturas.usuario_id')
                    ->leftJoin(self::USUARIO.' as u_anu', 'u_anu.id', '=', 'facturas.anulado_por')
                    ->where('facturas.aliado_id', $a)
                    ->select([
                        'facturas.id as id',
                        'facturas.numero_factura', 'facturas.tipo', 'facturas.mes',
                        'facturas.anio', 'facturas.cedula', 'facturas.estado',
                        'facturas.fecha_pago', 'facturas.forma_pago', 'facturas.dias_cotizados',
                        'facturas.v_eps', 'facturas.v_arl', 'facturas.v_afp', 'facturas.v_caja',
                        'facturas.total_ss', 'facturas.admon', 'facturas.admin_asesor',
                        'facturas.seguro', 'facturas.afiliacion', 'facturas.mensajeria',
                        'facturas.otros', 'facturas.iva', 'facturas.mora',
                        'facturas.anticipo_aplicado', 'facturas.total',
                        'facturas.valor_consignado', 'facturas.valor_efectivo',
                        'facturas.saldo_proximo', 'facturas.es_prestamo',
                        'facturas.valor_prestamo', 'facturas.retiro', 'facturas.c_asesor',
                        'facturas.c_utilidad', 'facturas.descripcion_tramite',
                        'facturas.motivo_anulacion', 'facturas.observacion',
                        'facturas.obs_factura', 'facturas.deleted_at', 'facturas.created_at',
                        'clientes.primer_nombre', 'clientes.segundo_nombre',
                        'clientes.primer_apellido', 'clientes.segundo_apellido',
                        'razones_sociales.razon_social',
                        'empresas.empresa',
                        'u_fac.nombre as facturado_por',
                        'u_anu.nombre as anulada_por',
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 7. Pagos recibidos (consignaciones + abonos + anticipos)
    // ══════════════════════════════════════════════════════════════════════

    private function pagosRecibidos(): array
    {
        $columnas = [
            'Consecutivo Factura' => fn ($f, $ctx) => $ctx->consecutivo('factura', $f->factura_id),
            'Concepto' => 'concepto',
            'Detalle del Concepto' => 'detalle',
            'Fecha' => fn ($f) => $this->fecha($f->fecha),
            'Documento' => 'cedula',
            'Nombre Completo' => fn ($f) => $this->limpiar($f->nombre_completo),
            'Valor' => fn ($f) => $this->dinero($f->valor),
            'Valor Aplicado' => fn ($f) => $this->dinero($f->valor_aplicado),
            'Valor en Efectivo' => fn ($f) => $this->dinero($f->valor_efectivo),
            'Valor Consignado' => fn ($f) => $this->dinero($f->valor_consignado),
            'Forma de Pago' => 'forma_pago',
            'Banco' => 'banco',
            'Referencia' => 'referencia',
            'Estado' => 'estado',
            'Registrado Por' => 'registrado_por',
            'Validado Por' => 'validado_por',
            'Fecha de Validación' => fn ($f) => $this->fechaHora($f->fecha_validacion),
            'Observación' => 'observacion',
        ];

        return [
            'archivo' => '07_Pagos_Recibidos',
            'titulo' => 'Pagos recibidos',
            'descripcion' => 'Consignaciones, abonos y anticipos. La columna «Consecutivo Factura» apunta al «Consecutivo» del archivo de Facturación.',
            'columnas' => $columnas,
            'fuentes' => [
                // Consignaciones
                [
                    'id' => 'consignaciones.id',
                    'builder' => fn (int $a) => DB::table('consignaciones')
                        ->leftJoin('facturas', 'facturas.id', '=', 'consignaciones.factura_id')
                        ->leftJoin('clientes', function ($j) {
                            $j->on('clientes.cedula', '=', 'facturas.cedula')
                                ->on('clientes.aliado_id', '=', 'facturas.aliado_id');
                        })
                        ->leftJoin('banco_cuentas', 'banco_cuentas.id', '=', 'consignaciones.banco_cuenta_id')
                        ->leftJoin(self::USUARIO.' as u_reg', 'u_reg.id', '=', 'consignaciones.usuario_id')
                        ->leftJoin(self::USUARIO.' as u_val', 'u_val.id', '=', 'consignaciones.usuario_validador_id')
                        ->where('consignaciones.aliado_id', $a)
                        ->whereNull('consignaciones.deleted_at')
                        ->select([
                            'consignaciones.id as id',
                            'consignaciones.factura_id',
                            'consignaciones.fecha', 'consignaciones.valor',
                            'consignaciones.referencia', 'consignaciones.observacion',
                            'consignaciones.fecha_validacion',
                            'facturas.cedula',
                            'u_reg.nombre as registrado_por',
                            'u_val.nombre as validado_por',
                            DB::raw($this->sqlNombre('clientes').' as nombre_completo'),
                            DB::raw("'Consignación' as concepto"),
                            DB::raw('consignaciones.tipo as detalle'),
                            DB::raw('NULL as valor_aplicado'),
                            DB::raw('NULL as valor_efectivo'),
                            DB::raw('NULL as valor_consignado'),
                            DB::raw("'' as forma_pago"),
                            DB::raw("CASE WHEN consignaciones.confirmado = 1 THEN 'Confirmada'
                                          WHEN consignaciones.no_aparece = 1 THEN 'No aparece en el banco'
                                          ELSE 'Pendiente' END as estado"),
                            DB::raw("LTRIM(RTRIM(CONCAT(banco_cuentas.banco, ' - ', banco_cuentas.nombre))) as banco"),
                        ]),
                ],
                // Abonos: no tienen aliado_id — se filtran por la factura padre.
                [
                    'id' => 'abonos.id',
                    'builder' => fn (int $a) => DB::table('abonos')
                        ->join('facturas', 'facturas.id', '=', 'abonos.factura_id')
                        ->leftJoin('clientes', function ($j) {
                            $j->on('clientes.cedula', '=', 'facturas.cedula')
                                ->on('clientes.aliado_id', '=', 'facturas.aliado_id');
                        })
                        ->leftJoin('banco_cuentas', 'banco_cuentas.id', '=', 'abonos.banco_cuenta_id')
                        ->leftJoin(self::USUARIO.' as u_reg', 'u_reg.id', '=', 'abonos.usuario_id')
                        ->where('facturas.aliado_id', $a)
                        ->select([
                            'abonos.id as id',
                            'abonos.factura_id', 'abonos.fecha', 'abonos.valor',
                            'abonos.valor_efectivo', 'abonos.valor_consignado',
                            'abonos.forma_pago', 'abonos.observacion',
                            'facturas.cedula',
                            'u_reg.nombre as registrado_por',
                            DB::raw($this->sqlNombre('clientes').' as nombre_completo'),
                            DB::raw("'Abono' as concepto"),
                            DB::raw("'' as detalle"),
                            DB::raw('NULL as valor_aplicado'),
                            DB::raw("'' as referencia"),
                            DB::raw("'' as estado"),
                            DB::raw('NULL as validado_por'),
                            DB::raw('NULL as fecha_validacion'),
                            DB::raw("LTRIM(RTRIM(CONCAT(banco_cuentas.banco, ' - ', banco_cuentas.nombre))) as banco"),
                        ]),
                ],
                // Anticipos
                [
                    'id' => 'anticipos.id',
                    'builder' => fn (int $a) => DB::table('anticipos')
                        ->leftJoin('clientes', function ($j) {
                            $j->on('clientes.cedula', '=', 'anticipos.cedula')
                                ->on('clientes.aliado_id', '=', 'anticipos.aliado_id');
                        })
                        ->leftJoin('banco_cuentas', 'banco_cuentas.id', '=', 'anticipos.banco_cuenta_id')
                        ->leftJoin(self::USUARIO.' as u_reg', 'u_reg.id', '=', 'anticipos.usuario_id')
                        ->where('anticipos.aliado_id', $a)
                        ->whereNull('anticipos.deleted_at')
                        ->select([
                            'anticipos.id as id',
                            'anticipos.factura_id', 'anticipos.cedula',
                            'anticipos.valor', 'anticipos.valor_aplicado',
                            'anticipos.forma_pago', 'anticipos.referencia',
                            'anticipos.observacion', 'anticipos.estado',
                            'anticipos.fecha_pago as fecha',
                            'u_reg.nombre as registrado_por',
                            DB::raw($this->sqlNombre('clientes').' as nombre_completo'),
                            DB::raw("'Anticipo' as concepto"),
                            DB::raw('anticipos.origen as detalle'),
                            DB::raw('NULL as valor_efectivo'),
                            DB::raw('NULL as valor_consignado'),
                            DB::raw('NULL as validado_por'),
                            DB::raw('NULL as fecha_validacion'),
                            DB::raw("LTRIM(RTRIM(CONCAT(banco_cuentas.banco, ' - ', banco_cuentas.nombre))) as banco"),
                        ]),
                ],
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 8. Gestiones de cobro
    // ══════════════════════════════════════════════════════════════════════

    private function gestionesCobro(): array
    {
        return [
            'archivo' => '08_Gestiones_de_Cobro',
            'titulo' => 'Gestiones de cobro',
            'descripcion' => 'Bitácora de llamadas y gestiones de cartera.',
            'columnas' => [
                'Consecutivo Factura' => fn ($f, $ctx) => $ctx->consecutivo('factura', $f->factura_id),
                'Fecha de la Gestión' => fn ($f) => $this->fechaHora($f->fecha_llamada),
                'Documento' => 'cedula',
                'Nombre Completo' => fn ($f) => $this->limpiar($f->nombre_completo),
                'Empresa' => 'empresa',
                'Tipo' => 'tipo',
                'Resultado' => 'resultado',
                'Observación' => 'observacion',
                'Registrado Por' => 'registrado_por',
            ],
            'fuentes' => [[
                'id' => 'bitacora_cobros.id',
                'builder' => fn (int $a) => DB::table('bitacora_cobros')
                    ->leftJoin('contratos', 'contratos.id', '=', 'bitacora_cobros.contrato_id')
                    ->leftJoin('clientes', function ($j) {
                        $j->on('clientes.cedula', '=', 'contratos.cedula')
                            ->on('clientes.aliado_id', '=', 'contratos.aliado_id');
                    })
                    ->leftJoin('empresas', 'empresas.id', '=', 'bitacora_cobros.empresa_id')
                    ->leftJoin(self::USUARIO.' as u_reg', 'u_reg.id', '=', 'bitacora_cobros.usuario_id')
                    ->where('bitacora_cobros.aliado_id', $a)
                    ->select([
                        'bitacora_cobros.id as id',
                        'bitacora_cobros.factura_id', 'bitacora_cobros.fecha_llamada',
                        'bitacora_cobros.resultado', 'bitacora_cobros.observacion',
                        'bitacora_cobros.tipo',
                        'contratos.cedula',
                        'empresas.empresa',
                        'u_reg.nombre as registrado_por',
                        DB::raw($this->sqlNombre('clientes').' as nombre_completo'),
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 9. Incapacidades
    // ══════════════════════════════════════════════════════════════════════

    private function incapacidades(): array
    {
        return [
            'archivo' => '09_Incapacidades',
            'titulo' => 'Incapacidades',
            'descripcion' => 'Incapacidades médicas registradas, con su radicación, su estado de pago y su encadenamiento de prórrogas.',
            'columnas' => [
                'Consecutivo' => fn ($f, $ctx) => $ctx->asignar('incapacidad', $f->id),
                'Consecutivo de la Incapacidad Padre' => fn ($f, $ctx) => $ctx->consecutivo('incapacidad', $f->incapacidad_padre_id),
                'Documento' => 'cedula_usuario',
                'Nombre Completo' => fn ($f) => $this->limpiar($f->nombre_completo),
                'Razón Social' => fn ($f) => $f->razon_social ?: $f->razon_social_nombre,
                'Tipo de Incapacidad' => 'tipo_incapacidad',
                'Es Prórroga' => fn ($f) => $this->si($f->prorroga),
                'Número de Prórroga' => 'numero_proroga',
                'Días de Incapacidad' => 'dias_incapacidad',
                'Fecha de Inicio' => fn ($f) => $this->fecha($f->fecha_inicio),
                'Fecha de Terminación' => fn ($f) => $this->fecha($f->fecha_terminacion),
                'Fecha de Recibido' => fn ($f) => $this->fecha($f->fecha_recibido),
                'Quién Remite' => 'quien_remite',
                'Quién Recibe' => 'quien_recibe',
                'Tipo de Entidad' => 'tipo_entidad',
                'Entidad Responsable' => 'entidad_nombre',
                'Número de Radicado' => 'numero_radicado',
                'Fecha de Radicado' => fn ($f) => $this->fecha($f->fecha_radicado),
                'Requiere Transcripción' => fn ($f) => $this->si($f->transcripcion_requerida),
                'Transcripción Completada' => fn ($f) => $this->si($f->transcripcion_completada),
                'Diagnóstico' => 'diagnostico',
                'Concepto de Rehabilitación' => 'concepto_rehabilitacion',
                'Estado' => 'estado',
                'Estado de Pago' => 'estado_pago',
                'Fecha de Pago' => fn ($f) => $this->fecha($f->fecha_pago),
                'Salario Base' => fn ($f) => $this->dinero($f->salario_base),
                'Valor Esperado' => fn ($f) => $this->dinero($f->valor_esperado),
                'Valor Pagado' => fn ($f) => $this->dinero($f->valor_pago),
                'Pagado A' => 'pagado_a',
                'Detalle del Pago' => 'detalle_pago',
                'Observación' => 'observacion',
                'Registrado Por' => 'registrado_por',
                'Fecha de Registro' => fn ($f) => $this->fechaHora($f->created_at),
            ],
            'fuentes' => [[
                'id' => 'incapacidades.id',
                'builder' => fn (int $a) => DB::table('incapacidades')
                    ->leftJoin('clientes', function ($j) {
                        $j->on('clientes.cedula', '=', 'incapacidades.cedula_usuario')
                            ->on('clientes.aliado_id', '=', 'incapacidades.aliado_id');
                    })
                    ->leftJoin('razones_sociales', 'razones_sociales.id', '=', 'incapacidades.razon_social_id')
                    ->leftJoin(self::USUARIO.' as u_rec', 'u_rec.id', '=', 'incapacidades.quien_recibe_id')
                    ->leftJoin(self::USUARIO.' as u_reg', 'u_reg.id', '=', 'incapacidades.created_by')
                    ->where('incapacidades.aliado_id', $a)
                    ->whereNull('incapacidades.deleted_at')
                    ->select([
                        'incapacidades.id as id',
                        'incapacidades.incapacidad_padre_id', 'incapacidades.numero_proroga',
                        'incapacidades.cedula_usuario', 'incapacidades.quien_remite',
                        'incapacidades.tipo_incapacidad', 'incapacidades.dias_incapacidad',
                        'incapacidades.fecha_inicio', 'incapacidades.fecha_terminacion',
                        'incapacidades.fecha_recibido', 'incapacidades.prorroga',
                        'incapacidades.tipo_entidad', 'incapacidades.entidad_nombre',
                        'incapacidades.razon_social_nombre', 'incapacidades.numero_radicado',
                        'incapacidades.fecha_radicado', 'incapacidades.transcripcion_requerida',
                        'incapacidades.transcripcion_completada', 'incapacidades.estado_pago',
                        'incapacidades.fecha_pago', 'incapacidades.valor_pago',
                        'incapacidades.valor_esperado', 'incapacidades.detalle_pago',
                        'incapacidades.pagado_a', 'incapacidades.diagnostico',
                        'incapacidades.concepto_rehabilitacion', 'incapacidades.observacion',
                        'incapacidades.estado', 'incapacidades.salario_base',
                        'incapacidades.created_at',
                        'razones_sociales.razon_social',
                        'u_rec.nombre as quien_recibe',
                        'u_reg.nombre as registrado_por',
                        DB::raw($this->sqlNombre('clientes').' as nombre_completo'),
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 10. Gestiones de incapacidades
    // ══════════════════════════════════════════════════════════════════════

    private function gestionesIncapacidades(): array
    {
        return [
            'archivo' => '10_Gestiones_de_Incapacidades',
            'titulo' => 'Gestiones de incapacidades',
            'descripcion' => 'Seguimiento realizado sobre cada incapacidad. «Consecutivo Incapacidad» apunta al archivo de Incapacidades.',
            'columnas' => [
                'Consecutivo Incapacidad' => fn ($f, $ctx) => $ctx->consecutivo('incapacidad', $f->incapacidad_id),
                'Documento' => 'cedula_usuario',
                'Fecha de Inicio de la Incapacidad' => fn ($f) => $this->fecha($f->fecha_inicio),
                'Fecha de la Gestión' => fn ($f) => $this->fechaHora($f->created_at),
                'Tipo' => 'tipo',
                'Trámite' => 'tramite',
                'Respuesta' => 'respuesta',
                'Resultado' => 'estado_resultado',
                'Aplica a Toda la Familia' => fn ($f) => $this->si($f->aplica_a_familia),
                'Cambió el Estado' => fn ($f) => $this->si($f->cambia_estado),
                'Estado Nuevo' => 'estado_nuevo',
                'Fecha para Recordar' => fn ($f) => $this->fecha($f->fecha_recordar),
                'Realizada Por' => 'realizada_por',
            ],
            'fuentes' => [[
                'id' => 'gestiones_incapacidad.id',
                // gestiones_incapacidad no tiene aliado_id: se filtra por la
                // incapacidad padre o se filtran datos de otros aliados.
                'builder' => fn (int $a) => DB::table('gestiones_incapacidad')
                    ->join('incapacidades', 'incapacidades.id', '=', 'gestiones_incapacidad.incapacidad_id')
                    ->leftJoin(self::USUARIO.' as u_reg', 'u_reg.id', '=', 'gestiones_incapacidad.user_id')
                    ->where('incapacidades.aliado_id', $a)
                    ->whereNull('incapacidades.deleted_at')
                    ->select([
                        'gestiones_incapacidad.id as id',
                        'gestiones_incapacidad.incapacidad_id',
                        'gestiones_incapacidad.tipo', 'gestiones_incapacidad.tramite',
                        'gestiones_incapacidad.respuesta', 'gestiones_incapacidad.estado_resultado',
                        'gestiones_incapacidad.aplica_a_familia', 'gestiones_incapacidad.cambia_estado',
                        'gestiones_incapacidad.estado_nuevo', 'gestiones_incapacidad.fecha_recordar',
                        'gestiones_incapacidad.created_at',
                        'incapacidades.cedula_usuario', 'incapacidades.fecha_inicio',
                        'u_reg.nombre as realizada_por',
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 11. Trámites y radicados
    // ══════════════════════════════════════════════════════════════════════

    private function tramites(): array
    {
        return [
            'archivo' => '11_Tramites_y_Radicados',
            'titulo' => 'Trámites y radicados',
            'descripcion' => 'Radicaciones ante EPS, ARL, pensión y caja, con su estado y su envío al cliente.',
            'columnas' => [
                'Consecutivo' => fn ($f, $ctx) => $ctx->asignar('radicado', $f->id),
                'Documento' => 'cedula',
                'Nombre Completo' => fn ($f) => $this->limpiar($f->nombre_completo),
                'Razón Social' => 'razon_social',
                'Trámite' => 'tipo',
                'Número de Radicado' => 'numero_radicado',
                'Estado' => 'estado',
                'Tipo de Documento' => 'tipo_documento',
                'Canal de Envío' => 'canal_envio',
                'Enviado al Cliente' => fn ($f) => $this->si($f->enviado_al_cliente),
                'Canal de Envío al Cliente' => 'canal_envio_cliente',
                'Fecha de Envío al Cliente' => fn ($f) => $this->fechaHora($f->fecha_envio_cliente),
                'Fecha de Inicio del Trámite' => fn ($f) => $this->fecha($f->fecha_inicio_tramite),
                'Fecha de Confirmación' => fn ($f) => $this->fecha($f->fecha_confirmacion),
                'Observación' => 'observacion',
                'Registrado Por' => 'registrado_por',
                'Fecha de Registro' => fn ($f) => $this->fechaHora($f->created_at),
            ],
            'fuentes' => [[
                'id' => 'radicados.id',
                'builder' => fn (int $a) => DB::table('radicados')
                    ->leftJoin('contratos', 'contratos.id', '=', 'radicados.contrato_id')
                    ->leftJoin('clientes', function ($j) {
                        $j->on('clientes.cedula', '=', 'contratos.cedula')
                            ->on('clientes.aliado_id', '=', 'contratos.aliado_id');
                    })
                    ->leftJoin('razones_sociales', 'razones_sociales.id', '=', 'contratos.razon_social_id')
                    ->leftJoin(self::USUARIO.' as u_reg', 'u_reg.id', '=', 'radicados.user_id')
                    ->where('radicados.aliado_id', $a)
                    ->select([
                        'radicados.id as id',
                        'radicados.tipo', 'radicados.numero_radicado', 'radicados.estado',
                        'radicados.canal_envio', 'radicados.enviado_al_cliente',
                        'radicados.canal_envio_cliente', 'radicados.fecha_envio_cliente',
                        'radicados.fecha_inicio_tramite', 'radicados.fecha_confirmacion',
                        'radicados.observacion', 'radicados.tipo_documento',
                        'radicados.created_at',
                        'contratos.cedula',
                        'razones_sociales.razon_social',
                        'u_reg.nombre as registrado_por',
                        DB::raw($this->sqlNombre('clientes').' as nombre_completo'),
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 12. Movimientos de trámites
    // ══════════════════════════════════════════════════════════════════════

    private function movimientosTramites(): array
    {
        return [
            'archivo' => '12_Movimientos_de_Tramites',
            'titulo' => 'Movimientos de trámites',
            'descripcion' => 'Cambios de estado de cada radicado. «Consecutivo Trámite» apunta al archivo de Trámites y Radicados.',
            'columnas' => [
                'Consecutivo Trámite' => fn ($f, $ctx) => $ctx->consecutivo('radicado', $f->radicado_id),
                'Documento' => 'cedula',
                'Proceso' => 'tipo_proceso',
                'Entidad' => 'entidad',
                'Estado Anterior' => 'estado_anterior',
                'Estado Nuevo' => 'estado_nuevo',
                'Observación' => 'observacion',
                'Registrado Por' => 'registrado_por',
                'Fecha del Movimiento' => fn ($f) => $this->fechaHora($f->created_at),
            ],
            'fuentes' => [[
                'id' => 'radicado_movimientos.id',
                // Sin aliado_id: se filtra por el radicado padre.
                'builder' => fn (int $a) => DB::table('radicado_movimientos')
                    ->join('radicados', 'radicados.id', '=', 'radicado_movimientos.radicado_id')
                    ->leftJoin('contratos', 'contratos.id', '=', 'radicado_movimientos.contrato_id')
                    ->leftJoin(self::USUARIO.' as u_reg', 'u_reg.id', '=', 'radicado_movimientos.user_id')
                    ->where('radicados.aliado_id', $a)
                    ->select([
                        'radicado_movimientos.id as id',
                        'radicado_movimientos.radicado_id',
                        'radicado_movimientos.tipo_proceso', 'radicado_movimientos.entidad',
                        'radicado_movimientos.estado_anterior', 'radicado_movimientos.estado_nuevo',
                        'radicado_movimientos.observacion', 'radicado_movimientos.created_at',
                        'contratos.cedula',
                        'u_reg.nombre as registrado_por',
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 13. Tareas
    // ══════════════════════════════════════════════════════════════════════

    private function tareas(): array
    {
        return [
            'archivo' => '13_Tareas',
            'titulo' => 'Tareas',
            'descripcion' => 'Tareas y pendientes operativos.',
            'columnas' => [
                'Consecutivo' => fn ($f, $ctx) => $ctx->asignar('tarea', $f->id),
                'Tipo' => 'tipo',
                'Estado' => 'estado',
                'Resultado' => 'resultado',
                'Documento' => 'cedula',
                'Nombre Completo' => fn ($f) => $this->limpiar($f->nombre_completo),
                'Razón Social' => 'razon_social',
                'Entidad' => 'entidad',
                'Tarea' => 'tarea',
                'Observación' => 'observacion',
                'Correo' => 'correo',
                'Número de Radicado' => 'numero_radicado',
                'Fecha de Radicado' => fn ($f) => $this->fecha($f->fecha_radicado),
                'Fecha Límite' => fn ($f) => $this->fecha($f->fecha_limite),
                'Fecha de Alerta' => fn ($f) => $this->fecha($f->fecha_alerta),
                'Encargado' => 'encargado',
                'Creada Por' => 'creada_por',
                'Fecha de Registro' => fn ($f) => $this->fechaHora($f->created_at),
            ],
            'fuentes' => [[
                'id' => 'tareas.id',
                'builder' => fn (int $a) => DB::table('tareas')
                    ->leftJoin('clientes', function ($j) {
                        $j->on('clientes.cedula', '=', 'tareas.cedula')
                            ->on('clientes.aliado_id', '=', 'tareas.aliado_id');
                    })
                    ->leftJoin('razones_sociales', 'razones_sociales.id', '=', 'tareas.razon_social_id')
                    ->leftJoin(self::USUARIO.' as u_enc', 'u_enc.id', '=', 'tareas.encargado_id')
                    ->leftJoin(self::USUARIO.' as u_cre', 'u_cre.id', '=', 'tareas.creado_por')
                    ->where('tareas.aliado_id', $a)
                    ->whereNull('tareas.deleted_at')
                    ->select([
                        'tareas.id as id',
                        'tareas.tipo', 'tareas.estado', 'tareas.resultado', 'tareas.cedula',
                        'tareas.entidad', 'tareas.tarea', 'tareas.observacion',
                        'tareas.fecha_limite', 'tareas.fecha_alerta', 'tareas.fecha_radicado',
                        'tareas.numero_radicado', 'tareas.correo', 'tareas.created_at',
                        'razones_sociales.razon_social',
                        'u_enc.nombre as encargado',
                        'u_cre.nombre as creada_por',
                        DB::raw($this->sqlNombre('clientes').' as nombre_completo'),
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 14. Gestiones de tareas
    // ══════════════════════════════════════════════════════════════════════

    private function gestionesTareas(): array
    {
        return [
            'archivo' => '14_Gestiones_de_Tareas',
            'titulo' => 'Gestiones de tareas',
            'descripcion' => 'Movimientos sobre cada tarea. «Consecutivo Tarea» apunta al archivo de Tareas.',
            'columnas' => [
                'Consecutivo Tarea' => fn ($f, $ctx) => $ctx->consecutivo('tarea', $f->tarea_id),
                'Documento' => 'cedula',
                'Acción' => 'tipo_accion',
                'Observación' => 'observacion',
                'Estado de la Tarea' => 'estado_tarea',
                'Días para Recordar' => 'recordar_dias',
                'Fecha de Alerta' => fn ($f) => $this->fecha($f->fecha_alerta),
                'Encargado Anterior' => 'encargado_anterior',
                'Encargado Nuevo' => 'encargado_nuevo',
                'Realizada Por' => 'realizada_por',
                'Fecha de la Gestión' => fn ($f) => $this->fechaHora($f->created_at),
            ],
            'fuentes' => [[
                'id' => 'tarea_gestiones.id',
                // Sin aliado_id: se filtra por la tarea padre.
                'builder' => fn (int $a) => DB::table('tarea_gestiones')
                    ->join('tareas', 'tareas.id', '=', 'tarea_gestiones.tarea_id')
                    ->leftJoin(self::USUARIO.' as u_reg', 'u_reg.id', '=', 'tarea_gestiones.user_id')
                    ->leftJoin(self::USUARIO.' as u_ant', 'u_ant.id', '=', 'tarea_gestiones.encargado_anterior')
                    ->leftJoin(self::USUARIO.' as u_nue', 'u_nue.id', '=', 'tarea_gestiones.encargado_nuevo')
                    ->where('tareas.aliado_id', $a)
                    ->whereNull('tareas.deleted_at')
                    ->select([
                        'tarea_gestiones.id as id',
                        'tarea_gestiones.tarea_id', 'tarea_gestiones.tipo_accion',
                        'tarea_gestiones.observacion', 'tarea_gestiones.recordar_dias',
                        'tarea_gestiones.fecha_alerta', 'tarea_gestiones.estado_tarea',
                        'tarea_gestiones.created_at',
                        'tareas.cedula',
                        'u_reg.nombre as realizada_por',
                        'u_ant.nombre as encargado_anterior',
                        'u_nue.nombre as encargado_nuevo',
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 15. Prospectos
    // ══════════════════════════════════════════════════════════════════════

    private function prospectos(): array
    {
        return [
            'archivo' => '15_Prospectos',
            'titulo' => 'Prospectos',
            'descripcion' => 'Personas cotizadas que aún no se afiliaron. No incluye el detalle del cálculo del cotizador.',
            'columnas' => [
                'Tipo de Documento' => 'tipo_doc',
                'Documento' => 'cedula',
                'Nombre Completo' => fn ($f) => $this->limpiar($f->nombre_completo ?: $this->nombre($f)),
                'Celular' => 'celular',
                'Correo' => 'correo',
                'Ocupación' => 'ocupacion',
                'Municipio' => fn ($f) => $f->municipio_nombre ?: $f->municipio,
                'Canal de Origen' => 'canal_origen',
                'Referido Por' => 'referido',
                'Modalidad' => 'modalidad',
                'Plan' => 'plan',
                'Es Independiente' => fn ($f) => $this->si($f->es_independiente),
                'Salario Base' => fn ($f) => $this->dinero($f->salario_base),
                'Nivel de Riesgo ARL' => 'n_arl',
                'Costo de Afiliación' => fn ($f) => $this->dinero($f->costo_afiliacion),
                'Administración' => fn ($f) => $this->dinero($f->administracion),
                'Estado' => 'estado',
                'Razón de No Afiliación' => 'razon_no_afiliacion',
                'Fecha de Cotización' => fn ($f) => $this->fecha($f->fecha_cotizacion),
                'Próxima Llamada' => fn ($f) => $this->fecha($f->proxima_llamada),
                'Fecha de Ingreso Prevista' => fn ($f) => $this->fecha($f->fecha_ingreso),
                'Asesor' => 'asesor',
                'Creado Por' => 'creado_por_nombre',
                'Fecha de Registro' => fn ($f) => $this->fechaHora($f->created_at),
            ],
            'fuentes' => [[
                'id' => 'cotizaciones_prospectos.id',
                'builder' => fn (int $a) => DB::table('cotizaciones_prospectos')
                    ->leftJoin('ciudades', 'ciudades.id', '=', 'cotizaciones_prospectos.municipio_id')
                    ->leftJoin('tipo_modalidad', 'tipo_modalidad.id', '=', 'cotizaciones_prospectos.modalidad_id')
                    ->leftJoin('planes_contrato', 'planes_contrato.id', '=', 'cotizaciones_prospectos.plan_id')
                    ->leftJoin('asesores', 'asesores.id', '=', 'cotizaciones_prospectos.asesor_id')
                    ->leftJoin(self::USUARIO.' as u_cre', 'u_cre.id', '=', 'cotizaciones_prospectos.creado_por')
                    ->where('cotizaciones_prospectos.aliado_id', $a)
                    ->whereNull('cotizaciones_prospectos.deleted_at')
                    ->select([
                        'cotizaciones_prospectos.id as id',
                        'cotizaciones_prospectos.tipo_doc', 'cotizaciones_prospectos.cedula',
                        'cotizaciones_prospectos.primer_nombre', 'cotizaciones_prospectos.segundo_nombre',
                        'cotizaciones_prospectos.primer_apellido', 'cotizaciones_prospectos.segundo_apellido',
                        'cotizaciones_prospectos.nombre_completo', 'cotizaciones_prospectos.celular',
                        'cotizaciones_prospectos.correo', 'cotizaciones_prospectos.ocupacion',
                        'cotizaciones_prospectos.referido', 'cotizaciones_prospectos.canal_origen',
                        'cotizaciones_prospectos.salario_base', 'cotizaciones_prospectos.estado',
                        'cotizaciones_prospectos.razon_no_afiliacion', 'cotizaciones_prospectos.fecha_cotizacion',
                        'cotizaciones_prospectos.proxima_llamada', 'cotizaciones_prospectos.es_independiente',
                        'cotizaciones_prospectos.fecha_ingreso', 'cotizaciones_prospectos.n_arl',
                        'cotizaciones_prospectos.costo_afiliacion', 'cotizaciones_prospectos.administracion',
                        'cotizaciones_prospectos.municipio', 'cotizaciones_prospectos.created_at',
                        'ciudades.nombre as municipio_nombre',
                        'tipo_modalidad.tipo_modalidad as modalidad',
                        'planes_contrato.nombre as plan',
                        'asesores.nombre as asesor',
                        'u_cre.nombre as creado_por_nombre',
                    ]),
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 16. Usuarios y asesores
    // ══════════════════════════════════════════════════════════════════════

    private function usuariosYAsesores(): array
    {
        $columnas = [
            'Tipo' => 'tipo_persona',
            'Nombre' => 'nombre',
            'Documento' => 'cedula',
            'Correo' => 'correo',
            'Teléfono' => 'telefono',
            'Celular' => 'celular',
            'Rol' => 'rol',
            'Ciudad' => 'ciudad',
            'Cuenta Bancaria' => 'cuenta_bancaria',
            'Comisión de Afiliación' => 'comision_afiliacion',
            'Comisión de Administración' => 'comision_administracion',
            'Estado' => fn ($f) => $this->si($f->activo, 'Activo', 'Inactivo'),
            'Fecha de Ingreso' => fn ($f) => $this->fecha($f->fecha_ingreso),
            'Fecha de Registro' => fn ($f) => $this->fechaHora($f->created_at),
        ];

        return [
            'archivo' => '16_Usuarios_y_Asesores',
            'titulo' => 'Usuarios y asesores',
            'descripcion' => 'Usuarios del sistema y asesores comerciales. No incluye contraseñas ni datos de sesión.',
            'columnas' => $columnas,
            'fuentes' => [
                [
                    'id' => 'users.id',
                    'builder' => fn (int $a) => DB::table('users')
                        ->where('users.aliado_id', $a)
                        ->whereNull('users.deleted_at')
                        ->select([
                            'users.id as id',
                            'users.nombre', 'users.cedula', 'users.telefono',
                            'users.activo', 'users.created_at',
                            'users.email as correo',
                            DB::raw("'Usuario del sistema' as tipo_persona"),
                            DB::raw("'' as celular"),
                            DB::raw("'' as ciudad"),
                            DB::raw("'' as cuenta_bancaria"),
                            DB::raw('NULL as comision_afiliacion'),
                            DB::raw('NULL as comision_administracion'),
                            DB::raw('NULL as fecha_ingreso'),
                        ])
                        // Un usuario puede tener varios roles; con TOP 1 la fila
                        // no se multiplica.
                        ->selectSub(
                            DB::table('model_has_roles as mhr')
                                ->join('roles', 'roles.id', '=', 'mhr.role_id')
                                ->whereColumn('mhr.model_id', 'users.id')
                                ->where('mhr.model_type', 'App\\Models\\User')
                                ->select('roles.name')
                                ->limit(1),
                            'rol'
                        ),
                ],
                [
                    'id' => 'asesores.id',
                    'builder' => fn (int $a) => DB::table('asesores')
                        ->where('asesores.aliado_id', $a)
                        ->whereNull('asesores.deleted_at')
                        ->select([
                            'asesores.id as id',
                            'asesores.nombre', 'asesores.cedula', 'asesores.telefono',
                            'asesores.celular', 'asesores.ciudad', 'asesores.cuenta_bancaria',
                            'asesores.activo', 'asesores.fecha_ingreso', 'asesores.created_at',
                            'asesores.correo',
                            DB::raw("'Asesor comercial' as tipo_persona"),
                            DB::raw("'Asesor' as rol"),
                            DB::raw("LTRIM(RTRIM(CONCAT(asesores.comision_afil_tipo, ' ', asesores.comision_afil_valor))) as comision_afiliacion"),
                            DB::raw("LTRIM(RTRIM(CONCAT(asesores.comision_admon_tipo, ' ', asesores.comision_admon_valor))) as comision_administracion"),
                        ]),
                ],
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // Formato de valores
    // ══════════════════════════════════════════════════════════════════════

    /** CONCAT de los cuatro campos de nombre de una tabla, para subconsultas. */
    private function sqlNombre(string $tabla): string
    {
        return "LTRIM(RTRIM(CONCAT({$tabla}.primer_nombre, ' ', {$tabla}.segundo_nombre, ' ', {$tabla}.primer_apellido, ' ', {$tabla}.segundo_apellido)))";
    }

    /** Nombre completo armado en PHP cuando las cuatro columnas vienen sueltas. */
    private function nombre(object $f): string
    {
        return $this->limpiar(implode(' ', array_filter([
            $f->primer_nombre ?? null,
            $f->segundo_nombre ?? null,
            $f->primer_apellido ?? null,
            $f->segundo_apellido ?? null,
        ])));
    }

    private function limpiar(?string $v): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $v));
    }

    /**
     * Fecha en d/m/Y. SQL Server devuelve formatos como
     * "Apr 1 2026 12:00:00:AM" según la colación — el mismo saneado que hace
     * BaseModel::asDateTime.
     */
    private function fecha($v): string
    {
        return $this->carbon($v)?->format('d/m/Y') ?? '';
    }

    private function fechaHora($v): string
    {
        return $this->carbon($v)?->format('d/m/Y H:i') ?? '';
    }

    private function carbon($v): ?Carbon
    {
        if ($v === null || $v === '') {
            return null;
        }

        if ($v instanceof \DateTimeInterface) {
            return Carbon::instance($v);
        }

        try {
            return Carbon::parse(preg_replace('/:(AM|PM)$/i', ' $1', trim((string) $v)));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Importe con punto decimal y sin separador de miles. El punto es
     * obligatorio: el CSV va separado por comas, y una coma decimal partiría
     * cada importe en dos columnas.
     */
    private function dinero($v): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        return number_format((float) $v, 2, '.', '');
    }

    /** sqlsrv devuelve los enteros como string: "1" nunca es === 1. */
    private function si($v, string $si = 'Sí', string $no = 'No'): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        return ((int) $v) === 1 ? $si : $no;
    }
}
