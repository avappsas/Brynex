<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\RegistroOficialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consulta de una persona por documento, para sistemas externos.
 *
 * Hoy la consume Cuenta_facil, que necesita precargar la ficha de un
 * contratista sin que nadie teclee el nombre. Responde con lo mismo que ve el
 * modal "Nuevo Cliente" del panel, pero normalizado y recortado: el payload
 * crudo del operador no sale de Brynex.
 *
 * El aliado sale del usuario dueño del token, no de la petición. Así un token
 * no puede leer los clientes de otro aliado ni pidiéndolo explícitamente.
 *
 * Las credenciales del operador (usuario, contraseña, clave secreta) tampoco
 * salen: quien llama nunca las ve ni las necesita.
 */
class ConsultaPersonaController extends Controller
{
    public function __construct(private RegistroOficialService $registro)
    {
    }

    public function mostrar(Request $request, string $tipoDoc, string $cedula): JsonResponse
    {
        $cedula = preg_replace('/\D/', '', $cedula);

        if (strlen($cedula) < 4) {
            return response()->json(['mensaje' => 'Documento inválido.'], 422);
        }

        // El registro responde por tipo + número y son espacios independientes:
        // el mismo número existe como CC de una persona y como CE de otra. Con
        // el tipo equivocado responde vacío, sin error — por eso se valida
        // contra el catálogo en vez de pasar lo que llegue.
        $tipoDoc = strtoupper($tipoDoc);
        if (! array_key_exists($tipoDoc, Cliente::TIPOS_DOC)) {
            return response()->json(['mensaje' => 'Tipo de documento no reconocido.'], 422);
        }

        $aliadoId = (int) $request->user()->aliado_id;

        $oficial = $this->registro->consultar($aliadoId, $cedula, $tipoDoc);

        // El celular no está en RUAF: si la persona ya es cliente de este
        // aliado se toma de ahí, y si no, se devuelve vacío y lo teclean.
        $cliente = Cliente::where('cedula', $cedula)
            ->where('aliado_id', $aliadoId)
            ->first();

        if ($oficial === null && $cliente === null) {
            return response()->json([
                'documento'  => $cedula,
                'tipo_doc'   => $tipoDoc,
                'encontrado' => false,
                'motivo'     => 'sin_datos',
            ]);
        }

        // Sin registro oficial pero con ficha propia: se responde con lo que
        // hay. Es peor devolver "no encontrado" teniendo el nombre guardado.
        if ($oficial === null) {
            return response()->json([
                'documento'       => $cedula,
                'tipo_doc'        => $cliente->tipo_doc ?: $tipoDoc,
                'encontrado'      => true,
                'fuente'          => 'brynex',
                'primer_nombre'   => $cliente->primer_nombre,
                'segundo_nombre'  => $cliente->segundo_nombre,
                'primer_apellido' => $cliente->primer_apellido,
                'segundo_apellido' => $cliente->segundo_apellido,
                'celular'         => $cliente->celular,
                'eps_codigo'      => optional($cliente->eps)->codigo,
                'pension_codigo'  => optional($cliente->pension)->codigo,
            ]);
        }

        return response()->json([
            'documento'        => $cedula,
            'tipo_doc'         => $tipoDoc,
            'encontrado'       => (bool) $oficial['encontrado'],
            'fuente'           => 'ruaf',
            'primer_nombre'    => $oficial['primer_nombre'] ?: null,
            'segundo_nombre'   => $oficial['segundo_nombre'] ?: null,
            'primer_apellido'  => $oficial['primer_apellido'] ?: null,
            'segundo_apellido' => $oficial['segundo_apellido'] ?: null,
            'celular'          => $cliente?->celular,
            'eps_codigo'       => $oficial['eps_codigo'],
            'eps_nombre'       => $oficial['eps_nombre'],
            'pension_codigo'   => $oficial['pension_codigo'],
            'pension_nombre'   => $oficial['pension_nombre'],
            'estado'           => $oficial['estado'],
            'regimen'          => $oficial['regimen'],
        ]);
    }
}
