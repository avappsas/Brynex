<?php

namespace App\Console\Commands;

use App\Services\Dataico\Adquiriente;
use App\Services\RegistroOficialService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Completa el nombre de las empresas que en realidad son personas naturales.
 *
 * En `empresas` conviven sociedades y empleadores persona natural. Los
 * segundos se crearon escribiendo el nombre a mano, así que están incompletos
 * o con títulos pegados: «ING. JHON OSPINA», «SONIA», «WILSON LARRAHONDO»
 * cuando el registro dice «WILSON MONTENEGRO LARRAHONDO». Eso llega tal cual a
 * la factura electrónica.
 *
 * Consulta BDUA/RUAF por la cédula guardada en `nit` —la misma consulta que
 * usa el modal de cliente nuevo— y guarda el resultado en `nombre_legal`, que
 * es el que viaja a la DIAN. `empresa` no se toca: ahí vive el nombre del
 * negocio, que es como se reconoce al cliente en el resto de Brynex.
 *
 * Lo que NO hace: sobrescribir cuando el nombre oficial no se parece al que
 * hay. Un desajuste total no es un nombre mal escrito, es una cédula
 * equivocada: la 16262803 está guardada como «ING. JHON OSPINA» y en el
 * registro es ISAI SANCHEZ CANAVAL. Actualizar a ciegas dejaría a esa empresa
 * con el nombre de un tercero y facturándole a él. Esos se listan y se dejan
 * quietos.
 *
 * Sin `--ejecutar` solo reporta.
 */
class EmpresasNombresOficiales extends Command
{
    protected $signature = 'empresas:nombres-oficiales
        {--aliado= : Aliado cuyas empresas se revisan}
        {--limite= : Tope de empresas a consultar}
        {--ejecutar : Escribe los nombres. Sin esto, solo reporta}';

    protected $description = 'Completa el nombre de las empresas persona natural contra el registro oficial';

    /** Títulos y tratamientos que la gente pega al nombre y no son parte de él. */
    private const RUIDO = ['ING', 'INGENIERO', 'SR', 'SRA', 'DR', 'DRA', 'LIC', 'ARQ', 'CONT', 'ADM'];

    public function handle(RegistroOficialService $registro): int
    {
        $aliadoId = (int) $this->option('aliado');

        if (! $aliadoId) {
            $this->error('Falta --aliado.');

            return self::FAILURE;
        }

        $empresas = DB::table('empresas')
            ->where('aliado_id', $aliadoId)
            ->whereNotNull('nit')
            ->whereRaw("LTRIM(RTRIM(nit)) <> ''")
            ->select('id', 'empresa', 'nombre_legal', 'nit', 'tipo_documento')
            ->orderBy('id')
            ->get()
            // Solo personas naturales. Manda el tipo capturado; si la empresa
            // todavía no lo tiene, se cae a la misma heurística que usa la
            // factura electrónica —un NIT de sociedad son 9-10 dígitos
            // empezando en 8 o 9—.
            //
            // Ojo con el largo: descartar por el primer dígito sin mirarlo deja
            // fuera cédulas normales como 94409836, que son de 8 dígitos.
            ->filter(function ($e) {
                $doc = preg_replace('/\D+/', '', (string) $e->nit);

                if (strlen($doc) < 6 || strlen($doc) > 10) {
                    return false;
                }

                $tipo = strtoupper(trim((string) ($e->tipo_documento ?? '')));

                return $tipo !== '' ? $tipo !== 'NIT' : ! Adquiriente::pareceNitEmpresa($doc);
            })
            ->when($this->option('limite'), fn ($c) => $c->take((int) $this->option('limite')))
            ->values();

        $ejecutar = (bool) $this->option('ejecutar');

        $this->info("Revisando {$empresas->count()} empresas del aliado {$aliadoId}"
                   .($ejecutar ? '' : '  (SIMULACIÓN — no escribe nada)'));

        $barra = $this->output->createProgressBar($empresas->count());
        $barra->start();

        $grupos = ['mejora' => [], 'igual' => [], 'dudoso' => [], 'sin_parecido' => [], 'no_encontrado' => [], 'error' => []];

        foreach ($empresas as $e) {
            $barra->advance();
            $doc = preg_replace('/\D+/', '', (string) $e->nit);

            $oficial = $this->consultarNombre($registro, $aliadoId, $doc);

            if ($oficial === null) {
                $grupos['error'][] = [$e->id, $e->empresa, $doc, 'el operador no respondió'];

                continue;
            }

            if ($oficial === '') {
                $grupos['no_encontrado'][] = [$e->id, $e->empresa, $doc, 'sin registro'];

                continue;
            }

            // Se compara contra el nombre legal si ya se capturó; si no,
            // contra el del negocio, que es lo único que había.
            $referencia = (string) ($e->nombre_legal ?: $e->empresa);
            $veredicto = $this->comparar($referencia, $oficial);
            $grupos[$veredicto][] = [$e->id, $referencia, $doc, $oficial];

            // Escribe en `nombre_legal`, NUNCA en `empresa`: el nombre del
            // negocio es como se reconoce al cliente en todo Brynex —MAXIDROGAS,
            // CHOMPAS— y pisarlo con el nombre del dueño pierde esa referencia.
            // A la DIAN va el legal; a las pantallas, el del negocio.
            if ($veredicto === 'mejora' && $ejecutar) {
                DB::table('empresas')->where('id', $e->id)->update(['nombre_legal' => $oficial]);
            }
        }

        $barra->finish();
        $this->newLine(2);

        foreach (['mejora' => 'nombre legal capturado', 'igual' => 'ya estaban bien', 'dudoso' => 'coinciden en una sola palabra',
            'sin_parecido' => '⚠️  NO se parecen — probable cédula equivocada',
            'no_encontrado' => 'sin registro en BDUA/RUAF', 'error' => 'no se pudieron consultar'] as $k => $titulo) {
            $this->line('  '.str_pad($titulo, 46).count($grupos[$k]));
        }

        foreach (['sin_parecido', 'dudoso', 'mejora'] as $k) {
            if (empty($grupos[$k])) {
                continue;
            }

            $this->newLine();
            $this->line(strtoupper(str_replace('_', ' ', $k)).':');
            $this->table(['id', 'Nombre actual', 'Documento', 'Nombre oficial'], array_slice($grupos[$k], 0, 40));

            if (count($grupos[$k]) > 40) {
                $this->line('  … y '.(count($grupos[$k]) - 40).' más.');
            }
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->comment('Nada se escribió. Repite con --ejecutar para guardar el nombre legal de las de «nombre legal capturado».');
        }

        return self::SUCCESS;
    }

