<?php

namespace App\Console\Commands;

use App\Models\OperadorCredencial;
use App\Models\OperadorPlanilla;
use App\Services\SuaporteApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Completa EPS, fondo de pensión y nombres de los clientes con lo que dice el
 * registro oficial (BDUA/RUAF), consultado a través del operador de planilla.
 *
 * Reglas de escritura — solo rellena huecos, nunca pisa un dato existente:
 *
 *   - EPS y pensión se escriben ÚNICAMENTE si el cliente los tiene vacíos
 *     (null, 0 o el id 1 = "[Ninguna]"). Si ya tiene uno distinto, no se toca:
 *     queda registrado como diferencia para el informe por aliado.
 *   - Los nombres se escriben campo por campo, y solo donde Brynex está vacío.
 *     Es lo normal en segundo nombre y segundo apellido, que el registro suele
 *     traer completos. Un campo con dato NUNCA se sobrescribe.
 *
 * Salvaguarda de identidad (lo más importante de este comando):
 *
 *   El registro responde por tipo + número de documento, y son espacios
 *   independientes: CC 104915 es una persona y CE 104915 es otra distinta.
 *   Por eso se consulta con el tipo_doc del cliente, y antes de escribir se
 *   compara el nombre devuelto con el que ya tiene Brynex. Si no se parecen,
 *   NO se escribe nada y el caso queda marcado como identidad dudosa. Los
 *   clientes sin tipo_doc (que se consultan como CC por defecto) son
 *   justamente los de mayor riesgo, así que ahí la verificación es la única
 *   defensa.
 *
 * Por defecto NO escribe nada: hay que pasar --aplicar.
 *
 *   php artisan clientes:completar-ruaf --limite=50              (simulación)
 *   php artisan clientes:completar-ruaf --limite=1000 --aplicar
 *   php artisan clientes:completar-ruaf --aliado=6 --fase=activos --aplicar
 *
 * El avance se guarda en `ruaf_consultas`: cada corrida toma los clientes que
 * todavía no tienen fila, así que se puede parar y seguir sin repetir. Para
 * reconsultar a alguien hay que borrar su fila de esa tabla.
 */
class CompletarRuafClientes extends Command
{
    protected $signature = 'clientes:completar-ruaf
        {--limite=100        : Cuántos clientes procesar en esta corrida}
        {--aliado=           : Procesar solo este aliado (id)}
        {--fase=pendientes   : pendientes | resto | todos}
        {--pausa=0           : Milisegundos de espera entre consultas}
        {--aplicar           : Escribir en la BD. Sin esto solo simula}
        {--reconsultar       : Volver a consultar los que ya tienen fila}';

    protected $description = 'Completa EPS, pensión y nombres de los clientes desde el registro oficial (BDUA/RUAF)';

    /** Ids que en los catálogos significan "ninguna" y cuentan como vacío. */
    private const ID_NINGUNA = 1;

    /** Códigos con que el registro dice "no está afiliado a nada". */
    private const CODIGOS_VACIOS = ['NIN-EP', 'NIN-AF', 'NINGUNA', 'N/A', ''];

    /**
     * Textos de relleno que el BDUA devuelve en vez de un nombre real. Se
     * descartan: escribirlos borraría el nombre bueno que ya tiene Brynex.
     */
    private const NOMBRES_BASURA = [
        'NOMBRE_INGRESO_BDUA', 'APELLI_INGRESO_BDUA', 'APELLIDO_INGRESO_BDUA',
        'SIN INFORMACION', 'SIN INFORMACIÓN', 'NO REPORTA', 'XXX', 'X',
    ];

    /** Por debajo de este parecido (0-100) se asume que es otra persona. */
    private const UMBRAL_IDENTIDAD = 55;

    private array $apis = [];
    private array $epsPorCodigo;
    private array $pensionPorCodigo;
    private array $contadores = [];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $limite  = max(1, (int) $this->option('limite'));
        $pausa   = max(0, (int) $this->option('pausa')) * 1000;

        // Los códigos que devuelve el registro son los mismos de los catálogos.
        $this->epsPorCodigo     = DB::table('eps')->pluck('id', 'codigo')->toArray();
        $this->pensionPorCodigo = DB::table('pensiones')->pluck('id', 'codigo')->toArray();

