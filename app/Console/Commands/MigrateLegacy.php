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
            'prep'        => fn() => $this->stepPrep(),
            'fix-aliados' => fn() => $this->stepFixAliados(),
            '01'          => fn() => $this->step01_RazonesSociales(),
            '02'          => fn() => $this->step02_Usuarios(),
            '03'          => fn() => $this->step03_Empresas(),
            '04'          => fn() => $this->step04_AsesoresBancos(),
            '05'          => fn() => $this->step05_Clientes(),
            '06'          => fn() => $this->step06_Contratos(),
            '07'          => fn() => $this->step07_Facturas(),
            '08'          => fn() => $this->step08_Beneficiarios(),
            '09'          => fn() => $this->step09_Planos(),
            '10'          => fn() => $this->step10_Abonos(),
            '11'          => fn() => $this->step11_DocumentosCliente(),
            '12'          => fn() => $this->step12_Gastos(),
            '13'          => fn() => $this->step13_Incapacidades(),
            '14'          => fn() => $this->step14_GestionesIncapacidad(),
            'prep'          => fn() => $this->stepPrep(),
            'fix-modalidad'        => fn() => $this->stepFixModalidad(),
            'fix-plan'             => fn() => $this->stepFixPlan(),
            'fix-narl'             => fn() => $this->stepFixNarl(),
            'fix-valoresfacturas'  => fn() => $this->stepFixValoresFacturas(),
            'fix-independiente'    => fn() => $this->stepFixIndependiente(),
            'fix-planos'           => fn() => $this->stepFixPlanos(),
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
        $attempts = 0;
        $maxAttempts = 5;

        while ($attempts < $maxAttempts) {
            try {
                $aliados = DB::table('aliados')->get(['id', 'nombre', 'nit']);
                foreach ($aliados as $a) {
                    if ($a->nit === '901918923')      $this->ids['brygar']    = $a->id;
                    if ($a->nombre === 'GiMave Integral') $this->ids['gimave']    = $a->id;
                    if ($a->nombre === 'Grupo Fecop')     $this->ids['fecop']     = $a->id;
                    if ($a->nombre === 'Luis Lopez')      $this->ids['luislopez'] = $a->id;
                    if ($a->nombre === 'Mave Anderson')   $this->ids['mave']      = $a->id;
                    if ($a->nombre === 'SS Faga')         $this->ids['faga']      = $a->id;
                }
                $this->line('IDs aliados: ' . json_encode($this->ids));
                return; // éxito
            } catch (\Exception $e) {
                $attempts++;
                if ($attempts >= $maxAttempts) {
                    throw $e; // falló todos los intentos
                }
                $this->warn("  ⚠ Conexión fallida (intento $attempts/$maxAttempts), reintentando en 3s...");
                sleep(3);
            }
        }
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
            $exists = DB::select("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?", [$t]);
            if ($exists[0]->cnt) {
                DB::statement("DELETE FROM $t");
                $this->line("  🗑  $t vaciada");
            } else {
                $this->warn("  ⚠  $t no existe, se omite");
            }
        }

        DB::statement('EXEC sp_MSforeachtable \'ALTER TABLE ? WITH CHECK CHECK CONSTRAINT ALL\'');

        // Insertar aliados si no existen
        // ⚠ Los aliados sin NIT real usan un NIT ficticio único (LEGACY_XXX)
        //   para evitar la restricción UNIQUE(nit) con múltiples NULLs en SQL Server
        $aliados = [
            ['nombre' => 'Brygar',         'nit' => '901918923',  'razon_social' => 'Brygar SAS'],
            ['nombre' => 'GiMave Integral', 'nit' => 'LEGACY_002', 'razon_social' => 'GiMave Integral'],
            ['nombre' => 'Grupo Fecop',    'nit' => 'LEGACY_003',  'razon_social' => 'Grupo Fecop'],
            ['nombre' => 'Luis Lopez',     'nit' => 'LEGACY_004',  'razon_social' => 'Luis Lopez'],
            ['nombre' => 'Mave Anderson',  'nit' => 'LEGACY_005',  'razon_social' => 'Mave Anderson'],
            ['nombre' => 'SS Faga',        'nit' => 'LEGACY_006',  'razon_social' => 'SS Faga'],
        ];
        foreach ($aliados as $a) {
            $exists = DB::table('aliados')->where('nombre', $a['nombre'])->exists();
            if (!$exists) {
                DB::table('aliados')->insert(array_merge($a, ['activo' => true, 'created_at' => now(), 'updated_at' => now()]));
                $this->line("  ✅ Aliado creado: {$a['nombre']}");
            } else {
                $this->line("  ℹ  Aliado ya existe: {$a['nombre']}");
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

    // ─── PASO FIX-ALIADOS: Crear aliados faltantes sin borrar datos ──────────
    private function stepFixAliados(): void
    {
        $aliados = [
            ['nombre' => 'Brygar',          'nit' => '901918923',  'razon_social' => 'Brygar SAS'],
            ['nombre' => 'GiMave Integral', 'nit' => 'LEGACY_002', 'razon_social' => 'GiMave Integral'],
            ['nombre' => 'Grupo Fecop',     'nit' => 'LEGACY_003', 'razon_social' => 'Grupo Fecop'],
            ['nombre' => 'Luis Lopez',      'nit' => 'LEGACY_004', 'razon_social' => 'Luis Lopez'],
            ['nombre' => 'Mave Anderson',   'nit' => 'LEGACY_005', 'razon_social' => 'Mave Anderson'],
            ['nombre' => 'SS Faga',         'nit' => 'LEGACY_006', 'razon_social' => 'SS Faga'],
        ];
        foreach ($aliados as $a) {
            $existing = DB::table('aliados')->where('nombre', $a['nombre'])->first();
            if (!$existing) {
                DB::table('aliados')->insert(array_merge($a, ['activo' => true, 'created_at' => now(), 'updated_at' => now()]));
                $id = DB::table('aliados')->where('nombre', $a['nombre'])->value('id');
                $this->info("  ✅ Creado: {$a['nombre']} (ID=$id)");
            } else {
                $this->line("  ℹ  Ya existe: {$a['nombre']} (ID={$existing->id})");
            }
        }
        $this->loadAliados();
        $this->info('IDs actualizados: ' . json_encode($this->ids));
    }

    // ─── PASO 01: RAZONES SOCIALES ───────────────────────────────────────────
    // NOTA: razones_sociales.id es PK manual (sin IDENTITY).
    //       Usamos un contador PHP para asignar IDs secuenciales únicos.
    //       El ID original del legacy se guarda en id_legacy.
    private function step01_RazonesSociales(): void
    {
        DB::statement('ALTER TABLE razones_sociales NOCHECK CONSTRAINT ALL');

        // Obtener el próximo ID disponible (por si se reinicia el paso)
        $nextId = (int) DB::table('razones_sociales')->max('id') + 1;
        $this->line("  ℹ  Iniciando IDs desde: $nextId");

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) { $this->warn("  ⚠ Aliado '$key' no encontrado, se omite"); continue; }

            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Razon_Social");
            $count = 0;

            foreach ($rows as $r) {
                DB::table('razones_sociales')->insert([
                    'id'                  => $nextId++,   // PK manual secuencial
                    'aliado_id'           => $aliadoId,
                    'id_legacy'           => $r->ID,      // ID original (puede ser negativo)
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
                    'arl_nit'             => is_numeric($r->ARL)  && $r->ARL  > 1 ? (int)$r->ARL  : null,
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
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count razones sociales");
        }
        DB::statement('ALTER TABLE razones_sociales WITH CHECK CHECK CONSTRAINT ALL');
        $this->info('  📊 Total razones_sociales: ' . DB::table('razones_sociales')->count());
    }

    // ─── PASO 02: USUARIOS ───────────────────────────────────────────────────
    private function step02_Usuarios(): void
    {
        DB::statement('ALTER TABLE users NOCHECK CONSTRAINT ALL');
        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) { $this->warn("  ⚠ Aliado '$key' no encontrado, se omite"); continue; }

            // Cargar id_legacy ya migrados para reanudar
            $yaExisten = DB::table('users')
                ->where('aliado_id', $aliadoId)
                ->pluck('id_legacy')->filter()->flip()->all();
            $existingCount = count($yaExisten);
            if ($existingCount > 0) {
                $this->line("  ℹ  $db: $existingCount usuarios ya migrados, insertando faltantes...");
            }

            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.usuarios WHERE Id_usuario IS NOT NULL");
            $count = 0; $skipped = 0;
            foreach ($rows as $r) {
                if (isset($yaExisten[$r->Id_usuario])) { $skipped++; continue; }

                $login  = trim($r->Login ?? 'usuario');
                $cedula = is_numeric($r->Cedula) && $r->Cedula > 0 ? (string)(int)$r->Cedula : null;
                DB::table('users')->insert([
                    'aliado_id'  => $aliadoId,
                    'id_legacy'  => $r->Id_usuario,
                    'nombre'     => $login,
                    'email'      => strtolower(str_replace(' ', '.', $login)) . "_{$key}_{$r->Id_usuario}@legacy.local",
                    'password'   => bcrypt($r->Password ?? 'changeme123'),
                    'cedula'     => $cedula,
                    'activo'     => 1,
                    'es_brynex'  => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count insertados, $skipped omitidos");
        }
        DB::statement('ALTER TABLE users WITH CHECK CHECK CONSTRAINT ALL');
        $this->info('  📊 Total users: ' . DB::table('users')->count());
    }

    // ─── PASO 03: EMPRESAS ───────────────────────────────────────────────────
    private function step03_Empresas(): void
    {
        DB::statement('ALTER TABLE empresas NOCHECK CONSTRAINT ALL');

        $nextId = (int) DB::table('empresas')->max('id') + 1;
        $this->line("  ℹ  Iniciando IDs desde: $nextId");

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) { $this->warn("  ⚠ Aliado '$key' no encontrado, se omite"); continue; }

            $yaExisten = DB::table('empresas')->where('aliado_id', $aliadoId)
                ->pluck('id_legacy')->filter()->flip()->all();
            if (count($yaExisten) > 0) $this->line("  ℹ  $db: " . count($yaExisten) . " empresas ya migradas, insertando faltantes...");

            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Empresas");
            $count = 0; $skipped = 0;
            foreach ($rows as $r) {
                if (isset($yaExisten[$r->Id])) { $skipped++; continue; }
                DB::table('empresas')->insert([
                    'id'                  => $nextId++,
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
            $this->info("  ✅ $db → $count insertadas, $skipped omitidas");
        }
        DB::statement('ALTER TABLE empresas WITH CHECK CHECK CONSTRAINT ALL');
        $this->info('  📊 Total empresas: ' . DB::table('empresas')->count());
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
            $asesoresExisten = DB::table('asesores')->where('aliado_id', $aliadoId)
                ->pluck('id_legacy')->filter()->flip()->all();
            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Asesores");
            $count = 0; $skipped = 0;
            foreach ($rows as $r) {
                $id = $this->col($r, 'ID');
                if ($id === null) continue;
                if (isset($asesoresExisten[$id])) { $skipped++; continue; }
                DB::table('asesores')->insert([
                    'aliado_id'          => $aliadoId,
                    'id_legacy'          => $id,
                    'cedula'             => (string)$id,
                    'nombre'             => trim($this->col($r, 'Nombre') ?? 'Sin nombre'),
                    'telefono'           => trim($this->col($r, 'Telefono') ?? ''),
                    'direccion'          => trim($this->col($r, 'DIreccion') ?? ''),
                    'ciudad'             => trim($this->col($r, 'Ciudad') ?? ''),
                    'departamento'       => trim($this->col($r, 'Departamento') ?? ''),
                    'cuenta_bancaria'    => trim($this->col($r, 'Cuenta_Bancaria') ?? ''),
                    'fecha_ingreso'      => $this->col($r, 'Fecha_Ingreso') ? substr($this->col($r, 'Fecha_Ingreso'), 0, 10) : null,
                    'activo'             => strtolower(trim($this->col($r, 'Activo') ?? '')) === 'activo' ? 1 : 0,
                    'id_original_access' => $id,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count asesores, $skipped omitidos");

            // Bancos
            $bancosExisten = DB::table('banco_cuentas')->where('aliado_id', $aliadoId)
                ->pluck('id_legacy')->filter()->flip()->all();
            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Bancos_cuentas");
            $count = 0; $skipped = 0;
            foreach ($rows as $r) {
                $id = $this->col($r, 'ID');
                if ($id === null) continue;
                if (isset($bancosExisten[$id])) { $skipped++; continue; }
                DB::table('banco_cuentas')->insert([
                    'aliado_id'     => $aliadoId,
                    'id_legacy'     => $id,
                    'nombre'        => trim($this->col($r, 'NOMBRE') ?? ''),
                    'nit'           => trim($this->col($r, 'NIT') ?? ''),
                    'banco'         => trim($this->col($r, 'BANCO') ?? ''),
                    'tipo_cuenta'   => trim($this->col($r, 'TIPO') ?? ''),
                    'numero_cuenta' => trim($this->col($r, 'NUMERO') ?? ''),
                    'activo'        => 1,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count cuentas bancarias, $skipped omitidas");
        }

        DB::statement('ALTER TABLE asesores WITH CHECK CHECK CONSTRAINT ALL');
        DB::statement('ALTER TABLE banco_cuentas WITH CHECK CHECK CONSTRAINT ALL');
    }

    // ─── PASO 05: CLIENTES ───────────────────────────────────────────────────
    private function step05_Clientes(): void
    {
        DB::statement('ALTER TABLE clientes NOCHECK CONSTRAINT ALL');

        $nextId = (int) DB::table('clientes')->max('id') + 1;
        $this->line("  ℹ  Iniciando IDs desde: $nextId");

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) { $this->warn("  ⚠ Aliado '$key' no encontrado, se omite"); continue; }

            // Cargar id_legacy ya existentes para este aliado (para reanudar)
            $yaExisten = DB::table('clientes')
                ->where('aliado_id', $aliadoId)
                ->pluck('id_legacy')
                ->flip()  // convertir a lookup O(1)
                ->all();
            $existingCount = count($yaExisten);
            if ($existingCount > 0) {
                $this->line("  ℹ  $db: $existingCount ya migrados, insertando faltantes...");
            }

            $total = DB::connection('sqlsrv_legacy')
                ->selectOne("SELECT COUNT(*) as cnt FROM [$db].dbo.Base_De_Datos")->cnt;
            $this->line("  ⏳ $db: $total total, " . ($total - $existingCount) . " faltantes...");

            $count = 0; $skipped = 0; $offset = 0; $chunk = 500;
            while (true) {
                $rows = $this->legacySelect("SELECT * FROM [$db].dbo.Base_De_Datos ORDER BY Id OFFSET $offset ROWS FETCH NEXT $chunk ROWS ONLY");
                if (empty($rows)) break;
                foreach ($rows as $r) {
                    // Saltar si ya existe este id_legacy para este aliado
                    if (isset($yaExisten[$r->Id])) { $skipped++; continue; }

                    $epsId     = $this->lookupByNit('eps',       $r->Eps);
                    $pensionId = $this->lookupByNit('pensiones',  $r->Pension);
                    DB::table('clientes')->insert([
                        'id'                  => $nextId++,
                        'aliado_id'           => $aliadoId,
                        'id_legacy'           => $r->Id,
                    'cod_empresa'         => $r->COD_EMPRESA,  // remap al final
                    'tipo_doc'            => substr(trim($r->TIPO_DOC ?? ''), 0, 10),
                    'cedula'              => $r->Cedula,
                    'primer_nombre'       => substr(trim($r->{'1_NOMBRE'} ?? ''), 0, 55),
                    'segundo_nombre'      => substr(trim($r->{'2_NOMBRE'} ?? ''), 0, 55),
                    'primer_apellido'     => substr(trim($r->{'1_APELLIDO'} ?? ''), 0, 55),
                    'segundo_apellido'    => substr(trim($r->{'2_APELLIDO'} ?? ''), 0, 55),
                    'genero'              => substr(trim($r->Genero ?? ''), 0, 10),
                    'sisben'              => substr(trim($r->Sisben ?? ''), 0, 50),
                    'fecha_nacimiento'    => $r->Fecha_Nacimiento ? substr($r->Fecha_Nacimiento, 0, 10) : null,
                    'fecha_expedicion'    => $r->Fecha_Expedicion ? substr($r->Fecha_Expedicion, 0, 10) : null,
                    'rh'                  => substr(trim($r->RH ?? ''), 0, 10),
                    'telefono'            => substr(trim($r->Telefono ?? ''), 0, 20),
                    'celular'             => is_numeric($r->Celular) && $r->Celular > 0 ? substr((string)(int)$r->Celular, 0, 20) : null,
                    'correo'              => substr(trim($r->Correo ?? ''), 0, 100),
                    'departamento_id'     => $r->Departamento,
                    'municipio_id'        => is_numeric($r->Municipio) ? (int)$r->Municipio : null,
                    'direccion_vivienda'  => substr(trim($r->Direccion_Vivienda ?? ''), 0, 150),
                    'direccion_cobro'     => substr(trim($r->Direccion_Cobro ?? ''), 0, 150),
                    'barrio'              => substr(trim($r->Barrio ?? ''), 0, 80),
                    'eps_id'              => $epsId,
                    'pension_id'          => $pensionId,
                    'ips'                 => substr(trim($r->IPS ?? ''), 0, 100),
                    'urgencias'           => substr(trim($r->URGENCIAS ?? ''), 0, 100),
                    'iva'                 => substr(trim($r->IVA ?? ''), 0, 20),
                    'ocupacion'           => substr(trim($r->Ocupacion ?? ''), 0, 80),
                    'referido'            => substr(trim($r->Referido ?? ''), 0, 80),
                    'observacion'         => trim($r->Observacion ?? ''),
                    'observacion_llamada' => (string)($r->Observacion_llamada ?? ''),
                    'deuda'               => $r->DEUDA,
                    'fecha_probable_pago' => substr(trim($r->Fecha_problable_pago ?? ''), 0, 50),
                    'modo_probable_pago'  => substr(trim($r->Modo_propable_pago ?? ''), 0, 50),
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                    $count++;
                    if ($count % 100 === 0) $this->line("    → $count / $total...");
                }
                $offset += $chunk;
                if (count($rows) < $chunk) break;
            }

            $this->info("  ✅ $db → $count insertados, $skipped omitidos (ya existían)");
            DB::statement("
                UPDATE c SET c.cod_empresa = e.id
                FROM clientes c JOIN empresas e
                  ON e.id_legacy = c.cod_empresa AND e.aliado_id = c.aliado_id
                WHERE c.aliado_id = ? AND c.cod_empresa IS NOT NULL
            ", [$aliadoId]);
            $this->line("     Remap empresa: OK");
        }

        DB::statement('ALTER TABLE clientes WITH CHECK CHECK CONSTRAINT ALL');
        $this->info('  📊 Total clientes: ' . DB::table('clientes')->count());
    }

    // ─── PASO 06: CONTRATOS ──────────────────────────────────────────────────
    private function step06_Contratos(): void
    {
        DB::statement('ALTER TABLE contratos NOCHECK CONSTRAINT ALL');
        DB::statement('ALTER TABLE radicados NOCHECK CONSTRAINT ALL');

        // Mapa texto legacy → motivo_afiliacion_id en BryNex
        $mapAfiliacion = [
            'nueva afiliacion'   => 1, 'nueva afiliación'  => 1,
            'cambio de plan'     => 2,
            'cambio razon social'=> 3, 'cambio razón social'=> 3,
            'recuperado'         => 4,
            'error'              => 5,
            'omiso'              => 6,
        ];

        // Mapa texto legacy → motivo_retiro_id en BryNex
        $mapRetiro = [
            'retiro real'            => 1,
            'retiro-reingreso'       => 2, 'retiro reingreso'  => 2,
            'fallecimiento'          => 3,
            'pension'                => 4, 'pensión'           => 4,
            'traslado empresa'       => 5,
            'cambio razon social'    => 6, 'cambio razón social'=> 6,
            'incumplimiento de pago' => 7,
            'solicitud del cliente'  => 8,
            'otro'                   => 9,
        ];

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) { $this->warn("  ⚠ Aliado '$key' no encontrado, se omite"); continue; }

            // Cargar contratos ya migrados para reanudar
            $yaExisten = DB::table('contratos')
                ->where('aliado_id', $aliadoId)
                ->pluck('id_legacy')->filter()->flip()->all();
            $existingCount = count($yaExisten);

            $total = DB::connection('sqlsrv_legacy')
                ->selectOne("SELECT COUNT(*) as cnt FROM [$db].dbo.Contratos")->cnt;
            $this->line("  ⏳ $db: $total total, " . ($total - $existingCount) . " faltantes...");

            $count = 0; $skipped = 0; $offset = 0; $chunk = 500;
            while (true) {
                $rows = $this->legacySelect("SELECT * FROM [$db].dbo.Contratos ORDER BY Id OFFSET $offset ROWS FETCH NEXT $chunk ROWS ONLY");
                if (empty($rows)) break;

                foreach ($rows as $r) {
                    if (isset($yaExisten[$r->Id])) { $skipped++; continue; }

                    $razonId      = DB::table('razones_sociales')->where('aliado_id', $aliadoId)->where('id_legacy', $this->col($r, 'COD_RAZON_SOC'))->value('id');
                    $asesorId     = DB::table('asesores')->where('aliado_id', $aliadoId)->where('id_legacy', $this->col($r, 'Asesor'))->value('id');
                    $userId       = DB::table('users')->where('aliado_id', $aliadoId)->where('id_legacy', $this->col($r, 'Encargado_Afiliacion'))->value('id');
                    $epsId        = $this->lookupByNit('eps',      $this->col($r, 'Eps_c'));
                    $pensionId    = $this->lookupByNit('pensiones', $this->col($r, 'Pension_c'));
                    $arlVal       = $this->col($r, 'ARL');
                    $arlId        = is_numeric($arlVal) && $arlVal > 1 ? $this->lookupByNit('arls', $arlVal) : null;
                    $cajaId       = $this->lookupByNit('cajas',    $this->col($r, 'Caja_Comp'));
                    $motivoAfil   = $mapAfiliacion[strtolower(trim($this->col($r, 'Motivo_Afiliacion') ?? ''))] ?? null;
                    $motivoRetiro = $mapRetiro[strtolower(trim($this->col($r, 'Motivo_Retiro') ?? ''))]         ?? null;

                    $contratoId = DB::table('contratos')->insertGetId([
                        'aliado_id'              => $aliadoId,
                        'id_legacy'              => $r->Id,
                        'cedula'                 => $this->col($r, 'Cedula'),
                        'estado'                 => strtolower(trim($this->col($r, 'Estado') ?? 'vigente')),
                        'razon_social_id'        => $razonId,
                        'asesor_id'              => $asesorId,
                        'encargado_id'           => $userId,
                        'eps_id'                 => $epsId,
                        'pension_id'             => $pensionId,
                        'arl_id'                 => $arlId,
                        'caja_id'                => $cajaId,
                        'n_arl'                  => is_numeric($this->col($r, 'N_ARL')) && $this->col($r, 'N_ARL') >= 1 && $this->col($r, 'N_ARL') <= 5 ? (int)$this->col($r, 'N_ARL') : null,
                        'cargo'                  => substr(trim($this->col($r, 'Cargo') ?? ''), 0, 100),
                        'fecha_ingreso'          => $this->col($r, 'Fecha_Ingreso')  ? substr($this->col($r, 'Fecha_Ingreso'),  0, 10) : null,
                        'fecha_retiro'           => $this->col($r, 'Fecha_Retiro')   ? substr($this->col($r, 'Fecha_Retiro'),   0, 10) : null,
                        'fecha_arl'              => $this->col($r, 'Fecha_ARL')      ? substr($this->col($r, 'Fecha_ARL'),      0, 10) : null,
                        'fecha_created'          => $this->col($r, 'Fecha_Created')  ? substr($this->col($r, 'Fecha_Created'),  0, 10) : null,
                        'salario'                => is_numeric($this->col($r, 'Salario_M'))        ? $this->col($r, 'Salario_M')        : 0,
                        'administracion'         => is_numeric($this->col($r, 'Administracion'))   ? $this->col($r, 'Administracion')   : 0,
                        'admon_asesor'           => is_numeric($this->col($r, 'admon_asesor'))     ? $this->col($r, 'admon_asesor')     : 0,
                        'costo_afiliacion'       => is_numeric($this->col($r, 'costo_afiliacion')) ? $this->col($r, 'costo_afiliacion') : 0,
                        'seguro'                 => is_numeric($this->col($r, 'Seguro'))           ? $this->col($r, 'Seguro')           : 0,
                        'np'                     => substr(trim($this->col($r, 'NP') ?? ''), 0, 50),
                        'envio_planilla'         => substr(trim($this->col($r, 'Envio_Planilla') ?? ''), 0, 50),
                        'fecha_probable_pago'    => substr(trim($this->col($r, 'Fecha_problable_pago') ?? ''), 0, 50),
                        'modo_probable_pago'     => substr(trim($this->col($r, 'Modo_propable_pago') ?? ''), 0, 50),
                        'observacion'            => trim($this->col($r, 'Observacion') ?? ''),
                        'observacion_afiliacion' => trim($this->col($r, 'Observacion_Afiliacion') ?? ''),
                        'observacion_llamada'    => (string)($this->col($r, 'Observacion_llamada') ?? ''),
                        // Tipo: el campo legacy es el ID directo del catalogo tipo_modalidad.
                        // Admitimos 0 (Tipo E) y negativos. Si no existe, inferimos por entidades.
                        'tipo_modalidad_id'      => $this->resolveTipoModalidad(
                                                       $this->col($r, 'Tipo'),
                                                       $epsId, $arlId, $pensionId, $cajaId),
                        'actividad_economica_id' => is_numeric($this->col($r, 'Actividad_Economica')) && $this->col($r, 'Actividad_Economica') > 0 ? (int)$this->col($r, 'Actividad_Economica') : null,
                        'motivo_afiliacion_id'   => $motivoAfil,
                        'motivo_retiro_id'       => $motivoRetiro,
                        'created_at'             => now(),
                        'updated_at'             => now(),
                    ]);

                    // Insertar radicados legacy en tabla radicados (eps, arl, caja, pension)
                    $radicadosLegacy = [
                        'eps'     => trim($r->Radicado_EPS ?? ''),
                        'arl'     => trim($r->Radicado_ARL ?? ''),
                        'caja'    => trim($r->Radicado_Caja ?? ''),
                        'pension' => trim($r->Radicado_Pension ?? ''),
                    ];
                    foreach ($radicadosLegacy as $tipo => $numero) {
                        if ($numero !== '') {
                            DB::table('radicados')->insert([
                                'contrato_id'     => $contratoId,
                                'aliado_id'       => $aliadoId,
                                'tipo'            => $tipo,
                                'numero_radicado' => $numero,
                                'estado'          => 'confirmado',
                                'created_at'      => now(),
                                'updated_at'      => now(),
                            ]);
                        }
                    }

                    $count++;
                    if ($count % 100 === 0) $this->line("    → $count / $total...");
                }
                $offset += $chunk;
                if (count($rows) < $chunk) break;
            }
            $this->info("  ✅ $db → $count insertados, $skipped omitidos");
        }

        // Limpiar FK huérfanos antes de rehabilitar constraints
        DB::statement("UPDATE contratos SET actividad_economica_id = NULL WHERE actividad_economica_id IS NOT NULL AND actividad_economica_id NOT IN (SELECT id FROM actividades_economicas)");
        DB::statement("UPDATE contratos SET asesor_id      = NULL WHERE asesor_id      IS NOT NULL AND asesor_id      NOT IN (SELECT id FROM asesores)");
        DB::statement("UPDATE contratos SET encargado_id   = NULL WHERE encargado_id   IS NOT NULL AND encargado_id   NOT IN (SELECT id FROM users)");
        DB::statement("UPDATE contratos SET razon_social_id = NULL WHERE razon_social_id IS NOT NULL AND razon_social_id NOT IN (SELECT id FROM razones_sociales)");
        DB::statement('ALTER TABLE contratos WITH CHECK CHECK CONSTRAINT ALL');

        DB::statement('ALTER TABLE radicados WITH CHECK CHECK CONSTRAINT ALL');

        $this->info('  📊 Total contratos: '  . DB::table('contratos')->count());
        $this->info('  📊 Total radicados: '  . DB::table('radicados')->count());
        $this->warn('  ⚠  Sin razon_social: ' . DB::table('contratos')->whereNull('razon_social_id')->count());
    }

    // ─── HELPER: lookup por NIT en tabla global ──────────────────────────────
    private function lookupByNit(string $tabla, mixed $nit): ?int
    {
        if (!is_numeric($nit) || (float)$nit <= 1) return null;
        return DB::table($tabla)->where('nit', (int)(float)$nit)->value('id');
    }

    // ─── HELPER: Resuelve tipo_modalidad_id ────────────────────────────────────
    // 1) Si el campo Tipo del legacy es un ID válido del catálogo, lo usa.
    // 2) Si no, infiere la modalidad por combinación de entidades activas.
    //
    // Catálogo BryNex (tipo_modalidad):
    //   0  = E        (Dependiente completo: EPS+ARL+CAJA+PENSION)
    //   6  = EPS      (Solo EPS)
    //   7  = EPS+ARL  (EPS + ARL, sin caja ni pension)
    //   10 = I Venc   (Independiente vencido: EPS+PENSION)
    //   11 = I Act    (Independiente actual: EPS+PENSION)
    //   8  = Y        (ARL tipo Y)
    //   5  = CS       (Contribucion solidaria)
    private function resolveTipoModalidad(
        mixed $tipoLegacy,
        ?int $epsId, ?int $arlId, ?int $pensionId, ?int $cajaId
    ): ?int {
        // IDs válidos en el catálogo tipo_modalidad
        static $validIds = [-100,-8,-7,-6,-4,-1,0,1,2,3,4,5,6,7,8,10,11,12,13];

        // Prioridad 1: usar el campo Tipo si es un ID del catálogo
        if ($tipoLegacy !== null && is_numeric($tipoLegacy)) {
            $t = (int)$tipoLegacy;
            if (in_array($t, $validIds, true)) return $t;
        }

        // Prioridad 2: inferir por combinación de entidades activas
        $hasEps     = $epsId     !== null;
        $hasArl     = $arlId     !== null;
        $hasPension = $pensionId !== null;
        $hasCaja    = $cajaId    !== null;

        if ($hasEps && $hasArl && $hasCaja && $hasPension) return 0;   // E completo
        if ($hasEps && $hasArl && !$hasCaja && !$hasPension) return 7;  // EPS+ARL
        if ($hasEps && !$hasArl && !$hasPension && !$hasCaja) return 6; // Solo EPS
        if ($hasEps && $hasPension && !$hasArl && !$hasCaja) return 10; // I Venc
        if ($hasEps && $hasArl && $hasCaja && !$hasPension) return 7;   // EPS+ARL+CAJA → EPS+ARL
        if ($hasEps && $hasPension && $hasCaja && !$hasArl) return 10;  // EPS+PENSION+CAJA → I Venc
        if (!$hasEps && $hasPension) return 10;                          // Solo pension → I Venc
        if ($hasEps) return 6;                                           // EPS + algo → Solo EPS
        return null;
    }

    // ─── HELPER: acceso a propiedad case-insensitive en stdClass ───────────────
    private function col(object $row, string $name): mixed
    {
        if (property_exists($row, $name)) return $row->$name;
        $arr = (array)$row;
        $lower = strtolower($name);
        foreach ($arr as $key => $val) {
            if (strtolower($key) === $lower) return $val;
        }
        return null;
    }

    // ─── HELPER: SELECT legacy con reintentos en timeout ─────────────────────
    // Reconecta y reintenta hasta $maxTries veces en caso de HYT00 / login timeout.
    private function legacySelect(string $sql, int $maxTries = 5): array
    {
        $try = 0;
        while (true) {
            try {
                DB::connection('sqlsrv_legacy')->reconnect();
                return DB::connection('sqlsrv_legacy')->select($sql);
            } catch (\Exception $e) {
                $try++;
                $msg = $e->getMessage();
                $isTimeout = str_contains($msg, 'HYT00')
                          || str_contains($msg, 'timeout')
                          || str_contains($msg, 'Login timeout');
                if (!$isTimeout || $try >= $maxTries) throw $e;
                $wait = min(30, $try * 5);
                $this->warn("  ⚠ Timeout legacy (intento $try/$maxTries), reintentando en {$wait}s...");
                sleep($wait);
            }
        }
    }

    // ─── PASO 07: FACTURAS + CONSIGNACIONES ──────────────────────────────────
    // Legacy: FACTURACION  →  BryNex: facturas + consignaciones
    // tipo legacy "mensualidad" → 'planilla'; resto → 'afiliacion'
    private function step07_Facturas(): void
    {
        DB::statement('ALTER TABLE facturas      NOCHECK CONSTRAINT ALL');
        DB::statement('ALTER TABLE consignaciones NOCHECK CONSTRAINT ALL');

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) { $this->warn("  ⚠ Aliado '$key' no encontrado, se omite"); continue; }

            $yaExisten = DB::table('facturas')->where('aliado_id', $aliadoId)
                ->pluck('id_legacy')->filter()->flip()->all();
            $total = DB::connection('sqlsrv_legacy')
                ->selectOne("SELECT COUNT(*) as cnt FROM [$db].dbo.FACTURACION")->cnt;
            $this->line("  ⏳ $db: $total facturas, " . ($total - count($yaExisten)) . " faltantes...");

            $count = 0; $skipped = 0; $offset = 0; $chunk = 500;
            while (true) {
                $rows = $this->legacySelect("SELECT * FROM [$db].dbo.FACTURACION ORDER BY Id_Factura OFFSET $offset ROWS FETCH NEXT $chunk ROWS ONLY");
                if (empty($rows)) break;

                foreach ($rows as $r) {
                    if (isset($yaExisten[$r->Id_Factura])) { $skipped++; continue; }

                    $contratoId = DB::table('contratos')
                        ->where('aliado_id', $aliadoId)
                        ->where('id_legacy', $r->Id_Contrato)
                        ->value('id');

                    // TIPO: mensualidad→planilla, retiro→retiro, resto→afiliacion
                    $tipoRaw = strtolower(trim($r->Tipo ?? ''));
                    if ($tipoRaw === 'mensualidad') {
                        $tipo = 'planilla';
                    } elseif ($tipoRaw === 'retiro') {
                        $tipo = 'retiro';
                    } else {
                        $tipo = 'afiliacion';
                    }

                    // ── VALORES ──────────────────────────────────────────────
                    // BUG#1 CORREGIDO: Pago=0 en consignaciones puras → usar Valor_Consignado
                    $valCons  = is_numeric($r->Valor_Consignado) ? (int)$r->Valor_Consignado : 0;
                    $valTotal = (is_numeric($r->Pago) && (int)$r->Pago > 0)
                        ? (int)$r->Pago
                        : $valCons;  // total real = Valor_Consignado cuando Pago está vacío

                    $valEfect = max(0, $valTotal - $valCons);

                    // ── FORMA_PAGO ────────────────────────────────────────────
                    // BUG#2 CORREGIDO: 'Consignacion' es la REFERENCIA de texto (ej: 'TRF-123'),
                    // NO el ID del banco. Solo 'COD' es el ID numérico de la cuenta bancaria.
                    // La detección de consignación debe basarse en Valor_Consignado > 0.
                    $refConsignacion = trim($this->col($r, 'Consignacion') ?? ''); // texto referencia
                    $codBanco        = $this->col($r, 'COD') ?? null;              // ID numérico banco

                    if ($valCons > 0) {
                        // Hay valor consignado: es consignación o mixto
                        $formaPago = $valEfect > 0 ? 'mixto' : 'consignacion';
                    } else {
                        // Sin valor consignado: todo en efectivo
                        $formaPago = 'efectivo';
                        $valEfect  = $valTotal;
                    }

                    // CEDULA y EMPRESA_ID:
                    // En legacy, si cedula < 100000 es un id_legacy de empresa, no cédula real.
                    $cedulaLegacy = is_numeric($r->Cedula) ? (int)$r->Cedula : 0;
                    $empresaId    = null;
                    $cedulaFinal  = $cedulaLegacy;
                    if ($cedulaLegacy > 0 && $cedulaLegacy < 100000) {
                        // Es id_legacy de empresa → buscar el nuevo id en BryNex
                        $empresaId   = DB::table('empresas')
                            ->where('aliado_id', $aliadoId)
                            ->where('id_legacy', $cedulaLegacy)
                            ->value('id');
                        // Obtener la cédula real del contrato relacionado
                        $cedulaFinal = DB::table('contratos')
                            ->where('id', $contratoId)
                            ->value('cedula') ?? $cedulaLegacy;
                    }

                    // USUARIO_ID: buscar el nuevo id en users por id_legacy
                    $usuarioLegacy = $this->col($r, 'Usuario') ?? $this->col($r, 'usuario_id') ?? null;
                    $usuarioId     = null;
                    if (is_numeric($usuarioLegacy) && (int)$usuarioLegacy > 0) {
                        $usuarioId = DB::table('users')
                            ->where('aliado_id', $aliadoId)
                            ->where('id_legacy', (int)$usuarioLegacy)
                            ->value('id');
                    }

                    $facturaId = DB::table('facturas')->insertGetId([
                        'aliado_id'          => $aliadoId,
                        'id_legacy'          => $r->Id_Factura,
                        'contrato_id'        => $contratoId,
                        'cedula'             => $cedulaFinal,
                        'empresa_id'         => $empresaId,
                        'usuario_id'         => $usuarioId,
                        'numero_factura'     => is_numeric($r->Factura) ? (int)$r->Factura : 0,
                        'tipo'               => $tipo,
                        'mes'                => is_numeric($r->Mes)  ? (int)$r->Mes  : 1,
                        'anio'               => is_numeric($this->col($r, 'Año')) ? (int)$this->col($r, 'Año') : date('Y'),
                        'fecha_pago'         => $r->Fecha_Pago ? substr($r->Fecha_Pago, 0, 10) : now()->toDateString(),
                        'estado'             => 'pagada',
                        'forma_pago'         => $formaPago,
                        'valor_consignado'   => $valCons,
                        'valor_efectivo'     => $valEfect,
                        // np y n_plano son INT: extraer solo la parte numérica ('1P' → 1, '2A' → 2)
                        'np'      => ($npVal = preg_replace('/[^0-9]/', '', $this->col($r, 'NP') ?? '')) !== '' ? (int)$npVal : null,
                        'n_plano' => ($npVal = preg_replace('/[^0-9]/', '', $this->col($r, 'n_plano') ?? '')) !== '' ? (int)$npVal : null,
                        'v_eps'              => is_numeric($r->V_EPS)  ? (int)$r->V_EPS  : 0,
                        'v_arl'              => is_numeric($r->V_Arl)  ? (int)$r->V_Arl  : 0,
                        'v_afp'              => is_numeric($r->V_AFP)  ? (int)$r->V_AFP  : 0,
                        'v_caja'             => is_numeric($r->V_CAJA) ? (int)$r->V_CAJA : 0,
                        'total_ss'           => (int)($r->V_EPS ?? 0) + (int)($r->V_Arl ?? 0) + (int)($r->V_AFP ?? 0) + (int)($r->V_CAJA ?? 0),
                        'admon'              => is_numeric($r->Admon)           ? (int)$r->Admon           : 0,
                        'admin_asesor'       => is_numeric($r->admin_asesor)    ? (int)$r->admin_asesor    : 0,
                        'seguro'             => is_numeric($r->seguro)          ? (int)$r->seguro          : 0,
                        'afiliacion'         => is_numeric($r->Afiliaciones)    ? (int)$r->Afiliaciones    : 0,
                        'mensajeria'         => is_numeric($r->Mensajeria)      ? (int)$r->Mensajeria      : 0,
                        'otros'              => is_numeric($r->Otros)           ? (int)$r->Otros           : 0,
                        'iva'                => is_numeric($r->Iva)             ? (int)$r->Iva             : 0,
                        'total'              => $valTotal,
                        'observacion'        => trim(trim($r->Observacion ?? '') . ' ' . trim($r->OBS_FACTURA ?? '')),
                        'created_at'         => $r->fecha_creacion    ? substr($r->fecha_creacion,    0, 19) : now(),
                        'updated_at'         => $r->fecha_modificacion ? substr($r->fecha_modificacion, 0, 19) : now(),
                    ]);

                    // Registrar consignación en tabla consignaciones si aplica
                    if ($valCons > 0 && $formaPago !== 'efectivo') {
                        $bancoCuentaId = null;
                        // COD es el ID numérico de la cuenta bancaria en legacy
                        if (is_numeric($codBanco) && (int)$codBanco > 0) {
                            $bancoCuentaId = DB::table('banco_cuentas')
                                ->where('aliado_id', $aliadoId)
                                ->where('id_legacy', (int)$codBanco)
                                ->value('id');
                        }
                        // Fallback: primera cuenta del aliado
                        if (!$bancoCuentaId) {
                            $bancoCuentaId = DB::table('banco_cuentas')
                                ->where('aliado_id', $aliadoId)
                                ->value('id');
                        }
                        if ($bancoCuentaId) {
                            DB::table('consignaciones')->insert([
                                'aliado_id'      => $aliadoId,
                                'banco_cuenta_id'=> $bancoCuentaId,
                                'fecha'          => $r->Fecha_Pago ? substr($r->Fecha_Pago, 0, 10) : now()->toDateString(),
                                'valor'          => $valCons,
                                'referencia'     => $refConsignacion, // texto referencia bancaria
                                'confirmado'     => true,
                                'observacion'    => "Migrada de factura legacy #{$r->Id_Factura}",
                                'created_at'     => now(),
                                'updated_at'     => now(),
                            ]);
                        }
                    }

                    $count++;
                    if ($count % 200 === 0) $this->line("    → $count / $total...");
                }
                $offset += $chunk;
                if (count($rows) < $chunk) break;
            }
            $this->info("  ✅ $db → $count facturas, $skipped omitidas");
        }
        DB::statement('ALTER TABLE facturas      WITH CHECK CHECK CONSTRAINT ALL');
        DB::statement('ALTER TABLE consignaciones WITH CHECK CHECK CONSTRAINT ALL');
        $this->info('  📊 Total facturas: '      . DB::table('facturas')->count());
        $this->info('  📊 Total consignaciones: ' . DB::table('consignaciones')->count());
    }

    // ─── PASO 08: BENEFICIARIOS ──────────────────────────────────────────────
    private function step08_Beneficiarios(): void
    {
        DB::statement('ALTER TABLE beneficiarios NOCHECK CONSTRAINT ALL');
        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            // Verificar existencia de tabla
            $exists = DB::connection('sqlsrv_legacy')
                ->selectOne("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_CATALOG=? AND TABLE_NAME='Beneficiarios'", [$db]);
            if (!$exists || !$exists->cnt) {
                $this->warn("  ⚠ $db: tabla Beneficiarios no existe, se omite");
                continue;
            }

            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Beneficiarios");
            $count = 0;
            foreach ($rows as $r) {
                // Cedula del titular: puede llamarse Cedula_Titular, Cedula, CC_Titular
                $cedula = $this->col($r, 'Cedula_Titular')
                       ?? $this->col($r, 'Cedula')
                       ?? $this->col($r, 'CC_Titular')
                       ?? 0;

                // Nombre completo: puede venir junto o separado
                $nombre = $this->col($r, 'Nombres')
                       ?? trim(($this->col($r, 'Nombre') ?? '') . ' ' . ($this->col($r, 'Apellido') ?? ''));

                DB::table('beneficiarios')->insert([
                    'aliado_id'        => $aliadoId,
                    'cc_cliente'       => is_numeric($cedula) ? (int)$cedula : 0,
                    'tipo_doc'         => trim($this->col($r, 'Tipo_Doc') ?? 'CC'),
                    'n_documento'      => trim($this->col($r, 'N_Documento') ?? $this->col($r, 'Documento') ?? ''),
                    'nombres'          => trim($nombre),
                    'fecha_expedicion' => $this->col($r, 'Fecha_Expedicion') ? substr($this->col($r, 'Fecha_Expedicion'), 0, 10) : null,
                    'fecha_nacimiento' => $this->col($r, 'Fecha_Nacimiento') ? substr($this->col($r, 'Fecha_Nacimiento'), 0, 10) : null,
                    'parentesco'       => trim($this->col($r, 'Parentesco') ?? ''),
                    'observacion'      => trim($this->col($r, 'Observacion') ?? ''),
                    'fecha_ingreso'    => $this->col($r, 'Fecha_Ingreso') ? substr($this->col($r, 'Fecha_Ingreso'), 0, 10) : null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count beneficiarios");
        }
        DB::statement('ALTER TABLE beneficiarios WITH CHECK CHECK CONSTRAINT ALL');
        $this->info('  📊 Total beneficiarios: ' . DB::table('beneficiarios')->count());
    }

    // ─── PASO 09: PLANOS (PLANILLAS PILA) ────────────────────────────────────
    // id_factura en planos debe remapearse al nuevo ID de facturas
    private function step09_Planos(): void
    {
        DB::statement('ALTER TABLE planos NOCHECK CONSTRAINT ALL');
        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            // Skip check: por factura_id (cuando existe) O por clave compuesta cedula+mes+año+rs
            $facturasMigradas = DB::table('planos')
                ->where('aliado_id', $aliadoId)
                ->pluck('factura_id')->filter()->flip()->all();

            // Clave compuesta para evitar duplicados cuando factura_id es null
            $clavesMigradas = DB::table('planos')
                ->where('aliado_id', $aliadoId)
                ->selectRaw("CONCAT(no_identifi,'|',ISNULL(mes_plano,''),'|',ISNULL(anio_plano,''),'|',ISNULL(razon_social_id,''),'|',ISNULL(n_plano,'')) AS clave")
                ->pluck('clave')->flip()->all();

            $total = DB::connection('sqlsrv_legacy')
                ->selectOne("SELECT COUNT(*) as cnt FROM [$db].dbo.PLANOS")->cnt;
            $this->line("  ⏳ $db: $total planos...");

            $count = 0; $skipped = 0; $offset = 0; $chunk = 500;
            while (true) {
                $rows = $this->legacySelect("SELECT * FROM [$db].dbo.PLANOS ORDER BY Id OFFSET $offset ROWS FETCH NEXT $chunk ROWS ONLY");
                if (empty($rows)) break;
                foreach ($rows as $r) {
                    // factura_id: PLANOS.id_facturacion → FACTURACION.Id = facturas.id_legacy
                    $facturaId = DB::table('facturas')
                        ->where('aliado_id', $aliadoId)
                        ->where('id_legacy', $this->col($r, 'id_facturacion') ?? $this->col($r, 'Id_Facturacion') ?? $this->col($r, 'ID_FACTURACION'))
                        ->value('id');

                    // Skip por factura_id si ya existe
                    if ($facturaId && isset($facturasMigradas[$facturaId])) { $skipped++; continue; }

                    // Skip por clave compuesta (cedula|mes|año|razon_social|n_plano)
                    $nit      = $this->col($r, 'Nit_Empresa') ?? $this->col($r, 'NIT');
                    $rsId     = (is_numeric($nit) && (int)$nit > 0)
                        ? DB::table('razones_sociales')->where('aliado_id', $aliadoId)->where('id_legacy', (int)$nit)->value('id')
                        : null;
                    $cedula   = $this->col($r, 'NO_IDENTIFI') ?? $this->col($r, 'No_Identifi') ?? $this->col($r, 'Cedula') ?? '';
                    $mes      = $this->col($r, 'MES_PLANO') ?? $this->col($r, 'Mes') ?? '';
                    $anio     = $this->col($r, 'AÑO_PLANO') ?? $this->col($r, 'Año') ?? $this->col($r, 'Anio') ?? '';
                    $nPlano   = $this->col($r, 'N_PLANO') ?? $this->col($r, 'N_Plano') ?? '';
                    $clave    = "{$cedula}|{$mes}|{$anio}|{$rsId}|{$nPlano}";
                    if (isset($clavesMigradas[$clave])) { $skipped++; continue; }

                    DB::table('planos')->insert([
                        'aliado_id'          => $aliadoId,
                        'factura_id'         => $facturaId,
                        // contrato_id: buscar por id_legacy del contrato
                        'contrato_id'        => DB::table('contratos')
                            ->where('aliado_id', $aliadoId)
                            ->where('id_legacy', $this->col($r, 'Id_Contrato'))
                            ->value('id'),
                        // Numero de planilla: campo "Planilla" del legacy
                        'numero_planilla'    => trim($this->col($r, 'Planilla') ?? $this->col($r, 'Numero_Planilla') ?? ''),
                        'numero_factura'     => is_numeric($this->col($r, 'Factura')) ? (int)$this->col($r, 'Factura') : null,
                        'n_plano'            => trim($this->col($r, 'N_PLANO') ?? $this->col($r, 'N_Plano') ?? ''),
                        // Mes y año: MES_PLANO y AÑO_PLANO del legacy
                        'mes_plano'          => is_numeric($this->col($r, 'MES_PLANO') ?? $this->col($r, 'Mes')) ? (int)($this->col($r, 'MES_PLANO') ?? $this->col($r, 'Mes')) : null,
                        'anio_plano'         => is_numeric($this->col($r, 'AÑO_PLANO') ?? $this->col($r, 'Año') ?? $this->col($r, 'Anio')) ? (int)($this->col($r, 'AÑO_PLANO') ?? $this->col($r, 'Año') ?? $this->col($r, 'Anio')) : null,
                        // Normalizar tipo_reg: PILA usa '01'=activo/planilla, '02'=retiro, '03'=novedad
                        // BryNex usa 'planilla' | 'afiliacion'
                        'tipo_reg'           => (function () use ($r) {
                            $raw = strtolower(trim($this->col($r, 'TIPO_REG') ?? '01'));
                            if (in_array($raw, ['planilla', 'afiliacion', 'retiro'])) return $raw;
                            // Códigos PILA estándar
                            return match($raw) {
                                '02', '2', 'retiro'     => 'retiro',
                                '03', '3', 'afiliacion' => 'afiliacion',
                                default                  => 'planilla', // '01', '1', vacío → planilla
                            };
                        })(),
                        'tipo_doc'           => trim($this->col($r, 'TIPO_DOC') ?? 'CC'),
                        // Cédula del cliente: NO_IDENTIFI es el campo estándar PILA
                        'no_identifi'        => (string)(  $this->col($r, 'NO_IDENTIFI')
                                                         ?? $this->col($r, 'No_Identifi')
                                                         ?? $this->col($r, 'Cedula')
                                                         ?? ''),
                        // Apellidos y nombres del legacy
                        'primer_ape'         => trim($this->col($r, 'PRIMER_APE')      ?? $this->col($r, '1_Apellido')  ?? ''),
                        'segundo_ape'        => trim($this->col($r, 'SEGUNDO_APELLID')  ?? $this->col($r, '2_Apellido')  ?? ''),
                        'primer_nombre'      => trim($this->col($r, 'PRIMER_NOMBRE')    ?? $this->col($r, '1_Nombre')    ?? ''),
                        'segundo_nombre'     => trim($this->col($r, 'SEGUNDO-NOMBRE')   ?? $this->col($r, '2_Nombre')    ?? ''),
                        // Fechas ingreso y retiro: columnas con espacio 'FECHA ING' / 'FECHA RET'
                        'fecha_ing'          => (function () use ($r) {
                            $v = $this->col($r, 'FECHA ING')
                              ?? $this->col($r, 'fecha_ing')
                              ?? $this->col($r, 'Fecha_Ingreso')
                              ?? $this->col($r, 'FECHA_ING');
                            return $v ? substr($v, 0, 10) : null;
                        })(),
                        'fecha_ret'          => (function () use ($r) {
                            $v = $this->col($r, 'FECHA RET')
                              ?? $this->col($r, 'fecha_ret')
                              ?? $this->col($r, 'Fecha_Retiro')
                              ?? $this->col($r, 'FECHA_RET');
                            return $v ? substr($v, 0, 10) : null;
                        })(),
                        // Salario: SALARIO_BASICO del legacy
                        'salario_basico'     => is_numeric($this->col($r, 'SALARIO_BASICO') ?? $this->col($r, 'Salario')) ? (int)($this->col($r, 'SALARIO_BASICO') ?? $this->col($r, 'Salario')) : 0,
                        'cod_eps'            => trim($this->col($r, 'Cod_EPS')  ?? $this->col($r, 'EPS') ?? ''),
                        // cod_afp = COD_ADM_PENS
                        'cod_afp'            => trim($this->col($r, 'COD_ADM_PENS') ?? $this->col($r, 'Cod_AFP') ?? $this->col($r, 'AFP') ?? ''),
                        // cod_arl = CODIGO ARL (con espacio)
                        'cod_arl'            => trim($this->col($r, 'CODIGO ARL') ?? $this->col($r, 'Cod_ARL') ?? $this->col($r, 'ARL') ?? ''),
                        // cod_caja = COD_CCF
                        'cod_caja'           => trim($this->col($r, 'COD_CCF') ?? $this->col($r, 'Cod_Caja') ?? $this->col($r, 'Caja') ?? ''),
                        'nombre_eps'         => trim($this->col($r, 'Nombre_EPS')  ?? $this->col($r, 'Nom_EPS')  ?? ''),
                        'nombre_afp'         => trim($this->col($r, 'Nombre_AFP')  ?? $this->col($r, 'Nom_AFP')  ?? ''),
                        'nombre_arl'         => trim($this->col($r, 'Nombre_ARL')  ?? $this->col($r, 'Nom_ARL')  ?? ''),
                        'nombre_caja'        => trim($this->col($r, 'Nombre_Caja') ?? $this->col($r, 'Nom_Caja') ?? ''),
                        'nivel_riesgo'       => is_numeric($this->col($r, 'N_ARL') ?? $this->col($r, 'Nivel_Riesgo')) ? (int)($this->col($r, 'N_ARL') ?? $this->col($r, 'Nivel_Riesgo')) : 1,
                        // razon_social_id: buscar por id_legacy = NIT del plano → retorna el id BryNex
                        // razon_social guarda el NIT como string (referencia legible)
                        'razon_social_id'    => (function () use ($r, $aliadoId) {
                            $nit = $this->col($r, 'Nit_Empresa') ?? $this->col($r, 'NIT') ?? null;
                            if (!is_numeric($nit) || (int)$nit <= 0) return null;
                            return DB::table('razones_sociales')
                                ->where('aliado_id', $aliadoId)
                                ->where('id_legacy', (int)$nit)
                                ->value('id');  // id BryNex (auto-increment)
                        })(),
                        'razon_social'       => (function () use ($r) {
                            $nit = $this->col($r, 'Nit_Empresa') ?? $this->col($r, 'NIT');
                            if (is_numeric($nit) && (int)$nit > 0) return (string)(int)$nit;
                            return trim($this->col($r, 'Razon_Social') ?? '');
                        })(),
                        // Normalizar tipo_reg: PILA usa '01'=planilla, '02'=retiro, '03'=afiliacion
                        // BryNex usa 'planilla' | 'afiliacion' | 'retiro'
                        'tipo_reg'           => (function () use ($r) {
                            $raw = strtolower(trim($this->col($r, 'TIPO_REG') ?? '01'));
                            if (in_array($raw, ['planilla', 'afiliacion', 'retiro'])) return $raw;
                            return match($raw) {
                                '02', '2' => 'retiro',
                                '03', '3' => 'afiliacion',
                                default   => 'planilla',
                            };
                        })(),
                        // tipo_modalidad_id: Tipo_P del legacy (ID directo del catálogo)
                        'tipo_modalidad_id'  => is_numeric($this->col($r, 'Tipo_P')) ? (int)$this->col($r, 'Tipo_P') : null,
                        'tipo_p'             => trim($this->col($r, 'Tipo_P') ?? $this->col($r, 'Tipo') ?? ''),
                        'num_dias'           => is_numeric($this->col($r, 'Num_Dias') ?? $this->col($r, 'N_Dias')) ? (int)($this->col($r, 'Num_Dias') ?? $this->col($r, 'N_Dias')) : 30,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                    $count++;
                    if ($count % 200 === 0) $this->line("    → $count / $total...");
                }
                $offset += $chunk;
                if (count($rows) < $chunk) break;
            }
            $this->info("  ✅ $db → $count planos, $skipped omitidos");
        }
        DB::statement('ALTER TABLE planos WITH CHECK CHECK CONSTRAINT ALL');
        $this->info('  📊 Total planos: ' . DB::table('planos')->count());
    }

    // ─── PASO 10: ABONOS ─────────────────────────────────────────────────────
    // Legacy: revisar si existe tabla Abonos; si no, omitir
    private function step10_Abonos(): void
    {
        DB::statement('ALTER TABLE abonos NOCHECK CONSTRAINT ALL');
        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            // Verificar si existe la tabla en este legacy
            $exists = DB::connection('sqlsrv_legacy')
                ->selectOne("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_CATALOG=? AND TABLE_NAME='Abonos'", [$db]);
            if (!$exists || !$exists->cnt) {
                $this->warn("  ⚠ $db: tabla Abonos no existe, se omite");
                continue;
            }

            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Abonos");
            $count = 0;
            foreach ($rows as $r) {
                $facturaId = DB::table('facturas')
                    ->where('aliado_id', $aliadoId)
                    ->where('id_legacy', $this->col($r, 'Id_Factura'))
                    ->value('id');

                DB::table('abonos')->insert([
                    'aliado_id'       => $aliadoId,
                    'factura_id'      => $facturaId,
                    'valor'           => is_numeric($this->col($r, 'Valor')) ? (int)$this->col($r, 'Valor') : 0,
                    'forma_pago'      => trim($this->col($r, 'Forma_Pago') ?? 'efectivo'),
                    'valor_efectivo'  => is_numeric($this->col($r, 'Valor_Efectivo'))   ? (int)$this->col($r, 'Valor_Efectivo')   : 0,
                    'valor_consignado'=> is_numeric($this->col($r, 'Valor_Consignado')) ? (int)$this->col($r, 'Valor_Consignado') : 0,
                    'fecha'           => $this->col($r, 'Fecha') ? substr($this->col($r, 'Fecha'), 0, 10) : now()->toDateString(),
                    'observacion'     => trim($this->col($r, 'Observacion') ?? ''),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count abonos");
        }
        DB::statement('ALTER TABLE abonos WITH CHECK CHECK CONSTRAINT ALL');
        $this->info('  📊 Total abonos: ' . DB::table('abonos')->count());
    }

    // ─── PASO 11: DOCUMENTOS CLIENTE ─────────────────────────────────────────
    private function step11_DocumentosCliente(): void
    {
        DB::statement('ALTER TABLE documentos_cliente NOCHECK CONSTRAINT ALL');
        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            $exists = DB::connection('sqlsrv_legacy')
                ->selectOne("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_CATALOG=? AND TABLE_NAME='Documentos_Cliente'", [$db]);
            if (!$exists || !$exists->cnt) {
                $this->warn("  ⚠ $db: tabla Documentos_Cliente no existe, se omite");
                continue;
            }

            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Documentos_Cliente");
            $count = 0;
            foreach ($rows as $r) {
                $cedula = $this->col($r, 'Cedula') ?? $this->col($r, 'CC');
                DB::table('documentos_cliente')->insert([
                    'aliado_id'      => $aliadoId,
                    'cedula'         => is_numeric($cedula) ? (int)$cedula : null,
                    'nombre_archivo' => trim($this->col($r, 'Nombre_Archivo') ?? $this->col($r, 'Archivo') ?? 'sin_nombre'),
                    'tipo'           => trim($this->col($r, 'Tipo') ?? 'otro'),
                    'ruta'           => trim($this->col($r, 'Ruta') ?? ''),
                    'observacion'    => trim($this->col($r, 'Observacion') ?? ''),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count documentos");
        }
        DB::statement('ALTER TABLE documentos_cliente WITH CHECK CHECK CONSTRAINT ALL');
        $this->info('  📊 Total documentos_cliente: ' . DB::table('documentos_cliente')->count());
    }

    // ─── PASO 12: GASTOS ─────────────────────────────────────────────────────
    // Legacy: Gastos  →  BryNex: gastos
    private function step12_Gastos(): void
    {
        DB::statement('ALTER TABLE gastos NOCHECK CONSTRAINT ALL');

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            // Verificar existencia usando cross-DB sys.objects
            $exists = DB::connection('sqlsrv_legacy')
                ->selectOne("SELECT COUNT(*) as cnt FROM [$db].sys.objects WHERE name='Gastos' AND type='U'");
            if (!$exists || !$exists->cnt) {
                $this->warn("  ⚠ $db: tabla Gastos no existe, se omite");
                continue;
            }

            // Re-entrant por id_legacy
            $yaExisten = DB::table('gastos')
                ->where('aliado_id', $aliadoId)
                ->pluck('id_legacy')->filter()->flip()->all();

            $rows  = DB::connection('sqlsrv_legacy')->select("SELECT * FROM [$db].dbo.Gastos");
            $count = 0;

            // Resolver user_id por defecto (primer usuario del aliado)
            $defaultUserId = DB::table('users')->where('aliado_id', $aliadoId)->value('id') ?? 1;

            foreach ($rows as $r) {
                $idLeg = $this->col($r, 'Id') ?? $this->col($r, 'Id_Gasto');
                if ($idLeg && isset($yaExisten[$idLeg])) { continue; }

                // Intentar resolver usuario por ID/nombre legacy
                $userId = DB::table('users')
                    ->where('aliado_id', $aliadoId)
                    ->where('id_legacy', $this->col($r, 'Usuario') ?? $this->col($r, 'Id_Usuario'))
                    ->value('id') ?? $defaultUserId;

                $forma = strtolower(trim($this->col($r, 'Forma_Pago') ?? 'efectivo'));

                DB::table('gastos')->insert([
                    'id_legacy'      => $idLeg,
                    'aliado_id'      => $aliadoId,
                    'usuario_id'     => $userId,
                    'fecha'          => $this->col($r, 'Fecha') ? substr($this->col($r, 'Fecha'), 0, 10) : now()->toDateString(),
                    'tipo'           => trim($this->col($r, 'Tipo') ?? 'otro_oficina'),
                    'descripcion'    => trim($this->col($r, 'Descripcion') ?? $this->col($r, 'Concepto') ?? 'Sin descripción'),
                    'pagado_a'       => trim($this->col($r, 'Pagado_A') ?? $this->col($r, 'Beneficiario') ?? ''),
                    'cc_pagado_a'    => trim($this->col($r, 'CC_Pagado_A') ?? ''),
                    'forma_pago'     => in_array($forma, ['efectivo','consignacion','transferencia','cheque']) ? $forma : 'efectivo',
                    'valor'          => is_numeric($this->col($r, 'Valor')) ? (int)$this->col($r, 'Valor') : 0,
                    'recibo_caja'    => trim($this->col($r, 'Recibo_Caja') ?? $this->col($r, 'Recibo') ?? ''),
                    'lugar'          => trim($this->col($r, 'Lugar') ?? ''),
                    'observacion'    => trim($this->col($r, 'Observacion') ?? ''),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count gastos");
        }

        DB::statement('ALTER TABLE gastos WITH CHECK CHECK CONSTRAINT ALL');
        $this->info('  📊 Total gastos: ' . DB::table('gastos')->count());
    }
    // ─── FIX-MODALIDAD: copia el campo Tipo del legacy directamente ──────────────
    // Para TODOS los contratos: busca en la BD legacy por id_legacy y copia Tipo
    // exactamente tal como viene. Solo usa inferencia como último fallback.
    private function stepFixModalidad(): void
    {
        $validIds = [-100,-8,-7,-6,-4,-1,0,1,2,3,4,5,6,7,8,10,11,12,13];
        $updated  = 0; $fallback = 0; $sin_dato = 0;

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            // Cargar todos los Tipo del legacy para esta BD
            $legacyTipos = DB::connection('sqlsrv_legacy')
                ->select("SELECT Id, Tipo FROM [$db].dbo.Contratos WHERE Id IS NOT NULL");

            // Indexar por Id
            $tipoMap = [];
            foreach ($legacyTipos as $lt) {
                $tipoMap[$lt->Id] = $lt->Tipo;
            }

            // Cargar todos los contratos BryNex de este aliado
            $contratos = DB::table('contratos')
                ->where('aliado_id', $aliadoId)
                ->whereNotNull('id_legacy')
                ->select('id', 'id_legacy', 'eps_id', 'arl_id', 'pension_id', 'caja_id')
                ->get();

            $this->line("  ⏳ $db: {$contratos->count()} contratos a actualizar...");

            foreach ($contratos as $c) {
                $tipoLegacy = $tipoMap[$c->id_legacy] ?? null;

                if ($tipoLegacy !== null && is_numeric($tipoLegacy) && in_array((int)$tipoLegacy, $validIds, true)) {
                    DB::table('contratos')->where('id', $c->id)
                        ->update(['tipo_modalidad_id' => (int)$tipoLegacy]);
                    $updated++;
                } else {
                    $modalidadId = $this->resolveTipoModalidad(
                        null, $c->eps_id, $c->arl_id, $c->pension_id, $c->caja_id
                    );
                    if ($modalidadId !== null) {
                        DB::table('contratos')->where('id', $c->id)
                            ->update(['tipo_modalidad_id' => $modalidadId]);
                        $fallback++;
                    } else {
                        $sin_dato++;
                    }
                }
            }
            $this->info("  ✅ $db → desde legacy: $updated | fallback: $fallback | sin dato: $sin_dato");
            $updated = 0; $fallback = 0; $sin_dato = 0;
        }

        $still = DB::table('contratos')->whereNull('tipo_modalidad_id')->count();
        $this->line("  ℹ  Total sin modalidad: $still");
    }

    // ─── FIX-PLAN: asigna plan_id a todos los contratos migrados ─────────────────
    // Regla: eps_id != null → incluye_eps=1, caja_id = null → incluye_caja=0, etc.
    private function stepFixPlan(): void
    {
        // Planes normalizados a int (SQL Server devuelve 0/1 no bool)
        $planesAll = DB::table('planes_contrato')
            ->where('activo', true)
            ->get(['id', 'nombre', 'incluye_eps', 'incluye_arl', 'incluye_pension', 'incluye_caja'])
            ->map(function ($p) {
                $p->incluye_eps     = (int)$p->incluye_eps;
                $p->incluye_arl     = (int)$p->incluye_arl;
                $p->incluye_pension = (int)$p->incluye_pension;
                $p->incluye_caja    = (int)$p->incluye_caja;
                return $p;
            });

        // Mapa modalidad_id => [plan_ids permitidos]
        $modalidadPlanes = DB::table('modalidad_planes')
            ->get()
            ->groupBy('tipo_modalidad_id')
            ->map(fn($rows) => $rows->pluck('plan_id')->all());

        // Solo contratos migrados SIN plan asignado (los que ya tienen plan se saltan)
        $contratos = DB::table('contratos')
            ->whereNotNull('tipo_modalidad_id')
            ->whereNotNull('id_legacy')
            ->whereNull('plan_id')
            ->select('id', 'tipo_modalidad_id', 'eps_id', 'arl_id', 'pension_id', 'caja_id')
            ->get();

        $this->line("  ℹ  Contratos sin plan: {$contratos->count()}");
        $total = $contratos->count();
        $updated = 0; $sin_plan = 0; $procesados = 0;

        foreach ($contratos as $c) {
            $procesados++;
            $planIds = $modalidadPlanes[$c->tipo_modalidad_id] ?? [];
            if (empty($planIds)) { $sin_plan++; continue; }

            // Entidades: 1 si tiene, 0 si no
            $hasEps     = $c->eps_id     !== null ? 1 : 0;
            $hasArl     = $c->arl_id     !== null ? 1 : 0;
            $hasPension = $c->pension_id !== null ? 1 : 0;
            $hasCaja    = $c->caja_id    !== null ? 1 : 0;

            $candidatos = $planesAll->whereIn('id', $planIds);

            // Nivel 1: coincidencia EXACTA de entidades
            $planElegido = $candidatos->first(fn($p) =>
                $p->incluye_eps     === $hasEps
                && $p->incluye_arl     === $hasArl
                && $p->incluye_pension === $hasPension
                && $p->incluye_caja    === $hasCaja
            );

            // Nivel 2: plan que al menos incluya las entidades que el contrato TIENE
            // (nunca quitar algo que ya tiene; puede tener entidades extra)
            if (!$planElegido) {
                $planElegido = $candidatos->first(fn($p) =>
                    ($hasEps     === 0 || $p->incluye_eps     === 1)
                    && ($hasArl     === 0 || $p->incluye_arl     === 1)
                    && ($hasPension === 0 || $p->incluye_pension === 1)
                    && ($hasCaja    === 0 || $p->incluye_caja    === 1)
                );
            }

            // Nivel 3: plan con más entidades (preserva datos, nunca elimina)
            if (!$planElegido) {
                $planElegido = $candidatos
                    ->sortByDesc(fn($p) => $p->incluye_eps + $p->incluye_arl + $p->incluye_pension + $p->incluye_caja)
                    ->first();
            }

            if ($planElegido) {
                DB::table('contratos')->where('id', $c->id)
                    ->update(['plan_id' => $planElegido->id]);
                $updated++;
            } else {
                $sin_plan++;
            }

            if ($procesados % 200 === 0) {
                $this->line("    → $procesados / $total procesados ($updated con plan)...");
            }
        }

        $this->info("  ✅ $updated contratos con plan | $sin_plan sin plan disponible");
        $still = DB::table('contratos')->whereNull('plan_id')->whereNotNull('tipo_modalidad_id')->count();
        $this->line("  ℹ  Aún sin plan: $still");
    }

    // ─── FIX-NARL: copia N_ARL del legacy directamente ────────────────────────
    // N_ARL = nivel de riesgo ARL (0-5). En step06 se filtraba con >= 0 <= 5
    // pero valores como 0.0 o strings no pasaban. Los copiamos directamente.
    private function stepFixNarl(): void
    {
        $updated = 0; $sin_dato = 0;

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            // Leer Id + N_ARL del legacy
            $legacyRows = DB::connection('sqlsrv_legacy')
                ->select("SELECT Id, N_ARL FROM [$db].dbo.Contratos WHERE Id IS NOT NULL");

            // Indexar por Id
            $narlMap = [];
            foreach ($legacyRows as $lr) {
                $val = $lr->N_ARL ?? null;
                // Normalizar: aceptar 0-5 inclusive (0 = nivel I, el mas bajo)
                if ($val !== null && is_numeric($val)) {
                    $int = (int)round((float)$val);
                    $narlMap[$lr->Id] = ($int >= 0 && $int <= 5) ? $int : null;
                } else {
                    $narlMap[$lr->Id] = null;
                }
            }

            // Contratos BryNex de este aliado
            $contratos = DB::table('contratos')
                ->where('aliado_id', $aliadoId)
                ->whereNotNull('id_legacy')
                ->select('id', 'id_legacy')
                ->get();

            $this->line("  ⧳ $db: {$contratos->count()} contratos...");
            $updDb = 0;

            foreach ($contratos as $c) {
                $narl = $narlMap[$c->id_legacy] ?? null;
                DB::table('contratos')->where('id', $c->id)
                    ->update(['n_arl' => $narl]);
                if ($narl !== null) $updDb++; else $sin_dato++;
            }
            $updated += $updDb;
            $this->info("  ✅ $db → $updDb contratos con n_arl asignado");
        }

        $this->info("  Total: $updated con n_arl | $sin_dato sin dato en legacy");
    }

    // ─── PASO 13: INCAPACIDADES ───────────────────────────────────────────────────
    // Legacy: Incapacidades → BryNex: incapacidades
    private function step13_Incapacidades(): void
    {
        DB::statement('ALTER TABLE incapacidades NOCHECK CONSTRAINT ALL');

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            // Verificar si existe la tabla en este legacy (cross-DB)
            $exists = DB::connection('sqlsrv_legacy')
                ->selectOne("SELECT COUNT(*) as cnt FROM [$db].sys.objects WHERE name='Incapacidades' AND type='U'");
            if (!($exists->cnt ?? 0)) {
                $this->warn("  ⚠ $db: tabla Incapacidades no existe, se omite");
                continue;
            }

            // Re-entrant: saltar los ya migrados por id_legacy
            $yaExisten = DB::table('incapacidades')
                ->where('aliado_id', $aliadoId)
                ->pluck('id_legacy')->filter()->flip()->all();

            $rows = DB::connection('sqlsrv_legacy')
                ->select("SELECT * FROM [$db].dbo.Incapacidades");

            $this->line("  ⧳ $db: " . count($rows) . " incapacidades...");
            $count = 0; $skipped = 0;

            foreach ($rows as $r) {
                $idLeg = $this->col($r, 'Id') ?? $this->col($r, 'Id_Incapacidad');
                if ($idLeg && isset($yaExisten[$idLeg])) { $skipped++; continue; }

                // Contrato BryNex
                $contratoId = null;
                $idContratoLeg = $this->col($r, 'Id_Contrato') ?? $this->col($r, 'contrato_id');
                if ($idContratoLeg) {
                    $contratoId = DB::table('contratos')
                        ->where('aliado_id', $aliadoId)
                        ->where('id_legacy', $idContratoLeg)
                        ->value('id');
                }

                // quien_recibe: usuario BryNex
                $quienRecibeId = null;
                $usuLeg = $this->col($r, 'quien_recibe') ?? $this->col($r, 'Usuario') ?? $this->col($r, 'Encargado');
                if (is_numeric($usuLeg) && $usuLeg > 0) {
                    $quienRecibeId = DB::table('users')
                        ->where('aliado_id', $aliadoId)
                        ->where('id_legacy', (int)$usuLeg)
                        ->value('id');
                }

                // Razon social
                $razonSocialId = null;
                $nitRs = $this->col($r, 'Nit_Empresa') ?? $this->col($r, 'NIT');
                if ($nitRs && is_numeric($nitRs)) {
                    // id de razones_sociales ES el NIT
                    $razonSocialId = DB::table('razones_sociales')
                        ->where('aliado_id', $aliadoId)
                        ->where('id', (int)$nitRs)
                        ->value('id');
                }

                // Tipo incapacidad: normalizar al catálogo BryNex
                $tipoRaw = strtolower(trim($this->col($r, 'Tipo_Incapacidad') ?? $this->col($r, 'Tipo') ?? ''));
                $tipoMap = [
                    'enfermedad general'    => 'enfermedad_general',
                    'enfermedad'            => 'enfermedad_general',
                    'licencia maternidad'   => 'licencia_maternidad',
                    'maternidad'            => 'licencia_maternidad',
                    'licencia paternidad'   => 'licencia_paternidad',
                    'paternidad'            => 'licencia_paternidad',
                    'accidente transito'    => 'accidente_transito',
                    'transito'              => 'accidente_transito',
                    'accidente laboral'     => 'accidente_laboral',
                    'laboral'               => 'accidente_laboral',
                    'arl'                   => 'accidente_laboral',
                ];
                $tipo = $tipoMap[$tipoRaw] ?? 'enfermedad_general';

                // Tipo entidad responsable
                $tipoEntidadRaw = strtolower(trim($this->col($r, 'Tipo_Entidad') ?? 'eps'));
                $tipoEntidad = in_array($tipoEntidadRaw, ['eps','arl','afp']) ? $tipoEntidadRaw : 'eps';

                // Estado general
                $estadoRaw = strtolower(trim($this->col($r, 'Estado') ?? 'recibido'));
                $estadosValidos = ['recibido','radicado','en_tramite','autorizado','liquidado','pagado_afiliado','rechazado','cerrado'];
                $estado = in_array($estadoRaw, $estadosValidos) ? $estadoRaw : 'recibido';

                DB::table('incapacidades')->insert([
                    'id_legacy'               => $idLeg,
                    'aliado_id'               => $aliadoId,
                    'contrato_id'             => $contratoId,
                    'cedula_usuario'           => (string)($this->col($r, 'Cedula') ?? $this->col($r, 'cedula_usuario') ?? ''),
                    'quien_remite'             => trim($this->col($r, 'quien_remite') ?? $this->col($r, 'Empresa') ?? ''),
                    'quien_recibe_id'          => $quienRecibeId,
                    'tipo_incapacidad'         => $tipo,
                    'dias_incapacidad'         => is_numeric($this->col($r, 'Dias') ?? $this->col($r, 'dias_incapacidad')) ? (int)($this->col($r, 'Dias') ?? $this->col($r, 'dias_incapacidad')) : 0,
                    'fecha_inicio'             => $this->col($r, 'Fecha_Inicio')  ? substr($this->col($r, 'Fecha_Inicio'),  0, 10) : null,
                    'fecha_terminacion'        => $this->col($r, 'Fecha_Fin')     ? substr($this->col($r, 'Fecha_Fin'),     0, 10)
                                               : ($this->col($r, 'Fecha_Terminacion') ? substr($this->col($r, 'Fecha_Terminacion'), 0, 10) : null),
                    'fecha_recibido'           => $this->col($r, 'Fecha_Recibido') ? substr($this->col($r, 'Fecha_Recibido'), 0, 10) : null,
                    'prorroga'                 => (bool)($this->col($r, 'Prorroga') ?? $this->col($r, 'Es_Prorroga') ?? false),
                    'numero_proroga'           => is_numeric($this->col($r, 'Numero_Prorroga') ?? $this->col($r, 'N_Prorroga')) ? (int)($this->col($r, 'Numero_Prorroga') ?? $this->col($r, 'N_Prorroga')) : 0,
                    'tipo_entidad'             => $tipoEntidad,
                    'entidad_nombre'           => trim($this->col($r, 'Entidad') ?? $this->col($r, 'Nombre_Entidad') ?? ''),
                    'razon_social_id'          => $razonSocialId,
                    'razon_social_nombre'      => trim($this->col($r, 'Razon_Social') ?? ''),
                    'numero_radicado'          => trim($this->col($r, 'Numero_Radicado') ?? $this->col($r, 'Radicado') ?? ''),
                    'fecha_radicado'           => $this->col($r, 'Fecha_Radicado') ? substr($this->col($r, 'Fecha_Radicado'), 0, 10) : null,
                    'estado_pago'              => 'pendiente',
                    'valor_pago'               => is_numeric($this->col($r, 'Valor_Pago') ?? $this->col($r, 'Valor')) ? $this->col($r, 'Valor_Pago') ?? $this->col($r, 'Valor') : null,
                    'pagado_a'                 => trim($this->col($r, 'Pagado_A') ?? ''),
                    'diagnostico'              => substr(trim($this->col($r, 'Diagnostico') ?? $this->col($r, 'CIE10') ?? ''), 0, 200),
                    'observacion'              => trim($this->col($r, 'Observacion') ?? ''),
                    'estado'                   => $estado,
                    'created_at'               => now(),
                    'updated_at'               => now(),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count incapacidades | $skipped omitidas");
        }

        DB::statement('ALTER TABLE incapacidades WITH CHECK CHECK CONSTRAINT ALL');
        $this->info('  📊 Total incapacidades: ' . DB::table('incapacidades')->count());
    }

    // ─── PASO 14: GESTIONES INCAPACIDAD ───────────────────────────────────────
    // Legacy: GestionesIncapacidad o Gestiones_Incapacidad → gestiones_incapacidad
    private function step14_GestionesIncapacidad(): void
    {
        // Precargar mapa id_legacy → nuevo id de incapacidades por aliado
        $incapMap = DB::table('incapacidades')
            ->whereNotNull('id_legacy')
            ->select('aliado_id', 'id', 'id_legacy')
            ->get()
            ->groupBy('aliado_id')
            ->map(fn($rows) => $rows->pluck('id', 'id_legacy')->all());

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) continue;

            // Buscar nombre real de la tabla con cross-DB sys.objects
            $tablaRes = DB::connection('sqlsrv_legacy')
                ->selectOne("SELECT TOP 1 name AS TABLE_NAME FROM [$db].sys.objects WHERE type='U' AND name IN ('GestionesIncapacidad','Gestiones_Incapacidad','Gestiones_incapacidad')");
            if (!$tablaRes) {
                $this->warn("  ⚠ $db: tabla GestionesIncapacidad no existe, se omite");
                continue;
            }
            $tabla = $tablaRes->TABLE_NAME;

            $aliadoIncapMap = $incapMap[$aliadoId] ?? [];

            $rows = DB::connection('sqlsrv_legacy')
                ->select("SELECT * FROM [$db].dbo.[$tabla]");

            $this->line("  ⧳ $db: " . count($rows) . " gestiones...");
            $count = 0; $sin_incap = 0;

            foreach ($rows as $r) {
                $idIncapLeg = $this->col($r, 'Id_Incapacidad') ?? $this->col($r, 'incapacidad_id');
                $incapacidadId = $aliadoIncapMap[$idIncapLeg] ?? null;
                if (!$incapacidadId) { $sin_incap++; continue; }

                // Usuario BryNex
                $usuLeg = $this->col($r, 'Usuario') ?? $this->col($r, 'user_id') ?? $this->col($r, 'Id_Usuario');
                $userId = null;
                if (is_numeric($usuLeg) && $usuLeg > 0) {
                    $userId = DB::table('users')
                        ->where('aliado_id', $aliadoId)
                        ->where('id_legacy', (int)$usuLeg)
                        ->value('id');
                }

                // Tipo de gestión
                $tipoRaw = strtolower(trim($this->col($r, 'Tipo') ?? 'otro'));
                $tiposValidos = ['llamada','correo','whatsapp','portal','radico','tutela',
                                 'transcripcion_ips','respuesta_entidad','autorizacion',
                                 'liquidacion','pago_afiliado','otro'];
                $tipo = in_array($tipoRaw, $tiposValidos) ? $tipoRaw : 'otro';

                // Estado resultado
                $estadoRaw = strtolower(trim($this->col($r, 'Estado') ?? ''));
                $estadosValidos = ['recibido','radicado','en_tramite','autorizado','liquidado','pagado_afiliado','rechazado','cerrado'];
                $estadoRes = in_array($estadoRaw, $estadosValidos) ? $estadoRaw : null;

                DB::table('gestiones_incapacidad')->insert([
                    'incapacidad_id'   => $incapacidadId,
                    'user_id'          => $userId ?? 1, // fallback admin si no encontrado
                    'aplica_a_familia' => false,
                    'tipo'             => $tipo,
                    'tramite'          => trim($this->col($r, 'Tramite') ?? $this->col($r, 'Observacion') ?? $this->col($r, 'Gestion') ?? ''),
                    'respuesta'        => trim($this->col($r, 'Respuesta') ?? ''),
                    'estado_resultado'  => $estadoRes,
                    'fecha_recordar'   => $this->col($r, 'Fecha_Recordar') ? substr($this->col($r, 'Fecha_Recordar'), 0, 10) : null,
                    'created_at'       => $this->col($r, 'Fecha') ? substr($this->col($r, 'Fecha'), 0, 19)
                                       : ($this->col($r, 'created_at') ? substr($this->col($r, 'created_at'), 0, 19) : now()),
                ]);
                $count++;
            }
            $this->info("  ✅ $db → $count gestiones | $sin_incap sin incapacidad encontrada");
        }

        $this->info('  📊 Total gestiones incapacidad: ' . DB::table('gestiones_incapacidad')->count());
    }

    // ─── PASO FIX-VALORESFACTURAS ─────────────────────────────────────────────
    // Corrige facturas migradas con total = 0 (y/o valores de SS en 0) buscando
    // los valores reales en la tabla FACTURACION de cada BD legacy.
    //
    // Columnas que actualiza en BryNex:
    //   total, valor_efectivo, valor_consignado, forma_pago,
    //   v_eps, v_arl, v_afp, v_caja, total_ss,
    //   admon, admin_asesor, seguro, afiliacion, mensajeria, otros, iva
    private function stepFixValoresFacturas(): void
    {
        $this->info('🔧 fix-valoresfacturas: Corrigiendo facturas con total = 0...');

        $totalActualizadas = 0;
        $totalSinLegacy    = 0;

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) {
                $this->warn("  ⚠ Aliado '$key' no encontrado, se omite");
                continue;
            }

            // Obtener facturas de este aliado cuyo total sea 0
            $facturasCero = DB::table('facturas')
                ->where('aliado_id', $aliadoId)
                ->where('total', 0)
                ->whereNotNull('id_legacy')
                ->select('id', 'id_legacy')
                ->get();

            if ($facturasCero->isEmpty()) {
                $this->line("  ℹ  $db: ninguna factura con total=0, se omite.");
                continue;
            }

            $this->line("  ⏳ $db: {$facturasCero->count()} facturas con total=0 por corregir...");
            $actualizadas = 0;
            $sinLegacy    = 0;

            foreach ($facturasCero as $fac) {
                // Buscar el registro original en el legacy
                $rows = $this->legacySelect(
                    "SELECT TOP 1 * FROM [$db].dbo.FACTURACION WHERE Id_Factura = {$fac->id_legacy}"
                );

                if (empty($rows)) {
                    $sinLegacy++;
                    $this->warn("    ⚠ id_legacy={$fac->id_legacy} no encontrado en legacy.");
                    continue;
                }

                $r = $rows[0];

                // ── Recalcular valores ──────────────────────────────────────
                // BUG ORIGINAL: En legacy, facturas 100% consignación tienen
                // Pago = 0 y el valor real está en Valor_Consignado.
                // Regla: total = Pago si Pago > 0, si no = Valor_Consignado.
                $valCons  = is_numeric($r->Valor_Consignado) ? (int)$r->Valor_Consignado : 0;
                $valTotal = is_numeric($r->Pago) && (int)$r->Pago > 0
                    ? (int)$r->Pago
                    : $valCons;   // ← corrección del bug: Pago=0 → usar Valor_Consignado

                // Si aún sigue en 0, intentar columnas alternativas
                if ($valTotal === 0) {
                    foreach (['Total', 'Valor', 'Valor_Factura', 'TOTAL', 'VALOR'] as $alt) {
                        $v = $this->col($r, $alt);
                        if (is_numeric($v) && (int)$v > 0) {
                            $valTotal = (int)$v;
                            break;
                        }
                    }
                }

                $valEfect = max(0, $valTotal - $valCons);

                $codBanco = $this->col($r, 'Consignacion') ?? $this->col($r, 'COD') ?? null;
                if ($valCons > 0 && is_numeric($codBanco) && (int)$codBanco > 0) {
                    $formaPago = $valEfect > 0 ? 'mixto' : 'consignacion';
                } else {
                    $formaPago = 'efectivo';
                    $valCons   = 0;
                    $valEfect  = $valTotal;
                }

                $vEps   = is_numeric($r->V_EPS)  ? (int)$r->V_EPS  : 0;
                $vArl   = is_numeric($r->V_Arl)  ? (int)$r->V_Arl  : 0;
                $vAfp   = is_numeric($r->V_AFP)  ? (int)$r->V_AFP  : 0;
                $vCaja  = is_numeric($r->V_CAJA) ? (int)$r->V_CAJA : 0;
                $totalSS = $vEps + $vArl + $vAfp + $vCaja;

                DB::table('facturas')
                    ->where('id', $fac->id)
                    ->update([
                        'total'              => $valTotal,
                        'valor_consignado'   => $valCons,
                        'valor_efectivo'     => $valEfect,
                        'forma_pago'         => $formaPago,
                        'v_eps'              => $vEps,
                        'v_arl'              => $vArl,
                        'v_afp'              => $vAfp,
                        'v_caja'             => $vCaja,
                        'total_ss'           => $totalSS,
                        'admon'              => is_numeric($r->Admon)        ? (int)$r->Admon        : 0,
                        'admin_asesor'       => is_numeric($r->admin_asesor) ? (int)$r->admin_asesor : 0,
                        'seguro'             => is_numeric($r->seguro)       ? (int)$r->seguro       : 0,
                        'afiliacion'         => is_numeric($r->Afiliaciones) ? (int)$r->Afiliaciones : 0,
                        'mensajeria'         => is_numeric($r->Mensajeria)   ? (int)$r->Mensajeria   : 0,
                        'otros'              => is_numeric($r->Otros)        ? (int)$r->Otros        : 0,
                        'iva'                => is_numeric($r->Iva)          ? (int)$r->Iva          : 0,
                        'updated_at'         => now(),
                    ]);

                $actualizadas++;
                if ($actualizadas % 100 === 0) {
                    $this->line("    → $actualizadas actualizadas...");
                }
            }

            $totalActualizadas += $actualizadas;
            $totalSinLegacy    += $sinLegacy;
            $this->info("  ✅ $db → $actualizadas actualizadas | $sinLegacy sin registro en legacy");
        }

        // Resumen global
        $aun0 = DB::table('facturas')->where('total', 0)->count();
        $this->info("\n📊 Resumen fix-valoresfacturas:");
        $this->info("   Actualizadas  : $totalActualizadas");
        $this->info("   Sin legacy    : $totalSinLegacy");
        $this->info("   Aún con total=0: $aun0");
    }

    // ─── PASO FIX-INDEPENDIENTE ──────────────────────────────────────────────
    // Corrige contratos de RS con es_independiente=true:
    //   1. Vincula planes con ARL a modalidades independientes en modalidad_planes
    //   2. Actualiza plan_id a un plan que incluye ARL (si el actual no lo tiene)
    //   3. Sincroniza arl_id y n_arl desde la BD legacy
    //
    // Uso: php artisan legacy:migrate --step=fix-independiente
    private function stepFixIndependiente(): void
    {
        $this->info('Ejecutando fix de contratos independientes (arl_id, n_arl, plan_id)…');
        $exitCode = \Illuminate\Support\Facades\Artisan::call(
            'brynex:fix-independiente-planes',
            ['--ejecutar' => true],
            $this->output
        );
        if ($exitCode === 0) {
            $this->info('✅ fix-independiente completado.');
        } else {
            $this->error("❌ fix-independiente terminó con código: $exitCode");
        }
    }

    // ─── FIX-PLANOS: actualiza campos de planos ya migrados desde el legacy ────────────────────
    // Corrige: no_identifi (NO_IDENTIFI), fecha_ing (FECHA ING), fecha_ret (FECHA RET),
    //          razon_social_id (id_legacy del NIT), tipo_reg (normalizar códigos PILA).
    // Match: plano ↔ legacy por factura_id (vía facturas.id_legacy = Id_Factura del plano).
    private function stepFixPlanos(): void
    {
        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) { $this->warn("  ⚠ Aliado '$key' no encontrado, se omite"); continue; }

            $total = DB::connection('sqlsrv_legacy')
                ->selectOne("SELECT COUNT(*) as cnt FROM [$db].dbo.PLANOS")->cnt;
            $this->line("  ⏳ $db: $total planos a revisar...");

            $updated = 0; $offset = 0; $chunk = 500;
            while (true) {
                $rows = $this->legacySelect(
                    "SELECT Id, Id_Factura, NO_IDENTIFI, [FECHA ING], [FECHA RET], Nit_Empresa, NIT
                     FROM [$db].dbo.PLANOS
                     ORDER BY Id OFFSET $offset ROWS FETCH NEXT $chunk ROWS ONLY"
                );
                if (empty($rows)) break;

                foreach ($rows as $r) {
                    // factura_id: PLANOS.id_facturacion → FACTURACION.Id = facturas.id_legacy → id BryNex
                    $facturaId = DB::table('facturas')
                        ->where('aliado_id', $aliadoId)
                        ->where('id_legacy', $this->col($r, 'id_facturacion') ?? $this->col($r, 'Id_Facturacion') ?? $this->col($r, 'ID_FACTURACION'))
                        ->value('id');

                    if (!$facturaId) continue; // Sin factura migrada → saltar

                    // Resolver razon_social_id: id_legacy = NIT del plano → id BryNex
                    $nit = $this->col($r, 'Nit_Empresa') ?? $this->col($r, 'NIT');
                    $razonSocialId = (is_numeric($nit) && (int)$nit > 0)
                        ? DB::table('razones_sociales')
                            ->where('aliado_id', $aliadoId)
                            ->where('id_legacy', (int)$nit)
                            ->value('id')
                        : null;

                    // no_identifi: NO_IDENTIFI es el campo estándar PILA
                    $noIdentifi = (string)(
                        $this->col($r, 'NO_IDENTIFI')
                        ?? $this->col($r, 'No_Identifi')
                        ?? $this->col($r, 'Cedula')
                        ?? ''
                    );

                    // fecha_ing: 'FECHA ING' con espacio
                    $fIngRaw = $this->col($r, 'FECHA ING')
                            ?? $this->col($r, 'fecha_ing')
                            ?? $this->col($r, 'Fecha_Ingreso');
                    $fechaIng = $fIngRaw ? substr($fIngRaw, 0, 10) : null;

                    // fecha_ret: 'FECHA RET' con espacio
                    $fRetRaw = $this->col($r, 'FECHA RET')
                            ?? $this->col($r, 'fecha_ret')
                            ?? $this->col($r, 'Fecha_Retiro');
                    $fechaRet = $fRetRaw ? substr($fRetRaw, 0, 10) : null;

                    // Construir datos a actualizar (solo los que tengan valor)
                    $data = ['updated_at' => now()];
                    if ($noIdentifi !== '')  $data['no_identifi']     = $noIdentifi;
                    if ($fechaIng)           $data['fecha_ing']       = $fechaIng;
                    if ($fechaRet)           $data['fecha_ret']       = $fechaRet;
                    if ($razonSocialId)      $data['razon_social_id'] = $razonSocialId;
                    // razon_social = NIT como string
                    if (is_numeric($nit) && (int)$nit > 0) {
                        $data['razon_social'] = (string)(int)$nit;
                    }

                    $affected = DB::table('planos')
                        ->where('aliado_id', $aliadoId)
                        ->where('factura_id', $facturaId)
                        ->update($data);

                    $updated += $affected;
                }

                $offset += $chunk;
                if (count($rows) < $chunk) break;
            }
            $this->info("  ✅ $db → $updated planos actualizados");
        }

        // Normalizar tipo_reg en toda la tabla (códigos PILA → BryNex)
        DB::statement("
            UPDATE planos SET tipo_reg = 'planilla'
            WHERE tipo_reg NOT IN ('planilla', 'afiliacion', 'retiro')
               OR tipo_reg IS NULL
        ");
        $this->info('  ✅ tipo_reg normalizado (planilla/afiliacion/retiro)');
    }
}
