<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Plano;

/**
 * Traduce los errores autocorregibles que devuelve la validación de Enlace
 * a cambios concretos sobre los datos de Brynex.
 *
 * Enlace marca como `autocorreccion: "Si"` los errores que su corrector puede
 * arreglar solo — en la práctica, que el cotizante venga en el archivo con una
 * administradora distinta de aquella a la que realmente está afiliado según
 * BDUA/RUAF. Ejemplo real:
 *
 *   "El cotizante aportará a la administradora de pension 25-14 COLPENSIONES,
 *    pero se encuentra afiliado a la administradora 230301 PORVENIR."
 *
 * Corregir SOLO en Enlace deja el dato malo en Brynex y el error se repite el
 * mes siguiente, así que además de llamar al corrector se refleja el cambio en
 * los tres lugares donde vive: el `plano` del período (de ahí sale el TXT), los
 * contratos vigentes del cotizante y la ficha del cliente (de ahí se precarga
 * el próximo contrato).
 */
class CorreccionEnlaceService
{
    /**
     * Subsistemas que sabemos reflejar en Brynex, con la tabla de
     * administradoras y las columnas donde vive el dato.
     *
     * `col_nombre_catalogo` no es uniforme entre tablas: se respeta el mismo
     * criterio que usa Plano::datosDesdeContrato() al armar el snapshot.
     *
     * `col_cliente` es null donde `clientes` no guarda esa administradora:
     * la tabla solo tiene EPS y pensión, que son las que precargan el
     * formulario de un contrato nuevo.
     */
    private const AMBITOS = [
        'pension' => [
            'etiqueta' => 'Pensión',
            'tabla' => 'pensiones',
            'col_nombre_catalogo' => 'razon_social',
            'col_cod_plano' => 'cod_afp',
            'col_nombre_plano' => 'nombre_afp',
            'col_contrato' => 'pension_id',
            'col_cliente' => 'pension_id',
        ],
        'salud' => [
            'etiqueta' => 'Salud',
            'tabla' => 'eps',
            'col_nombre_catalogo' => 'nombre',
            'col_cod_plano' => 'cod_eps',
            'col_nombre_plano' => 'nombre_eps',
            'col_contrato' => 'eps_id',
            'col_cliente' => 'eps_id',
        ],
        'caja' => [
            'etiqueta' => 'Caja de compensación',
            'tabla' => 'cajas',
            'col_nombre_catalogo' => 'nombre',
            'col_cod_plano' => 'cod_caja',
            'col_nombre_plano' => 'nombre_caja',
            'col_contrato' => 'caja_id',
            'col_cliente' => null,
        ],
        'arl' => [
            'etiqueta' => 'Riesgos laborales',
            'tabla' => 'arls',
            'col_nombre_catalogo' => 'nombre_arl',
            'col_cod_plano' => 'cod_arl',
            'col_nombre_plano' => 'nombre_arl',
            'col_contrato' => 'arl_id',
            'col_cliente' => null,
        ],
    ];

    /**
     * Errores de cotizante marcados como autocorregibles, traducidos a
     * "a quién y qué se le va a cambiar".
     *
     * Los que no se logran interpretar igual se devuelven, con `aplicable` en
     * false: Enlace los corregirá de todos modos y hay que mostrarlos para que
     * alguien los arregle a mano en Brynex.
     *
     * @param  array  $erroresCotizante  `erroresCotizantePlanilla` de la validación
     */
    public function interpretar(array $erroresCotizante, int $aliadoId): array
    {
        $correcciones = [];

        foreach ($erroresCotizante as $error) {
            if (mb_strtolower(trim((string) ($error['autocorreccion'] ?? ''))) !== 'si') {
                continue;
            }

            $correcciones[] = $this->interpretarError($error, $aliadoId);
        }

        return $correcciones;
    }

