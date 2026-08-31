<?php

namespace App\Services\ArlSura;

use App\Models\ArlCentroTrabajo;
use App\Models\RazonSocial;

/**
 * Sincroniza los centros de trabajo de una póliza.
 *
 * Vive aparte del comando porque hacen falta en dos momentos: el barrido
 * periódico (`arl:sincronizar-centros`) y justo después de registrar las
 * credenciales de una empresa, para que quede lista para afiliar sin un paso
 * manual de por medio.
 *
 * Los centros son de la PÓLIZA, así que se replican a todas las razones sociales
 * que la comparten, sin importar el aliado: es la misma empresa ante Sura.
 */
class ArlCentrosService
{
    /**
     * @return array{centros:int, razones:int} Cuántos centros distintos y a cuántas razones sociales se aplicaron.
     */
    public static function sincronizarPorPoliza(string $poliza, int $aliadoId): array
    {
        $api     = new ArlSuraApiService($aliadoId, $poliza);
        $centros = $api->centrosDeTrabajo();

        if (! $centros) {
            return ['centros' => 0, 'razones' => 0];
        }

        $normalizados = [];

        foreach ($centros as $c) {
            $codigo = trim((string) ($c['cdSucursal'] ?? ''));
            if ($codigo === '') {
                continue;
            }

            $normalizados[$codigo] = [
                'nombre_centro'   => $c['dsSucursal']     ?? null,
                'nivel_riesgo'    => (int) ($c['cdClase'] ?? 0),
                'tasa'            => $c['poCotizacion']   ?? null,
                'cd_actividad'    => $c['cdActividad']    ?? null,
                'municipio_sura'  => $c['cdMunicipio']    ?? null,
                'departamento'    => $c['dsDepartamento'] ?? null,
                'municipio'       => $c['dsMunicipio']    ?? null,
                'direccion'       => $c['direccion']      ?? null,
                'telefono'        => $c['telefono']       ?? null,
                'activo'          => true,
                'sincronizado_at' => now(),
            ];
        }

        $razones = RazonSocial::where('arl_poliza', $poliza)->get(['id', 'aliado_id']);

        foreach ($razones as $rs) {
            foreach ($normalizados as $codigo => $datos) {
                ArlCentroTrabajo::updateOrCreate(
                    ['razon_social_id' => $rs->id, 'codigo_centro' => $codigo],
                    $datos + ['aliado_id' => $rs->aliado_id]
                );
            }

            // Los que ya no están en el portal se desactivan, no se borran: el
            // historial de afiliaciones los referencia.
            ArlCentroTrabajo::where('razon_social_id', $rs->id)
                ->whereNotIn('codigo_centro', array_keys($normalizados))
                ->update(['activo' => false]);
        }

        return ['centros' => count($normalizados), 'razones' => $razones->count()];
    }
}
