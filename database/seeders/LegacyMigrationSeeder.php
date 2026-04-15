<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegacyMigrationSeeder extends Seeder
{
    /**
     * Importa TODOS los datos desde Brygar_BD (legacy) a las tablas locales BryNex.
     * Es idempotente: usa updateOrInsert para que pueda re-ejecutarse.
     */
    public function run(): void
    {
        $legacy = 'sqlsrv_legacy';

        // ── 1. Departamentos y Ciudades (ya existe seeder propio) ──
        $this->call(DepartamentosCiudadesSeeder::class);

        // ── 2. EPS ──
        $this->command->info('Importando EPS...');
        $eps = DB::connection($legacy)->table('EPS')->get();
        $epsMap = []; // nit → id local
        foreach ($eps as $row) {
            $nit = (int) ($row->NIT ?? 0);
            $id = DB::table('eps')->updateOrInsert(
                ['nit' => $nit],
                [
                    'codigo'           => trim($row->Codigo ?? ''),
                    'nombre'           => trim($row->N_EPS ?? ''),
                    'razon_social'     => trim($row->{'Razon Social'} ?? ''),
                    'direccion'        => trim($row->Direccion ?? ''),
                    'telefono'         => trim($row->Telefono ?? ''),
                    'ciudad'           => trim($row->Ciudad ?? ''),
                    'email'            => trim($row->Email ?? ''),
                    'nombre_aportes'   => trim($row->N_EPS_aportes ?? ''),
                    'nombre_asopagos'  => trim($row->N_EPS_Asopagos ?? ''),
                ]
            );
            $epsMap[$nit] = DB::table('eps')->where('nit', $nit)->value('id');
        }
        $this->command->info("  → {$eps->count()} EPS importadas.");

        // ── 3. PENSIONES ──
        $this->command->info('Importando Pensiones...');
        $pensiones = DB::connection($legacy)->table('PENSION')->get();
        $penMap = [];
        foreach ($pensiones as $row) {
            $nit = (int) ($row->NIT ?? 0);
            DB::table('pensiones')->updateOrInsert(
                ['nit' => $nit],
                [
                    'codigo'          => trim($row->Codigo ?? ''),
                    'razon_social'    => trim($row->{'Razon Social'} ?? ''),
                    'direccion'       => trim($row->{'Dirección'} ?? $row->Direccion ?? ''),
                    'telefono'        => trim($row->Telefono ?? ''),
                    'ciudad'          => trim($row->Ciudad ?? ''),
                    'email'           => trim($row->Email ?? ''),
                    'nombre_asopagos' => trim($row->N_AFP_Asopagos ?? ''),
                ]
            );
            $penMap[$nit] = DB::table('pensiones')->where('nit', $nit)->value('id');
        }
        $this->command->info("  → {$pensiones->count()} Pensiones importadas.");

        // ── 4. ARL ──
        $this->command->info('Importando ARL...');
        $arls = DB::connection($legacy)->table('ARL')->get();
        foreach ($arls as $row) {
            $nit = (int) ($row->NIT ?? 0);
            DB::table('arls')->updateOrInsert(
                ['nit' => $nit],
                [
                    'codigo'          => trim($row->Codigo ?? ''),
                    'razon_social'    => trim($row->{'Razon Social'} ?? ''),
                    'direccion'       => trim($row->Direccion ?? ''),
                    'telefono'        => trim($row->{'Teléfono'} ?? $row->Telefono ?? ''),
                    'ciudad'          => trim($row->Ciudad ?? ''),
                    'email'           => trim($row->Email ?? ''),
                    'nombre_arl'      => trim($row->ARL ?? ''),
                    'nombre_asopagos' => trim($row->N_ARL_Asopagos ?? ''),
                ]
            );
        }
        $this->command->info("  → {$arls->count()} ARL importadas.");

        // ── 5. CAJAS ──
        $this->command->info('Importando Cajas de Compensación...');
        $cajas = DB::connection($legacy)->table('CAJA')->get();
        foreach ($cajas as $row) {
            $nit = (int) ($row->NIT ?? 0);
            DB::table('cajas')->updateOrInsert(
                ['nit' => $nit],
                [
                    'codigo'          => trim($row->Codigo ?? ''),
                    'nombre'          => trim($row->N_CAJA ?? ''),
                    'razon_social'    => trim($row->{'Razon Social'} ?? ''),
                    'direccion'       => trim($row->Direccion ?? ''),
                    'telefono'        => trim($row->Telefono ?? ''),
                    'ciudad'          => trim($row->Ciudad ?? ''),
                    'email'           => trim($row->Email ?? ''),
                    'nombre_asopagos' => trim($row->N_CAJA_Asopagos ?? ''),
                ]
            );
        }
        $this->command->info("  → {$cajas->count()} Cajas importadas.");

        // ── 6. RAZONES SOCIALES ──
        $this->command->info('Importando Razones Sociales...');
        $razones = DB::connection($legacy)->table('Razon_Social')->get();
        foreach ($razones as $row) {
            DB::table('razones_sociales')->updateOrInsert(
                ['id' => (int) $row->ID],
                [
                    'dv'                    => $row->DV,
                    'razon_social'          => trim($row->Razon_Social ?? ''),
                    'estado'                => trim($row->Estado ?? ''),
                    'plan'                  => trim($row->Plan ?? ''),
                    'direccion'             => trim($row->Direccion ?? ''),
                    'telefonos'             => trim($row->Telefonos ?? ''),
                    'correos'               => trim($row->Correos ?? ''),
                    'actividad_economica'   => trim($row->Actividad_Economica ?? ''),
                    'objeto_social'         => trim($row->Objeto_Social ?? ''),
                    'observacion'           => trim($row->Observacion ?? ''),
                    'salario_minimo'        => $row->Salario_Minimo ?? 0,
                    'arl_nit'               => (int) ($row->ARL ?? 0),
                    'caja_nit'              => (int) ($row->CAJA ?? 0),
                    'mes_pagos'             => $row->MES_PAGOS,
                    'anio_pagos'            => $row->{'AÑO_PAGOS'} ?? null,
                    'n_plano'               => $row->N_PLANO,
                    'fecha_constitucion'    => $row->Fecha_Constitucion,
                    'fecha_limite_pago'     => $row->Fecha_Limite_pago,
                    'dia_habil'             => $row->Dia_Habil,
                    'forma_presentacion'    => trim($row->Forma_Presentacion ?? ''),
                    'codigo_sucursal'       => trim($row->Codigo_Sucursal_Aportante ?? ''),
                    'nombre_sucursal'       => trim($row->Nombre_Sucursal ?? ''),
                    'notas_factura1'        => trim($row->Notas_Factura1 ?? ''),
                    'notas_factura2'        => trim($row->Notas_Factura2 ?? ''),
                    'dir_formulario'        => trim($row->Dir_Formulario ?? ''),
                    'tel_formulario'        => trim($row->Tel_Formulario ?? ''),
                    'correo_formulario'     => trim($row->Correo_Formulario ?? ''),
                    'cedula_rep'            => (int) ($row->Cedula_Rep ?? 0),
                    'nombre_rep'            => trim($row->Nombre_Rep ?? ''),
                ]
            );
        }
        $this->command->info("  → {$razones->count()} Razones Sociales importadas.");

        // ── 7. CLIENTES ──
        $this->command->info('Importando Clientes (Base_De_Datos)...');
        $clientes = DB::connection($legacy)->table('Base_De_Datos')->get();
        $imported = 0;
        foreach ($clientes as $row) {
            $epsNit = (int) ($row->Eps ?? 0);
            $penNit = (int) ($row->Pension ?? 0);
            $epsId  = $epsMap[$epsNit] ?? null;
            $penId  = $penMap[$penNit] ?? null;
            $munId  = $row->Municipio ? (int) $row->Municipio : null;
            // Validar que municipio existe
            if ($munId && !DB::table('ciudades')->where('id', $munId)->exists()) {
                $munId = null;
            }

            DB::table('clientes')->updateOrInsert(
                ['id' => $row->Id],
                [
                    'cod_empresa'         => $row->COD_EMPRESA,
                    'tipo_doc'            => trim($row->TIPO_DOC ?? ''),
                    'cedula'              => (int) $row->Cedula,
                    'primer_nombre'       => trim($row->{'1_NOMBRE'} ?? ''),
                    'segundo_nombre'      => trim($row->{'2_NOMBRE'} ?? ''),
                    'primer_apellido'     => trim($row->{'1_APELLIDO'} ?? ''),
                    'segundo_apellido'    => trim($row->{'2_APELLIDO'} ?? ''),
                    'genero'              => trim($row->Genero ?? ''),
                    'sisben'              => trim($row->Sisben ?? ''),
                    'fecha_nacimiento'    => $row->Fecha_Nacimiento,
                    'fecha_expedicion'    => $row->Fecha_Expedicion,
                    'rh'                  => trim($row->RH ?? ''),
                    'telefono'            => trim($row->Telefono ?? ''),
                    'celular'             => $row->Celular ? (int) $row->Celular : null,
                    'correo'              => trim($row->Correo ?? ''),
                    'departamento_id'     => $row->Departamento ? (int) $row->Departamento : null,
                    'municipio_id'        => $munId,
                    'direccion_vivienda'  => trim($row->Direccion_Vivienda ?? ''),
                    'direccion_cobro'     => trim($row->Direccion_Cobro ?? ''),
                    'barrio'              => trim($row->Barrio ?? ''),
                    'eps_id'              => $epsId,
                    'pension_id'          => $penId,
                    'ips'                 => trim($row->IPS ?? ''),
                    'urgencias'           => trim($row->URGENCIAS ?? ''),
                    'iva'                 => trim($row->IVA ?? ''),
                    'ocupacion'           => trim($row->Ocupacion ?? ''),
                    'referido'            => trim($row->Referido ?? ''),
                    'observacion'         => trim($row->Observacion ?? ''),
                    'observacion_llamada' => $row->Observacion_llamada ?? null,
                    'claves'              => trim($row->Claves ?? ''),
                    'datos'               => trim($row->Datos ?? ''),
                    'deuda'               => $row->DEUDA,
                    'fecha_probable_pago' => trim($row->Fecha_problable_pago ?? ''),
                    'modo_probable_pago'  => trim($row->Modo_propable_pago ?? ''),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]
            );
            $imported++;
        }
        $this->command->info("  → {$imported} Clientes importados.");

        // ── 8. CONTRATOS ──
        $this->command->info('Importando Contratos...');
        $contratos = DB::connection($legacy)->table('Contratos')->get();
        $cImported = 0;

        // Maps para entidades por NIT → id local
        $arlMap  = DB::table('arls')->pluck('id', 'nit')->toArray();
        $cajaMap = DB::table('cajas')->pluck('id', 'nit')->toArray();

        foreach ($contratos as $row) {
            $rsId = $row->COD_RAZON_SOC ? (int) $row->COD_RAZON_SOC : null;
            if ($rsId && !DB::table('razones_sociales')->where('id', $rsId)->exists()) {
                $rsId = null;
            }

            // Mapear tipo (integer Access) → tipo_modalidad_id
            // Valores Access conocidos: 1=Dependiente E, 2=Dependiente Empresa, 3=Independiente, etc.
            $tipoModalidadId = $row->Tipo ? (int) $row->Tipo : null;

            // Entidades por NIT → FK
            $arlId  = null;
            $cajaId = null;
            if ($row->ARL) {
                $arlNit = (int) $row->ARL;
                $arlId  = $arlMap[$arlNit] ?? DB::table('arls')->where('nit', $arlNit)->value('id');
            }
            if ($row->Caja_Comp) {
                $cajaNit = (int) $row->Caja_Comp;
                $cajaId  = $cajaMap[$cajaNit] ?? DB::table('cajas')->where('nit', $cajaNit)->value('id');
            }

            // EPS y Pensión del contrato por NIT
            $epsId = null;
            $penId = null;
            if ($row->Eps_c) {
                $epsId = DB::table('eps')->where('nit', (int) $row->Eps_c)->value('id');
            }
            if ($row->Pension_c) {
                $penId = DB::table('pensiones')->where('nit', (int) $row->Pension_c)->value('id');
            }

            $salario = is_numeric($row->Salario_M) ? (float) $row->Salario_M : null;

            DB::table('contratos')->updateOrInsert(
                ['id' => $row->Id],
                [
                    'cedula'                 => (int) $row->Cedula,
                    // asesor_id se migra después cuando existan asesores normalizados
                    'estado'                 => trim($row->Estado ?? 'vigente'),
                    'razon_social_id'        => $rsId,
                    'razon_social_bloqueada' => false,
                    'tipo_modalidad_id'      => $tipoModalidadId,
                    'eps_id'                 => $epsId,
                    'pension_id'             => $penId,
                    'arl_id'                 => $arlId,
                    'n_arl'                  => $row->N_ARL,
                    'arl_modo'               => $row->ARL_INDEPENDIENTE ? 'razon_social' : null,
                    'caja_id'                => $cajaId,
                    'cargo'                  => trim($row->Cargo ?? ''),
                    'fecha_ingreso'          => $row->Fecha_Ingreso,
                    'fecha_retiro'           => $row->Fecha_Retiro,
                    'salario'                => $salario,
                    'ibc'                    => $salario, // IBC = salario por defecto
                    'administracion'         => $row->Administracion ?? 0,
                    'admon_asesor'           => $row->admon_asesor ?? 0,
                    'costo_afiliacion'       => $row->costo_afiliacion ?? 0,
                    'seguro'                 => $row->Seguro ?? 0,
                    'np'                     => trim($row->NP ?? ''),
                    'encargado_id'           => null, // Se asigna manualmente
                    'envio_planilla'         => trim($row->Envio_Planilla ?? ''),
                    'observacion'            => trim($row->Observacion ?? ''),
                    'observacion_afiliacion' => trim($row->Observacion_Afiliacion ?? ''),
                    'observacion_llamada'    => $row->Observacion_llamada ?? null,
                    'fecha_arl'              => $row->Fecha_ARL,
                    'fecha_probable_pago'    => trim($row->Fecha_problable_pago ?? ''),
                    'modo_probable_pago'     => trim($row->Modo_propable_pago ?? ''),
                    'fecha_created'          => $row->Fecha_Created,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]
            );
            $cImported++;
        }
        $this->command->info("  → {$cImported} Contratos importados.");

        $this->command->info('✅ Migración legacy completa.');
    }
}