    /**
     * Refleja en Brynex las correcciones que Enlace ya aplicó a la planilla:
     * el snapshot del `plano` del período, los contratos vigentes del cotizante
     * en ese aliado y su ficha de cliente (para que ni el mes entrante ni el
     * próximo contrato vuelvan a arrastrar el dato malo).
     *
     * @param  array  $correcciones  salida de interpretar()
     * @param  array  $lote  aliado_id, razon_social_id, n_plano, mes, anio, plano_id
     * @return array{aplicadas: array, planos: int, contratos: int, clientes: int}
     */
    public function aplicarEnBrynex(array $correcciones, array $lote): array
    {
        $aplicadas = [];
        $totalPlanos = 0;
        $totalContratos = 0;
        $totalClientes = 0;

        foreach ($correcciones as $correccion) {
            if (! ($correccion['aplicable'] ?? false)) {
                continue;
            }

            $ambito = self::AMBITOS[$correccion['ambito']];
            $destino = $correccion['nueva'];
            $aliadoId = (int) $lote['aliado_id'];

            DB::transaction(function () use ($correccion, $ambito, $destino, $lote, $aliadoId, &$totalPlanos, &$totalContratos, &$totalClientes, &$aplicadas) {
                $planos = $this->actualizarPlanos($correccion['documento'], $ambito, $destino, $lote);
                $contratos = $this->actualizarContratos($correccion['documento'], $ambito, $destino, $aliadoId);
                $clientes = $this->actualizarCliente($correccion['documento'], $ambito, $destino, $aliadoId);

                $totalPlanos += $planos;
                $totalContratos += $contratos;
                $totalClientes += $clientes;

                $aplicadas[] = [
                    'documento' => $correccion['documento'],
                    'nombre' => $correccion['nombre'],
                    'etiqueta' => $ambito['etiqueta'],
                    'de' => $correccion['actual']['nombre'],
                    'a' => $destino['nombre'],
                    'planos' => $planos,
                    'contratos' => $contratos,
                    'clientes' => $clientes,
                ];
            });
        }

        return [
            'aplicadas' => $aplicadas,
            'planos' => $totalPlanos,
            'contratos' => $totalContratos,
            'clientes' => $totalClientes,
        ];
    }

    // ── Interpretación ───────────────────────────────────────────────────

    /**
     * La descripción del error trae el código y el nombre de las dos
     * administradoras — la que va en el archivo y la real —, y `identificacion`
     * trae el documento pegado al tipo ("CC1062304870").
     */
    private function interpretarError(array $error, int $aliadoId): array
    {
        $descripcion = (string) ($error['descripcion'] ?? '');
        $identificacion = trim((string) ($error['identificacion'] ?? ''));

        preg_match('/^([A-Z]{2,3})\s*(\d+)$/i', $identificacion, $doc);
        $tipoDoc = strtoupper($doc[1] ?? '');
        $documento = $doc[2] ?? preg_replace('/\D/', '', $identificacion);

        $base = [
            'linea' => $error['linea'] ?? null,
            'identificacion' => $identificacion,
            'tipo_doc' => $tipoDoc,
            'documento' => $documento,
            'nombre' => $documento ? $this->nombreCotizante($documento, $aliadoId) : null,
            'descripcion' => $descripcion,
            'id_regla' => $error['idRegla'] ?? null,
            'ambito' => null,
            'etiqueta' => null,
            'actual' => null,
            'nueva' => null,
            'aplicable' => false,
            'motivo' => null,
        ];

        $patron = '/administradora de\s+(?P<ambito>[^\d]+?)\s+(?P<cod_actual>[A-Z0-9][A-Z0-9\-]*)\s+'
                 .'(?P<nom_actual>[^,]+?)\s*,\s*pero se encuentra afiliado a la administradora\s+'
                 .'(?P<cod_nueva>[A-Z0-9][A-Z0-9\-]*)\s+(?P<nom_nueva>[^.]+)/iu';

        if (! preg_match($patron, $descripcion, $m)) {
            return array_merge($base, [
                'motivo' => 'Enlace lo corrige, pero Brynex no pudo interpretar qué dato cambia.',
            ]);
        }

        $ambito = $this->ambitoDe($m['ambito']);

        if (! $ambito) {
            return array_merge($base, [
                'actual' => ['codigo' => $m['cod_actual'], 'nombre' => trim($m['nom_actual'])],
                'nueva' => ['codigo' => $m['cod_nueva'], 'nombre' => trim($m['nom_nueva'])],
                'motivo' => 'Subsistema no reconocido ("'.trim($m['ambito']).'"): corríjalo a mano en Brynex.',
            ]);
        }

        $config = self::AMBITOS[$ambito];
        $destino = $this->buscarAdministradora($config, trim($m['cod_nueva']), trim($m['nom_nueva']));

        return array_merge($base, [
            'ambito' => $ambito,
            'etiqueta' => $config['etiqueta'],
            'actual' => ['codigo' => trim($m['cod_actual']), 'nombre' => trim($m['nom_actual'])],
            'nueva' => $destino ?: ['codigo' => trim($m['cod_nueva']), 'nombre' => trim($m['nom_nueva'])],
            'aplicable' => $destino !== null && $documento !== '',
            'motivo' => $destino === null
                ? trim($m['nom_nueva']).' ('.trim($m['cod_nueva']).') no está en el catálogo de '.$config['tabla'].' de Brynex.'
                : null,
        ]);
    }

