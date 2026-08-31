<?php

namespace App\Console\Commands;

use App\Services\ArlSura\ArlSuraApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rellena `eps.codigo_sura` y `pensiones.codigo_sura` con los códigos que espera
 * ARL Sura al afiliar.
 *
 * Hace falta porque los códigos no coinciden con los nuestros: EMSSANAR es
 * `ESSC18` en BryNex y `148` en Sura; PORVENIR es `230301` (PILA) y `003`.
 * Sin esta equivalencia el afiliar rechaza la EPS y la AFP de cualquier
 * trabajador.
 *
 * El cruce se hace por NIT, no por nombre: los nombres comerciales cambian y
 * están escritos distinto en cada sistema ("NUEVA EPS S.A." vs "NUEVAEPS"),
 * mientras que el NIT es el mismo documento. Sura lo manda como `N800224808`,
 * con la letra del tipo de documento por delante.
 */
class ArlSincronizarCatalogos extends Command
{
    protected $signature = 'arl:sincronizar-catalogos
                            {--aliado=1 : Aliado dueño de la sesión}
                            {--poliza= : Póliza ARL con la que consultar}
                            {--cookie= : Cookie de una sesión abierta en el portal}
                            {--dry-run : Muestra lo que haría sin escribir en la BD}';

    protected $description = 'Trae de ARL Sura los códigos de EPS y AFP y los guarda como codigo_sura';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        $poliza   = (string) ($this->option('poliza') ?: '');
        $seco     = (bool) $this->option('dry-run');

        if ($poliza === '') {
            $this->error('Falta --poliza. Es la póliza ARL de la razón social (el número de contrato que muestra el portal).');
            return self::FAILURE;
        }

        if ($cookie = $this->option('cookie')) {
            ArlSuraApiService::guardarSesion($aliadoId, $poliza, $cookie);
            $this->line('Sesión sembrada.');
        }

        $api = new ArlSuraApiService($aliadoId, $poliza);

        if (! $api->sesionViva()) {
            $this->error('La sesión de ARL Sura no responde. Vuelve a entrar al portal y pasa la cookie con --cookie.');
            return self::FAILURE;
        }

        $total = 0;
        $total += $this->sincronizar($api->epsListado(), 'eps', 'codigoEps', $seco);
        $total += $this->sincronizar($api->afpListado(), 'pensiones', 'codigoAfp', $seco);

        $this->newLine();
        $this->info(($seco ? '[dry-run] ' : '')."Entidades emparejadas: {$total}");

