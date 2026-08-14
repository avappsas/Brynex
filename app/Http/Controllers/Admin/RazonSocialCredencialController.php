<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\RazonSocialCredencial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Claves de portales críticos de una razón social: DIAN, bancos, cámara de
 * comercio.
 *
 * Dos reglas que no se pueden relajar:
 *
 * 1. La contraseña NO viaja en el listado. Sale una a una por `revelar`, y
 *    cada revelación queda en la bitácora. Mandarla en el JSON y taparla con
 *    un <span> no tapa nada: basta abrir el inspector (misma lección de
 *    ClaveAccesoController::taparContrasenas).
 * 2. Todo query arranca filtrando por `aliado_id` de sesión Y por la razón
 *    social de la URL. Sin scope global de tenant, un id ajeno es un IDOR.
 */
class RazonSocialCredencialController extends Controller
{
    /** La razón social debe existir y ser del aliado activo. 404 si no. */
    private function razonSocial(int $razonSocialId)
    {
        $aliadoId = session('aliado_id_activo');

        $rs = DB::table('razones_sociales')
            ->where('id', $razonSocialId)
            ->where('aliado_id', $aliadoId)
            ->first(['id', 'nit', 'dv', 'razon_social']);

        abort_if(! $rs, 404);

        return $rs;
    }

    /** Credencial de esa razón social y de ese aliado. 404 si no. */
    private function credencial(int $razonSocialId, int $credencialId): RazonSocialCredencial
    {
        $cred = RazonSocialCredencial::deAliado((int) session('aliado_id_activo'))
            ->deRazonSocial($razonSocialId)
            ->find($credencialId);

        abort_if(! $cred, 404);

        return $cred;
    }

    /** Fila para el listado: sin la contraseña, solo si hay o no. */
    private function fila(RazonSocialCredencial $c): array
    {
        return [
            'id' => $c->id,
            'tipo' => $c->tipo,
            'tipo_etiqueta' => $c->tipoEtiqueta(),
            'entidad' => $c->entidad,
            'link_acceso' => $c->link_acceso,
            'usuario' => $c->usuario,
            'tiene_contrasena' => filled($c->contrasena),
            'observacion' => $c->observacion,
            'actualizado' => optional($c->updated_at)->format('d/m/Y h:i A'),
        ];
    }

    // ─── Listado (JSON) ───────────────────────────────────────────────
    public function index(int $razonSocialId)
    {
        $rs = $this->razonSocial($razonSocialId);

        $claves = RazonSocialCredencial::deAliado((int) session('aliado_id_activo'))
            ->deRazonSocial($razonSocialId)
            ->orderBy('tipo')
            ->orderBy('entidad')
            ->get();

        return response()->json([
            'razon_social' => [
                'id' => $rs->id,
                'nit' => $rs->nit,
                'dv' => $rs->dv,
                'nombre' => $rs->razon_social,
            ],
            'puede_gestionar' => auth()->user()->can('credenciales_rs.gestionar'),
            'claves' => $claves->map(fn ($c) => $this->fila($c))->values(),
        ]);
    }

    // ─── Revelar una contraseña ───────────────────────────────────────
    // Se registra en bitácora: quién miró qué clave y cuándo. Es el único
    // rastro que queda de un dato que después vive fuera del sistema.
    public function revelar(int $razonSocialId, int $credencialId)
    {
        $this->razonSocial($razonSocialId);
        $cred = $this->credencial($razonSocialId, $credencialId);

        Bitacora::registrar(
            'consulta',
            'RazonSocialCredencial',
            $cred->id,
            "Reveló la contraseña de {$cred->entidad} ({$cred->tipoEtiqueta()})",
            ['razon_social_id' => $razonSocialId, 'entidad' => $cred->entidad]
        );

        return response()->json(['contrasena' => $cred->contrasena ?? '']);
    }

    // ─── Crear ────────────────────────────────────────────────────────
    public function store(Request $request, int $razonSocialId)
    {
        $rs = $this->razonSocial($razonSocialId);
        $data = $this->validar($request);

        $cred = RazonSocialCredencial::create($data + [
            'aliado_id' => session('aliado_id_activo'),
            'razon_social_id' => $rs->id,
            'creado_por' => auth()->id(),
            'actualizado_por' => auth()->id(),
        ]);

        Bitacora::registrar(
            'created',
            'RazonSocialCredencial',
            $cred->id,
            "Guardó la clave de {$cred->entidad} para {$rs->razon_social}",
            ['razon_social_id' => $rs->id, 'tipo' => $cred->tipo]
        );

        return response()->json(['ok' => true, 'clave' => $this->fila($cred)]);
    }

    // ─── Actualizar ───────────────────────────────────────────────────
    // La contraseña en blanco conserva la actual: así se puede corregir el
    // usuario o el link sin tener que volver a escribir el secreto.
    public function update(Request $request, int $razonSocialId, int $credencialId)
    {
        $this->razonSocial($razonSocialId);
        $cred = $this->credencial($razonSocialId, $credencialId);
        $data = $this->validar($request);

        if (! filled($data['contrasena'] ?? null)) {
            unset($data['contrasena']);
        }

        $cred->update($data + ['actualizado_por' => auth()->id()]);

        Bitacora::registrar(
            'updated',
            'RazonSocialCredencial',
            $cred->id,
            "Actualizó la clave de {$cred->entidad}",
            ['razon_social_id' => $razonSocialId, 'cambio_contrasena' => isset($data['contrasena'])]
        );

        return response()->json(['ok' => true, 'clave' => $this->fila($cred->fresh())]);
    }

    // ─── Eliminar ─────────────────────────────────────────────────────
    public function destroy(int $razonSocialId, int $credencialId)
    {
        $this->razonSocial($razonSocialId);
        $cred = $this->credencial($razonSocialId, $credencialId);

        $entidad = $cred->entidad;
        $cred->delete();

        Bitacora::registrar(
            'deleted',
            'RazonSocialCredencial',
            $credencialId,
            "Eliminó la clave de {$entidad}",
            ['razon_social_id' => $razonSocialId]
        );

        return response()->json(['ok' => true]);
    }

    // ─── Validación ───────────────────────────────────────────────────
    private function validar(Request $request): array
    {
        $data = $request->validate([
            'tipo' => 'required|string|in:'.implode(',', array_keys(RazonSocialCredencial::TIPOS)),
            'entidad' => 'required|string|max:150',
            'link_acceso' => 'nullable|string|max:350',
            'usuario' => 'nullable|string|max:150',
            'contrasena' => 'nullable|string|max:200',
            'observacion' => 'nullable|string|max:500',
        ], [
            'tipo.required' => 'El tipo de portal es obligatorio.',
            'tipo.in' => 'El tipo de portal no es válido.',
            'entidad.required' => 'El nombre de la entidad es obligatorio.',
        ]);

        // Un link sin esquema no abre: el navegador lo lee como ruta relativa.
        if (filled($data['link_acceso'] ?? null) && ! preg_match('#^https?://#i', $data['link_acceso'])) {
            $data['link_acceso'] = 'https://'.ltrim($data['link_acceso'], '/');
        }

        return $data;
    }
}