    /** Palabra del texto de Enlace → subsistema de AMBITOS. */
    private function ambitoDe(string $texto): ?string
    {
        $texto = $this->normalizar($texto);

        return match (true) {
            str_contains($texto, 'PENSION') => 'pension',
            str_contains($texto, 'SALUD') => 'salud',
            str_contains($texto, 'COMPENSACION'), str_contains($texto, 'CAJA') => 'caja',
            str_contains($texto, 'RIESGO') => 'arl',
            default => null,
        };
    }

    /**
     * Ubica la administradora en el catálogo de Brynex: primero por el código
     * PILA, y si no aparece, por nombre — los códigos de pensiones conviven en
     * dos formatos (230301 y 25-05) según de dónde venga el dato.
     */
    private function buscarAdministradora(array $config, string $codigo, string $nombre): ?array
    {
        $columnas = ['id', 'nit', 'codigo', $config['col_nombre_catalogo'].' AS nombre_catalogo'];

        $fila = DB::table($config['tabla'])
            ->whereRaw('UPPER(LTRIM(RTRIM(codigo))) = ?', [mb_strtoupper($codigo)])
            ->first($columnas);

        if (! $fila) {
            $buscado = $this->normalizar($nombre);

            $fila = DB::table($config['tabla'])
                ->get($columnas)
                ->first(fn ($f) => $this->normalizar((string) $f->nombre_catalogo) === $buscado);
        }

        if (! $fila) {
            return null;
        }

        return [
            'id' => (int) $fila->id,
            'nit' => (string) $fila->nit,
            'codigo' => (string) $fila->codigo,
            'nombre' => (string) ($fila->nombre_catalogo ?: $nombre),
        ];
    }

