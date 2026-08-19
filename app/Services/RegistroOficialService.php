<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Consulta BDUA/RUAF a través del operador de planilla (ARUS / Simple).
 *
 * Vivía dentro de Admin\ClienteController, que era su único consumidor. Salió
 * de ahí cuando apareció el segundo: la API que consume Cuenta_facil para
 * precargar la ficha del contratista. El código es el mismo, movido sin
 * cambios de comportamiento; lo único que cambió es que el punto de entrada
 * dejó de ser privado.
 *
 * Nunca lanza: si el aliado no tiene credenciales de operador, o el operador
 * no responde, devuelve null y lo deja en el log. Las dos pantallas que lo
 * usan tienen que seguir funcionando con el registro oficial caído.
 */
class RegistroOficialService
{
    /**
     * Busca un operador (ARUS/Simple) con credenciales para consultar BDUA/RUAF.
     *
     * Consultar la afiliación de una cédula es una operación de solo lectura
     * sin autorización por aportante: cualquier cuenta de operador sirve para
     * cualquier persona. Por eso, si el aliado activo no tiene credenciales
     * propias, se cae a las del aliado BryNex — así la verificación funciona
     * en todos los aliados aunque no hayan configurado su propia cuenta.
     *
     * Esto NO aplica a liquidar planillas (PlanillaApiController), que sí
     * exige la autorización real del aliado sobre ese aportante específico.
     *
     * @return array{0: ?\App\Models\OperadorPlanilla, 1: ?\App\Models\OperadorCredencial}
     */
    private function credencialParaRuaf(int $aliadoId): array
    {
        $buscar = function (int $idParaOperadores, int $idParaCredencial) {
            $operador = \App\Models\OperadorPlanilla::paraAliado($idParaOperadores)
                ->whereIn('codigo', array_keys(\App\Services\SuaporteApiService::HOSTS))
                ->get()
                ->first(fn ($op) => \App\Models\OperadorCredencial::paraOperador($idParaCredencial, $op->id)->exists());

            if (! $operador) {
                return [null, null];
            }

            return [$operador, \App\Models\OperadorCredencial::paraOperador($idParaCredencial, $operador->id)->first()];
        };

        [$operador, $credencial] = $buscar($aliadoId, $aliadoId);
        if ($operador && $credencial) {
            return [$operador, $credencial];
        }

        $fallbackId = (int) config('services.suaporte.aliado_fallback_ruaf');
        if ($fallbackId && $fallbackId !== $aliadoId) {
            // Los operadores se buscan con el aliado real (respeta si él los
            // desactivó); solo la credencial cae al aliado de respaldo.
            return $buscar($aliadoId, $fallbackId);
        }

        return [null, null];
    }

    /**
     * Consulta BDUA/RUAF a través de la API del operador de planilla para
     * traer los datos oficiales de la persona: nombres, EPS, fondo de
     * pensión, régimen y estado.
     *
     * Devuelve null si el aliado no tiene credenciales de operador cargadas,
     * de modo que el formulario siga funcionando igual que siempre.
     *
     * El registro responde por tipo + número, y son espacios independientes:
     * el mismo número existe como CC de una persona y como CE de otra. Con el
     * tipo equivocado la respuesta llega vacía, sin error, e indistinguible de
     * "esta persona no está registrada" — por eso el tipo lo escoge el asesor
     * y no se asume.
     */
    public function consultar(int $aliadoId, string $cedula, string $tipoDoc = 'CC'): ?array
    {
        $cedula = preg_replace('/\D/', '', $cedula);

        if (strlen($cedula) < 4) {
            return null;
        }

        // El asesor puede entrar y salir del campo varias veces: se cachea
        // para no repetir el login contra el operador en cada blur.
        //
        // La caché es una optimización, NO un requisito: se lee y se escribe
        // dentro de try/catch para que un fallo del driver no tumbe la
        // consulta. Con CACHE_DRIVER=file basta un directorio de
        // storage/framework/cache que Apache no pueda escribir (lo deja
        // cualquier artisan corrido como root) para que Cache::remember
        // lance ErrorException, y el modal muestre "No se pudo consultar"
        // aunque el operador haya respondido bien.
        //
        // Por eso NO se usa Cache::remember: si la escritura falla después de
        // consultar, se devuelve igual el resultado en vez de perderlo.
        //
        // El tipo entra en la llave: CC 104915 y CE 104915 son dos personas
        // distintas y no pueden compartir entrada de caché.
        $llave = "ruaf_{$aliadoId}_{$tipoDoc}_{$cedula}";

        try {
            $cacheado = Cache::get($llave);
        } catch (\Throwable $e) {
            Log::warning('RUAF: no se pudo leer la caché', ['llave' => $llave, 'message' => $e->getMessage()]);
            $cacheado = null;
        }

        if ($cacheado !== null) {
            return $cacheado;
        }

        $resultado = $this->consultarEnOperador($aliadoId, $cedula, $tipoDoc);

        // Un null (sin credenciales, o el operador falló) no se cachea a
        // propósito: el siguiente intento vuelve a preguntar.
        if ($resultado !== null) {
            try {
                Cache::put($llave, $resultado, 600);
            } catch (\Throwable $e) {
                Log::warning('RUAF: no se pudo guardar en caché', ['llave' => $llave, 'message' => $e->getMessage()]);
            }
        }

        return $resultado;
    }

