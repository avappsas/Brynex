<?php

namespace App\Services\ArlSura;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\OperadorCredencial;
use App\Models\OperadorPlanilla;
use App\Services\SuaporteApiService;
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
 *  - **Sexo**: Sura mismo, con `afiliacion/consultarDependiente`. Lo exige el
 *    formulario y además lo valida contra el documento, así que no se puede
 *    inventar; pero si la persona ya estuvo afiliada, el portal lo sabe.
 *
 * Lo que encuentra se guarda en BryNex: la próxima afiliación ya no lo consulta.
 */
class ArlDatosFaltantesService
{
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
        try {
            $poliza = $contrato->razonSocial?->arl_poliza;

            if (! $poliza) {
                return null;
            }

            $api  = new ArlSuraApiService((int) $contrato->aliado_id, $poliza);
            $dni  = ArlSuraPayloadBuilder::tipoDocumento($cliente->tipo_doc).$cliente->cedula;
            $r    = $api->consultarDependiente($dni);
            $sexo = strtoupper(trim((string) ($r['sexo'] ?? '')));

            return in_array($sexo, ['M', 'F'], true) ? $sexo : null;
        } catch (Throwable) {
            return null;
        }
    }
}
