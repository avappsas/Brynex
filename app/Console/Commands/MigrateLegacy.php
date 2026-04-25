<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacy extends Command
{
    protected $signature   = 'legacy:migrate {--step=all : Paso a ejecutar (prep,01..09,all)}';
    protected $description = 'Migra datos de las 6 BDs legacy a BryNex';

    // IDs de aliados en BryNex (se llenan en handle())
    private array $ids = [];

    // Bases de datos legacy → clave de aliado
    private array $dbs = [
        'Brygar_BD'       => 'brygar',
        'GiMave_Integral' => 'gimave',
        'Grupo_Fecop'     => 'fecop',
        'LuisLopez'       => 'luislopez',
        'Mave_Anderson'   => 'mave',
        'SS_Faga'         => 'faga',
    ];

    public function handle(): void
    {
        $step = $this->option('step');

        // Cargar IDs de aliados
        $this->loadAliados();

        $steps = [
            'prep' => fn() => $this->stepPrep(),
            '01'   => fn() => $this->step01_RazonesSociales(),
            '02'   => fn() => $this->step02_Usuarios(),
            '03'   => fn() => $this->step03_Empresas(),
            '04'   => fn() => $this->step04_AsesoresBancos(),
            '05'   => fn() => $this->step05_Clientes(),
            '06'   => fn() => $this->step06_Contratos(),
        ];

        if ($step === 'all') {
            foreach ($steps as $key => $fn) {
                $this->info("\n" . str_repeat('─', 60));
                $this->info("PASO: $key");
                $this->info(str_repeat('─', 60));
                $fn();
            }
        } elseif (isset($steps[$step])) {
            $steps[$step]();
        } else {
            $this->error("Paso '$step' no existe. Opciones: " . implode(', ', array_keys($steps)) . ', all');
        }

        $this->info("\n✅ Migración completada.");
    }

    private function loadAliados(): void
    {
        $aliados = DB::table('aliados')->get(['id', 'nombre', 'nit']);
        foreach ($aliados as $a) {
            if ($a->nit === '901918923') $this->ids['brygar']    = $a->id;
            if ($a->nombre === 'GiMave Integral') $this->ids['gimave']    = $a->id;
            if ($a->nombre === 'Grupo Fecop')     $this->ids['fecop']     = $a->id;
            if ($a->nombre === 'Luis Lopez')      $this->ids['luislopez'] = $a->id;
            if ($a->nombre === 'Mave Anderson')   $this->ids['mave']      = $a->id;
            if ($a->nombre === 'SS Faga')         $this->ids['faga']      = $a->id;
        }
        $this->line('IDs aliados: ' . json_encode($this->ids));
    }

    // ─── PASO PREP ───────────────────────────────────────────────────────────
    private function stepPrep(): void
    {
        $this->info('Limpiando tablas BryNex...');
        DB::statement('EXEC sp_MSforeachtable \'ALTER TABLE ? NOCHECK CONSTRAINT ALL\'');

        $tables = [
            'gestiones_incapacidad','incapacidades','radicado_movimientos',
            'radicados','tarea_gestiones','tarea_documentos','tareas',
            'planos','abonos','consignacion_factura','consignaciones',
            'facturas','factura_secuencias','bitacora_cobros','bitacora',
            'documentos_cliente','beneficiarios','clave_accesos',
            'comisiones_asesores','contratos','clientes','empresas',
            'asesores','razones_sociales','banco_cuentas','gastos',
            'saldos_banco','cuadres',
        ];
        foreach ($tables as $t) {
            DB::statement("DELETE FROM $t");
            $this->line("  🗑  $t vaciada");
        }

        DB::statement('EXEC sp_MSforeachtable \'ALTER TABLE ? WITH CHECK CHECK CONSTRAINT ALL\'');

        // Insertar aliados si no existen
        $aliados = [
            ['nombre' => 'Brygar',        'nit' => '901918923', 'razon_social' => 'Brygar SAS'],
            ['nombre' => 'GiMave Integral','nit' => null,        'razon_social' => 'GiMave Integral'],
            ['nombre' => 'Grupo Fecop',   'nit' => null,        'razon_social' => 'Grupo Fecop'],
            ['nombre' => 'Luis Lopez',    'nit' => null,        'razon_social' => 'Luis Lopez'],
            ['nombre' => 'Mave Anderson', 'nit' => null,        'razon_social' => 'Mave Anderson'],
            ['nombre' => 'SS Faga',       'nit' => null,        'razon_social' => 'SS Faga'],
        ];
        foreach ($aliados as $a) {
            $exists = DB::table('aliados')->where('nombre', $a['nombre'])->exists();
            if (!$exists) {
                DB::table('aliados')->insert(array_merge($a, ['activo' => true, 'created_at' => now(), 'updated_at' => now()]));
                $this->line("  ✅ Aliado creado: {$a['nombre']}");
            }
        }

        // Agregar id_legacy si no existen
        $columnas = [
            'razones_sociales','empresas','asesores',
            'banco_cuentas','users','clientes','contratos','facturas','incapacidades',
        ];
        foreach ($columnas as $tabla) {
            $existe = DB::select("SELECT COUNT(*) as cnt FROM sys.columns WHERE object_id=OBJECT_ID(?) AND name='id_legacy'", [$tabla]);
            if (!$existe[0]->cnt) {
                DB::statement("ALTER TABLE $tabla ADD id_legacy INT NULL");
                $this->line("  ✅ id_legacy agregado a: $tabla");
            }
        }

        $this->loadAliados();
        $this->info('✅ Preparación completa.');
    }

    // ─── PASO 01: RAZONES SOCIALES ───────────────────────────────────────────
    private function step01_RazonesSociales(): void
    {
        DB::statement('ALTER TABLE razones_sociales NOCHECK CONSTRAINT ALL');
        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) { $this->warn("  ⚠ Aliado '$key' no encontrado"); continue; }

            $rows = DB::connection('sqlsrv_legacy')
                ->select("SELECT * FROM [$db].dbo.Razon_Social");

            $count = 0;
            foreach ($rows as $r) {
                DB::table('razones_sociales')->insert([
                    'aliado_id'           => $aliadoId,
                    'id_legacy'           => $r->ID,
                    'dv'                  => $r->DV,
                    'razon_social'        => trim($r->Razon_Social ?? ''),
                    'estado'              => trim($r->Estado ?? ''),
                    'plan'                => trim($r->Plan ?? ''),
                    'direccion'           => trim($r->Direccion ?? ''),
                    'telefonos'           => trim($r->Telefonos ?? ''),
                    'correos'             => trim($r->Correos ?? ''),
                    'actividad_economica' => trim($r->Actividad_Economica ?? ''),
                    'objeto_social'       => trim($r->Objeto_Social ?? ''),
                    'observacion'         => trim($r->Observacion ?? ''),
                    'salario_minimo'      => $r->Salario_Minimo ?? 0,
                    'arl_nit'             => is_numeric($r->ARL) && $r->ARL > 1 ? (int)$r->ARL : null,
                    'caja_nit'            => is_numeric($r->CAJA) && $r->CAJA > 1 ? (int)$r->CAJA : null,
                    'mes_pagos'           => $r->MES_PAGOS,
                    'anio_pagos'          => $r->{'AÑO_PAGOS'},
                    'n_plano'             => $r->N_PLANO,
                    'fecha_constitucion'  => $r->Fecha_Constitucion,
                    'fecha_limite_pago'   => $r->Fecha_Limite_pago,
                    'dia_habil'           => $r->Dia_Habil,
                    'forma_presentacion'  => trim($r->Forma_Presentacion ?? ''),
                    'codigo_sucursal'     => trim($r->Codigo_Sucursal_Aportante ?? ''),
                    'nombre_sucursal'     => trim($r->Nombre_Sucursal ?? ''),
                    'notas_factura1'      => trim($r->Notas_Factura1 ?? ''),
                    'notas_factura2'      => trim($r->Notas_Factura2 ?? ''),
                    'dir_formulario'      => trim($r->Dir_Formulario ?? ''),
                    'tel_formulario'      => trim($r->Tel_Formulario ?? ''),
                    'correo_formulario'   => trim($r->Correo_Formulario ?? ''),
                    'cedula_rep'          => is_numeric($r->Cedula_Rep) ? (int)$r->Cedula_Rep : null,
                    'nombre_rep'          => trim($r->Nombre_Rep ?? ''),
                    // SIN timestamps (la tabla no los tiene)
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count razones sociales");
        }
        DB::statement('ALTER TABLE razones_sociales WITH CHECK CHECK CONSTRAINT ALL');
    }

    // ─── PASO 02: USUARIOS ───────────────────────────────────────────────────
    private function step02_Usuarios(): void
    {
        DB::statement('ALTER TABLE users NOCHECK CONSTRAINT ALL');
        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.usuarios WHERE Id_usuario IS NOT NULL");
            $count = 0;
            foreach ($rows as $r) {
                $login = trim($r->Login ?? 'usuario');
                DB::table('users')->insert([
                    'aliado_id'  => $aliadoId,
                    'id_legacy'  => $r->Id_usuario,
                    'name'       => $login,
                    'email'      => strtolower(str_replace(' ', '.', $login)) . "_{$key}_{$r->Id_usuario}@legacy.local",
                    'password'   => bcrypt($r->Password ?? 'changeme123'),
                    'cedula'     => is_numeric($r->Cedula) ? (int)$r->Cedula : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count usuarios");
        }
        DB::statement('ALTER TABLE users WITH CHECK CHECK CONSTRAINT ALL');
    }

    // ─── PASO 03: EMPRESAS ───────────────────────────────────────────────────
    private function step03_Empresas(): void
    {
        DB::statement('ALTER TABLE empresas NOCHECK CONSTRAINT ALL');
        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Empresas");
            $count = 0;
            foreach ($rows as $r) {
                DB::table('empresas')->insert([
                    'aliado_id'           => $aliadoId,
                    'id_legacy'           => $r->Id,
                    'nit'                 => is_numeric($r->NIT) && $r->NIT > 0 ? (int)$r->NIT : null,
                    'empresa'             => trim($r->Empresa ?? ''),
                    'contacto'            => trim($r->Contacto ?? ''),
                    'telefono'            => trim($r->Telefono ?? ''),
                    'celular'             => trim($r->Celular ?? ''),
                    'direccion'           => trim($r->Direccion ?? ''),
                    'observacion'         => trim($r->Observacion ?? ''),
                    'cliente_de'          => trim($r->Cliente_De ?? ''),
                    'tipo_facturacion'    => trim($r->Tipo_Facturacion ?? ''),
                    'iva'                 => trim($r->IVA ?? ''),
                    'correo'              => trim($r->Correo ?? ''),
                    'actividad_economica' => substr((string)($r->Actividad_economica ?? ''), 0, 1000),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count empresas");
        }
        DB::statement('ALTER TABLE empresas WITH CHECK CHECK CONSTRAINT ALL');
    }

    // ─── PASO 04: ASESORES + BANCOS ──────────────────────────────────────────
    private function step04_AsesoresBancos(): void
    {
        DB::statement('ALTER TABLE asesores NOCHECK CONSTRAINT ALL');
        DB::statement('ALTER TABLE banco_cuentas NOCHECK CONSTRAINT ALL');

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            // Asesores
            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Asesores WHERE ID IS NOT NULL");
            $count = 0;
            foreach ($rows as $r) {
                DB::table('asesores')->insert([
                    'aliado_id'          => $aliadoId,
                    'id_legacy'          => $r->ID,
                    'cedula'             => (string)$r->ID,
                    'nombre'             => trim($r->Nombre ?? 'Sin nombre'),
                    'telefono'           => trim($r->Telefono ?? ''),
                    'direccion'          => trim($r->DIreccion ?? ''),
                    'ciudad'             => trim($r->Ciudad ?? ''),
                    'departamento'       => trim($r->Departamento ?? ''),
                    'cuenta_bancaria'    => trim($r->Cuenta_Bancaria ?? ''),
                    'fecha_ingreso'      => $r->Fecha_Ingreso ? substr($r->Fecha_Ingreso, 0, 10) : null,
                    'activo'             => strtolower(trim($r->Activo ?? '')) === 'activo' ? 1 : 0,
                    'id_original_access' => $r->ID,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count asesores");

            // Bancos
            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Bancos_cuentas WHERE ID IS NOT NULL");
            $count = 0;
            foreach ($rows as $r) {
                DB::table('banco_cuentas')->insert([
                    'aliado_id'     => $aliadoId,
                    'id_legacy'     => $r->ID,
                    'nombre'        => trim($r->NOMBRE ?? ''),
                    'nit'           => trim($r->NIT ?? ''),
                    'banco'         => trim($r->BANCO ?? ''),
                    'tipo_cuenta'   => trim($r->TIPO ?? ''),
                    'numero_cuenta' => trim($r->NUMERO ?? ''),
                    'activo'        => 1,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count cuentas bancarias");
        }

        DB::statement('ALTER TABLE asesores WITH CHECK CHECK CONSTRAINT ALL');
        DB::statement('ALTER TABLE banco_cuentas WITH CHECK CHECK CONSTRAINT ALL');
    }

    // ─── PASO 05: CLIENTES ───────────────────────────────────────────────────
    private function step05_Clientes(): void
    {
        DB::statement('ALTER TABLE clientes NOCHECK CONSTRAINT ALL');

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Base_De_Datos");
            $count = 0;

            foreach ($rows as $r) {
                $epsId     = $this->lookupByNit('eps',      $r->Eps);
                $pensionId = $this->lookupByNit('pensiones', $r->Pension);

                DB::table('clientes')->insert([
                    'aliado_id'           => $aliadoId,
                    'id_legacy'           => $r->Id,
                    'cod_empresa'         => $r->COD_EMPRESA,  // remap al final
                    'tipo_doc'            => trim($r->TIPO_DOC ?? ''),
                    'cedula'              => $r->Cedula,
                    'primer_nombre'       => trim($r->{'1_NOMBRE'} ?? ''),
                    'segundo_nombre'      => trim($r->{'2_NOMBRE'} ?? ''),
                    'primer_apellido'     => trim($r->{'1_APELLIDO'} ?? ''),
                    'segundo_apellido'    => trim($r->{'2_APELLIDO'} ?? ''),
                    'genero'              => trim($r->Genero ?? ''),
                    'sisben'              => trim($r->Sisben ?? ''),
                    'fecha_nacimiento'    => $r->Fecha_Nacimiento ? substr($r->Fecha_Nacimiento, 0, 10) : null,
                    'fecha_expedicion'    => $r->Fecha_Expedicion ? substr($r->Fecha_Expedicion, 0, 10) : null,
                    'rh'                  => trim($r->RH ?? ''),
                    'telefono'            => trim($r->Telefono ?? ''),
                    'celular'             => is_numeric($r->Celular) && $r->Celular > 0 ? (int)$r->Celular : null,
                    'correo'              => trim($r->Correo ?? ''),
                    'departamento_id'     => $r->Departamento,
                    'municipio_id'        => is_numeric($r->Municipio) ? (int)$r->Municipio : null,
                    'direccion_vivienda'  => trim($r->Direccion_Vivienda ?? ''),
                    'direccion_cobro'     => trim($r->Direccion_Cobro ?? ''),
                    'barrio'              => trim($r->Barrio ?? ''),
                    'eps_id'              => $epsId,
                    'pension_id'          => $pensionId,
                    'ips'                 => trim($r->IPS ?? ''),
                    'urgencias'           => trim($r->URGENCIAS ?? ''),
                    'iva'                 => trim($r->IVA ?? ''),
                    'ocupacion'           => trim($r->Ocupacion ?? ''),
                    'referido'            => trim($r->Referido ?? ''),
                    'observacion'         => trim($r->Observacion ?? ''),
                    'observacion_llamada' => (string)($r->Observacion_llamada ?? ''),
                    'deuda'               => $r->DEUDA,
                    'fecha_probable_pago' => trim($r->Fecha_problable_pago ?? ''),
                    'modo_probable_pago'  => trim($r->Modo_propable_pago ?? ''),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count clientes");

            // Remap cod_empresa al nuevo ID
            DB::statement("
                UPDATE c SET c.cod_empresa = e.id
                FROM clientes c
                JOIN empresas e ON e.id_legacy = c.cod_empresa AND e.aliado_id = c.aliado_id
                WHERE c.aliado_id = ? AND c.cod_empresa IS NOT NULL
            ", [$aliadoId]);
        }

        DB::statement('ALTER TABLE clientes WITH CHECK CHECK CONSTRAINT ALL');
    }

    // ─── PASO 06: CONTRATOS ──────────────────────────────────────────────────
    private function step06_Contratos(): void
    {
        DB::statement('ALTER TABLE contratos NOCHECK CONSTRAINT ALL');

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Contratos");
            $count = 0;

            foreach ($rows as $r) {
                $razonId   = DB::table('razones_sociales')->where('aliado_id', $aliadoId)->where('id_legacy', $r->COD_RAZON_SOC)->value('id');
                $asesorId  = DB::table('asesores')->where('aliado_id', $aliadoId)->where('id_legacy', $r->Asesor)->value('id');
                $userId    = DB::table('users')->where('aliado_id', $aliadoId)->where('id_legacy', $r->Encargado_Afiliacion)->value('id');
                $epsId     = $this->lookupByNit('eps',      $r->Eps_c);
                $pensionId = $this->lookupByNit('pensiones', $r->Pension_c);
                $arlId     = is_numeric($r->ARL) && $r->ARL > 1 ? $this->lookupByNit('arls', $r->ARL) : null;
                $cajaId    = $this->lookupByNit('cajas',    $r->Caja_Comp);

                DB::table('contratos')->insert([
                    'aliado_id'              => $aliadoId,
                    'id_legacy'              => $r->Id,
                    'cedula'                 => $r->Cedula,
                    'estado'                 => strtolower(trim($r->Estado ?? '')),
                    'razon_social_id'        => $razonId,
                    'asesor_id'              => $asesorId,
                    'encargado_id'           => $userId,
                    'eps_id'                 => $epsId,
                    'pension_id'             => $pensionId,
                    'arl_id'                 => $arlId,
                    'caja_id'                => $cajaId,
                    'n_arl'                  => $r->N_ARL,
                    'cargo'                  => trim($r->Cargo ?? ''),
                    'fecha_ingreso'          => $r->Fecha_Ingreso ? substr($r->Fecha_Ingreso, 0, 10) : null,
                    'fecha_retiro'           => $r->Fecha_Retiro  ? substr($r->Fecha_Retiro,  0, 10) : null,
                    'fecha_arl'              => $r->Fecha_ARL     ? substr($r->Fecha_ARL,     0, 10) : null,
                    'fecha_created'          => $r->Fecha_Created  ? substr($r->Fecha_Created, 0, 10) : null,
                    'salario'                => $r->Salario_M ?? 0,
                    'administracion'         => $r->Administracion ?? 0,
                    'admon_asesor'           => $r->admon_asesor   ?? 0,
                    'costo_afiliacion'       => $r->costo_afiliacion ?? 0,
                    'seguro'                 => $r->Seguro ?? 0,
                    'np'                     => trim($r->NP ?? ''),
                    'envio_planilla'         => trim($r->Envio_Planilla ?? ''),
                    'fecha_probable_pago'    => trim($r->Fecha_problable_pago ?? ''),
                    'modo_probable_pago'     => trim($r->Modo_propable_pago ?? ''),
                    'observacion'            => trim($r->Observacion ?? ''),
                    'observacion_afiliacion' => trim($r->Observacion_Afiliacion ?? ''),
                    'observacion_llamada'    => (string)($r->Observacion_llamada ?? ''),
                    'motivo_afiliacion'      => trim($r->Motivo_Afiliacion ?? ''),
                    'motivo_retiro'          => trim($r->Motivo_Retiro ?? ''),
                    'tipo_modalidad_id'      => $r->Tipo,
                    'actividad_economica_id' => $r->Actividad_Economica,
                    'radicado_eps'           => trim($r->Radicado_EPS ?? ''),
                    'radicado_arl'           => trim($r->Radicado_ARL ?? ''),
                    'radicado_caja'          => trim($r->Radicado_Caja ?? ''),
                    'radicado_pension'       => trim($r->Radicado_Pension ?? ''),
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count contratos");
        }

        DB::statement('ALTER TABLE contratos WITH CHECK CHECK CONSTRAINT ALL');

        // Resumen de FKs nulas
        $this->warn('Contratos sin razon_social_id: ' .
            DB::table('contratos')->whereNull('razon_social_id')->count());
    }

    // ─── HELPER: lookup por NIT en tabla global ───────────────────────────────
    private function lookupByNit(string $tabla, mixed $nit): ?int
    {
        if (!is_numeric($nit) || (float)$nit <= 1) return null;
        return DB::table($tabla)->where('nit', (int)(float)$nit)->value('id');
    }
}