    /**
     * Nombre oficial de una cédula, o '' si no figura, o null si falló.
     *
     * El tipo de documento es parte de la llave: una CC y una CE con el mismo
     * número son personas distintas, y el tipo equivocado devuelve vacío en vez
     * de error. Por eso, si con CC no aparece, se intenta con CE.
     */
    private function consultarNombre(RegistroOficialService $registro, int $aliadoId, string $doc): ?string
    {
        foreach (['CC', 'CE'] as $tipo) {
            $r = $registro->consultar($aliadoId, $doc, $tipo);

            if ($r === null) {
                return null;
            }

            $nombre = trim(implode(' ', array_filter([
                $r['primer_nombre'] ?? '', $r['segundo_nombre'] ?? '',
                $r['primer_apellido'] ?? '', $r['segundo_apellido'] ?? '',
            ])));

            if ($nombre !== '') {
                return preg_replace('/\s+/', ' ', $nombre);
            }
        }

        return '';
    }

    /**
     * ¿El nombre oficial es el mismo de siempre, una mejora, o otra persona?
     *
     * Se compara por palabras compartidas. Dos coincidencias bastan para dar
     * por buena la mejora; una sola es ambigua —«JUAN PEREZ» y «JUAN GOMEZ»
     * comparten el nombre de pila y son dos personas— salvo que el nombre
     * guardado sea de una sola palabra, como «SONIA».
     */
    private function comparar(string $actual, string $oficial): string
    {
        $a = $this->palabras($actual);
        $o = $this->palabras($oficial);

        if (empty($a) || empty($o)) {
            return 'sin_parecido';
        }

        // Se compara la frase completa, no el conjunto de palabras: con
        // palabras únicas «ODALINDA CARO» y «ODALINDA CARO CARO» salen iguales
        // porque el apellido repetido se colapsa, y el segundo apellido se
        // pierde para siempre.
        if (implode(' ', $this->palabras($actual, false)) === implode(' ', $this->palabras($oficial, false))) {
            return 'igual';
        }

        $comunes = count(array_intersect($a, $o));

        return match (true) {
            $comunes >= 2 => 'mejora',
            $comunes === 1 && (count($a) === 1 || count($o) === 1) => 'mejora',
            $comunes === 1 => 'dudoso',
            default => 'sin_parecido',
        };
    }

    /**
     * Palabras significativas, sin tildes, títulos ni partículas.
     *
     * `$unicas` en falso conserva el orden y las repeticiones, que es lo que
     * hace falta para comparar dos nombres como frases.
     */
    private function palabras(string $s, bool $unicas = true): array
    {
        $s = mb_strtoupper(trim($s));
        $s = strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U']);
        $s = preg_replace('/[^A-Z ]+/', ' ', $s);

        $p = array_filter(
            preg_split('/\s+/', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn ($w) => strlen($w) >= 3 && ! in_array($w, self::RUIDO, true)
        );

        if (! $unicas) {
            return array_values($p);
        }

        $p = array_unique($p);
        sort($p);

        return array_values($p);
    }
}
