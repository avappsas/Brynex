<?php

namespace App\Http\Controllers;

use App\Services\Dataico\PortalDianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Consulta DIAN por documento, para BryNex.
 *
 * Sirve para llenar la ficha de un cliente con el nombre tal como lo tiene
 * registrado la DIAN —que es el que después viaja en la factura electrónica—
 * y de paso ver el correo del RUT.
 *
 * Es un módulo de la casa, no del aliado: la consulta sale de la cuenta de
 * Dataico de BryNex y cruza los clientes de TODOS los aliados para decir dónde
 * está esa cédula. Por eso `brynex_dian` es `solo_brynex` y por eso aquí NO se
 * filtra por `session('aliado_id_activo')`: es a propósito, igual que en
 * razones sociales. Lo que sí se respeta es que solo se listan aliados
 * activos y solo se muestra a quien tiene el permiso.
 *
 * No escribe nada. Muestra lo que dice la DIAN, lo que tiene BryNex y en qué
 * se diferencian; corregir la ficha se hace en la ficha, con sus permisos.
 */
class BrynexDianController extends Controller
{
    public function __construct(private readonly PortalDianService $portal) {}

    public function index()
    {
        return view('brynex.consulta_dian', [
            'estado' => $this->portal->estado(),
            'tipos' => PortalDianService::TIPOS_VISIBLES,
            'puedeConfigurar' => auth()->user()?->can('brynex_dian.configurar') ?? false,
        ]);
    }

    public function consultar(Request $request)
    {
        $datos = $request->validate([
            'tipo_doc' => 'required|string|max:10',
            'numero' => 'required|string|max:20',
        ]);

        try {
            $dian = $this->portal->consultar($datos['tipo_doc'], $datos['numero']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'dian' => $dian,
            'brynex' => $this->enBrynex($dian['identificacion']),
        ]);
    }

    public function guardarCredenciales(Request $request)
    {
        $datos = $request->validate([
            'correo' => 'required|email|max:190',
            'clave' => 'nullable|string|max:190',
        ]);

        $this->portal->guardarCredenciales($datos['correo'], $datos['clave'] ?? null);

        return back()->with('exito', 'Credenciales del portal guardadas.');
    }

    /**
     * Dónde vive ya ese documento dentro de BryNex.
     *
     * Una misma cédula puede ser cliente en varios aliados —son bases lógicas
     * distintas sobre la misma tabla—, así que se devuelven todas las
     * apariciones y no la primera. Se busca también en `empresas`, donde el
     * campo `nit` guarda cédulas de empleadores persona natural (ver
     * `Adquiriente`).
     */
    private function enBrynex(string $documento): array
    {
        $clientes = DB::table('clientes')
            ->join('aliados', 'aliados.id', '=', 'clientes.aliado_id')
            ->whereNull('aliados.deleted_at')
            ->where('clientes.cedula', $documento)
            ->select(
                'clientes.id', 'clientes.aliado_id', 'aliados.nombre as aliado',
                'clientes.primer_nombre', 'clientes.segundo_nombre',
                'clientes.primer_apellido', 'clientes.segundo_apellido',
                'clientes.correo', 'clientes.celular'
            )
            ->orderBy('clientes.aliado_id')
            ->get()
            ->map(fn ($c) => [
                'tipo' => 'cliente',
                'id' => $c->id,
                'aliado_id' => (int) $c->aliado_id,
                'aliado' => $c->aliado,
                'nombre' => preg_replace('/\s+/', ' ', trim(
                    "$c->primer_nombre $c->segundo_nombre $c->primer_apellido $c->segundo_apellido"
                )),
                'correo' => trim((string) $c->correo),
                'celular' => trim((string) $c->celular),
                'url' => route('admin.clientes.edit', $c->id).'?aliado='.$c->aliado_id,
            ]);

        $empresas = DB::table('empresas')
            ->join('aliados', 'aliados.id', '=', 'empresas.aliado_id')
            ->whereNull('aliados.deleted_at')
            ->where('empresas.nit', $documento)
            ->select(
                'empresas.id', 'empresas.aliado_id', 'aliados.nombre as aliado',
                'empresas.empresa', 'empresas.nombre_legal', 'empresas.correo'
            )
            ->orderBy('empresas.aliado_id')
            ->get()
            ->map(fn ($e) => [
                'tipo' => 'empresa',
                'id' => $e->id,
                'aliado_id' => (int) $e->aliado_id,
                'aliado' => $e->aliado,
                'nombre' => trim(($e->nombre_legal ?: $e->empresa) ?? ''),
                'correo' => trim((string) $e->correo),
                'celular' => '',
                'url' => null,
            ]);

        return $clientes->concat($empresas)->values()->all();
    }
}
