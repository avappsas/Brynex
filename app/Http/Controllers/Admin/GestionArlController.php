<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aliado;
use App\Models\Contrato;
use App\Models\Factura;
use App\Models\User;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GestionArlController extends Controller
{
    const TIPO_MODALIDAD_ARL = 15;
    const DIAS_VIGENCIA      = 28; // máximo días ARL activa

    // Semáforo: días restantes
    const VERDE    = 10; // >= 10 días restantes → verde
    const AMARILLO = 4;  // 4-9 días restantes → amarillo
    const ROJO     = 3;  // 0-3 días o vencido → rojo

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Vista principal del módulo Gestión ARL.
     * Muestra todos los contratos vigentes con tipo_modalidad_id = 15, en tiempo real.
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user    = Auth::user();
        $alidoId = $this->resolverAliado($request, $user);

        // ── Filtros ────────────────────────────────────────────────────
        $encId   = $request->has('encargado_id') ? $request->get('encargado_id') : $user->id;
        $rsId    = $request->get('razon_social_id');
        $arlF    = $request->get('arl_id');
        $sort    = $request->get('sort', 'semaforo'); // default: más urgentes primero
        $dir     = $request->get('dir', 'asc');

        $sortAllowed = ['semaforo', 'fecha_arl', 'cedula', 'razon_social_id'];
        if (!in_array($sort, $sortAllowed)) $sort = 'semaforo';
        if (!in_array($dir, ['asc', 'desc'])) $dir = 'asc';

        // ── Query base ────────────────────────────────────────────────
        $query = Contrato::with([
            'cliente:id,cedula,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,cod_empresa',
            'cliente.empresa:id,empresa',
            'razonSocial:id,razon_social,arl_nit',
            'arl:id,nombre_arl,razon_social',
            'tipoModalidad:id,tipo_modalidad,modalidad',
            'encargado:id,nombre',
        ])
        ->where('aliado_id', $alidoId)
        ->where('estado', 'vigente')
        ->where('tipo_modalidad_id', self::TIPO_MODALIDAD_ARL);

        if ($encId)  $query->where('encargado_id', $encId);
        if ($rsId)   $query->where('razon_social_id', $rsId);
        if ($arlF)   $query->where('arl_id', $arlF);

        // Ordenar por columnas simples
        if ($sort === 'fecha_arl') {
            $query->orderByRaw('fecha_arl IS NULL ASC')->orderBy('fecha_arl', $dir);
        } elseif ($sort === 'cedula') {
            $query->orderBy('cedula', $dir);
        } elseif ($sort === 'razon_social_id') {
            $query->orderBy('razon_social_id', $dir);
        }
        // semaforo se ordena en PHP después

        $contratos = $query->get();

        // ── ARL efectiva por NIT de razón social ──────────────────────
        $arlsNit = $contratos->pluck('razonSocial.arl_nit')->filter()->unique();
        $arlsPorNit = $arlsNit->isNotEmpty()
            ? DB::table('arls')->whereIn('nit', $arlsNit)->get(['nit', 'nombre_arl', 'razon_social'])->keyBy('nit')
            : collect();

        // ── Calcular semáforo + última factura de cada contrato ────────
        $contratoIds = $contratos->pluck('id');

        // Última factura de afiliación por contrato
        $ultimasFacturas = Factura::whereIn('contrato_id', $contratoIds)
            ->where('tipo', 'afiliacion')
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get(['id', 'contrato_id', 'numero_factura', 'mes', 'anio', 'fecha_pago'])
            ->groupBy('contrato_id')
            ->map(fn($g) => $g->first());

        $hoy = now()->startOfDay();

        $contratos->each(function ($c) use ($arlsPorNit, $ultimasFacturas, $hoy) {
            // ARL efectiva
            if ($c->razonSocial?->arl_nit) {
                $arlRs = $arlsPorNit->get($c->razonSocial->arl_nit);
                $c->arl_efectiva_nombre = $arlRs?->nombre_arl ?? $arlRs?->razon_social ?? '[ARL Empresa]';
            } else {
                $c->arl_efectiva_nombre = $c->arl?->nombre_arl ?? $c->arl?->razon_social ?? '—';
            }

            // Semáforo: basado en fecha_arl
            if ($c->fecha_arl) {
                $diasTranscurridos = (int) $c->fecha_arl->diffInDays($hoy, false);
                $diasRestantes = self::DIAS_VIGENCIA - $diasTranscurridos;
                $c->dias_restantes = $diasRestantes;

                if ($diasRestantes >= self::VERDE) {
                    $c->semaforo = 'verde';
                    $c->semaforo_orden = 3;
                } elseif ($diasRestantes >= self::AMARILLO) {
                    $c->semaforo = 'amarillo';
                    $c->semaforo_orden = 2;
                } else {
                    $c->semaforo = 'rojo';
                    $c->semaforo_orden = 1;
                }
            } else {
                // Sin fecha_arl = pendiente de primera afiliación
                $c->dias_restantes = null;
                $c->semaforo = 'sin_fecha';
                $c->semaforo_orden = 4; // al final
            }

            // Primera afiliación: si fecha_ingreso es este mes → mostrar badge
            $c->es_primer_mes = $c->fecha_ingreso
                && (int)$c->fecha_ingreso->month === (int)$hoy->month
                && (int)$c->fecha_ingreso->year  === (int)$hoy->year;

            // Última factura
            $c->ultima_factura = $ultimasFacturas->get($c->id);
        });

        // Ordenar por semáforo en PHP (urgentes primero)
        if ($sort === 'semaforo') {
            $contratos = $dir === 'asc'
                ? $contratos->sortBy('semaforo_orden')
                : $contratos->sortByDesc('semaforo_orden');
        }

        // ── Datos para filtros dinámicos ──────────────────────────────
        $encargados = User::where('aliado_id', $alidoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $razonesDisponibles = DB::table('razones_sociales')
            ->whereIn('id', Contrato::where('aliado_id', $alidoId)
                ->where('estado', 'vigente')
                ->where('tipo_modalidad_id', self::TIPO_MODALIDAD_ARL)
                ->pluck('razon_social_id')->filter()->unique())
            ->orderBy('razon_social')
            ->get(['id', 'razon_social']);

        $arlDisponibles = DB::table('arls')
            ->whereIn('id', Contrato::where('aliado_id', $alidoId)
                ->where('estado', 'vigente')
                ->where('tipo_modalidad_id', self::TIPO_MODALIDAD_ARL)
                ->pluck('arl_id')->filter()->unique())
            ->orderBy('nombre_arl')
            ->get(['id', 'nombre_arl']);

        $alidosDisponibles = [];
        if ($user->es_brynex) {
            $alidosDisponibles = $this->alidosParaBrynex($user);
        }

        return view('admin.gestion-arl.index', compact(
            'contratos', 'encId', 'encargados',
            'alidoId', 'alidosDisponibles', 'user',
            'rsId', 'arlF', 'sort', 'dir',
            'razonesDisponibles', 'arlDisponibles'
        ));
    }

    /**
     * Actualizar fecha_arl de un contrato (renovación en portal ARL).
     * AJAX: PATCH /admin/gestion-arl/{id}/renovar
     */
    public function renovar(Request $request, int $id)
    {
        $alidoId  = $this->resolverAliado($request, Auth::user());
        $contrato = Contrato::where('aliado_id', $alidoId)
            ->where('tipo_modalidad_id', self::TIPO_MODALIDAD_ARL)
            ->findOrFail($id);

        $validated = $request->validate([
            'fecha_arl' => 'required|date',
        ]);

        $fechaAnterior = $contrato->fecha_arl?->format('d/m/Y') ?? 'Sin fecha';
        $fechaNueva    = \Carbon\Carbon::parse($validated['fecha_arl'])->format('d/m/Y');

        $contrato->update(['fecha_arl' => $validated['fecha_arl']]);

        // Registrar en bitácora
        Bitacora::registrar(
            'updated',
            'Contrato',
            $contrato->id,
            "Gestión ARL: fecha_arl actualizada de {$fechaAnterior} a {$fechaNueva}",
            ['fecha_anterior' => $fechaAnterior, 'fecha_nueva' => $fechaNueva],
            $alidoId
        );

        return response()->json([
            'ok'           => true,
            'mensaje'      => "Fecha ARL actualizada a {$fechaNueva}",
            'fecha_arl'    => $contrato->fecha_arl->format('Y-m-d'),
            'fecha_display'=> $fechaNueva,
        ]);
    }

    // ── Helpers privados ──────────────────────────────────────────────

    private function resolverAliado(Request $request, User $user): int
    {
        $alidoSesion = (int) session('aliado_id_activo', $user->aliado_id);
        if ($user->es_brynex) {
            $alidoParam = (int) $request->get('aliado_id', $alidoSesion);
            if ($alidoParam && $user->puedeAccederAliado($alidoParam)) {
                return $alidoParam;
            }
            return $alidoSesion ?: $user->aliado_id;
        }
        return $user->aliado_id;
    }

    private function alidosParaBrynex(User $user): \Illuminate\Support\Collection
    {
        $ids = collect([$user->aliado_id]);
        $user->aliados()->wherePivot('activo', true)->get(['aliados.id'])->each(
            fn($a) => $ids->push($a->id)
        );
        return Aliado::whereIn('id', $ids->unique()->filter())
            ->orderBy('nombre')->get(['id', 'nombre']);
    }
}
