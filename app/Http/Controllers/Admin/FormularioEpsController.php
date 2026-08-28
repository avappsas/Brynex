<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Services\FormularioEpsService;
use Illuminate\Http\Request;

class FormularioEpsController extends Controller
{
    public function __construct(protected FormularioEpsService $service) {}

    /**
     * Vista de impresión del formulario de EPS.
     * GET /admin/afiliaciones/{contrato}/formulario/eps
     */
    public function vista(Contrato $contrato)
    {
        return $this->vistaFormulario($contrato, 'eps');
    }

    /**
     * Vista de impresión del formulario del fondo de pensión (COLPENSIONES y demás).
     * GET /admin/afiliaciones/{contrato}/formulario/pension
     */
    public function vistaPension(Contrato $contrato)
    {
        return $this->vistaFormulario($contrato, 'pension');
    }

    /**
     * Arma la vista de impresión: muestra el PDF en un iframe con el botón de
     * imprimir. El único cambio entre EPS y pensión es de quién sale el mapeo.
     */
    protected function vistaFormulario(Contrato $contrato, string $tipo)
    {
        $conBeneficiarios = request()->boolean('beneficiarios', false);

        $contrato->loadMissing([
            'cliente.municipio',
            'cliente.departamento',
            'cliente.beneficiarios',
            'razonSocial',
            'eps',
            'arl',
            'pension',
        ]);

        $cliente = $contrato->cliente;
        $entidad = $tipo === 'pension' ? $contrato->pension : $contrato->eps;
        $tituloEntidad = $tipo === 'pension'
            ? ($contrato->pension?->razon_social ?? 'Pensión')
            : ($contrato->eps?->nombre ?? 'EPS');

        $nombreCompleto = $cliente?->nombre_completo ?? '—';
        $empresa = $contrato->razonSocial?->razon_social ?? '—';
        $fechaIngreso = $contrato->fecha_ingreso?->format('d/m/Y') ?? '—';
        $salario = $contrato->salario
            ? '$ '.number_format((float) $contrato->salario, 0, ',', '.')
            : '—';

        // Campos de texto libre (custom.*) configurados para esta entidad
        $camposCustom = collect($entidad?->formulario_campos ?? [])
            ->filter(fn ($c) => str_starts_with($c['dato'] ?? '', 'custom.'))
            ->map(fn ($c) => [
                'clave' => $c['dato'],
                'sufijo' => str_replace('custom.', '', $c['dato']),
                'label' => $c['label'] ?? $c['dato'],
            ])
            ->unique('clave')
            ->values();

        $rutaVista = $tipo === 'pension'
            ? 'admin.afiliaciones.formulario.pension'
            : 'admin.afiliaciones.formulario.eps';
        $rutaRaw = $rutaVista.'.raw';

        return view('admin.afiliaciones.formulario_print', [
            'contrato' => $contrato,
            'tituloEntidad' => $tituloEntidad,
            'nombreCompleto' => $nombreCompleto,
            'empresa' => $empresa,
            'fechaIngreso' => $fechaIngreso,
            'salario' => $salario,
            'conBeneficiarios' => $conBeneficiarios,
            'camposCustom' => $camposCustom,
            'urlVista' => route($rutaVista, ['contrato' => $contrato->id]),
            'urlRaw' => route($rutaRaw, ['contrato' => $contrato->id]),
            'urlFirma' => route('admin.afiliaciones.formulario.eps.firma', $contrato->id),
        ]);
    }

    /**
     * Guarda la firma del cliente (base64 PNG) en disco.
     * POST /admin/afiliaciones/{contrato}/formulario/eps/firma
     */
    public function guardarFirma(Request $request, Contrato $contrato)
    {
        $request->validate(['firma' => 'required|string']);

        $data = $request->input('firma');
        if (str_contains($data, ',')) {
            $data = explode(',', $data, 2)[1];
        }

        $contrato->loadMissing('cliente');
        $cedula = $contrato->cliente?->cedula ?? $contrato->id;

        $dir = storage_path('app/firmas');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dir.'/'.$cedula.'.png', base64_decode($data));

        return response()->json(['ok' => true]);
    }

    /**
     * PDF relleno de la EPS (binario) para el iframe.
     * GET /admin/afiliaciones/{contrato}/formulario/eps/raw
     */
    public function generar(Contrato $contrato)
    {
        return $this->generarPdf($contrato, 'eps');
    }

    /**
     * PDF relleno del fondo de pensión (binario) para el iframe.
     * GET /admin/afiliaciones/{contrato}/formulario/pension/raw
     */
    public function generarPension(Contrato $contrato)
    {
        return $this->generarPdf($contrato, 'pension');
    }

    protected function generarPdf(Contrato $contrato, string $tipo)
    {
        $incluirBeneficiarios = request()->boolean('beneficiarios', false);

        // Datos de texto libre enviados desde la vista (custom[texto_1]=valor, …)
        $customDatos = request()->input('custom', []);
        if (! is_array($customDatos)) {
            $customDatos = [];
        }

        $contrato->loadMissing([
            'cliente.municipio',
            'cliente.departamento',
            'cliente.beneficiarios',
            'razonSocial',
            'eps',
            'arl',
            'pension',
        ]);

        $pdfBinario = $tipo === 'pension'
            ? $this->service->generarPension($contrato, $incluirBeneficiarios, $customDatos)
            : $this->service->generar($contrato, $incluirBeneficiarios, $customDatos);

        $nombreCliente = str_replace([' ', '/'], '_', $contrato->cliente?->nombre_completo ?? 'formulario');
        $nombreEntidad = $tipo === 'pension'
            ? str_replace([' ', '/'], '_', $contrato->pension?->razon_social ?? 'pension')
            : str_replace([' ', '/'], '_', $contrato->eps?->nombre ?? 'eps');
        $filename = "Formulario_{$nombreEntidad}_{$nombreCliente}.pdf";

        return response($pdfBinario, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