        $clientes = $this->clientesAProcesar($limite);

        if ($clientes->isEmpty()) {
            $this->info('No quedan clientes pendientes con los filtros dados.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info(($aplicar ? '● APLICANDO' : '○ SIMULACIÓN (no escribe nada)')." — {$clientes->count()} clientes");
        $this->line('');

        $barra = $this->output->createProgressBar($clientes->count());
        $barra->start();

        foreach ($clientes as $cliente) {
            $this->procesar($cliente, $aplicar);
            $barra->advance();

            if ($pausa) {
                usleep($pausa);
            }
        }

        $barra->finish();
        $this->line('');
        $this->resumen($aplicar);

        return self::SUCCESS;
    }

    /**
     * Orden de trabajo: primero los que no tienen ni EPS ni pensión (el hueco
     * más grande), luego el resto. Los que ya se consultaron se saltan.
     */
    private function clientesAProcesar(int $limite)
    {
        $q = DB::table('clientes as c')
            ->select('c.id', 'c.aliado_id', 'c.cedula', 'c.tipo_doc', 'c.eps_id', 'c.pension_id',
                'c.primer_nombre', 'c.segundo_nombre', 'c.primer_apellido', 'c.segundo_apellido');

        if (! $this->option('reconsultar')) {
            $q->whereNotExists(fn ($s) => $s->select(DB::raw(1))
                ->from('ruaf_consultas as r')
                ->whereColumn('r.cliente_id', 'c.id'));
        }

        if ($aliado = $this->option('aliado')) {
            $q->where('c.aliado_id', (int) $aliado);
        }

        $sinEps = fn ($w) => $w->whereNull('c.eps_id')->orWhere('c.eps_id', 0)->orWhere('c.eps_id', self::ID_NINGUNA);
        $sinPen = fn ($w) => $w->whereNull('c.pension_id')->orWhere('c.pension_id', 0)->orWhere('c.pension_id', self::ID_NINGUNA);

        match ($this->option('fase')) {
            // Lo que pediste primero: sin EPS ni pensión.
            'pendientes' => $q->where(fn ($w) => $w->where($sinEps))->where(fn ($w) => $w->where($sinPen)),
            // Los que ya tienen ambos: solo para detectar diferencias.
            'resto'      => $q->whereNotNull('c.eps_id')->where('c.eps_id', '>', self::ID_NINGUNA)
                              ->whereNotNull('c.pension_id')->where('c.pension_id', '>', self::ID_NINGUNA),
            default      => null,
        };

        return $q->orderBy('c.aliado_id')->orderBy('c.id')->limit($limite)->get();
    }

    private function procesar(object $c, bool $aplicar): void
    {
        $fila = [
            'cliente_id' => $c->id,
            'aliado_id'  => $c->aliado_id,
            'tipo_doc'   => $c->tipo_doc ?: 'CC',
            'cedula'     => (string) $c->cedula,
            'eps_id_antes'     => $c->eps_id,
            'pension_id_antes' => $c->pension_id,
            'nombre_antes'     => $this->nombreDe($c),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        [$api, $operador] = $this->apiPara((int) $c->aliado_id);

        if (! $api) {
            $this->guardar($fila + ['estado' => 'sin_credencial', 'mensaje' => 'El aliado no tiene credenciales ni respaldo']);
            $this->cuenta('sin_credencial');

            return;
        }

        $fila['operador'] = $operador;
        $r = $api->consultarAfiliacion($fila['tipo_doc'], $fila['cedula']);

        if (! $r['success']) {
            $this->guardar($fila + ['estado' => 'error', 'mensaje' => mb_substr($r['message'] ?? 'sin detalle', 0, 250)]);
            $this->cuenta('error');

            return;
        }

        if (! $r['registrado']) {
            $this->guardar($fila + ['estado' => 'no_hallado']);
            $this->cuenta('no_hallado');

            return;
        }

        $d = $r['afiliacion'];
        $this->cuenta('hallado');

        $fila['estado']      = 'hallado';
        $fila['payload']     = json_encode($d, JSON_UNESCAPED_UNICODE);
        $fila['nombre_ruaf'] = $this->nombreRuaf($d);

        // ── Salvaguarda de identidad ──────────────────────────────────────
        // Si Brynex ya tiene nombre, tiene que parecerse al del registro. Si
        // no se parece, el tipo de documento probablemente está mal y estamos
        // viendo a otra persona: no se escribe nada.
        $similitud = $this->similitud($fila['nombre_antes'], $fila['nombre_ruaf']);
        $fila['similitud_nombre'] = $similitud;
        $dudosa = $fila['nombre_antes'] !== '' && $similitud !== null && $similitud < self::UMBRAL_IDENTIDAD;
        $fila['identidad_dudosa'] = $dudosa;

        if ($dudosa) {
            $fila['accion_eps'] = $fila['accion_pension'] = $fila['accion_nombre'] = 'omitido';
            $fila['mensaje'] = "Identidad dudosa (parecido {$similitud}%): no se escribió nada";
            $this->guardar($fila);
            $this->cuenta('identidad_dudosa');

            return;
        }

        $cambios = [];

        // ── EPS ───────────────────────────────────────────────────────────
        $fila['eps_codigo'] = $d['administradoraBDUA'] ?? null;
        $epsId = $this->idDeCodigo($fila['eps_codigo'], $this->epsPorCodigo);
        $fila['eps_id_ruaf'] = $epsId;

        if (! $epsId) {
            $fila['accion_eps'] = 'sin_dato';
        } elseif ($this->estaVacio($c->eps_id)) {
            $fila['accion_eps'] = 'lleno';
            $cambios['eps_id'] = $epsId;
            $this->cuenta('eps_llenada');
        } elseif ((int) $c->eps_id === $epsId) {
            $fila['accion_eps'] = 'coincide';
        } else {
            $fila['accion_eps'] = 'difiere';
            $this->cuenta('eps_difiere');
        }

        // ── Pensión ───────────────────────────────────────────────────────
        $fila['pension_codigo'] = $d['administradoraRUAF'] ?? null;
        $penId = $this->idDeCodigo($fila['pension_codigo'], $this->pensionPorCodigo);
        $fila['pension_id_ruaf'] = $penId;

        if (! $penId) {
            $fila['accion_pension'] = 'sin_dato';
        } elseif ($this->estaVacio($c->pension_id)) {
            $fila['accion_pension'] = 'lleno';
            $cambios['pension_id'] = $penId;
            $this->cuenta('pension_llenada');
        } elseif ((int) $c->pension_id === $penId) {
            $fila['accion_pension'] = 'coincide';
        } else {
            $fila['accion_pension'] = 'difiere';
            $this->cuenta('pension_difiere');
        }

        // ── Nombres: campo por campo, solo los vacíos ─────────────────────
        $mapa = [
            'primer_nombre'    => 'primerNombre',
            'segundo_nombre'   => 'segundoNombre',
            'primer_apellido'  => 'primerApellido',
            'segundo_apellido' => 'segundoApellido',
        ];
        $escritos = [];
        $desalineado = false;

        // Palabras que el cliente YA tiene en cualquiera de sus campos de
        // nombre. Sirve para no duplicar cuando Brynex guardó un apellido en
        // la casilla equivocada: p. ej. Brynex tiene primer_apellido =
        // MONTENEGRO y el registro dice primer_apellido = ORDOÑEZ, segundo =
        // MONTENEGRO. Escribir el segundo dejaría "MONTENEGRO MONTENEGRO".
        // En ese caso no se escribe nada de nombres y el caso va al informe:
        // reordenar los campos es una decisión del aliado, no de este comando.
        $yaTiene = [];

        foreach (array_keys($mapa) as $campo) {
            $v = mb_strtoupper(trim((string) $c->{$campo}));

            if ($v !== '') {
                $yaTiene[] = $v;
            }
        }

        foreach ($mapa as $campo => $clave) {
            $valor = $this->limpiarNombre($d[$clave] ?? null);

            if ($valor === null) {
                continue;
            }
            if (trim((string) $c->{$campo}) !== '') {
                continue;   // ya tiene dato: no se pisa nunca
            }
            if (in_array($valor, $yaTiene, true)) {
                $desalineado = true;    // ese apellido ya está, en otra casilla
                continue;
            }

            $cambios[$campo] = $valor;
            $escritos[] = $campo;
        }

        // Si algún campo quedó desalineado, no se escribe ningún nombre: el
        // registro y Brynex no están hablando de las mismas casillas y un
        // relleno parcial dejaría el nombre peor de como estaba.
        if ($desalineado) {
            foreach (array_keys($mapa) as $campo) {
                unset($cambios[$campo]);
            }

            $escritos = [];
            $fila['mensaje'] = 'Campos de nombre desalineados: se completaron solo EPS/pensión';
            $this->cuenta('nombre_desalineado');
        }

        if ($escritos) {
            $fila['accion_nombre']   = 'lleno';
            $fila['campos_escritos'] = implode(',', $escritos);
            $this->cuenta('nombres_llenados');
        } elseif ($desalineado) {
            $fila['accion_nombre'] = 'desalineado';
        } elseif ($fila['nombre_antes'] !== '' && $similitud !== null && $similitud < 100) {
            $fila['accion_nombre'] = 'difiere';
            $this->cuenta('nombre_difiere');
        } else {
            $fila['accion_nombre'] = 'coincide';
        }

        if ($cambios && $aplicar) {
            DB::table('clientes')->where('id', $c->id)->update($cambios + ['updated_at' => now()]);
            $this->cuenta('clientes_actualizados');
        } elseif ($cambios) {
            $this->cuenta('clientes_actualizarian');
        }

        $this->guardar($fila);
    }

    /**
     * La bitácora se escribe siempre, incluso simulando: así una simulación
     * ya deja armado el informe de diferencias, y la siguiente corrida sabe
     * por dónde iba. Para reconsultar, borrar la fila o usar --reconsultar.
     */
    private function guardar(array $fila): void
    {
        DB::table('ruaf_consultas')->updateOrInsert(
            ['cliente_id' => $fila['cliente_id']],
            $fila
        );
    }

    /**
     * API del aliado; si no tiene credenciales propias cae a las de BryNex,
     * igual que la consulta del modal de clientes. Consultar una afiliación
     * es de solo lectura y no exige autorización sobre el aportante.
     */
    private function apiPara(int $aliadoId): array
    {
        if (array_key_exists($aliadoId, $this->apis)) {
            return $this->apis[$aliadoId];
        }

        foreach ([$aliadoId, 1] as $id) {
            $cred = OperadorCredencial::where('aliado_id', $id)
                ->whereNull('deleted_at')
                ->get()
                ->first(function ($c) {
                    $op = OperadorPlanilla::find($c->operador_planilla_id);

                    return $op && SuaporteApiService::soportaOperador($op->codigo);
                });

            if (! $cred) {
                continue;
            }

            $op = OperadorPlanilla::find($cred->operador_planilla_id);

            return $this->apis[$aliadoId] = [
                new SuaporteApiService([
                    'operador'      => $op->codigo,
                    'usuario'       => $cred->usuario,
                    'contrasena'    => $cred->contrasena,
                    'clave_secreta' => $cred->clave_secreta,
                ]),
                $op->codigo,
            ];
        }

        return $this->apis[$aliadoId] = [null, null];
    }

    private function estaVacio($valor): bool
    {
        return $valor === null || $valor === '' || (int) $valor === 0 || (int) $valor === self::ID_NINGUNA;
    }

    private function idDeCodigo(?string $codigo, array $catalogo): ?int
    {
        $codigo = trim((string) $codigo);

        if ($codigo === '' || in_array(strtoupper($codigo), self::CODIGOS_VACIOS, true)) {
            return null;
        }

        return isset($catalogo[$codigo]) ? (int) $catalogo[$codigo] : null;
    }

    /** Descarta los rellenos del BDUA y normaliza espacios. */
    private function limpiarNombre(?string $valor): ?string
    {
        $valor = trim(preg_replace('/\s+/', ' ', (string) $valor));

        if ($valor === '' || mb_strlen($valor) < 2) {
            return null;
        }

        $mayus = mb_strtoupper($valor);

        foreach (self::NOMBRES_BASURA as $basura) {
            if (str_contains($mayus, $basura)) {
                return null;
            }
        }

        // Un "nombre" sin una sola vocal no es un nombre.
        if (! preg_match('/[AEIOUÁÉÍÓÚ]/u', $mayus)) {
            return null;
        }

        return $mayus;
    }

    private function nombreDe(object $c): string
    {
        return trim(preg_replace('/\s+/', ' ',
            "{$c->primer_nombre} {$c->segundo_nombre} {$c->primer_apellido} {$c->segundo_apellido}"));
    }

    private function nombreRuaf(array $d): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', [
            $d['primerNombre'] ?? '', $d['segundoNombre'] ?? '',
            $d['primerApellido'] ?? '', $d['segundoApellido'] ?? '',
        ])));
    }

    /**
     * Parecido entre dos nombres, 0-100, sin tildes ni mayúsculas.
     *
     * No compara las cadenas completas sino los conjuntos de palabras: Brynex
     * suele tener el nombre incompleto ("CARMEN GAMBOA" vs "CARMEN EDILSA
     * GAMBOA MOSQUERA"), y eso es un dato a completar, no otra persona. Lo que
     * delata a otra persona es que NINGUNA palabra coincida.
     */
    private function similitud(string $a, string $b): ?int
    {
        if ($a === '' || $b === '') {
            return null;
        }

        $normalizar = function (string $s) {
            $s = mb_strtoupper($s);
            $s = strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U']);

            return array_values(array_filter(explode(' ', preg_replace('/[^A-Z ]/', '', $s))));
        };

        $pa = $normalizar($a);
        $pb = $normalizar($b);

        if (! $pa || ! $pb) {
            return null;
        }

        // Cada palabra del nombre más corto busca su mejor pareja en el otro.
        [$corto, $largo] = count($pa) <= count($pb) ? [$pa, $pb] : [$pb, $pa];
        $suma = 0;

        foreach ($corto as $palabra) {
            $mejor = 0;

            foreach ($largo as $otra) {
                similar_text($palabra, $otra, $pct);
                $mejor = max($mejor, $pct);
            }

            $suma += $mejor;
        }

        return (int) round($suma / count($corto));
    }

    private function cuenta(string $clave): void
    {
        $this->contadores[$clave] = ($this->contadores[$clave] ?? 0) + 1;
    }

    private function resumen(bool $aplicar): void
    {
        $c = $this->contadores;
        $filas = [
            ['Hallados en el registro',      $c['hallado'] ?? 0],
            ['No hallados',                  $c['no_hallado'] ?? 0],
            ['Errores del operador',         $c['error'] ?? 0],
            ['Sin credencial',               $c['sin_credencial'] ?? 0],
            ['― Identidad dudosa (omitidos)', $c['identidad_dudosa'] ?? 0],
            ['EPS llenadas',                 $c['eps_llenada'] ?? 0],
            ['Pensiones llenadas',           $c['pension_llenada'] ?? 0],
            ['Nombres completados',          $c['nombres_llenados'] ?? 0],
            ['― Nombre desalineado (informe)', $c['nombre_desalineado'] ?? 0],
            ['EPS distintas (informe)',      $c['eps_difiere'] ?? 0],
            ['Pensiones distintas (informe)', $c['pension_difiere'] ?? 0],
            ['Nombres distintos (informe)',  $c['nombre_difiere'] ?? 0],
            [$aplicar ? 'Clientes ACTUALIZADOS' : 'Clientes que se actualizarían',
                $aplicar ? ($c['clientes_actualizados'] ?? 0) : ($c['clientes_actualizarian'] ?? 0)],
        ];

        $this->line('');
        $this->table(['Concepto', 'Cantidad'], $filas);

        if (! $aplicar) {
            $this->warn('Simulación: no se escribió nada en clientes. Repita con --aplicar.');
        }

        $pendientes = DB::table('clientes')
            ->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('ruaf_consultas as r')->whereColumn('r.cliente_id', 'clientes.id'))
            ->count();
        $this->line("Clientes sin consultar todavía: ".number_format($pendientes));
    }
}