    /**
     * La consulta real al operador, sin caché. Separada de
     * consultar() solo para que el manejo de la caché quede
     * legible; no llamar directamente.
     */
    private function consultarEnOperador(int $aliadoId, string $cedula, string $tipoDoc = 'CC'): ?array
    {
        [$operador, $credencial] = $this->credencialParaRuaf($aliadoId);

        if (! $operador || ! $credencial) {
            return null;
        }

        $api = new \App\Services\SuaporteApiService([
            'operador' => $operador->codigo,
            'usuario' => $credencial->usuario,
            'contrasena' => $credencial->contrasena,
            'clave_secreta' => $credencial->clave_secreta,
        ]);

        $resultado = $api->consultarAfiliacion($tipoDoc, $cedula);

        if (! $resultado['success']) {
            Log::warning('RUAF: el operador no respondió la consulta', [
                'aliado_id' => $aliadoId,
                'operador' => $operador->codigo,
                'tipo_doc' => $tipoDoc,
                'message' => $resultado['message'] ?? null,
            ]);

            return null;
        }

        $d = $resultado['afiliacion'];

        // Los códigos que devuelve el registro son los mismos que usan
        // las tablas de referencia de Brynex.
        $epsId = ! empty($d['administradoraBDUA'])
            ? DB::table('eps')->where('codigo', $d['administradoraBDUA'])->value('id')
            : null;

        $pensionId = ! empty($d['administradoraRUAF'])
            ? DB::table('pensiones')->where('codigo', $d['administradoraRUAF'])->value('id')
            : null;

        return [
            'encontrado' => $resultado['registrado'],
            'operador' => $operador->nombre,
            'tipo_doc' => $tipoDoc,
            'primer_nombre' => $d['primerNombre'] ?? '',
            'segundo_nombre' => $d['segundoNombre'] ?? '',
            'primer_apellido' => $d['primerApellido'] ?? '',
            'segundo_apellido' => $d['segundoApellido'] ?? '',
            'eps_id' => $epsId,
            'eps_nombre' => $epsId ? DB::table('eps')->where('id', $epsId)->value('nombre') : null,
            'eps_codigo' => $d['administradoraBDUA'] ?? null,
            'pension_id' => $pensionId,
            'pension_nombre' => $pensionId ? DB::table('pensiones')->where('id', $pensionId)->value('razon_social') : null,
            'pension_codigo' => $d['administradoraRUAF'] ?? null,
            'estado' => $d['estado'] ?? null,
            'regimen' => $d['regimen'] ?? null,
            // Figurar en RUAF (aunque hoy no tenga fondo activo) es lo
            // que impide declarar el subtipo 03 "no obligado por edad".
            'en_ruaf' => ! empty($d['fechaAfiliacionRUAF']),
            'ruaf_desde' => $d['fechaAfiliacionRUAF'] ?? null,
            // Payload crudo del operador, sin filtrar: incluye campos
            // que hoy no se usan (valorUPC, coincidencia, fechas sin
            // formatear) para que el modal pueda mostrarlos todos.
            'raw' => $d,
        ];
    }
}
