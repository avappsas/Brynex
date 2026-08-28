<?php

namespace App\Console\Commands;

use App\Services\Dataico\PortalDianService;
use Illuminate\Console\Command;

/**
 * Consulta un documento en la DIAN desde la terminal.
 *
 * Existe para probar la conexión sin pasar por la pantalla —y sin que nadie
 * tenga que escribir la contraseña en ningún lado: las credenciales ya viven
 * cifradas en `configuracion_brynex`—.
 */
class DataicoConsultaDian extends Command
{
    protected $signature = 'dataico:consulta-dian
        {documento : Número de documento, sin puntos}
        {--tipo=CC : CC, CE, NIT, PPT, PEP, PAS o TI}';

    protected $description = 'Consulta en la DIAN los datos de un documento, por el portal de Dataico';

    public function handle(PortalDianService $portal): int
    {
        $estado = $portal->estado();

        if (! $estado['configurado']) {
            $this->error('No hay credenciales del portal cargadas. Cárgalas en /brynex/consulta-dian.');

            return self::FAILURE;
        }

        if ($estado['bloqueo']) {
            $this->error($estado['bloqueo']);

            return self::FAILURE;
        }

        try {
            $r = $portal->consultar($this->option('tipo'), $this->argument('documento'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $r['encontrado']) {
            $this->warn($r['mensaje']);
        }

        $this->table(['Campo', 'DIAN'], collect([
            'Documento' => $r['tipo_doc'].' '.$r['identificacion'],
            'Tipo de persona' => $r['tipo_persona'],
            'Primer nombre' => $r['primer_nombre'],
            'Otros nombres' => $r['otros_nombres'],
            'Primer apellido' => $r['primer_apellido'],
            'Segundo apellido' => $r['segundo_apellido'],
            'Nombre completo' => $r['nombre_completo'],
            'Nombre comercial' => $r['nombre_comercial'],
            'Correo' => $r['correo'],
        ])->map(fn ($v, $k) => [$k, $v])->values()->all());

        return self::SUCCESS;
    }
}
