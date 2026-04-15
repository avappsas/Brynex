<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartamentosCiudadesSeeder extends Seeder
{
    /**
     * Importa Departamentos y Ciudades desde la BD legacy (Brygar_BD) a BryNex.
     */
    public function run(): void
    {
        $this->command->info('Importando departamentos desde Brygar_BD...');

        $deptos = DB::connection('sqlsrv_legacy')
            ->table('Departamentos')
            ->get();

        foreach ($deptos as $d) {
            DB::table('departamentos')->updateOrInsert(
                ['id' => (int) $d->Id],
                [
                    'nombre'         => trim($d->Depart),
                    'dept_aportes'   => trim($d->Dept_aportes ?? ''),
                    'dept_asopagos'  => trim($d->Dept_asopagos ?? ''),
                ]
            );
        }

        $this->command->info("  → {$deptos->count()} departamentos importados.");

        $this->command->info('Importando ciudades desde Brygar_BD...');

        $ciudades = DB::connection('sqlsrv_legacy')
            ->table('Ciudades')
            ->get();

        $importadas = 0;
        foreach ($ciudades as $c) {
            $idCiudad = (int) $c->IdCiudad;
            $deptoId  = (int) $c->Departamento;

            // Solo importar si el departamento existe
            if ($deptoId <= 0 || !DB::table('departamentos')->where('id', $deptoId)->exists()) {
                continue;
            }

            DB::table('ciudades')->updateOrInsert(
                ['id' => $idCiudad],
                [
                    'departamento_id'  => $deptoId,
                    'nombre'           => trim($c->Ciudad ?? ''),
                    'ciudad_aportes'   => trim($c->Ciudad_aportes ?? ''),
                    'ciudad_asopagos'  => trim($c->Ciudad_Asopagos ?? ''),
                ]
            );
            $importadas++;
        }

        $this->command->info("  → {$importadas} ciudades importadas.");
    }
}