        return self::SUCCESS;
    }

    /**
     * @param  array<int,array<string,mixed>>  $remotas
     */
    private function sincronizar(array $remotas, string $tabla, string $campoCodigo, bool $seco): int
    {
        $this->newLine();
        $this->line("── {$tabla} ──");

        // NIT limpio => TODAS las filas de Sura con ese NIT. La letra inicial del
        // `dni` es el tipo de documento.
        //
        // Ojo: el mismo NIT aparece con varios códigos —NUEVA EPS es 037 y también
        // S41 "REG MOVILIDAD"; MEDIMAS es 044 contributivo y 045 subsidiado—, así
        // que quedarse con el último que llega elige mal más veces de las que
        // acierta. Por eso se agrupan y se desempata más abajo.
        $porNit = [];
        foreach ($remotas as $r) {
            if ($nit = $this->soloDigitos($r['dni'] ?? '')) {
                $porNit[$nit][] = $r;
            }
        }

        $emparejadas = 0;
        $huerfanas   = [];

        foreach (DB::table($tabla)->get(['id', 'nit', 'razon_social']) as $local) {
            $nit        = $this->soloDigitos($local->nit ?? '');
            $candidatas = $porNit[$nit] ?? [];
            $porNombre  = false;

            // Varios NIT nuestros están desactualizados: PORVENIR figura con
            // 800144331 y en Sura es 800224808; EMSSANAR con 814000337 contra
            // 901021565. Son las dos entidades más usadas, así que cuando el NIT
            // no cruza se intenta por nombre antes de darlas por perdidas.
            if (! $candidatas) {
                if ($similar = $this->buscarPorNombre($remotas, (string) ($local->razon_social ?? ''))) {
                    $candidatas = [$similar];
                    $porNombre  = true;
                } else {
                    $huerfanas[] = trim($local->razon_social ?? ('id '.$local->id));
                    continue;
                }
            }

            $remota    = $this->elegir($candidatas, (string) ($local->razon_social ?? ''), $campoCodigo);
            $desempate = count($candidatas) > 1;

            $emparejadas++;

            if (! $seco) {
                DB::table($tabla)->where('id', $local->id)->update([
                    'codigo_sura' => $remota[$campoCodigo] ?? null,
                    'dni_sura'    => $remota['dni'] ?? null,
                ]);
            }

            $this->line(sprintf('  %-44s → %-4s %s',
                mb_substr(trim($local->razon_social ?? ''), 0, 44),
                $remota[$campoCodigo] ?? '?',
                $porNombre
                    ? '  ⟵ por NOMBRE (el NIT no cruza): '.trim($remota['dsNombre'] ?? '')
                    : ($desempate ? '  ⟵ había '.count($candidatas).' con ese NIT: '.trim($remota['dsNombre'] ?? '') : '')));
        }

        if ($huerfanas) {
            $this->warn('  Sin equivalencia en Sura ('.count($huerfanas).'): '.implode(', ', array_slice($huerfanas, 0, 8)).(count($huerfanas) > 8 ? '…' : ''));
            $this->line('  No es necesariamente un error: incluye entidades liquidadas que Sura ya no lista.');
        }

        return $emparejadas;
    }

    /**
     * Desempata cuando un NIT tiene varios códigos en Sura.
     *
     * Descarta primero los regímenes que no aplican a un trabajador formal
     * —movilidad y subsidiado— y entre lo que queda se queda con el nombre más
     * parecido al nuestro. Si aun así hay empate, gana el primero, pero el
     * comando lo imprime para que se revise con --dry-run antes de guardar.
     *
     * @param  array<int,array<string,mixed>>  $candidatas
     * @return array<string,mixed>
     */
    private function elegir(array $candidatas, string $nombreLocal, string $campoCodigo): array
    {
        if (count($candidatas) === 1) {
            return $candidatas[0];
        }

        $puntaje = function (array $c) use ($nombreLocal): int {
            $nombre = mb_strtoupper(trim($c['dsNombre'] ?? ''));
            $p = 0;

            foreach (['MOVILIDAD', 'SUBSIDIADO', 'FOSYGA'] as $descartable) {
                if (str_contains($nombre, $descartable)) {
                    $p -= 50;
                }
            }

            similar_text(mb_strtoupper($nombreLocal), $nombre, $porcentaje);

            return $p + (int) $porcentaje;
        };

        usort($candidatas, fn ($a, $b) => $puntaje($b) <=> $puntaje($a));

        return $candidatas[0];
    }

    /**
     * Último recurso cuando el NIT no cruza: buscar por nombre.
     *
     * No se compara palabra por palabra —"Eps-s emssanar" y "EMSSANAR E.S.S.
     * EMPRESA SOLIDARIA DE SALUD NARINO" no se parecen como cadenas— sino por la
     * parte distintiva del nombre, quitando el ruido común del sector.
     *
     * @param  array<int,array<string,mixed>>  $remotas
     * @return array<string,mixed>|null
     */
    private function buscarPorNombre(array $remotas, string $nombreLocal): ?array
    {
        $clave = $this->nucleo($nombreLocal);

        if (mb_strlen($clave) < 4) {
            return null;
        }

        foreach ($remotas as $r) {
            $remoto = $this->nucleo((string) ($r['dsNombre'] ?? ''));

            if ($remoto !== '' && (str_starts_with($remoto, $clave) || str_starts_with($clave, $remoto))) {
                return $r;
            }
        }

        return null;
    }

    /** Deja solo la parte que identifica a la entidad, sin acentos ni palabras de relleno. */
    private function nucleo(string $nombre): string
    {
        $texto = mb_strtoupper(trim($nombre));
        $texto = strtr($texto, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
        $texto = preg_replace('/[^A-Z0-9 ]/', ' ', $texto);

        $relleno = ['EPS', 'EPSS', 'ESS', 'SA', 'SAS', 'LTDA', 'S', 'E', 'P',
                    'ENTIDAD', 'PROMOTORA', 'DE', 'SALUD', 'EMPRESA', 'SOLIDARIA',
                    'CAJA', 'COMPENSACION', 'FAMILIAR', 'CCF', 'FONDO', 'PENSIONES',
                    'Y', 'DEL', 'LA', 'EL', 'ADMINISTRADORA'];

        $palabras = array_values(array_diff(preg_split('/\s+/', trim($texto)), $relleno));

        return implode('', array_slice($palabras, 0, 2));
    }

    private function soloDigitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?: '';
    }
}
