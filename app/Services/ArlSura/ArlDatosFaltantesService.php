<?php

namespace App\Services\ArlSura;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\OperadorCredencial;
use App\Models\OperadorPlanilla;
use App\Services\SuaporteApiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Completa los datos que le faltan a un contrato consultando fuentes oficiales,
 * en vez de pedírselos a quien va a afiliar.
 *
 * Dos huecos concretos, con dos fuentes distintas:
 *
 *  - **AFP y EPS**: el RUAF/BDUA del operador de planilla, que es la misma
 *    consulta que ya usa `CompletarRuafClientes`.
 *  - **Sexo y fecha de nacimiento**: Sura mismo, con
 *    `afiliacion/consultarDependiente`. Los exige el formulario y el sexo lo
 *    valida contra el documento, así que no se pueden inventar; pero si la
 *    persona ya estuvo afiliada, el portal los sabe. Los dos salen de la misma
 *    respuesta, así que se consulta una sola vez.
 *
 * Lo que encuentra se guarda en BryNex: la próxima afiliación ya no lo consulta.
 */
class ArlDatosFaltantesService
{
    /** @var array<string,array> Respuesta de Sura por contrato, para no repetirla. */
    private array $consultados = [];

    /**
     * @return array<string,string> Lo que se completó, para poder contarlo.
     */
    public function completar(Contrato $contrato): array
    {
        $cliente = $contrato->cliente;

        if (! $cliente) {
            return [];
        }

        $completado = [];

        if ($this->faltaPension($contrato, $cliente) && $afp = $this->pensionDesdeRuaf($contrato, $cliente)) {
            $cliente->pension_id = $afp;
            if ($this->faltaPension($contrato, $cliente)) {
                $contrato->pension_id = $afp;
                $contrato->save();
            }
            $cliente->save();
            $completado['pension'] = 'RUAF';
        }

        if (! $cliente->genero && $sexo = $this->sexoDesdeSura($contrato, $cliente)) {
            $cliente->genero = $sexo;
            $cliente->save();
            $completado['sexo'] = 'ARL Sura';
        }

        if (! $cliente->fecha_nacimiento && $fn = $this->nacimientoDesdeSura($contrato, $cliente)) {
            $cliente->fecha_nacimiento = $fn;
            $cliente->save();
            $completado['fecha_nacimiento'] = 'ARL Sura';
        }

        return $completado;
    }

    private function faltaPension(Contrato $contrato, Cliente $cliente): bool
    {
        $id = (int) ($contrato->pension_id ?: $cliente->pension_id);

        return $id === 0;
    }

    /**
     * La pensión que reporta el RUAF. `administradoraRUAF` trae el código con el
     * que se cruza contra `pensiones.codigo`, igual que en CompletarRuafClientes.
     */
    private function pensionDesdeRuaf(Contrato $contrato, Cliente $cliente): ?int
    {
        try {
            $credencial = OperadorCredencial::where('aliado_id', $contrato->aliado_id)
                ->whereNotNull('usuario')
                ->first();

            if (! $credencial) {
                return null;
            }

            $operador = OperadorPlanilla::find($credencial->operador_planilla_id);

            if (! $operador || ! SuaporteApiService::soportaOperador($operador->codigo)) {
                return null;
            }

            $api = new SuaporteApiService([
                'operador'      => $operador->codigo,
                'usuario'       => $credencial->usuario,
                'contrasena'    => $credencial->contrasena,
                'clave_secreta' => $credencial->clave_secreta,
            ]);

            $r = $api->consultarAfiliacion($cliente->tipo_doc, (string) $cliente->cedula);

            if (! ($r['success'] ?? false) || ! ($r['registrado'] ?? false)) {
                return null;
            }

            $codigo = $r['afiliacion']['administradoraRUAF'] ?? null;

            if (! $codigo) {
                return null;
            }

            // "NIN-AF" es como el RUAF dice que la persona no está afiliada a
            // ningún fondo. No es un dato faltante: es la respuesta. Sura tiene
            // "NINGUNA AFP" (000) justamente para eso —el caso de quien solo
            // cotiza a riesgos— así que se usa esa en vez de bloquear.
            if (str_starts_with($codigo, 'NIN')) {
                return DB::table('pensiones')->where('codigo_sura', '000')->value('id');
            }

            return DB::table('pensiones')->where('codigo', $codigo)->value('id');
        } catch (Throwable) {
            return null;
        }
    }

    /** El sexo que Sura tiene registrado para ese documento, si la persona ya existe allí. */
    private function sexoDesdeSura(Contrato $contrato, Cliente $cliente): ?string
    {
        $sexo = strtoupper(trim((string) ($this->deSura($contrato, $cliente)['sexo'] ?? '')));

        return in_array($sexo, ['M', 'F'], true) ? $sexo : null;
    }

    /** La fecha de nacimiento que Sura ya tiene. Llega como `ddmmYYYY`. */
    private function nacimientoDesdeSura(Contrato $contrato, Cliente $cliente): ?Carbon
    {
        $crudo = preg_replace('/\D/', '', (string) ($this->deSura($contrato, $cliente)['feNacimiento'] ?? ''));

        if (strlen($crudo) !== 8) {
            return null;
        }

        try {
            $fecha = Carbon::createFromFormat('dmY', $crudo)->startOfDay();
        } catch (Throwable) {
            return null;
        }

        // Sura devuelve rellenos como 31/12/3000 en las fechas que no conoce, y
        // guardar eso sería peor que dejar el campo vacío: nadie lo revisaría.
        return $fecha->year >= 1900 && $fecha->isPast() ? $fecha : null;
    }

    /**
     * Lo que Sura sabe del trabajador, consultado una sola vez.
     *
     * Sexo y fecha de nacimiento vienen en la misma respuesta y los dos suelen
     * faltar juntos, así que sin la memoria serían dos viajes al portal —cada
     * uno con su sesión— para leer el mismo JSON.
     */
    private function deSura(Contrato $contrato, Cliente $cliente): array
    {
        $llave = $contrato->id.'|'.$cliente->cedula;

        if (array_key_exists($llave, $this->consultados)) {
            return $this->consultados[$llave];
        }

        try {
            $poliza = $contrato->razonSocial?->arl_poliza;

            $this->consultados[$llave] = $poliza
                ? (new ArlSuraApiService((int) $contrato->aliado_id, $poliza))->consultarDependiente(
                    ArlSuraPayloadBuilder::tipoDocumento($cliente->tipo_doc).$cliente->cedula
                )
                : [];
        } catch (Throwable) {
            $this->consultados[$llave] = [];
        }

        return $this->consultados[$llave];
    }
}
