<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Radiografía de las cuentas de cada aliado: cuáles se usan, cuáles no, y
 * cuáles no identifican a una persona.
 *
 * Las cuentas genéricas ("Soporte", "Afiliaciones") son el punto ciego de todo
 * lo demás: si tres personas comparten una, el registro de accesos dirá que
 * entró "Soporte" desde un equipo nuevo y no habrá a quién preguntarle. Toda
 * la trazabilidad se apoya en que una cuenta sea una persona.
 *
 * Mientras `users.ultimo_acceso` se llena (solo registra desde que se desplegó
 * el registro de accesos), la última actividad se saca de la bitácora, que ya
 * lleva meses acumulando.
 */
class UsuariosAuditar extends Command
{
    protected $signature = 'usuarios:auditar {aliado? : Id del aliado; si se omite, todos}';

    protected $description = 'Audita las cuentas por aliado: uso real, cuentas genéricas y cuentas muertas';

    /** Días sin actividad a partir de los cuales una cuenta se considera dormida. */
    private const DIAS_DORMIDA = 60;

    /** Palabras que delatan una cuenta de función, no de persona. */
    private const PALABRAS_GENERICAS = [
        'soporte', 'admin', 'administrador', 'sistema', 'brynex', 'afiliacion',
        'afiliaciones', 'contabilidad', 'cartera', 'prueba', 'test', 'temporal',
        'usuario', 'oficina', 'recepcion', 'ventas', 'info', 'general',
    ];

    public function handle(): int
    {
        $usuarios = User::with(['aliado', 'roles'])
            ->where('es_brynex', false)
            ->when($this->argument('aliado'), fn ($q, $id) => $q->where('aliado_id', $id))
            ->orderBy('aliado_id')
            ->orderBy('nombre')
            ->get();

        if ($usuarios->isEmpty()) {
            $this->error('No hay usuarios que auditar.');

            return self::FAILURE;
        }

        // Una sola consulta para toda la actividad: la BD es remota y cada
        // viaje cuesta, así que no se pregunta usuario por usuario.
        $actividad = DB::table('bitacora')
            ->select('user_id', DB::raw('MAX(created_at) as ultima'), DB::raw('COUNT(*) as total'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $nombresRepetidos = $usuarios->groupBy(fn ($u) => Str::upper(trim($u->nombre)))
            ->filter(fn ($g) => $g->count() > 1)
            ->keys();

        $filas = [];
        $resumen = ['genericas' => 0, 'nunca' => 0, 'dormidas' => 0, 'sin_rol' => 0, 'pausadas' => 0];

        foreach ($usuarios as $u) {
            $act = $actividad->get($u->id);
            $ultima = $act?->ultima ? \Carbon\Carbon::parse($act->ultima) : null;
            $dias = $ultima?->diffInDays(now());

            $marcas = [];

            if ($this->esGenerica($u->nombre)) {
                $marcas[] = 'GENÉRICA';
                $resumen['genericas']++;
            }
            if ($nombresRepetidos->contains(Str::upper(trim($u->nombre)))) {
                $marcas[] = 'nombre repetido';
            }
            if ($u->roles->isEmpty()) {
                $marcas[] = 'sin rol';
                $resumen['sin_rol']++;
            }
            if (! $u->activo) {
                $marcas[] = 'pausada';
                $resumen['pausadas']++;
            }
            if ($u->ultimo_acceso) {
                $fuente = 'acceso';
                $referencia = $u->ultimo_acceso;
            } elseif ($ultima) {
                $fuente = 'cambios';
                $referencia = $ultima;
            } else {
                $fuente = null;
                $referencia = null;
            }

            if (! $referencia) {
                $marcas[] = 'sin rastro';
                $resumen['nunca']++;
            } elseif ($fuente === 'acceso' && $referencia->diffInDays(now()) > self::DIAS_DORMIDA) {
                $marcas[] = 'dormida';
                $resumen['dormidas']++;
            }

            $filas[] = [
                Str::limit($u->aliado->nombre ?? (string) $u->aliado_id, 16, ''),
                Str::limit($u->nombre, 26, ''),
                $u->roles->pluck('name')->first() ?? '—',
                $referencia ? $referencia->format('Y-m-d').' ('.$fuente.')' : '—',
                $act->total ?? 0,
                implode(', ', $marcas),
            ];
        }

        $this->table(
            ['Aliado', 'Usuario', 'Rol', 'Últ. actividad', 'Acciones', 'Observaciones'],
            $filas
        );

        $this->newLine();
        $this->line(sprintf(
            'Total %d cuentas · %d genéricas · %d sin rastro · %d dormidas (+%dd) · %d sin rol · %d pausadas',
            count($filas),
            $resumen['genericas'],
            $resumen['nunca'],
            $resumen['dormidas'],
            self::DIAS_DORMIDA,
            $resumen['sin_rol'],
            $resumen['pausadas']
        ));

        $this->newLine();
        $this->warn('«sin rastro» NO significa cuenta sin usar.');
        $this->line('  La columna (cambios) sale de la bitácora, que solo observa Clientes, Beneficiarios');
        $this->line('  y Documentos: quien únicamente factura, cobra, radica o consulta no aparece ahí.');
        $this->line('  La señal fiable es (acceso), y solo se llena desde que se desplegó el registro de');
        $this->line('  accesos. Deja pasar unas semanas antes de dar de baja una cuenta por este listado.');

        return self::SUCCESS;
    }

    /**
     * Una cuenta es genérica si su nombre no identifica a una persona: o es una
     * sola palabra, o contiene un término de función.
     */
    private function esGenerica(string $nombre): bool
    {
        $limpio = Str::lower(Str::ascii(trim($nombre)));

        if (count(array_filter(explode(' ', $limpio))) < 2) {
            return true;
        }

        foreach (self::PALABRAS_GENERICAS as $palabra) {
            if (str_contains($limpio, $palabra)) {
                return true;
            }
        }

        return false;
    }
}
