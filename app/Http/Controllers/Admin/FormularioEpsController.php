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
     * Vista de impresión: muestra el formulario en un iframe con botón de impresión.
     * GET /admin/afiliaciones/{contrato}/formulario/eps/vista
     */
    public function vista(Contrato $contrato)
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

        $cliente      = $contrato->cliente;
        $eps          = $contrato->eps;
        $nombreCompleto = $cliente?->nombre_completo ?? '—';
        $empresa        = $contrato->razonSocial?->razon_social ?? '—';
        $fechaIngreso   = $contrato->fecha_ingreso?->format('d/m/Y') ?? '—';
        $salario        = $contrato->salario
            ? '$ ' . number_format((float)$contrato->salario, 0, ',', '.')
            : '—';

        return view('admin.afiliaciones.formulario_print', compact(
            'contrato', 'eps', 'nombreCompleto',
            'empresa', 'fechaIngreso', 'salario', 'conBeneficiarios'
        ));
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
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        file_put_contents($dir . '/' . $cedula . '.png', base64_decode($data));

        return response()->json(['ok' => true]);
    }

    /**
     * Devuelve el PDF relleno (binario) para el iframe.
     * GET /admin/afiliaciones/{contrato}/formulario/eps/raw
     */
    public function generar(Contrato $contrato)
    {
        $incluirBeneficiarios = request()->boolean('beneficiarios', false);

        $contrato->loadMissing([
            'cliente.municipio',
            'cliente.departamento',
            'cliente.beneficiarios',
            'razonSocial',
            'eps',
            'arl',
            'pension',
        ]);

        $pdfBinario = $this->service->generar($contrato, $incluirBeneficiarios);

        $nombreCliente = str_replace([' ', '/'], '_', $contrato->cliente?->nombre_completo ?? 'formulario');
        $nombreEps     = str_replace([' ', '/'], '_', $contrato->eps?->nombre ?? 'eps');
        $filename      = "Formulario_{$nombreEps}_{$nombreCliente}.pdf";

        return response($pdfBinario, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }
}