    /** Nombre del cotizante, para que el usuario vea a quién se le corrige. */
    private function nombreCotizante(string $documento, int $aliadoId): ?string
    {
        $fila = DB::table('planos')
            ->where('aliado_id', $aliadoId)
            ->where('no_identifi', $documento)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first(['primer_nombre', 'segundo_nombre', 'primer_ape', 'segundo_ape']);

        if (! $fila) {
            return null;
        }

        $nombre = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
            $fila->primer_nombre, $fila->segundo_nombre, $fila->primer_ape, $fila->segundo_ape,
        ]))));

        return $nombre !== '' ? $nombre : null;
    }

    /** Sin tildes, en mayúsculas y sin espacios de sobra. */
    private function normalizar(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto), 'UTF-8');
        $texto = strtr($texto, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U']);

        return preg_replace('/\s+/', ' ', $texto);
    }

    // ── Escritura ────────────────────────────────────────────────────────

    /**
     * Snapshot del plano del período. Solo se tocan los que siguen sin número
     * de planilla: si ya se liquidó, el snapshot debe reflejar lo que se pagó.
     *
     * El período se resuelve igual que en PlanoPilaTxtService: los
     * los de `paga_mes_actual` guardan el mes de pago y los demás el mes
     * vencido.
     */
    private function actualizarPlanos(string $documento, array $ambito, array $destino, array $lote): int
    {
        $query = DB::table('planos')
            ->where('aliado_id', $lote['aliado_id'])
            ->where('no_identifi', $documento)
            ->whereNull('deleted_at')
            ->whereNull('numero_planilla');

        if (! empty($lote['plano_id'])) {
            $query->where('id', $lote['plano_id']);
        } else {
            $mesPago = (int) $lote['mes'];
            $anioPago = (int) $lote['anio'];
            $mesVencido = $mesPago === 1 ? 12 : $mesPago - 1;
            $anioVencido = $mesPago === 1 ? $anioPago - 1 : $anioPago;

            $query->where('razon_social_id', $lote['razon_social_id'])
                ->where('n_plano', $lote['n_plano'])
                ->tap(fn ($q) => Plano::filtrarPeriodoDePago($q, $mesPago, $anioPago, null));
        }

        return $query->update([
            $ambito['col_cod_plano'] => $destino['nit'],
            $ambito['col_nombre_plano'] => $destino['nombre'],
            'updated_at' => now(),
        ]);
    }

    /**
     * Contratos vigentes del cotizante en el aliado. Se corrigen todos —no
     * solo el de la razón social que se está liquidando— porque la afiliación
     * es de la persona: si tiene otro contrato vigente, ese plano fallaría
     * igual el mes siguiente. Los retirados se dejan como están.
     */
    private function actualizarContratos(string $documento, array $ambito, array $destino, int $aliadoId): int
    {
        return DB::table('contratos')
            ->where('aliado_id', $aliadoId)
            ->where('cedula', $documento)
            ->where('estado', 'vigente')
            ->where(function ($q) use ($ambito, $destino) {
                $q->where($ambito['col_contrato'], '<>', $destino['id'])
                    ->orWhereNull($ambito['col_contrato']);
            })
            ->update([
                $ambito['col_contrato'] => $destino['id'],
                'updated_at' => now(),
            ]);
    }

    /**
     * Ficha del cliente. Solo guarda EPS y pensión, y son las que precargan el
     * formulario de un contrato nuevo (ver `clientePensionId` en
     * ContratoController::lookups) — sin esto el siguiente contrato de la
     * persona vuelve a nacer con la administradora equivocada.
     *
     * `pension_id = Pension::ID_PENSIONADO` no es una AFP sino la marca de que
     * la persona es pensionada, así que ese caso se deja intacto.
     */
    private function actualizarCliente(string $documento, array $ambito, array $destino, int $aliadoId): int
    {
        if (empty($ambito['col_cliente'])) {
            return 0;
        }

        $query = DB::table('clientes')
            ->where('aliado_id', $aliadoId)
            ->where('cedula', $documento)
            ->where(function ($q) use ($ambito, $destino) {
                $q->where($ambito['col_cliente'], '<>', $destino['id'])
                    ->orWhereNull($ambito['col_cliente']);
            });

        if ($ambito['col_cliente'] === 'pension_id') {
            $query->where(function ($q) {
                $q->where('pension_id', '<>', \App\Models\Pension::ID_PENSIONADO)
                    ->orWhereNull('pension_id');
            });
        }

        return $query->update([
            $ambito['col_cliente'] => $destino['id'],
            'updated_at' => now(),
        ]);
    }
}
