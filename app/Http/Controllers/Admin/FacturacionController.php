<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Factura, Abono, Plano, Contrato, Empresa, RazonSocial, User, BancoCuenta};
use App\Models\Bitacora;
use App\Services\MoraClienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill, Font, NumberFormat};

class FacturacionController extends Controller
{
    // ─── Listado de empresas ─────────────────────────────────────────
    public function index(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        // Subquery corroborada como objeto Eloquent/Builder válido para selectSub
        $subContratos = DB::table('contratos as c')
            ->join('clientes as cl', 'cl.cedula', '=', 'c.cedula')
            ->where('c.aliado_id', $aliadoId)
            ->whereIn('c.estado', ['vigente', 'activo'])
            ->whereColumn('cl.cod_empresa', 'empresas.id')
            ->selectRaw('COUNT(DISTINCT c.id)');

        $empresas = Empresa::where('aliado_id', $aliadoId)
            ->where('id', '>', 1)
            ->select(['id','empresa','nit','contacto','telefono','celular','iva'])
            ->selectSub($subContratos, 'contratos_activos_count')
            ->get()
            ->sortBy([
                // Las que tienen contratos activos van primero
                fn ($a, $b) => ($b->contratos_activos_count > 0) <=> ($a->contratos_activos_count > 0),
                // Dentro de cada grupo, A-Z
                fn ($a, $b) => strcmp($a->empresa, $b->empresa),
            ])
            ->values();

        return view('admin.facturacion.index', compact('empresas'));
    }

    // ─── Vista empresa (tabla de trabajadores) ───────────────────────
    public function empresa(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $mes  = (int) $request->get('mes',  now()->month);
        $anio = (int) $request->get('anio', now()->year);

        $datos = $this->getDatosEmpresaPeriodo($empresaId, $mes, $anio, $aliadoId);

        return view('admin.facturacion.empresa', array_merge($datos, [
            'mes'  => $mes,
            'anio' => $anio,
        ]));
    }

    /**
     * Obtiene y pre-calcula los datos de facturación para los contratos de una empresa en un período dado.
     */
    private function getDatosEmpresaPeriodo(int $empresaId, int $mes, int $anio, int $aliadoId): array
    {
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        // Pre-cargar configuración global en 1 query (evita N+1 en calcularCotizacion)
        \App\Models\ConfiguracionBrynex::precargar();

        // Traer todos los contratos vigentes cuyos clientes pertenecen a esta empresa
        $cedulasEmpresa = DB::table('clientes')
            ->where('aliado_id', $aliadoId)
            ->where('cod_empresa', $empresaId)
            ->pluck('cedula');

        // Retirados visibles en este período
        $mesAnterior  = $mes  === 1 ? 12 : $mes  - 1;
        $anioAnterior = $mes  === 1 ? $anio - 1 : $anio;

        $contratos = Contrato::where('aliado_id', $aliadoId)
            ->whereIn('cedula', $cedulasEmpresa)
            ->where(function ($q) use ($mes, $anio, $mesAnterior, $anioAnterior) {
                $q->whereIn('estado', ['vigente', 'activo'])
                  ->orWhere(function ($q2) use ($mes, $anio, $mesAnterior, $anioAnterior) {
                      $q2->where('estado', 'retirado')
                         ->where(function ($q3) use ($mes, $anio, $mesAnterior, $anioAnterior) {
                             $q3->where(function ($qa) use ($mes, $anio) {
                                     $qa->where('tipo_modalidad_id', 11)
                                        ->whereMonth('fecha_retiro', $mes)
                                        ->whereYear('fecha_retiro', $anio);
                                 })
                                ->orWhere(function ($qb) use ($mesAnterior, $anioAnterior) {
                                     $qb->where('tipo_modalidad_id', '!=', 11)
                                        ->whereMonth('fecha_retiro', $mesAnterior)
                                        ->whereYear('fecha_retiro', $anioAnterior);
                                 });
                         });
                  });
            })
            ->with([
                'cliente'      => fn($q) => $q->where('aliado_id', $aliadoId),
                'tipoModalidad', 'razonSocial', 'eps', 'arl', 'pension', 'caja', 'asesor',
                'plan',
            ])
            ->orderBy('cedula')
            ->get();

        $facturasExistentes = Factura::where('aliado_id', $aliadoId)
            ->periodo($mes, $anio)
            ->whereIn('tipo', ['planilla', 'afiliacion'])
            ->whereIn('cedula', $contratos->pluck('cedula'))
            ->whereNotNull('contrato_id')
            ->where('numero_factura', '>', 0)
            ->get()
            ->keyBy('contrato_id');

        $facturasRetiro0 = Factura::where('aliado_id', $aliadoId)
            ->whereIn('contrato_id', $contratos->pluck('id'))
            ->where('numero_factura', 0)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('contrato_id');

        $contratoIds = $contratos->pluck('id')->all();

        $saldosTotales = DB::table('facturas')
            ->where('aliado_id', $aliadoId)
            ->whereIn('contrato_id', $contratoIds)
            ->whereNotNull('saldo_proximo')
            ->whereIn('estado', ['pagada', 'prestamo', 'abono'])
            ->whereNull('deleted_at')
            ->groupBy('contrato_id')
            ->select('contrato_id', DB::raw('SUM(saldo_proximo) as suma'))
            ->pluck('suma', 'contrato_id');

        $saldosPrevios = DB::table('facturas')
            ->where('aliado_id', $aliadoId)
            ->whereIn('contrato_id', $contratoIds)
            ->whereNull('empresa_id')
            ->whereNotNull('saldo_proximo')
            ->whereIn('estado', ['pagada', 'prestamo', 'abono'])
            ->whereNull('deleted_at')
            ->where(fn($q) => $q->where('anio', '<', $anio)
                ->orWhere(fn($q2) => $q2->where('anio', $anio)->where('mes', '<', $mes)))
            ->groupBy('contrato_id')
            ->select('contrato_id', DB::raw('SUM(saldo_proximo) as suma'))
            ->pluck('suma', 'contrato_id');

        $ivaClientes = DB::table('clientes')
            ->where('aliado_id', $aliadoId)
            ->where('cod_empresa', $empresaId)
            ->pluck('iva', 'cedula')
            ->map(fn($v) => strtoupper(trim($v ?? '')) === 'SI')
            ->toArray();

        $hoy = now();

        $contratos = $contratos->map(function ($c) use ($mes, $anio, $hoy, $facturasExistentes, $facturasRetiro0, $saldosTotales, $saldosPrevios, $ivaClientes) {
            $diasCotizar = 30;
            $esIndActPrimerMes = false;

            $esArlModalidad = (int)($c->tipo_modalidad_id) === 15;
            if ($esArlModalidad) {
                $fArl = $c->fecha_arl ?? $c->fecha_ingreso;
                if ($fArl) {
                    $mesArl  = (int)$fArl->month;
                    $anioArl = (int)$fArl->year;
                    if ($mesArl === $mes && $anioArl === $anio) {
                        $diasCotizar = 0;
                    } else {
                        $diasCotizar = 0;
                    }
                } else {
                    $diasCotizar = 0;
                }
            } elseif ($c->fecha_ingreso) {
                $fIng = $c->fecha_ingreso;
                $mesIngreso  = (int)$fIng->month;
                $anioIngreso = (int)$fIng->year;
                $esIndAct = (int)($c->tipo_modalidad_id) === 11;

                if ($mesIngreso === $mes && $anioIngreso === $anio) {
                    if ($esIndAct) {
                        $esIndActPrimerMes = true;
                        $diasCotizar = max(1, 30 - $fIng->day + 1);
                    } else {
                        $diasCotizar = 0;
                    }
                } else {
                    $mesAnterior  = $mes === 1 ? 12 : $mes - 1;
                    $anioAnterior = $mes === 1 ? $anio - 1 : $anio;

                    if ($mesIngreso === $mesAnterior && $anioIngreso === $anioAnterior) {
                        $diasCotizar = max(1, 30 - $fIng->day + 1);
                    }
                }
            }
            $c->dias_cotizar          = $diasCotizar;
            $c->es_ind_act_primer_mes = $esIndActPrimerMes;

            // ── Retiro Pendiente (registrado desde vista empresa, aún no facturado) ──
            // Si el contrato vigente tiene fecha_retiro_pendiente, los días cotizables
            // son los días del mes hasta esa fecha (ej: día 20 = 20 días).
            if ($c->estado === 'vigente' && $c->fecha_retiro_pendiente) {
                $c->dias_cotizar = (int) $c->fecha_retiro_pendiente->day;
                $c->tiene_retiro_pendiente = true;
            } else {
                $c->tiene_retiro_pendiente = false;
            }

            $c->factura_exist         = $facturasExistentes->get($c->id);

            $facturaRetiro0 = $facturasRetiro0->get($c->id);
            $c->factura_retiro_0      = $facturaRetiro0;
            $c->tiene_retiro_facturable = $c->estado === 'retirado'
                && $facturaRetiro0 !== null
                && $c->factura_exist === null;

            $ivaFlag = $ivaClientes[$c->cedula] ?? null;
            $c->cotizacion_calc = $c->calcularCotizacion($diasCotizar, $ivaFlag);

            $sumaPrev = (int)($saldosPrevios[$c->id] ?? 0);
            $c->saldo_a_favor_facturar   = $sumaPrev > 0 ? $sumaPrev : 0;
            $c->saldo_pendiente_facturar = $sumaPrev < 0 ? abs($sumaPrev) : 0;

            $sumaTotal = (int)($saldosTotales[$c->id] ?? 0);
            $c->saldo_a_favor   = $sumaTotal > 0 ? $sumaTotal : 0;
            $c->saldo_pendiente = $sumaTotal < 0 ? abs($sumaTotal) : 0;

            $sp = $c->factura_exist ? (int)($c->factura_exist->saldo_proximo ?? 0) : 0;
            $c->saldo_proximo_favor     = $sp > 0 ? $sp : 0;
            $c->saldo_proximo_pendiente = $sp < 0 ? abs($sp) : 0;

            return $c;
        });

        $filasMora = [];
        foreach ($contratos as $c) {
            if ($c->factura_exist) continue;
            if ($c->estado === 'retirado') continue;
            if ($c->es_ind_act_primer_mes === false && $c->cotizacion_calc['ss'] == 0) continue;
            if ((int)$c->tipo_modalidad_id === 15) continue;
            $rs = $c->razonSocial;
            $esIndep = $c->esIndependiente() || ($rs && $rs->es_independiente);
            $rsNit = $esIndep ? (int)$c->cedula : ($rs ? (int)($rs->nit ?: $rs->id) : 0);
            if (!$rsNit) continue;
            $vSS = (int)($c->cotizacion_calc['ss'] ?? 0);
            if ($vSS <= 0) continue;
            $filasMora[$c->id] = [
                'contrato_id'  => $c->id,
                'rs_nit'       => $rsNit,
                'rs_dia_habil' => $esIndep ? null : ($rs->dia_habil ?? null),
                'total_ss'     => $vSS,
                'mes'          => $mes,
                'anio'         => $anio,
            ];
        }
        $moraPorContrato = [];
        if (!empty($filasMora)) {
            $resultadosMora = \App\Services\MoraClienteService::calcularLote($aliadoId, array_values($filasMora));
            foreach ($resultadosMora as $fila) {
                $moraPorContrato[$fila['contrato_id']] = (int)($fila['mora'] ?? 0);
            }
        }

        $bancos   = BancoCuenta::activas($aliadoId);
        $asesores = \App\Models\Asesor::where('aliado_id', $aliadoId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $planosActuales = DB::table('planos')
            ->where('aliado_id', $aliadoId)
            ->where('mes_plano', $mes)->where('anio_plano', $anio)
            ->select('razon_social', DB::raw('MAX(n_plano) as n_plano_max'))
            ->groupBy('razon_social')
            ->get()->keyBy('razon_social');

        $saldoNetoEmpresa = Factura::where('aliado_id', $aliadoId)
            ->where('empresa_id', $empresa->id)
            ->whereNotNull('saldo_proximo')
            ->whereIn('estado', ['pagada', 'prestamo', 'abono'])
            ->whereNull('deleted_at')
            ->sum('saldo_proximo');

        $saldoEmpresaFavor    = $saldoNetoEmpresa > 0 ? (int)$saldoNetoEmpresa : 0;
        $saldoEmpresaPendiente = $saldoNetoEmpresa < 0 ? (int)abs($saldoNetoEmpresa) : 0;

        $anticiposEmpresa       = \App\Models\Anticipo::disponiblesParaEmpresa($aliadoId, $empresa->id);
        $totalAnticipoDisponible = (int)$anticiposEmpresa->sum('valor_disponible');

        $anticiposPorContrato = \App\Models\Anticipo::aliado($aliadoId)
            ->whereIn('contrato_id', $contratoIds)
            ->conSaldo()
            ->get()
            ->groupBy('contrato_id');

        $saldoAnticipoPorContrato = $anticiposPorContrato->map(fn($group) => $group->sum('valor_disponible'));
        $hayAnticipos = $saldoAnticipoPorContrato->isNotEmpty() || $totalAnticipoDisponible > 0;

        $cobrosAdicionales = \App\Models\CobrosAdicionalEmpresa::where('aliado_id', $aliadoId)
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->orderBy('tipo')
            ->orderBy('descripcion')
            ->get();
        $cobrosRecurrentes = $cobrosAdicionales->where('tipo', 'recurrente')->values();

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        return compact(
            'empresa', 'contratos', 'facturasExistentes', 'bancos', 'planosActuales', 'asesores',
            'saldoEmpresaFavor', 'saldoEmpresaPendiente', 'moraPorContrato',
            'anticiposEmpresa', 'totalAnticipoDisponible', 'saldoAnticipoPorContrato', 'hayAnticipos',
            'cobrosAdicionales', 'cobrosRecurrentes', 'meses'
        );
    }

    /**
     * Exporta la planilla de facturación de una empresa a formato XLSX.
     */
    public function exportarEmpresaExcel(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $mes  = (int) $request->get('mes',  now()->month);
        $anio = (int) $request->get('anio', now()->year);

        $datos = $this->getDatosEmpresaPeriodo($empresaId, $mes, $anio, $aliadoId);

        $empresa = $datos['empresa'];
        $contratos = $datos['contratos'];
        $facturasExistentes = $datos['facturasExistentes'];
        $moraPorContrato = $datos['moraPorContrato'];
        $saldoAnticipoPorContrato = $datos['saldoAnticipoPorContrato'];
        $meses = $datos['meses'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Facturación');

        // Título principal
        $sheet->setCellValue('A1', 'PLANILLA DE COBRO - ' . $empresa->empresa);
        $sheet->mergeCells('A1:R1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        
        $sheet->setCellValue('A2', 'Periodo: ' . $meses[$mes] . ' de ' . $anio);
        $sheet->mergeCells('A2:R2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

        // Cabeceras
        $headers = [
            'TIPO', 'CÉDULA', 'NOMBRE', 'RAZÓN SOCIAL', 'ING/RET', 'DÍAS',
            'EPS', 'ARL', 'CAJA', 'PENSIÓN', 'ADMON', 'ADMON ASESOR', 'AFILIACIÓN',
            'TOTAL', 'MORA', 'ANTICIPO', 'ESTADO', 'NP'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '4', $header);
            $col++;
        }

        // Estilo cabecera
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A8A'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ];
        $sheet->getStyle('A4:R4')->applyFromArray($headerStyle);
        $sheet->getRowDimension(4)->setRowHeight(25);

        $r100 = fn($val) => (int) round($val ?? 0);
        $row = 5;

        foreach ($contratos as $c) {
            $fact  = $c->factura_exist;
            $factRetiroPreview = (!$fact && ($c->tiene_retiro_facturable ?? false)) ? ($c->factura_retiro_0 ?? null) : null;
            $esRetirado = $c->estado === 'retirado';
            
            $nombre = $c->cliente?->nombre_completo ?: '—';
            
            $tipoMod = $c->tipoModalidad?->tipo_modalidad ?? '—';
            $rs = $c->razonSocial?->razon_social ?? '—';
            
            $fIng = $c->fecha_ingreso ? $c->fecha_ingreso->format('d/m/Y') : '—';
            $fRet = ($esRetirado && $c->fecha_retiro) ? $c->fecha_retiro->format('d/m/Y') : null;
            
            $dias = $fact
                ? (int)$fact->dias_cotizados
                : ($factRetiroPreview
                    ? (int)$factRetiroPreview->dias_cotizados
                    : ($c->dias_cotizar ?? 30));

            $esIndActPrimerMes = $c->es_ind_act_primer_mes ?? false;
            $esArlModalidad = (int)($c->tipo_modalidad_id) === 15;
            
            $esAfil = false;
            if ($esArlModalidad) {
                $esAfil = true;
            } elseif ($c->fecha_ingreso) {
                $fIngC = $c->fecha_ingreso;
                if ((int)$fIngC->month === $mes && (int)$fIngC->year === $anio) {
                    if (!$esIndActPrimerMes) {
                        $esAfil = true;
                    }
                }
            }
            if ($fact) {
                $esAfil = $fact->tipo === 'afiliacion' && !($fact->afiliacion > 0 && $fact->total_ss > 0);
            }

            $cotiz = $c->cotizacion_calc ?? $c->calcularCotizacion($dias);

            $vEps  = $fact ? $r100($fact->v_eps)  : 0;
            $vArl  = $fact ? $r100($fact->v_arl)  : 0;
            $vCaja = $fact ? $r100($fact->v_caja) : 0;
            $vPen  = $fact ? $r100($fact->v_afp)  : 0;
            $vIva  = $fact ? $r100($fact->iva)    : 0;

            $vAdmonBase = 0;
            $vAdmonAsesor = 0;
            if ($fact) {
                $vAdmonBase = (int)$fact->admon;
                $vAdmonAsesor = (int)$fact->admin_asesor;
            } else {
                if ($esRetirado) {
                    if ($factRetiroPreview) {
                        $vAdmonBase = (int)($c->administracion ?? 0);
                        $vAdmonAsesor = (int)($c->admon_asesor ?? 0);
                        $vAdmonBase = (int) round(($vAdmonBase / 30) * $dias);
                        $vAdmonAsesor = (int) round(($vAdmonAsesor / 30) * $dias);
                    } else {
                        $vAdmonBase = 0;
                        $vAdmonAsesor = 0;
                    }
                } elseif ($esArlModalidad && !$esAfil) {
                    $vAdmonBase = 0;
                    $vAdmonAsesor = 0;
                } elseif ($esAfil) {
                    $vAdmonBase = 0;
                    $vAdmonAsesor = 0;
                } else {
                    $vAdmonBase = (int)($c->administracion ?? 0);
                    $vAdmonAsesor = (int)($c->admon_asesor ?? 0);
                }
            }

            $vAfiliacion = ($esAfil || $esIndActPrimerMes) ? (int)($c->costo_afiliacion ?? 0) : 0;
            if ($fact) {
                $vAfiliacion = (int)$fact->afiliacion;
            }

            if (!$fact) {
                if ($esRetirado && $factRetiroPreview) {
                    $vEps  = $r100($factRetiroPreview->v_eps);
                    $vArl  = $r100($factRetiroPreview->v_arl);
                    $vPen  = $r100($factRetiroPreview->v_afp);
                    $vCaja = $r100($factRetiroPreview->v_caja);
                    $vIva  = $r100($factRetiroPreview->iva);
                    $vSS   = $r100($factRetiroPreview->total_ss);
                    $vTot  = $vSS + ($vAdmonBase + $vAdmonAsesor) + $vIva;
                } elseif ($esRetirado && !$factRetiroPreview) {
                    $vEps = $vArl = $vPen = $vCaja = $vIva = $vSS = 0;
                    $vTot = 0;
                } elseif ($esArlModalidad && !$esAfil) {
                    $vEps = $vArl = $vPen = $vCaja = $vIva = $vSS = 0;
                    $vTot = 0;
                } elseif ($esIndActPrimerMes) {
                    $vEps  = $r100($cotiz['eps']??0);
                    $vArl  = $r100($cotiz['arl']??0);
                    $vPen  = $r100($cotiz['pen']??0);
                    $vCaja = $r100($cotiz['caja']??0);
                    $vIva  = $r100($cotiz['iva']??0);
                    $vSS   = $r100($cotiz['ss']);
                    $vTot  = $vSS + ($vAdmonBase + $vAdmonAsesor) + $vIva + (int)(($c->costo_afiliacion ?? 0) + ($c->seguro ?? 0));
                } elseif ($esAfil) {
                    $vEps  = 0; $vArl  = 0; $vPen  = 0; $vCaja = 0;
                    $vSS   = 0; $vIva  = 0;
                    $vTot  = (int)(($c->costo_afiliacion ?? 0) + ($c->seguro ?? 0));
                } else {
                    $vEps  = $r100($cotiz['eps']??0);
                    $vArl  = $r100($cotiz['arl']??0);
                    $vPen  = $r100($cotiz['pen']??0);
                    $vCaja = $r100($cotiz['caja']??0);
                    $vIva  = $r100($cotiz['iva']??0);
                    $vSS   = $r100($cotiz['ss']);
                    $vTot  = $vSS + ($vAdmonBase + $vAdmonAsesor) + $vIva;
                }
            } else {
                $vSS = $r100($fact->total_ss);
                $vTot = (int)$fact->total;
            }

            $vMora = 0;
            if ($fact && ($fact->mora ?? 0) > 0) {
                $vMora = (int)$fact->mora;
            } elseif (!$fact) {
                $vMora = (int)($moraPorContrato[$c->id] ?? 0);
            }

            $vAnticipo = $saldoAnticipoPorContrato->get($c->id, 0);

            $sheet->setCellValue('A' . $row, $tipoMod);
            $sheet->setCellValue('B' . $row, $c->cedula);
            $sheet->setCellValue('C' . $row, $nombre);
            $sheet->setCellValue('D' . $row, $rs);
            
            $fechaObj = ($esRetirado && $c->fecha_retiro) ? $c->fecha_retiro : $c->fecha_ingreso;
            if ($fechaObj instanceof \DateTimeInterface) {
                $sheet->setCellValue('E' . $row, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($fechaObj));
                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            } else {
                $sheet->setCellValue('E' . $row, '—');
            }
            $sheet->setCellValue('F' . $row, $dias);
            
            $sheet->setCellValue('G' . $row, $vEps);
            $sheet->setCellValue('H' . $row, $vArl);
            $sheet->setCellValue('I' . $row, $vCaja);
            $sheet->setCellValue('J' . $row, $vPen);
            $sheet->setCellValue('K' . $row, $vAdmonBase);
            $sheet->setCellValue('L' . $row, $vAdmonAsesor);
            $sheet->setCellValue('M' . $row, $vAfiliacion);
            $sheet->setCellValue('N' . $row, $vTot);
            $sheet->setCellValue('O' . $row, $vMora);
            $sheet->setCellValue('P' . $row, $vAnticipo);
            
            $estadoTxt = $fact ? strtoupper($fact->estado) : ($esRetirado ? 'RETIRO' : 'PENDIENTE');
            $sheet->setCellValue('Q' . $row, $estadoTxt);
            $sheet->setCellValue('R' . $row, $fact?->np ?? '');

            // Alineación de texto corto
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('Q' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('R' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Formato de moneda para columnas de dinero (G a P)
            $sheet->getStyle('G' . $row . ':P' . $row)
                ->getNumberFormat()
                ->setFormatCode('$#,##0');

            // Bordes para los datos
            $sheet->getStyle('A' . $row . ':R' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            
            $row++;
        }

        // Fila de totales
        $sheet->setCellValue('A' . $row, 'TOTALES');
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Suma de Días (F)
        $sheet->setCellValue('F' . $row, "=SUM(F5:F" . ($row - 1) . ")");
        $sheet->getStyle('F' . $row)->getFont()->setBold(true);
        $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Sumas por columnas con fórmulas de Excel (G a P)
        $columnasMoneda = ['G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'];
        foreach ($columnasMoneda as $colChar) {
            $sheet->setCellValue($colChar . $row, "=SUM(" . $colChar . "5:" . $colChar . ($row - 1) . ")");
            $sheet->getStyle($colChar . $row)->getFont()->setBold(true);
            $sheet->getStyle($colChar . $row)->getNumberFormat()->setFormatCode('$#,##0');
        }

        // Estilo de fila de totales
        $totalStyle = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '999999'],
                ],
            ],
        ];
        $sheet->getStyle('A' . $row . ':R' . $row)->applyFromArray($totalStyle);
        $sheet->getStyle('A' . $row . ':R' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Auto-ajustar ancho de columnas
        foreach (range('A', 'R') as $colChar) {
            $sheet->getColumnDimension($colChar)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $nomEmpresaClean = preg_replace('/[^a-zA-Z0-9]/', '_', $empresa->empresa);
        $mesNom = $meses[$mes];
        $filename = "Planilla_{$nomEmpresaClean}_{$mesNom}_{$anio}.xlsx";

        return response()->stream(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control'       => 'max-age=0',
            ]
        );
    }

    // ─── Facturar (crear factura) ────────────────────────────────────
    public function facturar(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'contratos'            => 'required|array|min:1',
            'contratos.*'          => 'exists:contratos,id',
            'tipo'                 => 'required|in:afiliacion,planilla',
            'mes'                  => 'required|integer|min:1|max:12',
            'anio'                 => 'required|integer|min:2000|max:2100',
            'forma_pago'           => 'required|in:efectivo,consignacion,mixto,prestamo',
            'estado'               => 'required|in:pre_factura,pagada,prestamo',
            'es_prestamo'          => 'boolean',
            // Array dinámico de consignaciones bancarias
            'consignaciones'             => 'nullable|array',
            'consignaciones.*.banco_cuenta_id' => 'required_with:consignaciones|integer',
            'consignaciones.*.valor'           => 'required_with:consignaciones|numeric|min:0',
            'consignaciones.*.fecha'           => 'nullable|date',
            'consignaciones.*.referencia'      => 'nullable|string|max:100',
            'valor_efectivo'       => 'nullable|numeric|min:0',
            'valor_prestamo'       => 'nullable|numeric|min:0',
            'otros'                => 'nullable|numeric|min:0',
            'otros_admon'          => 'nullable|numeric|min:0',
            'mensajeria'           => 'nullable|numeric|min:0',
            'np'                   => 'nullable|integer',
            'n_plano'              => 'nullable|integer',
            'empresa_id'           => 'nullable|integer',
            'aplicar_saldo'        => 'boolean',
            'observacion'          => 'nullable|string|max:500',
            // Anticipos (pagos previos sin factura)
            'anticipo_ids'         => 'nullable|array',
            'anticipo_ids.*'       => 'integer',
            // SS editables desde la UI (override manual — solo 1 contrato)
            'v_eps_manual'         => 'nullable|integer|min:0',
            'v_arl_manual'         => 'nullable|integer|min:0',
            'v_afp_manual'         => 'nullable|integer|min:0',
            'v_caja_manual'        => 'nullable|integer|min:0',
            // SS manuales por contrato (multi-contrato desde form individual)
            // JSON: { "<contrato_id>": { "eps": X, "arl": X, "afp": X, "caja": X } }
            'manual_ss_por_contrato' => 'nullable|string',
            // Distribución de afiliación (override manual desde la UI)
            'dist_asesor'          => 'nullable|integer|min:0',
            'dist_retiro'          => 'nullable|integer|min:0',
            'dist_encargado'       => 'nullable|integer|min:0',
            'dist_admon'           => 'nullable|integer|min:0',
            // Retiro en el período
            'es_retiro'            => 'boolean',
            'fecha_retiro'         => 'nullable|date',
            'dias_retiro'          => 'nullable|integer|min:1|max:30',
            // Mora al cliente (cobrada en la factura, no es ingreso)
            'mora'                 => 'nullable|integer|min:0',
            // Cartera pendiente: marcar si el usuario incluyó deuda de préstamo anterior
            'incluir_cartera'      => 'boolean',
            'valor_cartera'        => 'nullable|integer|min:0',
            // Modo independiente: 'normal' | 'ambos' (afiliación + planilla en mismo recibo)
            'indep_modo'           => 'nullable|in:normal,ambos',
            // Retiro desde empresa: incluir administración aunque sean ≤3 días
            'incluir_admon_retiro_corto' => 'boolean',
        ]);

        // Decodificar manual_ss_por_contrato si viene como JSON string
        $manualSsPorContrato = [];
        if (!empty($validated['manual_ss_por_contrato'])) {
            $decoded = json_decode($validated['manual_ss_por_contrato'], true);
            if (is_array($decoded)) {
                $manualSsPorContrato = $decoded;
            }
        }

        $np = $validated['np'] ?? null;
        // Si es pago masivo y no tiene NP, generar uno nuevo.
        // El NP es por empresa + mes + año: cada empresa tiene su propio contador
        // que se reinicia cada mes. Primer pago mayo=1, segundo=2, junio vuelve a 1.
        if (!$np && count($validated['contratos']) > 1) {
            $mes        = (int)($validated['mes']        ?? now()->month);
            $anio       = (int)($validated['anio']       ?? now()->year);
            $empresaIdNp = $validated['empresa_id'] ?? null;
            $qNp = DB::table('facturas')
                ->where('aliado_id', $aliadoId)
                ->where('mes',  $mes)
                ->where('anio', $anio)
                ->whereNull('deleted_at');
            // Si viene empresa_id, restringir el contador a esa empresa
            if ($empresaIdNp) {
                $qNp->where('empresa_id', $empresaIdNp);
            }
            $np = ($qNp->max('np') ?? 0) + 1;
        }

        $mes  = (int)$validated['mes'];
        $anio = (int)$validated['anio'];

        // ─── Validar orden secuencial de facturación ────────────────────────
        // Solo para facturas individuales (single contrato); en masivo se omite
        // la validación por desempeño, ya que empresa gestiona su propio orden.
        foreach ($validated['contratos'] as $cId) {
            $cChk = Contrato::where('aliado_id', $aliadoId)->find($cId);
            if ($cChk) {
                $gap = $this->verificarOrdenFacturacion($aliadoId, $cChk, $mes, $anio);
                if ($gap) {
                    return response()->json([
                        'error'       => true,
                        'mensaje'     => $gap['mensaje'],
                        'mes_gap'     => $gap['mes'],
                        'anio_gap'    => $gap['anio'],
                    ], 422);
                }
            }
        }

        // ─── Pre-calcular n_plano compartido por razón social ──────────────
        // Todos los contratos de la misma RS en este lote deben tener el MISMO n_plano.
        // Se calcula UNA VEZ por RS antes de entrar al foreach.
        $nPlanosPorRS = [];

        // ─── Pre-calcular totales del pago ─────────────────────────────────
        // valor_consignado = suma de TODAS las consignaciones del array
        $consignacionesData = $validated['consignaciones'] ?? [];
        $totalPagoConsig    = array_sum(array_column($consignacionesData, 'valor'));
        $totalPagoEfectivo  = (int)($validated['valor_efectivo']  ?? 0);
        $totalPagoPrestamo  = (int)($validated['valor_prestamo']  ?? 0);

        // ─── Pre-cargar anticipos seleccionados ────────────────────────────
        // Los anticipos son pagos previos sin factura. Se separan de valor_efectivo
        // y valor_consignado para evitar doble conteo en el cuadre diario.
        $anticiposSeleccionados = collect();
        $totalAnticipo = 0;
        if (!empty($validated['anticipo_ids'])) {
            $anticiposSeleccionados = \App\Models\Anticipo::whereIn('id', $validated['anticipo_ids'])
                ->where('aliado_id', $aliadoId)
                ->whereIn('estado', [\App\Models\Anticipo::ESTADO_DISPONIBLE, \App\Models\Anticipo::ESTADO_PARCIAL])
                ->lockForUpdate()  // previene race condition
                ->get();
            $totalAnticipo = $anticiposSeleccionados->sum('valor_disponible');
        }

        // IVA configurado (se aplica a clientes con IVA=SI, igual que en la factura)
        $cfgIvaPct = \App\Models\ConfiguracionBrynex::porcentajeIva(); // ej: 19

        // Precargar todos los contratos involucrados en una sola query (con eager loading de relaciones)
        $contratosCargados = Contrato::where('aliado_id', $aliadoId)
            ->whereIn('id', $validated['contratos'])
            ->with(['eps', 'arl', 'pension', 'caja', 'tipoModalidad', 'razonSocial', 'asesor', 'cliente'])
            ->get()
            ->keyBy('id');

        // Calcular COSTO BRUTO REAL de cada contrato para proporcionar el pago proporcional.
        $totalesRealesPorContrato = [];
        foreach ($validated['contratos'] as $cId) {
            $c = $contratosCargados->get($cId);
            if (!$c) { $totalesRealesPorContrato[$cId] = 0; continue; }
            
            $esIndep = $c->tipoModalidad?->esIndependiente() ?? false;
            $esIndAct = (int)($c->tipo_modalidad_id) === 11;
            $esArl = (int)($c->tipo_modalidad_id) === 15;
            $tipoForzado = $validated['tipo'];
            $esMesIng = false;

            if ($esArl) {
                // Gestión ARL siempre es cobro de afiliación, no planilla
                $esMesIng = true;
            } elseif ($c->fecha_ingreso) {
                $mesIng = (int)$c->fecha_ingreso->month;
                $anioIng = (int)$c->fecha_ingreso->year;
                $esMesIng = ($mes === $mesIng && $anio === $anioIng);
            }

            if ($esMesIng) {
                if ($esArl) {
                    $tipoForzado = 'afiliacion';
                } elseif (!$esIndep) {
                    $tipoForzado = 'afiliacion';
                } elseif (!$esIndAct) {
                    $tipoForzado = 'afiliacion';
                }
            }

            if ($esArl) {
                $tipoForzado = 'afiliacion';
            }

            $esIndActPrimerMes = $esIndAct && isset($esMesIng) && $esMesIng;
            $esAfiliacion = $tipoForzado === 'afiliacion';

            if ($esArl && !$esMesIng) {
                $diasCotizar = 0;
                $calcSS = ['eps' => 0, 'arl' => 0, 'afp' => 0, 'caja' => 0];
                $afiliacion = 0;
                $seguro = 0;
                $admon = 0;
                $adminAsesor = 0;
                $otrosAdmon = 0;
                $totalSS = 0;
                $ivaBase = 0;
                $iva = 0;
                $total = 0;
                $moraCliente = 0;
            } else {
                if ($esIndActPrimerMes) {
                    $diasCotizar = max(1, 30 - (int)$c->fecha_ingreso->day + 1);
                } elseif ($esAfiliacion) {
                    $diasCotizar = 0;
                } else {
                    $diasCotizar = $this->calcularDias($c, $mes, $anio);
                }

                $esRetiro    = !empty($validated['es_retiro']);
                $diasRetiro  = $esRetiro ? (int)($validated['dias_retiro'] ?? $diasCotizar) : null;
                if ($esRetiro && $diasRetiro !== null && !$esAfiliacion) {
                    $diasCotizar = $diasRetiro;
                }

                $tieneIva = $c->cliente ? (strtoupper(trim($c->cliente->iva ?? '')) === 'SI') : false;

                if ($esAfiliacion && !$esIndActPrimerMes) {
                    $calcSS = ['eps' => 0, 'arl' => 0, 'afp' => 0, 'caja' => 0];
                } else {
                    $cotiz = $c->calcularCotizacion($diasCotizar, $tieneIva);
                    $calcSS = [
                        'eps'  => (int)($cotiz['eps']  ?? 0),
                        'arl'  => (int)($cotiz['arl']  ?? 0),
                        'afp'  => (int)($cotiz['pen']  ?? 0),
                        'caja' => (int)($cotiz['caja']  ?? 0),
                    ];
                }

                // Override manual
                $esModoIndividual = count($validated['contratos']) === 1;
                if (!$esAfiliacion) {
                    if ($esModoIndividual) {
                        if (isset($validated['v_eps_manual']))  $calcSS['eps']  = intval($validated['v_eps_manual']);
                        if (isset($validated['v_arl_manual']))  $calcSS['arl']  = intval($validated['v_arl_manual']);
                        if (isset($validated['v_afp_manual']))  $calcSS['afp']  = intval($validated['v_afp_manual']);
                        if (isset($validated['v_caja_manual'])) $calcSS['caja'] = intval($validated['v_caja_manual']);
                    } elseif (!empty($manualSsPorContrato[(string)$cId])) {
                        $ssMap = $manualSsPorContrato[(string)$cId];
                        if (isset($ssMap['eps']))  $calcSS['eps']  = intval($ssMap['eps']);
                        if (isset($ssMap['arl']))  $calcSS['arl']  = intval($ssMap['arl']);
                        if (isset($ssMap['afp']))  $calcSS['afp']  = intval($ssMap['afp']);
                        if (isset($ssMap['caja'])) $calcSS['caja'] = intval($ssMap['caja']);
                    }
                }

                $afiliacion = ($esAfiliacion || $esIndActPrimerMes) ? (int)($c->costo_afiliacion ?? 0) : 0;
                $seguro     = (int)($c->seguro ?? 0);
                $admon       = ($esAfiliacion && !$esIndActPrimerMes) ? 0 : intval($c->administracion ?? 0);
                $adminAsesor = ($esAfiliacion && !$esIndActPrimerMes) ? 0 : intval($c->admon_asesor   ?? 0);
                $otrosAdmon  = intval($validated['otros_admon'] ?? 0);

                $totalSS  = $calcSS['eps'] + $calcSS['arl'] + $calcSS['afp'] + $calcSS['caja'];
                $ivaBase  = $admon + $adminAsesor;
                $iva      = 0;

                if (!$esAfiliacion || $esIndActPrimerMes) {
                    if ($tieneIva) {
                        $cfgIva = \App\Models\ConfiguracionBrynex::porcentajeIva();
                        $iva    = (int) round($ivaBase * $cfgIva / 100);
                    }
                }

                $total = $totalSS + $admon + $adminAsesor + $otrosAdmon + $seguro + $afiliacion + $iva;

                $moraCliente = 0;
                if ($esModoIndividual) {
                    $moraCliente = $esAfiliacion ? 0 : (int)($validated['mora'] ?? 0);
                } else {
                    if (!$esAfiliacion) {
                        $rs       = $c->razonSocial;
                        $esIndep  = $c->esIndependiente() || ($rs && $rs->es_independiente);
                        $rsNit    = $esIndep ? (int)$c->cedula : ($rs ? (int)($rs->nit ?: $rs->id) : 0);
                        $rsDiaH   = $esIndep ? null : ($rs ? ($rs->dia_habil ?? null) : null);
                        if ($rsNit && $totalSS > 0) {
                            $moraInfo   = MoraClienteService::calcular($aliadoId, $rsNit, $rsDiaH, $totalSS, $mes, $anio);
                            $moraCliente = $moraInfo['mora'];
                        }
                    }
                }
                $total += $moraCliente;
            }
            $totalesRealesPorContrato[$cId] = max(0, $total);
        }
        $granTotalReal = array_sum($totalesRealesPorContrato) ?: 1; // evitar división por 0

        // ─── numero_factura compartido para el lote masivo ─────────────────
        // En modo masivo todos los contratos comparten el MISMO número de recibo.
        // En modo individual se genera uno por llamada, pero aquí pre-calculamos uno solo.
        $esMasivo = count($validated['contratos']) > 1;
        $batchNumeroFactura = Factura::siguienteNumero($aliadoId); // único para el lote

        $facturasCreadas  = [];
        $omitidos         = [];  // contratos ya facturados para ese período
        // Acumuladores para ajuste de redondeo post-loop en la última factura del batch.
        // Garantiza sum(ef_i) = ef_total exactamente, eliminando residuos de redondeo.
        $efAcum = $csAcum = $prAcum = $sfAcum = $antAcum = 0;
        // Saldo empresa a aplicar como credito en este batch (distribuido igualmente)
        $empresaId = $validated['empresa_id'] ?? null;
        $saldoEmpresaAplicar = 0;
        $contratosPendientes = count(array_filter($validated['contratos']));
        if ($empresaId && $esMasivo) {
            // ── Saldo neto REAL de la empresa (sin filtro de fecha) ──────────
            // Se debe sumar TODOS los saldo_proximo de empresa_id, incluyendo
            // los del mes actual ya facturados. Razón: al facturar un lote
            // parcial (ej. 9 de 14 contratos ya facturados en Mayo con sp=-87500),
            // esos negativos deben compensar el saldo positivo de Abril antes de
            // determinar si queda algún crédito real. Sin este criterio, el sistema
            // ve el saldo bruto de Abril (+700k) sin ver los -700k de Mayo ya
            // registrados, y aplica un descuento fantasma.
            $histSaldo = Factura::where('aliado_id', $aliadoId)
                ->where('empresa_id', $empresaId)
                ->whereIn('estado', ['pagada', 'prestamo', 'abono'])
                ->whereNull('deleted_at')
                ->sum('saldo_proximo');
            // Solo aplicar como crédito si el neto es estrictamente positivo
            $saldoEmpresaAplicar = max(0, (int)$histSaldo);
        }

        // ── Anti-duplicado: verificar por contrato_id (no por cédula+RS)
        // Permite contratos distintos aunque compartan la misma Razón Social.
        // Bloquea solo si el MISMO contrato ya tiene una factura del MISMO tipo en el período.
        // EXCEPCIÓN: contratos retirados con factura numero_factura=0 → se procesan con lógica de retiro facturable.
        $contratoIdsLote = $contratosCargados->pluck('id')->toArray();
        $facturasDuplicadasLote = Factura::where('aliado_id', $aliadoId)
            ->whereIn('contrato_id', $contratoIdsLote)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->whereNotIn('estado', ['anulada'])
            ->get(['id', 'contrato_id', 'tipo', 'estado', 'numero_factura'])
            ->groupBy('contrato_id');

        // ── Pre-cargar facturas de retiro con numero_factura=0 (para el flujo de retiro facturable) ──
        $facturasRetiro0Lote = Factura::withTrashed()
            ->where('aliado_id', $aliadoId)
            ->whereIn('contrato_id', $contratoIdsLote)
            ->where('numero_factura', 0)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('contrato_id');

        $incluirAdmonRetiroCorto = !empty($validated['incluir_admon_retiro_corto']);

        DB::transaction(function () use (
            $validated, $aliadoId, $np, $mes, $anio,
            $esMasivo,
            &$facturasCreadas, &$omitidos, &$nPlanosPorRS,
            $totalPagoConsig, $totalPagoEfectivo, $totalPagoPrestamo,
            $totalAnticipo, $anticiposSeleccionados,
            $consignacionesData, $totalesRealesPorContrato, $granTotalReal, $batchNumeroFactura,
            &$efAcum, &$csAcum, &$prAcum, &$sfAcum, &$antAcum,
            $saldoEmpresaAplicar, &$contratosPendientes, $contratosCargados, $facturasDuplicadasLote,
            $manualSsPorContrato, $facturasRetiro0Lote, $incluirAdmonRetiroCorto
        ) {
            foreach ($validated['contratos'] as $contratoId) {
                $contrato = $contratosCargados->get($contratoId);
                if (!$contrato) continue;

                // ─── Validación anti-duplicado por CONTRATO ───────────────
                // Bloquea si el MISMO contrato_id ya tiene una factura del MISMO tipo
                // en el mismo período (ej: 2 planillas del mismo contrato en junio).
                // Permite contratos distintos aunque compartan la misma Razón Social.
                $facturasDup    = $facturasDuplicadasLote->get($contrato->id) ?: collect();
                $tipoSolicitado = $validated['tipo'];

                // ─── Detectar modo "ambos" (afiliación + planilla) ────────
                $indepModo      = $validated['indep_modo'] ?? 'normal';
                $esIndVenc      = (int)($contrato->tipo_modalidad_id) === 10;
                $esIndActCheck  = (int)($contrato->tipo_modalidad_id) === 11;
                $esIndepCheck   = $contrato->tipoModalidad?->esIndependiente() ?? false;
                $esMesIngresoCheck = false;
                if ($contrato->fecha_ingreso) {
                    $esMesIngresoCheck = ((int)$contrato->fecha_ingreso->month === $mes
                                      && (int)$contrato->fecha_ingreso->year  === $anio);
                }

                $esAmbos = ($indepModo === 'ambos')
                    && $esIndepCheck
                    && ($esIndVenc || $esIndActCheck)
                    && $esMesIngresoCheck;

                if ($esAmbos) {
                    // ── Verificar que no existan ya afiliación NI planilla para este contrato ──
                    $yaAfil = $facturasDup->contains(fn($f) => $f->tipo === 'afiliacion');
                    if ($yaAfil) {
                        $omitidos[] = [
                            'cedula'  => $contrato->cedula,
                            'nombre'  => $contrato->cliente?->primer_nombre . ' ' . $contrato->cliente?->primer_apellido,
                            'motivo'  => "Ya existe una afiliación para {$mes}/{$anio} en este contrato",
                        ];
                        $contratosPendientes--;
                        continue;
                    }

                    // Para I Venc: verificar que no exista planilla en el mes siguiente
                    if ($esIndVenc) {
                        $mesPlan  = $mes === 12 ? 1 : $mes + 1;
                        $anioPlan = $mes === 12 ? $anio + 1 : $anio;
                        $yaPlana  = Factura::where('aliado_id', $aliadoId)
                            ->where('contrato_id', $contrato->id)
                            ->where('mes', $mesPlan)
                            ->where('anio', $anioPlan)
                            ->where('tipo', 'planilla')
                            ->whereNull('deleted_at')
                            ->exists();
                        if ($yaPlana) {
                            $omitidos[] = [
                                'cedula'  => $contrato->cedula,
                                'nombre'  => $contrato->cliente?->primer_nombre . ' ' . $contrato->cliente?->primer_apellido,
                                'motivo'  => "Ya existe una planilla para {$mesPlan}/{$anioPlan} en este contrato",
                            ];
                            $contratosPendientes--;
                            continue;
                        }
                    }

                    // ── Calcular proporción de pago para distribución ──────
                    $costoContrato = $totalesRealesPorContrato[$contratoId] ?? 0;
                    $proporcion    = $granTotalReal > 0 ? ($costoContrato / $granTotalReal) : 0;
                    $contratosPendientes--;
                    $esUltimoNoOmitido = ($contratosPendientes === 0);

                    if ($esUltimoNoOmitido) {
                        $vConsig     = $totalPagoConsig     - $csAcum;
                        $vEfectivo   = $totalPagoEfectivo   - $efAcum;
                        $vPrestamo   = $totalPagoPrestamo   - $prAcum;
                        $vSaldoFavor = $saldoEmpresaAplicar - $sfAcum;
                        $vAnticipo   = $totalAnticipo        - $antAcum;
                    } else {
                        $vConsig     = (int) round($totalPagoConsig     * $proporcion);
                        $vEfectivo   = (int) round($totalPagoEfectivo   * $proporcion);
                        $vPrestamo   = (int) round($totalPagoPrestamo   * $proporcion);
                        $vSaldoFavor = (int) round($saldoEmpresaAplicar * $proporcion);
                        $vAnticipo   = (int) round($totalAnticipo        * $proporcion);
                        $csAcum  += $vConsig;
                        $efAcum  += $vEfectivo;
                        $prAcum  += $vPrestamo;
                        $sfAcum  += $vSaldoFavor;
                        $antAcum += $vAnticipo;
                    }

                    // ── Crear par afiliación + planilla ───────────────────
                    $idsCreados = $this->_crearParAfilPlanilla(
                        contrato: $contrato,
                        validated: $validated,
                        mes: $mes,
                        anio: $anio,
                        aliadoId: $aliadoId,
                        batchNumeroFactura: $batchNumeroFactura,
                        np: $np,
                        nPlanosPorRS: $nPlanosPorRS,
                        vConsig: $vConsig,
                        vEfectivo: $vEfectivo,
                        vPrestamo: $vPrestamo,
                        vAnticipo: $vAnticipo,
                        esFirstOfBatch: empty($facturasCreadas),
                        consignacionesData: $consignacionesData,
                        esIndVenc: $esIndVenc,
                        manualSsPorContrato: $manualSsPorContrato,
                    );

                    // Si es un retiro y el par se creó con éxito, retirar contrato
                    $esRetiro    = !empty($validated['es_retiro']);
                    $fechaRetiro = $esRetiro ? ($validated['fecha_retiro'] ?? null) : null;
                    if ($esRetiro && $fechaRetiro && !empty($idsCreados)) {
                        $contrato->update([
                            'estado'       => 'retirado',
                            'fecha_retiro' => $fechaRetiro,
                        ]);

                        Bitacora::registrar(
                            accion: 'updated',
                            modelo: 'Contrato',
                            registroId: $contrato->id,
                            descripcion: "Contrato marcado como retirado con fecha {$fechaRetiro} por facturación de retiro en par Afil+Planilla (Facturas: " . implode(', ', $idsCreados) . ").",
                            detalle: [
                                'fecha_retiro' => $fechaRetiro,
                                'factura_ids'  => $idsCreados,
                            ],
                            alidoId: $aliadoId
                        );
                    }

                    array_push($facturasCreadas, ...$idsCreados);
                    continue; // saltar la lógica normal del foreach
                }

                // ─── Validación anti-duplicado (flujo normal) ─────────────
                // EXCEPCIÓN: contratos retirados con factura 0 pendiente no se bloquean aquí
                // — se procesan en el bloque de retiro facturable de más abajo.
                $tieneRetiroFacturable = $contrato->estado === 'retirado'
                    && $facturasRetiro0Lote->has($contrato->id);

                // ── FLUJO ESPECIAL: Retiro facturable (contrato retirado con factura 0) ──────────
                // El usuario seleccionó un contrato retirado desde la vista empresa.
                // La factura 0 (creada al marcar el retiro individual) se anula y se crea una real.
                if ($tieneRetiroFacturable) {
                    $facturaRetiroOrigen = $facturasRetiro0Lote->get($contrato->id);

                    // Calcular distribución de pago proporcional
                    $costoContrato = $totalesRealesPorContrato[$contratoId] ?? 0;
                    $proporcion    = $granTotalReal > 0 ? ($costoContrato / $granTotalReal) : 0;
                    $contratosPendientes--;
                    $esUltimoNoOmitido = ($contratosPendientes === 0);

                    if ($esUltimoNoOmitido) {
                        $vConsig     = $totalPagoConsig     - $csAcum;
                        $vEfectivo   = $totalPagoEfectivo   - $efAcum;
                        $vPrestamo   = $totalPagoPrestamo   - $prAcum;
                        $vSaldoFavor = $saldoEmpresaAplicar - $sfAcum;
                        $vAnticipo   = $totalAnticipo        - $antAcum;
                    } else {
                        $vConsig     = (int) round($totalPagoConsig     * $proporcion);
                        $vEfectivo   = (int) round($totalPagoEfectivo   * $proporcion);
                        $vPrestamo   = (int) round($totalPagoPrestamo   * $proporcion);
                        $vSaldoFavor = (int) round($saldoEmpresaAplicar * $proporcion);
                        $vAnticipo   = (int) round($totalAnticipo        * $proporcion);
                        $csAcum  += $vConsig;
                        $efAcum  += $vEfectivo;
                        $prAcum  += $vPrestamo;
                        $sfAcum  += $vSaldoFavor;
                        $antAcum += $vAnticipo;
                    }

                    // Tomar SS de la factura 0 (ya calculados al marcar el retiro)
                    $diasRetiroReal = (int)($facturaRetiroOrigen->dias_cotizados ?? 0);
                    $vEpsRet  = (int)($facturaRetiroOrigen->v_eps  ?? 0);
                    $vArlRet  = (int)($facturaRetiroOrigen->v_arl  ?? 0);
                    $vAfpRet  = (int)($facturaRetiroOrigen->v_afp  ?? 0);
                    $vCajaRet = (int)($facturaRetiroOrigen->v_caja ?? 0);
                    $totalSSRet = $vEpsRet + $vArlRet + $vAfpRet + $vCajaRet;

                    // Admon: solo se cobra si días > 3, o si el usuario marcó "incluir admon retiro corto"
                    $admonRetiro = 0;
                    $adminAsesorRetiro = 0;
                    if ($diasRetiroReal > 3 || $incluirAdmonRetiroCorto) {
                        $admonRetiro       = intval($contrato->administracion ?? 0);
                        $adminAsesorRetiro = intval($contrato->admon_asesor   ?? 0);
                    }

                    $totalRetiro = $totalSSRet + $admonRetiro + $adminAsesorRetiro;

                    // n_plano: reutilizar el del plano de retiro existente
                    $rsIdRet = $contrato->razon_social_id;
                    if ($rsIdRet && !isset($nPlanosPorRS[$rsIdRet])) {
                        $nPlanosPorRS[$rsIdRet] = static::_nPlanoParaRS($aliadoId, $rsIdRet, $mes, $anio);
                    }
                    $nPlanoRetiro = $rsIdRet ? ($nPlanosPorRS[$rsIdRet] ?? null) : null;

                    // Crear factura real del retiro
                    $facturaReal = Factura::create([
                        'aliado_id'               => $aliadoId,
                        'numero_factura'          => $batchNumeroFactura,
                        'tipo'                    => 'planilla',
                        'cedula'                  => $contrato->cedula,
                        'contrato_id'             => $contrato->id,
                        'empresa_id'              => $validated['empresa_id'] ?? null,
                        'mes'                     => $mes,
                        'anio'                    => $anio,
                        'fecha_pago'              => now()->toDateString(),
                        'estado'                  => $validated['estado'],
                        'es_prestamo'             => $validated['estado'] === 'prestamo',
                        'forma_pago'              => $validated['forma_pago'],
                        'valor_consignado'        => $vConsig,
                        'valor_efectivo'          => $vEfectivo,
                        'valor_prestamo'          => $vPrestamo,
                        'dias_cotizados'          => $diasRetiroReal,
                        'v_eps'                   => $vEpsRet,
                        'v_arl'                   => $vArlRet,
                        'v_afp'                   => $vAfpRet,
                        'v_caja'                  => $vCajaRet,
                        'total_ss'                => $totalSSRet,
                        'admon'                   => $admonRetiro,
                        'admin_asesor'            => $adminAsesorRetiro,
                        'seguro'                  => 0,
                        'afiliacion'              => 0,
                        'iva'                     => 0,
                        'mora'                    => 0,
                        'otros'                   => 0,
                        'otros_admon'             => 0,
                        'mensajeria'              => 0,
                        'total'                   => $totalRetiro,
                        'anticipo_aplicado'       => $vAnticipo,
                        'np'                      => $np,
                        'n_plano'                 => $nPlanoRetiro,
                        'razon_social_id'         => $contrato->razon_social_id,
                        'usuario_id'              => Auth::id(),
                        'observacion'             => $validated['observacion'] ?? null,
                        // Enlace con la factura 0 original para poder reactivarla si se anula esta
                        'factura_retiro_origen_id' => $facturaRetiroOrigen->id,
                    ]);

                    // saldo_proximo de la factura real del retiro
                    $pagadoRealRet = (int)$facturaReal->valor_consignado
                                  + (int)$facturaReal->valor_efectivo
                                  + (int)$facturaReal->anticipo_aplicado;
                    $facturaReal->update(['saldo_proximo' => $pagadoRealRet - $totalRetiro]);

                    // Soft-delete de la factura 0 (motivo: cobrada en la factura real)
                    $facturaRetiroOrigen->motivo_anulacion = "Cobrado en factura #{$batchNumeroFactura}";
                    $facturaRetiroOrigen->anulado_por      = Auth::id();
                    $facturaRetiroOrigen->save();
                    $facturaRetiroOrigen->delete(); // SoftDeletes → deleted_at

                    // Actualizar el plano de retiro: ahora apunta a la factura real
                    Plano::where('factura_id', $facturaRetiroOrigen->id)
                        ->whereNull('deleted_at')
                        ->update([
                            'factura_id'     => $facturaReal->id,
                            'numero_factura' => $batchNumeroFactura,
                            'n_plano'        => $nPlanoRetiro,
                        ]);

                    // Bitácora
                    Bitacora::registrar(
                        accion: 'updated',
                        modelo: 'Factura',
                        registroId: $facturaReal->id,
                        descripcion: "Retiro de {$contrato->cedula} cobrado en factura #{$batchNumeroFactura}. Factura origen (retiro) #{$facturaRetiroOrigen->id} anulada.",
                        detalle: [
                            'factura_retiro_origen_id' => $facturaRetiroOrigen->id,
                            'dias_retiro' => $diasRetiroReal,
                            'admon_cobrada' => $admonRetiro,
                        ],
                        alidoId: $aliadoId
                    );

                    // Guardar consignaciones (solo primera factura del lote)
                    if (empty($facturasCreadas)) {
                        foreach ($consignacionesData as $cs) {
                            $valorCs = (int)$cs['valor'];
                            if ($valorCs <= 0) continue;
                            \App\Models\Consignacion::create([
                                'aliado_id'       => $aliadoId,
                                'factura_id'      => $facturaReal->id,
                                'banco_cuenta_id' => (int) $cs['banco_cuenta_id'],
                                'fecha'           => $cs['fecha'] ?? now()->toDateString(),
                                'valor'           => $valorCs,
                                'referencia'      => $cs['referencia'] ?? null,
                                'confirmado'      => false,
                                'usuario_id'      => Auth::id(),
                            ]);
                        }
                    }

                    $facturasCreadas[] = $facturaReal->id;
                    continue; // saltar la lógica normal del foreach
                }

                $yaExiste = $facturasDup->contains(fn($f) => $f->tipo === $tipoSolicitado);

                if ($yaExiste) {
                    $omitidos[] = [
                        'cedula'  => $contrato->cedula,
                        'nombre'  => $contrato->cliente?->primer_nombre . ' ' . $contrato->cliente?->primer_apellido,
                        'motivo'  => "Ya existe una {$tipoSolicitado} para {$mes}/{$anio} en este contrato",
                    ];
                    $contratosPendientes--; // descontar aunque se omita
                    continue; // saltar este contrato
                }


                $esIndependiente = $contrato->tipoModalidad?->esIndependiente() ?? false;
                // I Act (id=11): cobra afiliación + planilla el mismo mes de ingreso
                // I Venc (id=10): solo afiliación el primer mes
                $esIndAct = (int)($contrato->tipo_modalidad_id) === 11;
                $tipoForzado = $validated['tipo'];

                if ($contrato->fecha_ingreso) {
                    $mesIngreso  = (int)$contrato->fecha_ingreso->month;
                    $anioIngreso = (int)$contrato->fecha_ingreso->year;
                    $esMesIngreso = ($mes === $mesIngreso && $anio === $anioIngreso);

                    if ($esMesIngreso) {
                        if (!$esIndependiente) {
                            // Empresa / dependiente: siempre afiliación en el mes de ingreso
                            $tipoForzado = 'afiliacion';
                        } elseif (!$esIndAct) {
                            // I Venc: solo afiliación el primer mes
                            $tipoForzado = 'afiliacion';
                        }
                        // I Act: tipo=planilla (afiliación se suma al total, ver abajo)
                    }
                }

                // ── Gestión ARL (id=15): SIEMPRE afiliación, nunca planilla ──
                if ((int)$contrato->tipo_modalidad_id === 15) {
                    $tipoForzado = 'afiliacion';
                }

                // ─── Detectar I Act primer mes ─────────────────────────────
                // I Act (id=11) en mes de ingreso: paga afiliación + planilla juntas
                $esIndActPrimerMes = $esIndAct && isset($esMesIngreso) && $esMesIngreso;

                // ─── Tipo, días y SS ───────────────────────────────────────
                $esAfiliacion = $tipoForzado === 'afiliacion';
                // Para I ACT primer mes: días = activos del mes de ingreso (no 0)
                if ($esIndActPrimerMes) {
                    $diasCotizar = max(1, 30 - (int)$contrato->fecha_ingreso->day + 1);
                } elseif ($esAfiliacion) {
                    $diasCotizar = 0;
                } else {
                    $diasCotizar = $this->calcularDias($contrato, $mes, $anio);
                }

                // SS = 0 en afiliación pura (I VENC, empresa);
                // Para I ACT primer mes se calcula con días reales del mes de ingreso.
                // Si hay retiro, sobreescribir los días con los del retiro.
                $esRetiro    = !empty($validated['es_retiro']);
                $fechaRetiro = $esRetiro ? ($validated['fecha_retiro'] ?? null) : null;
                $diasRetiro  = $esRetiro ? (int)($validated['dias_retiro'] ?? $diasCotizar) : null;

                if ($esRetiro && $diasRetiro !== null && !$esAfiliacion) {
                    // Retiro: usar los días proporcionales indicados por el usuario
                    $diasCotizar = $diasRetiro;
                }

                // ── Retiro Pendiente desde vista empresa ─────────────────────────────────
                // Si el contrato vigente tiene fecha_retiro_pendiente, se procesa como retiro:
                // los días cotizados son los del día del mes de esa fecha, se marca el contrato
                // como retirado al crear la factura, y se limpia el campo fecha_retiro_pendiente.
                $tieneRetiroPendiente = ($contrato->estado === 'vigente' && $contrato->fecha_retiro_pendiente !== null);
                if ($tieneRetiroPendiente && !$esRetiro && !$esAfiliacion) {
                    $esRetiro       = true;
                    $fechaRetiro    = $contrato->fecha_retiro_pendiente->toDateString();
                    $diasRetiro     = (int) $contrato->fecha_retiro_pendiente->day;
                    $diasCotizar    = $diasRetiro;
                    // La decisión de cobrar admon ya fue tomada por el aliado al registrar el retiro
                    $cobrarAdmonRetiroPendiente = (bool) ($contrato->retiro_pendiente_cobrar_admon ?? false);
                }


                // ── Fuente de verdad: calcularCotizacion() del modelo ──────────────────────
                // Usar el mismo método que la UI para que total facturado = estimación exacta.
                $tieneIva = $contrato->cliente ? (strtoupper(trim($contrato->cliente->iva ?? '')) === 'SI') : false;

                if ($esAfiliacion && !$esIndActPrimerMes) {
                    $calcSS = ['eps' => 0, 'arl' => 0, 'afp' => 0, 'caja' => 0];
                } else {
                    $cotizacion = $contrato->calcularCotizacion($diasCotizar, $tieneIva);
                    $calcSS = [
                        'eps'  => (int)($cotizacion['eps']  ?? 0),
                        'arl'  => (int)($cotizacion['arl']  ?? 0),
                        'afp'  => (int)($cotizacion['pen']  ?? 0),
                        'caja' => (int)($cotizacion['caja'] ?? 0),
                    ];
                }

                // Override manual de SS desde la UI:
                // 1) Modo individual (1 contrato): usa v_*_manual directamente.
                // 2) Multi-contrato desde form individual: usa manual_ss_por_contrato[id].
                //    Solo se aplica al contrato que tiene la entrada; el otro auto-calcula.
                // 3) Modo masivo empresa: sin override (totales del lote).
                $esModoIndividual = count($validated['contratos']) === 1;
                if (!$esAfiliacion) {
                    if ($esModoIndividual) {
                        // Modo individual clásico
                        if (isset($validated['v_eps_manual']))  $calcSS['eps']  = intval($validated['v_eps_manual']);
                        if (isset($validated['v_arl_manual']))  $calcSS['arl']  = intval($validated['v_arl_manual']);
                        if (isset($validated['v_afp_manual']))  $calcSS['afp']  = intval($validated['v_afp_manual']);
                        if (isset($validated['v_caja_manual'])) $calcSS['caja'] = intval($validated['v_caja_manual']);
                    } elseif (!empty($manualSsPorContrato[(string)$contratoId])) {
                        // Multi-contrato desde form individual: override específico por contrato
                        $ssMap = $manualSsPorContrato[(string)$contratoId];
                        if (isset($ssMap['eps']))  $calcSS['eps']  = intval($ssMap['eps']);
                        if (isset($ssMap['arl']))  $calcSS['arl']  = intval($ssMap['arl']);
                        if (isset($ssMap['afp']))  $calcSS['afp']  = intval($ssMap['afp']);
                        if (isset($ssMap['caja'])) $calcSS['caja'] = intval($ssMap['caja']);
                    }
                }


                // Afiliación:
                // • I ACT primer mes: se incluye SIEMPRE junto con SS (pago conjunto)
                // • Afiliación pura (I VENC, empresa): total = costo_afiliacion + seguro
                // • Planilla normal: no se incluye afiliación
                $afiliacion = ($esAfiliacion || $esIndActPrimerMes)
                    ? (int)($contrato->costo_afiliacion ?? 0)
                    : 0;
                $seguro     = (int)($contrato->seguro ?? 0);

                // Admon:
                // • Afiliación pura (I VENC, empresa): sin admon mensual
                // • I ACT primer mes y planilla normal: con admon completa
                // • Retiro Pendiente: usa la decisión guardada por el aliado (retiro_pendiente_cobrar_admon)
                if ($esAfiliacion && !$esIndActPrimerMes) {
                    $admon       = 0;
                    $adminAsesor = 0;
                } elseif (isset($tieneRetiroPendiente) && $tieneRetiroPendiente) {
                    // Usar la decisión del aliado al registrar el retiro pendiente
                    $admon       = (isset($cobrarAdmonRetiroPendiente) && $cobrarAdmonRetiroPendiente)
                        ? intval($contrato->administracion ?? 0) : 0;
                    $adminAsesor = (isset($cobrarAdmonRetiroPendiente) && $cobrarAdmonRetiroPendiente)
                        ? intval($contrato->admon_asesor   ?? 0) : 0;
                } else {
                    $admon       = intval($contrato->administracion ?? 0);
                    $adminAsesor = intval($contrato->admon_asesor   ?? 0);
                }
                $otrosAdmon  = intval($validated['otros_admon'] ?? 0);


                $totalSS  = $calcSS['eps'] + $calcSS['arl'] + $calcSS['afp'] + $calcSS['caja'];
                $ivaBase  = $admon + $adminAsesor;
                $iva      = 0;

                // IVA aplica en planilla (sobre admon) — también para I ACT primer mes
                // Usar round() igual que calcularCotizacion() del modelo (no ceil)
                if (!$esAfiliacion || $esIndActPrimerMes) {
                    if ($tieneIva) {
                        $cfgIva = \App\Models\ConfiguracionBrynex::porcentajeIva();
                        $iva    = (int) round($ivaBase * $cfgIva / 100);
                    }
                }

                // total = BRUTO (SS + admon + seguro + IVA + afiliacion + otros).
                // El anticipo (saldo_a_favor) y la deuda previa (saldo_pendiente) se guardan
                // en columnas separadas y el sistema acumulativo de saldo_proximo los maneja.
                $total = $totalSS + $admon + $adminAsesor + $otrosAdmon + $seguro + $afiliacion + $iva;

                // ─── Mora al cliente ────────────────────────────────────────
                // La mora viene del modal (pre-calculada + editable por el usuario).
                // En modo masivo: se distribuye SOLO en facturación individual (1 contrato);
                // en modo masivo NO dividimos la mora entre todos los clientes porque
                // cada RS tiene su propio vencimiento y su propio cálculo.
                // Por diseño: en masivo el frontend envía mora=0 (pendiente de impl. por RS).
                $moraCliente = 0;
                if ($esModoIndividual) {
                    // Modo individual: usar el valor del modal (puede ser 0 o el calculado)
                    // Si es afiliación pura, forzar mora=0 sin importar lo que envíe el frontend
                    $moraCliente = $esAfiliacion ? 0 : (int)($validated['mora'] ?? 0);
                } else {
                    // Modo masivo (empresa): calcular mora por contrato si aplica
                    // Afiliaciones nunca generan mora (no hay pago de planilla)
                    if (!$esAfiliacion) {
                        $rs       = $contrato->razonSocial;
                        $esIndep  = $contrato->esIndependiente() || ($rs && $rs->es_independiente);
                        $rsNit    = $esIndep ? (int)$contrato->cedula : ($rs ? (int)($rs->nit ?: $rs->id) : 0);
                        $rsDiaH   = $esIndep ? null : ($rs ? ($rs->dia_habil ?? null) : null);
                        if ($rsNit && $totalSS > 0) {
                            $moraInfo   = MoraClienteService::calcular($aliadoId, $rsNit, $rsDiaH, $totalSS, $mes, $anio);
                            $moraCliente = $moraInfo['mora']; // aplicar tramos automáticamente
                        }
                    }
                }

                $total += $moraCliente;

                // ─── Calcular distribución de afiliación ───────────────────
                $distAdmon = $distAsesor = $distRetiro = $distUtilidad = $distEncargado = 0;
                if ($esAfiliacion && $afiliacion > 0) {
                    // Si el frontend envió valores manuales, usarlos
                    $hasManual = isset($validated['dist_asesor']) || isset($validated['dist_retiro'])
                              || isset($validated['dist_encargado']) || isset($validated['dist_admon']);

                    if ($hasManual) {
                        $distAsesor   = (int)($validated['dist_asesor']    ?? 0);
                        $distRetiro   = (int)($validated['dist_retiro']    ?? 0);
                        $distEncargado = (int)($validated['dist_encargado'] ?? 0);
                        $distAdmonRaw  = (int)($validated['dist_admon']     ?? 0);
                        // dist_admon en la tabla = empresa admon puro
                        $distAdmon    = $distAdmonRaw;
                        
                        if ((int)$contrato->tipo_modalidad_id === 15) {
                            $distRetiro = 0;
                        }

                        // Recalcular utilidad = total - todos los demás
                        $distUtilidad = max(0, $afiliacion - $distAsesor - $distRetiro - $distEncargado - $distAdmon);
                    } else {
                        $cfg = \App\Models\ConfiguracionAliado::paraAliado($aliadoId, $contrato->plan_id);
                        if ($cfg) {
                            $dist         = $cfg->calcularDistribucion($afiliacion, $contrato->asesor ?? null);
                            $distAdmon    = $dist['admon'];
                            $distAsesor   = $dist['asesor'];
                            $distRetiro   = $dist['retiro'];
                            $distUtilidad = $dist['utilidad'];
                            
                            if ((int)$contrato->tipo_modalidad_id === 15) {
                                $distUtilidad += $distRetiro;
                                $distRetiro = 0;
                            }
                        }
                    }
                }

                // ─── n_plano compartido por RS en este lote ────────────────
                // Todos los contratos de la misma RS deben tener el mismo n_plano.
                // EXCEPCIÓN: Ingreso-Retiro (tipo_modalidad_id=12) con retiro usa n_plano=100
                // para separarlo de los planos normales y facilitar el control de pagos.
                // Si el IR es solo ingreso (sin retiro), usa el plano normal por RS.
                $rsId = $contrato->razon_social_id;
                if ((int)$contrato->tipo_modalidad_id === 12 && $esRetiro) {
                    $nPlanoFactura = 100; // IR retiro → plano 100
                } else {
                    if ($rsId && !isset($nPlanosPorRS[$rsId])) {
                        $nPlanosPorRS[$rsId] = static::_nPlanoParaRS($aliadoId, $rsId, $mes, $anio);
                    }
                    $nPlanoFactura = $rsId ? ($nPlanosPorRS[$rsId] ?? null) : null;
                }

                // --- Distribucion PROPORCIONAL entre contratos del batch ---
                // Ef, consignacion, saldo a favor y anticipo en proporción al costo real de la factura.
                // El último contrato recibe el residuo acumulado para que sumen exacto.
                $nContratos = max(1, count($validated['contratos']));
                $contratosPendientes--;
                $esUltimoNoOmitido = ($contratosPendientes === 0);
                $vSaldoFavor = 0; // Inicializar siempre (evita Undefined variable)

                $costoContrato = $totalesRealesPorContrato[$contratoId] ?? 0;
                $proporcion = $granTotalReal > 0 ? ($costoContrato / $granTotalReal) : 0;

                if ($esUltimoNoOmitido) {
                    // Ultimo: residuo exacto
                    $vConsig      = $totalPagoConsig     - $csAcum;
                    $vEfectivo    = $totalPagoEfectivo   - $efAcum;
                    $vPrestamo    = $totalPagoPrestamo   - $prAcum;
                    $vSaldoFavor  = $saldoEmpresaAplicar - $sfAcum;
                    $vAnticipo    = $totalAnticipo        - $antAcum;
                } else {
                    $vConsig      = (int) round($totalPagoConsig     * $proporcion);
                    $vEfectivo    = (int) round($totalPagoEfectivo   * $proporcion);
                    $vPrestamo    = (int) round($totalPagoPrestamo   * $proporcion);
                    $vSaldoFavor  = (int) round($saldoEmpresaAplicar * $proporcion);
                    $vAnticipo    = (int) round($totalAnticipo        * $proporcion);
                    
                    $csAcum   += $vConsig;
                    $efAcum   += $vEfectivo;
                    $prAcum   += $vPrestamo;
                    $sfAcum   += $vSaldoFavor;
                    $antAcum  += $vAnticipo;
                }

                $factura = Factura::create([
                    'aliado_id'        => $aliadoId,
                    'numero_factura'   => $batchNumeroFactura,
                    'tipo'             => $tipoForzado,
                    'cedula'           => $contrato->cedula,
                    'contrato_id'      => $contrato->id,
                    'mes'              => $mes,
                    'anio'             => $anio,
                    'fecha_pago'       => now()->toDateString(),
                    'estado'           => $validated['estado'],
                    'es_prestamo'      => $validated['estado'] === 'prestamo',
                    'forma_pago'       => $validated['forma_pago'],
                    'valor_consignado' => $vConsig,
                    'valor_efectivo'   => $vEfectivo,
                    'valor_prestamo'   => $vPrestamo,
                    'otros'            => (int)($validated['otros']      ?? 0),
                    'otros_admon'      => $otrosAdmon,
                    'mensajeria'       => (int)($validated['mensajeria'] ?? 0),
                    'dias_cotizados'   => $diasCotizar,
                    'v_eps'            => $calcSS['eps'],
                    'v_arl'            => $calcSS['arl'],
                    'v_afp'            => $calcSS['afp'],
                    'v_caja'           => $calcSS['caja'],
                    'total_ss'         => $totalSS,
                    'admon'            => $admon,
                    'admin_asesor'     => $adminAsesor,
                    'seguro'           => $seguro,
                    'afiliacion'       => $afiliacion,
                    'iva'              => $iva,
                    'total'            => max(0, $total),
                    'dist_admon'       => $distAdmon,
                    'dist_asesor'      => $distAsesor,
                    'dist_retiro'      => $distRetiro,
                    'dist_utilidad'    => $distUtilidad,
                    'dist_encargado'   => $distEncargado,
                    'np'               => $np,
                    'n_plano'          => $nPlanoFactura,
                    'empresa_id'        => $validated['empresa_id'] ?? null,
                    'razon_social_id'   => $contrato->razon_social_id,
                    'usuario_id'        => Auth::id(),
                    'observacion'       => $validated['observacion'] ?? null,
                    // Mora al cliente (no es ingreso — se reporta separado en SS)
                    'mora'              => $moraCliente,
                    // Anticipo: pagos previos registrados antes de la factura.
                    // Se guarda separado de valor_efectivo/consignado para evitar
                    // doble conteo en el cuadre diario (ese dinero ya se contabilizó
                    // en el mes en que se recibió el anticipo).
                    'anticipo_aplicado' => $vAnticipo,
                ]);

                // ─── Guardar consignaciones bancarias ──────────────────────
                // Las consignaciones representan comprobantes bancarios reales.
                // Se guardan UNA SOLA VEZ (en la primera factura del lote) con
                // el valor real. Las demás facturas del NP solo registran
                // valor_consignado proporcional en su campo, sin fila en consignaciones.
                if (empty($facturasCreadas)) {
                    // Esta es la PRIMERA factura del lote → guardar consignaciones reales
                    foreach ($consignacionesData as $cs) {
                        $valorCs = (int)$cs['valor'];
                        if ($valorCs <= 0) continue;
                        \App\Models\Consignacion::create([
                            'aliado_id'       => $aliadoId,
                            'factura_id'      => $factura->id,
                            'banco_cuenta_id' => (int) $cs['banco_cuenta_id'],
                            'fecha'           => $cs['fecha'] ?? now()->toDateString(),
                            'valor'           => $valorCs,
                            'referencia'      => $cs['referencia'] ?? null,
                            'confirmado'      => false,
                            'usuario_id'      => Auth::id(),
                        ]);
                    }
                }
                // Las facturas 2..N del lote NO crean filas en consignaciones;
                // su valor_consignado proporcional ya quedó en el campo de la factura.

                // ─── Calcular saldo_proximo ────────────────────────────────
                // saldo_proximo = valor_efectivo + valor_consignado - total_bruto
                //
                // El anticipo (saldo_a_favor) ya está acumulado en el historial de
                // facturas anteriores como saldo_proximo positivo.  Sumarlo aquí
                // generaría doble conteo → se usa SIEMPRE la misma fórmula base.
                //
                // Con SUM acumulativo (SUM saldo_proximo de empresa):
                //   Mes anterior pagó de más → sp = +X
                //   Este mes aplica anticipo, paga solo diferencia → sp = ef+cs-total
                //   Acumulado = +X + (ef+cs-total) → refleja correctamente el neto.
                //
                // PRÉSTAMO CON PAGO PARCIAL: si la persona consignó $587k sobre $587.8k,
                // el saldo real pendiente es solo $800, no el total bruto completo.
                // Se usa la misma fórmula (pagadoReal - total) en todos los casos.
                // pagadoReal = efectivo nuevo + consig nuevo + anticipo aplicado
                $pagadoReal = (int)$factura->valor_consignado
                            + (int)$factura->valor_efectivo
                            + (int)$factura->anticipo_aplicado;
                if ($factura->es_prestamo) {
                    // Préstamo: graba el saldo REAL pendiente (puede ser pago parcial o $0).
                    $saldoProximo = $pagadoReal - (int)$factura->total;
                } else {
                    if ($esMasivo && $saldoEmpresaAplicar > 0) {
                        $saldoProximo = -$vSaldoFavor;
                    } else {
                        $saldoProximo = $pagadoReal - (int)$factura->total;
                    }
                }
                $factura->update(['saldo_proximo' => $saldoProximo]);

                // Si es un retiro y la factura se generó en estado pagada/prestamo, retirar contrato
                if ($esRetiro && $fechaRetiro && in_array($factura->estado, [Factura::ESTADO_PAGADA, Factura::ESTADO_PRESTAMO])) {
                    $updateData = [
                        'estado'       => 'retirado',
                        'fecha_retiro' => $fechaRetiro,
                    ];
                    // Si era un retiro pendiente, limpiar los campos de pendiente
                    if (isset($tieneRetiroPendiente) && $tieneRetiroPendiente) {
                        $updateData['fecha_retiro_pendiente']        = null;
                        $updateData['retiro_pendiente_cobrar_admon'] = null;
                    }
                    $contrato->update($updateData);

                    Bitacora::registrar(
                        accion: 'updated',
                        modelo: 'Contrato',
                        registroId: $contrato->id,
                        descripcion: "Contrato marcado como retirado con fecha {$fechaRetiro} por facturación de retiro en factura #{$factura->id}.",
                        detalle: [
                            'fecha_retiro' => $fechaRetiro,
                            'factura_id'   => $factura->id,
                        ],
                        alidoId: $aliadoId
                    );
                }


                // Si está pagada o en préstamo, generar plano
                if (in_array($factura->estado, [Factura::ESTADO_PAGADA, Factura::ESTADO_PRESTAMO])) {
                    Plano::generarDesdeContrato($contrato, $factura, $fechaRetiro ?? null);
                }

                $facturasCreadas[] = $factura->id;
            }

            // ─── Marcar anticipos como aplicados (post-loop, fuera del foreach) ──
            // Se hace al final de la transacción para que todos los factura->id existan.
            // La primera factura del lote "absorbe" los anticipos.
            if ($anticiposSeleccionados->isNotEmpty() && !empty($facturasCreadas)) {
                $facturaAnticipo = $facturasCreadas[0]; // primera del lote
                $pendienteAplicar = $totalAnticipo;
                foreach ($anticiposSeleccionados as $ant) {
                    if ($pendienteAplicar <= 0) break;
                    $aplicado = $ant->aplicarAFactura($facturaAnticipo, $pendienteAplicar);
                    $pendienteAplicar -= $aplicado;
                }
            }
        });


        $primera = !empty($facturasCreadas) ? $facturasCreadas[0] : null;

        // Si no se creó ninguna (todos eran duplicados) → error
        if (empty($facturasCreadas) && !empty($omitidos)) {
            $nombres = collect($omitidos)->pluck('nombre')->join(', ');
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Ya existe factura para este período: ' . $nombres . '. Anule la factura existente antes de refacturar.',
                'omitidos'=> $omitidos,
            ], 422);
        }

        // ─── Liquidar cartera pendiente de préstamos anteriores ────────────────────
        // Se ejecuta FUERA de la transacción principal para que un error aqui
        // no revierta las facturas ya creadas. Tiene su propia transacción interna.
        if (!empty($validated['incluir_cartera']) && !empty($validated['valor_cartera']) && !empty($facturasCreadas)) {
            $this->_liquidarCartera($aliadoId, $validated, $facturasCreadas);
        }

        // Éxito con posibles omitidos parciales
        $msgOmit = !empty($omitidos)
            ? ' | ' . count($omitidos) . ' omitido(s) por duplicado.'
            : '';

        return response()->json([
            'ok'              => true,
            'mensaje'         => count($facturasCreadas) . ' factura(s) generada(s) correctamente.' . $msgOmit,
            'facturas'        => $facturasCreadas,
            'omitidos'        => $omitidos,
            'recibo_url'      => $primera ? route('admin.facturacion.recibo', $primera) : null,
            // Indica al frontend que se usó modo "ambos" (afil + planilla ya creadas)
            // para que NO reabra el modal automáticamente al siguiente mes.
            'indep_ambos'     => (($validated['indep_modo'] ?? 'normal') === 'ambos') && count($facturasCreadas) >= 2,
            // IDs de consignaciones creadas (solo las de la primera factura del batch)
            // El JS los usa para subir las imágenes de soporte después de crear la factura.
            'consignacion_ids' => \App\Models\Consignacion::whereIn('factura_id', $facturasCreadas)
                ->orderBy('id')
                ->pluck('id')
                ->values()
                ->all(),
        ]);
    }

    // ─── Liquidar cartera pendiente al facturar ────────────────────────────────
    /**
     * Cuando el usuario marca "Cartera pendiente" en el modal de facturación y confirma,
     * este método busca las facturas en estado=prestamo del cliente/empresa,
     * registra un Abono (con referencia a la nueva factura creada) y actualiza el estado.
     *
     * NO afecta el informe financiero: los ingresos se calculan de admon+seguro+mensajeria,
     * nunca de la tabla abonos. Sin riesgo de duplicación de ingresos.
     */
    private function _liquidarCartera(int $aliadoId, array $validated, array $facturasCreadas): void
    {
        $empresaId   = $validated['empresa_id'] ?? null;
        $valorCartera = (int)($validated['valor_cartera'] ?? 0);
        if ($valorCartera <= 0) return;

        // Buscar cédulas de los contratos facturados
        $cedulas = Contrato::whereIn('id', $validated['contratos'])->pluck('cedula');

        // Query base: facturas en estado=prestamo de este aliado
        $query = Factura::where('aliado_id', $aliadoId)
            ->where('estado', Factura::ESTADO_PRESTAMO)
            ->whereNull('deleted_at')
            ->with('abonos')
            ->orderBy('anio')->orderBy('mes'); // pagar primero los más antiguos

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        } else {
            $query->whereIn('cedula', $cedulas)->whereNull('empresa_id');
        }

        $facturasPrestamo = $query->get();
        if ($facturasPrestamo->isEmpty()) return;

        // Texto de referencia para el Abono
        $nuevaFactura = Factura::find($facturasCreadas[0] ?? null);
        $refNro  = $nuevaFactura
            ? str_pad($nuevaFactura->numero_factura, 6, '0', STR_PAD_LEFT)
            : '—';
        $meses   = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $mesNom  = $meses[((int)($validated['mes'])) - 1] ?? '';
        $obsText = "Cobrado con factura #{$refNro} — {$mesNom} {$validated['anio']}";

        $pendiente = $valorCartera;

        try {
            DB::transaction(function () use (
                $facturasPrestamo, $empresaId, &$pendiente,
                $validated, $obsText, $aliadoId
            ) {
                if ($empresaId) {
                    // Lote empresa: agrupar por numero_factura, pagar lote por lote
                    $lotes = $facturasPrestamo->groupBy('numero_factura');
                    foreach ($lotes as $lote) {
                        if ($pendiente <= 0) break;

                        // Saldo a nivel de lote: misma lógica que PrestamosController e InformeController.
                        // valor_prestamo = monto explícito del préstamo al facturar (fuente de verdad).
                        $abonosLote    = $lote->sum(fn($f) => (int)$f->abonos->sum('valor'));
                        $valorPrestamo = (int)$lote->sum('valor_prestamo');
                        $saldoLote     = $valorPrestamo > 0
                            ? max(0, $valorPrestamo - $abonosLote)
                            : max(0, abs((int)$lote->sum('saldo_proximo')) - $abonosLote);

                        if ($saldoLote <= 0) continue;

                        $abono = min($pendiente, $saldoLote);
                        $pendiente -= $abono;

                        // Registrar abono en la fila de referencia (primera del lote)
                        Abono::create([
                            'factura_id'       => $lote->first()->id,
                            'valor'            => $abono,
                            'forma_pago'       => $validated['forma_pago'] ?? 'efectivo',
                            'valor_efectivo'   => $abono,
                            'valor_consignado' => 0,
                            'observacion'      => $obsText,
                            'fecha'            => today()->toDateString(),
                            'usuario_id'       => Auth::id(),
                        ]);

                        // Actualizar estado de TODAS las filas del lote
                        $nuevoSaldo  = $saldoLote - $abono;
                        $estadoNuevo = $nuevoSaldo <= 0
                            ? Factura::ESTADO_PAGADA
                            : Factura::ESTADO_PRESTAMO;

                        foreach ($lote as $f) {
                            $f->update([
                                'estado'        => $estadoNuevo,
                                'saldo_proximo' => $estadoNuevo === Factura::ESTADO_PAGADA ? 0 : $f->saldo_proximo,
                            ]);
                        }
                    }
                } else {
                    // Individual: una factura por cédula, pagar de más antigua a más nueva
                    foreach ($facturasPrestamo as $fp) {
                        if ($pendiente <= 0) break;
                        $saldo = $fp->saldo_pendiente_prestamo;
                        if ($saldo <= 0) continue;

                        $abono = min($pendiente, $saldo);
                        $pendiente -= $abono;

                        Abono::create([
                            'factura_id'       => $fp->id,
                            'valor'            => $abono,
                            'forma_pago'       => $validated['forma_pago'] ?? 'efectivo',
                            'valor_efectivo'   => $abono,
                            'valor_consignado' => 0,
                            'observacion'      => $obsText,
                            'fecha'            => today()->toDateString(),
                            'usuario_id'       => Auth::id(),
                        ]);

                        $fp->refresh();
                        if ($fp->estaCompletamentePagada()) {
                            $fp->update([
                                'estado'        => Factura::ESTADO_PAGADA,
                                'saldo_proximo' => 0,
                            ]);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            // Si falla el cierre del préstamo, loguear pero NO revertir la factura ya creada.
            \Log::warning("[_liquidarCartera] No se pudo liquidar la cartera: " . $e->getMessage(), [
                'aliado_id'    => $aliadoId,
                'empresa_id'   => $empresaId,
                'valor_cartera'=> $valorCartera,
                'facturas'     => $facturasCreadas,
            ]);
        }
    }

    // ─── Crear par Afiliación + Planilla (independientes mes ingreso) ───────────
    /**
     * Crea 2 registros Factura con el mismo numero_factura:
     *   1) Afiliación  → mes=X, tipo=afiliacion, dias=0, SS=0
     *   2) Planilla    → mes=X (I Act) | mes=X+1 (I Venc), tipo=planilla, SS calculado
     *
     * Ambos registros comparten: numero_factura, np, estado, forma_pago, fecha_pago.
     * El pago (efectivo + consig) se divide proporcionalmente según el costo de cada uno.
     * Las consignaciones se guardan en la primera factura (afiliación), igual que el batch.
     *
     * @return array  [$idFactAfiliacion, $idFactPlanilla]
     */
    private function _crearParAfilPlanilla(
        \App\Models\Contrato $contrato,
        array $validated,
        int $mes,
        int $anio,
        int $aliadoId,
        int $batchNumeroFactura,
        ?int $np,
        array &$nPlanosPorRS,
        int $vConsig,
        int $vEfectivo,
        int $vPrestamo,
        int $vAnticipo,
        bool $esFirstOfBatch,
        array $consignacionesData,
        bool $esIndVenc,
        array $manualSsPorContrato = [],
    ): array {
        // ── Datos comunes ────────────────────────────────────────────────────────
        $tieneIva       = $contrato->cliente
            ? (strtoupper(trim($contrato->cliente->iva ?? '')) === 'SI')
            : false;
        $costoAfiliacion = (int)($contrato->costo_afiliacion ?? 0);
        $seguro          = (int)($contrato->seguro ?? 0);
        $admon           = (int)($contrato->administracion ?? 0);
        $adminAsesor     = (int)($contrato->admon_asesor ?? 0);

        // ── n_plano compartido por RS ────────────────────────────────────────────
        $rsId = $contrato->razon_social_id;
        if ($rsId && !isset($nPlanosPorRS[$rsId])) {
            $nPlanosPorRS[$rsId] = static::_nPlanoParaRS($aliadoId, $rsId, $mes, $anio);
        }
        $nPlanoFactura = $rsId ? ($nPlanosPorRS[$rsId] ?? null) : null;

        // ── Mes/Año de la planilla ───────────────────────────────────────────────
        // I Venc → mes siguiente (paga adelantado al ingresar)
        // I Act  → mismo mes
        if ($esIndVenc) {
            $mesPlan  = $mes === 12 ? 1  : $mes + 1;
            $anioPlan = $mes === 12 ? $anio + 1 : $anio;
        } else {
            $mesPlan  = $mes;
            $anioPlan = $anio;
        }

        // ── Días proporcionales para la planilla ─────────────────────────────────
        $diaIngreso = $contrato->fecha_ingreso ? (int)$contrato->fecha_ingreso->day : 1;
        $diasPlan   = max(1, 30 - $diaIngreso + 1);

        // ── Calcular SS de la planilla ───────────────────────────────────────────
        $cotiz = $contrato->calcularCotizacion($diasPlan, $tieneIva);
        $calcSS = [
            'eps'  => (int)($cotiz['eps']  ?? 0),
            'arl'  => (int)($cotiz['arl']  ?? 0),
            'afp'  => (int)($cotiz['pen']  ?? 0),
            'caja' => (int)($cotiz['caja'] ?? 0),
        ];

        // Override manual de SS (modo individual, 1 contrato)
        $esModoIndividual = count($validated['contratos']) === 1;
        if ($esModoIndividual) {
            if (isset($validated['v_eps_manual']))  $calcSS['eps']  = intval($validated['v_eps_manual']);
            if (isset($validated['v_arl_manual']))  $calcSS['arl']  = intval($validated['v_arl_manual']);
            if (isset($validated['v_afp_manual']))  $calcSS['afp']  = intval($validated['v_afp_manual']);
            if (isset($validated['v_caja_manual'])) $calcSS['caja'] = intval($validated['v_caja_manual']);
        } elseif (!empty($manualSsPorContrato[(string)$contrato->id])) {
            $ssMap = $manualSsPorContrato[(string)$contrato->id];
            if (isset($ssMap['eps']))  $calcSS['eps']  = intval($ssMap['eps']);
            if (isset($ssMap['arl']))  $calcSS['arl']  = intval($ssMap['arl']);
            if (isset($ssMap['afp']))  $calcSS['afp']  = intval($ssMap['afp']);
            if (isset($ssMap['caja'])) $calcSS['caja'] = intval($ssMap['caja']);
        }

        $totalSS = $calcSS['eps'] + $calcSS['arl'] + $calcSS['afp'] + $calcSS['caja'];

        // IVA de la planilla (sobre admon)
        $iva = 0;
        if ($tieneIva && ($admon + $adminAsesor) > 0) {
            $cfgIva = \App\Models\ConfiguracionBrynex::porcentajeIva();
            $iva    = (int) round(($admon + $adminAsesor) * $cfgIva / 100);
        }

        // ── Totales de cada registro ─────────────────────────────────────────────
        $totalAfil = $costoAfiliacion; // afiliación: solo costo_afiliacion (sin admon, sin SS)
        $totalPlan = $totalSS + $admon + $adminAsesor + $seguro + $iva
                   + (int)($validated['otros_admon'] ?? 0);

        $totalAmbos = max(1, $totalAfil + $totalPlan);

        // ── Distribución proporcional del pago entre los 2 registros ────────────
        $propAfil   = $totalAfil / $totalAmbos;
        $propPlan   = 1 - $propAfil;

        $vConsigAfil   = (int) round($vConsig   * $propAfil);
        $vEfectAfil    = (int) round($vEfectivo * $propAfil);
        $vPrestAfil    = (int) round($vPrestamo * $propAfil);
        $vAntAfil      = (int) round($vAnticipo * $propAfil);

        $vConsigPlan   = $vConsig   - $vConsigAfil;
        $vEfectPlan    = $vEfectivo - $vEfectAfil;
        $vPrestPlan    = $vPrestamo - $vPrestAfil;
        $vAntPlan      = $vAnticipo - $vAntAfil;

        // ── Distribución de afiliación ────────────────────────────────────────────
        $distAsesor = $distRetiro = $distEncargado = $distAdmon = $distUtilidad = 0;
        if ($costoAfiliacion > 0) {
            $hasManual = isset($validated['dist_asesor']) || isset($validated['dist_retiro'])
                      || isset($validated['dist_encargado']) || isset($validated['dist_admon']);
            if ($hasManual) {
                $distAsesor    = (int)($validated['dist_asesor']    ?? 0);
                $distRetiro    = (int)($validated['dist_retiro']    ?? 0);
                $distEncargado = (int)($validated['dist_encargado'] ?? 0);
                $distAdmon     = (int)($validated['dist_admon']     ?? 0);

                if ((int)$contrato->tipo_modalidad_id === 15) {
                    $distRetiro = 0;
                }

                $distUtilidad  = max(0, $costoAfiliacion - $distAsesor - $distRetiro - $distEncargado - $distAdmon);
            } else {
                $cfg = \App\Models\ConfiguracionAliado::paraAliado($aliadoId, $contrato->plan_id);
                if ($cfg) {
                    $dist          = $cfg->calcularDistribucion($costoAfiliacion, $contrato->asesor ?? null);
                    $distAdmon     = $dist['admon'];
                    $distAsesor    = $dist['asesor'];
                    $distRetiro    = $dist['retiro'];
                    $distUtilidad  = $dist['utilidad'];

                    if ((int)$contrato->tipo_modalidad_id === 15) {
                        $distUtilidad += $distRetiro;
                        $distRetiro = 0;
                    }
                }
            }
        }

        $estado    = $validated['estado'];
        $formaPago = $validated['forma_pago'];

        // ────────────────────────────────────────────────────────────────────────
        // REGISTRO 1: AFILIACIÓN
        // ────────────────────────────────────────────────────────────────────────
        $factAfil = Factura::create([
            'aliado_id'        => $aliadoId,
            'numero_factura'   => $batchNumeroFactura,
            'tipo'             => 'afiliacion',
            'cedula'           => $contrato->cedula,
            'contrato_id'      => $contrato->id,
            'mes'              => $mes,
            'anio'             => $anio,
            'fecha_pago'       => now()->toDateString(),
            'estado'           => $estado,
            'es_prestamo'      => $estado === 'prestamo',
            'forma_pago'       => $formaPago,
            'valor_consignado' => $vConsigAfil,
            'valor_efectivo'   => $vEfectAfil,
            'valor_prestamo'   => $vPrestAfil,
            'anticipo_aplicado'=> $vAntAfil,
            'otros'            => 0,
            'otros_admon'      => 0,
            'mensajeria'       => 0,
            'dias_cotizados'   => 0,
            'v_eps'            => 0,
            'v_arl'            => 0,
            'v_afp'            => 0,
            'v_caja'           => 0,
            'total_ss'         => 0,
            'admon'            => 0,
            'admin_asesor'     => 0,
            'seguro'           => 0,
            'afiliacion'       => $costoAfiliacion,
            'iva'              => 0,
            'mora'             => 0,
            'total'            => max(0, $totalAfil),
            'dist_admon'       => $distAdmon,
            'dist_asesor'      => $distAsesor,
            'dist_retiro'      => $distRetiro,
            'dist_utilidad'    => $distUtilidad,
            'dist_encargado'   => $distEncargado,
            'np'               => $np,
            'n_plano'          => $nPlanoFactura,
            'empresa_id'       => $validated['empresa_id'] ?? null,
            'razon_social_id'  => $contrato->razon_social_id,
            'usuario_id'       => \Illuminate\Support\Facades\Auth::id(),
            'observacion'      => $validated['observacion'] ?? null,
        ]);

        // Saldo próximo de la afiliación
        $pagadoAfil = $vConsigAfil + $vEfectAfil + $vAntAfil;
        $factAfil->update(['saldo_proximo' => $pagadoAfil - $totalAfil]);

        // Consignaciones: solo en la PRIMERA factura de todo el batch
        if ($esFirstOfBatch) {
            foreach ($consignacionesData as $cs) {
                $valorCs = (int)$cs['valor'];
                if ($valorCs <= 0) continue;
                \App\Models\Consignacion::create([
                    'aliado_id'       => $aliadoId,
                    'factura_id'      => $factAfil->id,
                    'banco_cuenta_id' => (int) $cs['banco_cuenta_id'],
                    'fecha'           => $cs['fecha'] ?? now()->toDateString(),
                    'valor'           => $valorCs,
                    'referencia'      => $cs['referencia'] ?? null,
                    'confirmado'      => false,
                    'usuario_id'      => \Illuminate\Support\Facades\Auth::id(),
                ]);
            }
        }

        // Plano de afiliación
        if (in_array($factAfil->estado, [Factura::ESTADO_PAGADA, Factura::ESTADO_PRESTAMO])) {
            Plano::generarDesdeContrato($contrato, $factAfil);
        }

        // ────────────────────────────────────────────────────────────────────────
        // REGISTRO 2: PLANILLA
        // ────────────────────────────────────────────────────────────────────────
        $factPlan = Factura::create([
            'aliado_id'        => $aliadoId,
            'numero_factura'   => $batchNumeroFactura,
            'tipo'             => 'planilla',
            'cedula'           => $contrato->cedula,
            'contrato_id'      => $contrato->id,
            'mes'              => $mesPlan,
            'anio'             => $anioPlan,
            'fecha_pago'       => now()->toDateString(),
            'estado'           => $estado,
            'es_prestamo'      => $estado === 'prestamo',
            'forma_pago'       => $formaPago,
            'valor_consignado' => $vConsigPlan,
            'valor_efectivo'   => $vEfectPlan,
            'valor_prestamo'   => $vPrestPlan,
            'anticipo_aplicado'=> $vAntPlan,
            'otros'            => (int)($validated['otros'] ?? 0),
            'otros_admon'      => (int)($validated['otros_admon'] ?? 0),
            'mensajeria'       => 0,
            'dias_cotizados'   => $diasPlan,
            'v_eps'            => $calcSS['eps'],
            'v_arl'            => $calcSS['arl'],
            'v_afp'            => $calcSS['afp'],
            'v_caja'           => $calcSS['caja'],
            'total_ss'         => $totalSS,
            'admon'            => $admon,
            'admin_asesor'     => $adminAsesor,
            'seguro'           => $seguro,
            'afiliacion'       => 0, // ya se cobró en el registro de afiliación
            'iva'              => $iva,
            'mora'             => 0,
            'total'            => max(0, $totalPlan),
            'dist_admon'       => 0,
            'dist_asesor'      => 0,
            'dist_retiro'      => 0,
            'dist_utilidad'    => 0,
            'dist_encargado'   => 0,
            'np'               => $np,
            'n_plano'          => $nPlanoFactura,
            'empresa_id'       => $validated['empresa_id'] ?? null,
            'razon_social_id'  => $contrato->razon_social_id,
            'usuario_id'       => \Illuminate\Support\Facades\Auth::id(),
            'observacion'      => $validated['observacion'] ?? null,
        ]);

        // Saldo próximo de la planilla
        $pagadoPlan = $vConsigPlan + $vEfectPlan + $vAntPlan;
        $factPlan->update(['saldo_proximo' => $pagadoPlan - $totalPlan]);

        // Plano de planilla
        if (in_array($factPlan->estado, [Factura::ESTADO_PAGADA, Factura::ESTADO_PRESTAMO])) {
            Plano::generarDesdeContrato($contrato, $factPlan);
        }

        return [$factAfil->id, $factPlan->id];
    }



    public function abonar(Request $request, int $facturaId)
    {
        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::where('aliado_id', $aliadoId)->findOrFail($facturaId);

        $validated = $request->validate([
            'valor'            => 'required|numeric|min:1',
            'forma_pago'       => 'required|in:efectivo,consignacion,mixto',
            'valor_efectivo'   => 'nullable|numeric|min:0',
            'valor_consignado' => 'nullable|numeric|min:0',
            'banco_cuenta_id'  => 'nullable|integer',
            'observacion'      => 'nullable|string|max:300',
        ]);

        $abono = DB::transaction(function () use ($factura, $validated) {
            $ab = Abono::create([
                ...$validated,
                'factura_id' => $factura->id,
                'fecha'      => now()->toDateString(),
                'usuario_id' => Auth::id(),
            ]);

            // ¿El total abonado cubre el total?
            $factura->refresh();
            if ($factura->estaCompletamentePagada()) {
                $factura->update(['estado' => Factura::ESTADO_PAGADA]);
                // Generar plano si no existe
                if (!$factura->plano) {
                    $c = $factura->contrato()->with('eps','arl','pension','caja','tipoModalidad')->first();
                    if ($c) Plano::generarDesdeContrato($c, $factura);
                }
            } else {
                $factura->update(['estado' => Factura::ESTADO_ABONO]);
            }

            return $ab;
        });

        return response()->json([
            'ok'             => true,
            'abono_id'       => $abono->id,
            'total_abonado'  => $factura->total_abonado,
            'saldo_restante' => $factura->saldo_restante,
            'estado'         => $factura->estado,
            'recibo_url'     => route('admin.facturacion.recibo-abono', $abono->id),
        ]);
    }

    // ─── API: Cotización de un contrato individual (para modal multi-contrato) ──
    /**
     * GET /admin/facturacion/api/cotizacion-contrato/{id}?mes=X&anio=Y
     * Devuelve los valores calculados de un contrato para el período dado.
     * Usado por el modal de facturación cuando el usuario selecciona un 2do contrato.
     */
    public function cotizacionContrato(Request $request, int $contratoId)
    {
        $aliadoId = session('aliado_id_activo');
        $mes  = (int) $request->get('mes',  now()->month);
        $anio = (int) $request->get('anio', now()->year);

        $contrato = Contrato::where('aliado_id', $aliadoId)
            ->with(['eps','arl','pension','caja','tipoModalidad','razonSocial','cliente'])
            ->find($contratoId);

        if (!$contrato) {
            return response()->json(['ok' => false, 'mensaje' => 'Contrato no encontrado.'], 404);
        }

        // Verificar si ya fue facturado para ese período
        $yaFacturado = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $contrato->cedula)
            ->where('razon_social_id', $contrato->razon_social_id)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->whereNotIn('estado', ['anulada'])
            ->exists();

        if ($yaFacturado) {
            return response()->json([
                'ok'           => false,
                'ya_facturado' => true,
                'mensaje'      => 'Este contrato ya fue facturado para ' . $mes . '/' . $anio . '.',
            ]);
        }

        // Detectar tipo
        $esIndependiente = $contrato->tipoModalidad?->esIndependiente() ?? false;
        $esIndAct        = (int)($contrato->tipo_modalidad_id) === 11;
        $esArl           = (int)($contrato->tipo_modalidad_id) === 15;
        $esMesIngreso    = false;

        if ($esArl) {
            // Gestión ARL siempre es cobro de afiliación, no planilla
            $esMesIngreso = true;
        } elseif ($contrato->fecha_ingreso) {
            $esMesIngreso = (int)$contrato->fecha_ingreso->month === $mes
                && (int)$contrato->fecha_ingreso->year  === $anio;
        }

        $esAfiliacion = false;
        if ($esMesIngreso) {
            if ($esArl) {
                $esAfiliacion = true;
            } elseif (!$esIndependiente) {
                $esAfiliacion = true; // empresa: afiliación pura
            } elseif (!$esIndAct) {
                $esAfiliacion = true; // I Venc: afiliación pura
            }
        }
        $esIndActPrimerMes = $esIndAct && $esMesIngreso;

        if ($esArl && !$esMesIngreso) {
            $diasCotizar = 0;
            $calcSS = ['eps' => 0, 'arl' => 0, 'afp' => 0, 'caja' => 0, 'ss' => 0];
            $afiliacion  = 0;
            $seguro      = 0;
            $admon       = 0;
            $admonAsesor = 0;
            $iva         = 0;
            $total       = 0;
        } else {
            // Calcular días
            if ($esIndActPrimerMes) {
                $diasCotizar = max(1, 30 - (int)$contrato->fecha_ingreso->day + 1);
            } elseif ($esAfiliacion) {
                $diasCotizar = 0;
            } else {
                $diasCotizar = $this->calcularDias($contrato, $mes, $anio);
            }

            // Calcular SS
            if ($esAfiliacion && !$esIndActPrimerMes) {
                $calcSS = ['eps' => 0, 'arl' => 0, 'afp' => 0, 'caja' => 0, 'ss' => 0];
            } else {
                $cotizacion = $contrato->calcularCotizacion($diasCotizar);
                $calcSS = [
                    'eps'  => (int)($cotizacion['eps']  ?? 0),
                    'arl'  => (int)($cotizacion['arl']  ?? 0),
                    'afp'  => (int)($cotizacion['pen']  ?? 0),
                    'caja' => (int)($cotizacion['caja'] ?? 0),
                    'ss'   => (int)($cotizacion['ss']   ?? 0),
                ];
            }

            $afiliacion  = ($esAfiliacion || $esIndActPrimerMes) ? (int)($contrato->costo_afiliacion ?? 0) : 0;
            $seguro      = (int)($contrato->seguro ?? 0);
            $admon       = ($esAfiliacion && !$esIndActPrimerMes) ? 0 : (int)($contrato->administracion ?? 0);
            $admonAsesor = ($esAfiliacion && !$esIndActPrimerMes) ? 0 : (int)($contrato->admon_asesor   ?? 0);

            // IVA
            $iva = 0;
            if (!$esAfiliacion || $esIndActPrimerMes) {
                $clienteIva = DB::table('clientes')->where('cedula', $contrato->cedula)->value('iva');
                if (strtoupper(trim($clienteIva ?? '')) === 'SI') {
                    $cfgIva = \App\Models\ConfiguracionBrynex::porcentajeIva();
                    $iva    = (int) round(($admon + $admonAsesor) * $cfgIva / 100);
                }
            }

            $total = $calcSS['ss'] + $admon + $admonAsesor + $seguro + $afiliacion + $iva;
        }

        // Mora estimada — las afiliaciones nunca generan mora (no hay pago de planilla)
        $mora = 0;
        if (!$esAfiliacion) {
            try {
                $rs    = $contrato->razonSocial;
                $esIndep = $contrato->esIndependiente() || ($rs && $rs->es_independiente);
                $rsNit = $esIndep ? (int)$contrato->cedula : ($rs ? (int)($rs->nit ?: $rs->id) : 0);
                $rsDia = $esIndep ? null : ($rs ? ($rs->dia_habil ?? null) : null);
                if ($rsNit && $calcSS['ss'] > 0) {
                    $moraInfo = MoraClienteService::calcular($aliadoId, $rsNit, $rsDia, $calcSS['ss'], $mes, $anio);
                    $mora = (int)($moraInfo['mora'] ?? 0);
                }
            } catch (\Throwable) {}
        }

        $tipo = $esAfiliacion ? 'afiliacion' : 'planilla';

        return response()->json([
            'ok'           => true,
            'ya_facturado' => false,
            'contrato_id'  => $contrato->id,
            'razon_social' => $contrato->razonSocial?->razon_social ?? '—',
            'tipo'         => $tipo,
            'eps'          => $calcSS['eps'],
            'arl'          => $calcSS['arl'],
            'afp'          => $calcSS['afp'],
            'caja'         => $calcSS['caja'],
            'ss'           => $calcSS['ss'],
            'admon'        => $admon + $admonAsesor,
            'seguro'       => $seguro,
            'afiliacion'   => $afiliacion,
            'iva'          => $iva,
            'mora'         => $mora,
            'total'        => $total + $mora,
            'dias'         => $diasCotizar,
        ]);
    }

    // ─── Recibo de factura ───────────────────────────────────────────
    public function recibo(int $facturaId)
    {
        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::where('aliado_id', $aliadoId)
            ->with(['contrato.cliente','contrato.eps','contrato.arl',
                    'contrato.pension','contrato.caja','contrato.razonSocial',
                    'razonSocial','usuario','abonos',
                    'consignaciones.bancoCuenta'])   // ← todas las cuentas consignadas
            ->findOrFail($facturaId);

        // Grupo del recibo: todas las facturas del mismo numero_factura dentro del aliado.
        // Se usa numero_factura (identificador único del lote) en lugar de np+mes+año+empresa_id
        // para evitar mezclar dos recibos distintos que casualmente comparten el mismo NP
        // (p.ej. dos lotes de "NP 2" facturados en fechas distintas del mismo mes).
        $grupoNp = null;
        if ($factura->numero_factura) {
            $grupoNp = Factura::where('aliado_id', $aliadoId)
                ->where('numero_factura', $factura->numero_factura)
                ->with(['contrato.cliente','contrato.eps','contrato.arl',
                        'contrato.pension','contrato.caja','contrato.razonSocial',
                        'abonos','consignaciones.bancoCuenta'])
                ->orderBy('id')
                ->get();
        }

        // Anticipos aplicados a esta factura (para el recibo/PDF)
        $anticiposAplicados = \App\Models\Anticipo::where('factura_id', $facturaId)
            ->with(['bancoCuenta', 'usuario'])
            ->orderBy('fecha_pago')
            ->get();

        return view('admin.facturacion.recibo',
            compact('factura','grupoNp','anticiposAplicados'));
    }

    // ─── Anular factura (solo admin) ─────────────────────────────────
    public function anular(Request $request, int $facturaId)
    {
        $user = Auth::user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('superadmin'))) {
            return response()->json(['ok' => false, 'message' => 'Sin permisos.'], 403);
        }

        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::where('aliado_id', $aliadoId)
            ->with(['contrato.cliente', 'abonos', 'plano'])
            ->findOrFail($facturaId);

        // ── Protección: factura con planilla pagada solo la puede anular superadmin BryNex ──
        $numeroPlanillaOp = $factura->plano?->numero_planilla;
        if ($numeroPlanillaOp) {
            $esSuperBrynex = $user->es_brynex && $user->hasRole('superadmin');
            if (!$esSuperBrynex) {
                return response()->json([
                    'ok'      => false,
                    'message' => "Esta factura tiene planilla pagada al operador (Nº {$numeroPlanillaOp}). "
                               . 'Solo un superadmin de BryNex puede anularla.',
                ], 403);
            }
        }

        $motivo = trim($request->input('motivo', ''));
        if (!$motivo) {
            return response()->json(['ok' => false, 'message' => 'Debe indicar el motivo de anulación.'], 422);
        }

        // Anula solo las facturas con el mismo numero_factura dentro del aliado.
        // NO filtra por mes/año ni por NP para no afectar lotes de otros períodos.
        $facturasAnular = collect([$factura]);
        if ($factura->numero_factura && $request->boolean('todo_np', false)) {
            $facturasAnular = Factura::where('aliado_id', $aliadoId)
                ->where('numero_factura', $factura->numero_factura)
                ->with(['abonos', 'plano'])
                ->get();
        }


        DB::transaction(function () use ($facturasAnular, $motivo, $aliadoId, $user) {
            foreach ($facturasAnular as $f) {
                // Registrar en bitácora ANTES de anular
                Bitacora::registrar(
                    accion: 'deleted',
                    modelo: 'Factura',
                    registroId: $f->id,
                    descripcion: "Factura #{$f->numero_factura} anulada. Motivo: {$motivo}",
                    detalle: [
                        'snapshot' => $f->toArray(),
                        'abonos'   => $f->abonos->toArray(),
                        'plano_id' => $f->plano?->id,
                        'motivo'   => $motivo,
                    ],
                    alidoId: $aliadoId
                );

                // Soft-delete de la factura (guarda motivo y quién anuló)
                $f->motivo_anulacion = $motivo;
                $f->anulado_por      = $user->id;
                $f->saldo_proximo    = 0; // limpiar para no influir en futuros cálculos
                $f->save();
                $f->delete(); // SoftDeletes → establece deleted_at

                // Soft-delete de TODOS los planos de esta factura.
                // IMPORTANTE: en lotes masivos hay N planos por factura_id (uno por contrato).
                // El hasOne solo eliminaría el primero, dejando los demás activos y causando
                // duplicados cuando se re-factura el mismo período tras una anulación.
                Plano::where('factura_id', $f->id)->each(fn($p) => $p->delete());

                // \u2500\u2500 Reversar retiro: dos casos seg\u00fan el tipo de factura de retiro \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
                // CASO 1: Factura de retiro facturado desde empresa (factura_retiro_origen_id != null)
                //   \u2192 Reactivar la factura 0 original, restaurar el plano, NO revertir el contrato
                // CASO 2: Factura de retiro original (numero_factura = 0 o plano con fecha_ret)
                //   \u2192 Revertir el contrato a vigente (comportamiento original)

                if ($f->factura_retiro_origen_id && $f->contrato_id) {
                    // CASO 1: Anular retiro facturado desde empresa
                    // Restaurar la factura 0 original (reactivar soft-delete)
                    $facturaOrigenRestore = Factura::withTrashed()
                        ->find($f->factura_retiro_origen_id);

                    if ($facturaOrigenRestore) {
                        $facturaOrigenRestore->restore(); // quitar deleted_at
                        $facturaOrigenRestore->update([
                            'motivo_anulacion' => null,
                            'anulado_por'      => null,
                        ]);

                        // Restaurar el plano al estado original (apuntar a factura 0)
                        Plano::where('factura_id', $f->id)
                            ->whereNull('deleted_at')
                            ->update([
                                'factura_id'     => $facturaOrigenRestore->id,
                                'numero_factura' => 0,
                            ]);

                        Bitacora::registrar(
                            accion: 'updated',
                            modelo: 'Factura',
                            registroId: $facturaOrigenRestore->id,
                            descripcion: "Factura de retiro (origen) #{$facturaOrigenRestore->id} reactivada por anulaci\u00f3n de factura real #{$f->id}. Contrato {$f->contrato_id} permanece retirado.",
                            detalle: [
                                'factura_anulada_id' => $f->id,
                                'motivo' => $motivo,
                            ],
                            alidoId: $aliadoId
                        );
                    }
                } elseif (((int)$f->numero_factura === 0 || ($f->plano && $f->plano->fecha_ret)) && $f->contrato_id) {
                    // CASO 2: Factura de retiro original (numero_factura=0) \u2192 revertir contrato a vigente
                    $contratoRetiro = \App\Models\Contrato::find($f->contrato_id);
                    if ($contratoRetiro && $contratoRetiro->estado === 'retirado') {
                        $contratoRetiro->update([
                            'estado'           => 'vigente',
                            'fecha_retiro'     => null,
                            'motivo_retiro_id' => null,
                        ]);

                        // Registrar reversi\u00f3n en bit\u00e1cora
                        Bitacora::registrar(
                            accion: 'updated',
                            modelo: 'Contrato',
                            registroId: $contratoRetiro->id,
                            descripcion: "Contrato revertido a vigente por anulaci\u00f3n de factura de retiro #{$f->id} (N\u00ba Factura: {$f->numero_factura}). Motivo: {$motivo}",
                            detalle: ['revertido_por_anulacion_factura_id' => $f->id],
                            alidoId: $aliadoId
                        );
                    }
                }

                // ── Restaurar anticipos aplicados a esta factura ──────────────────
                // Al anular, los anticipos que se aplicaron en el momento de facturar
                // deben quedar nuevamente disponibles para ser usados en una re-facturación.
                \App\Models\Anticipo::where('factura_id', $f->id)->each(function ($ant) use ($aliadoId, $f) {
                    Bitacora::registrar(
                        accion:      'updated',
                        modelo:      'Anticipo',
                        registroId:  $ant->id,
                        descripcion: "Anticipo #{$ant->id} revertido a disponible por anulación de factura #{$f->numero_factura}.",
                        detalle:     [
                            'valor_aplicado_revertido' => $ant->valor_aplicado,
                            'estado_anterior'          => $ant->estado,
                            'factura_id_anulada'       => $f->id,
                        ],
                        alidoId: $aliadoId
                    );

                    $ant->update([
                        'valor_aplicado' => 0,
                        'estado'         => \App\Models\Anticipo::ESTADO_DISPONIBLE,
                        'factura_id'     => null,
                    ]);
                });

                // Las consignaciones se eliminan físicamente (quedan en el snapshot de la bitácora)
                DB::table('consignaciones')->where('factura_id', $f->id)->delete();
                DB::table('abonos')->where('factura_id', $f->id)->delete();
            }
        });

        return response()->json([
            'ok'      => true,
            'mensaje' => $facturasAnular->count() > 1
                ? "{$facturasAnular->count()} facturas del recibo #{$factura->numero_factura} anuladas."
                : "Recibo #{$factura->numero_factura} anulado.",
        ]);
    }

    // ─── Listado de facturas anuladas ────────────────────────────────
    public function anuladas(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $q = Factura::onlyTrashed()
            ->where('aliado_id', $aliadoId)
            ->with(['contrato.cliente', 'razonSocial']);

        // Filtros opcionales
        if ($request->filled('cedula'))  $q->where('cedula', $request->cedula);
        if ($request->filled('mes'))     $q->where('mes',  $request->mes);
        if ($request->filled('anio'))    $q->where('anio', $request->anio);
        if ($request->filled('buscar')) {
            $b = $request->buscar;
            $q->where(fn($sq) => $sq->where('cedula','like',"%$b%")
                ->orWhere('numero_factura','like',"%$b%")
                ->orWhere('motivo_anulacion','like',"%$b%"));
        }

        $facturas = $q->orderByDesc('deleted_at')->paginate(25)->withQueryString();

        return view('admin.facturacion.anuladas', compact('facturas'));
    }

    // ─── Restaurar una factura anulada ───────────────────────────────
    public function restaurar(Request $request, int $facturaId)
    {
        $user = Auth::user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('superadmin'))) {
            return response()->json(['ok' => false, 'message' => 'Sin permisos.'], 403);
        }

        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::onlyTrashed()
            ->where('aliado_id', $aliadoId)
            ->findOrFail($facturaId);

        DB::transaction(function () use ($factura, $aliadoId, $user) {
            $factura->restore();
            $factura->motivo_anulacion = null;
            $factura->anulado_por      = null;
            $factura->save();

            // Restaurar el plano asociado si existe
            Plano::onlyTrashed()->where('factura_id', $factura->id)->restore();

            Bitacora::registrar(
                accion: 'updated',
                modelo: 'Factura',
                registroId: $factura->id,
                descripcion: 'Recibo #'.$factura->numero_factura.' restaurado por '.($user->nombre ?? $user->name).'.',
                detalle: ['restaurado_por' => $user->id],
                alidoId: $aliadoId
            );
        });

        return response()->json(['ok' => true, 'mensaje' => "Recibo #{$factura->numero_factura} restaurado correctamente."]);
    }


    // ─── Recibo de abono ─────────────────────────────────────────────
    public function reciboAbono(int $abonoId)
    {
        $aliadoId = session('aliado_id_activo');
        $abono    = Abono::whereHas('factura', fn($q) => $q->where('aliado_id', $aliadoId))
            ->with(['factura.contrato.cliente','usuario'])
            ->findOrFail($abonoId);

        return view('admin.facturacion.recibo-abono', compact('abono'));
    }

    // ─── API: Saldo previo de cliente ────────────────────────────────
    public function saldoCliente(Request $request, int $cedula)
    {
        $aliadoId = session('aliado_id_activo');
        $mes  = (int) $request->mes;
        $anio = (int) $request->anio;
        return response()->json(
            Factura::saldoClienteMesPrevio($aliadoId, $cedula, $mes, $anio)
        );
    }

    // ─── API: Verificar si mes ya está facturado para un contrato ────
    public function mesPagado(Request $request, int $contratoId)
    {
        $aliadoId = session('aliado_id_activo');
        $contrato = Contrato::where('aliado_id', $aliadoId)->find($contratoId);
        if (!$contrato) {
            return response()->json(['pagado' => false, 'mes' => null, 'anio' => null]);
        }

        $mes  = (int) ($request->mes  ?? now()->month);
        $anio = (int) ($request->anio ?? now()->year);

        // Verificar si ya existe factura pagada o pre-factura para este periodo
        $existe = Factura::where('aliado_id', $aliadoId)
            ->where('contrato_id', $contratoId)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->whereIn('estado', ['pagada', 'pre_factura', 'abono', 'prestamo'])
            ->exists();

        // Si ya está pagado, calcular el siguiente mes disponible
        $mesSiguiente  = $mes;
        $anioSiguiente = $anio;
        if ($existe) {
            $mesSiguiente++;
            if ($mesSiguiente > 12) { $mesSiguiente = 1; $anioSiguiente++; }
        }

        // Calcular saldo del cliente para el nuevo mes — SOLO del contrato actual
        $saldo = Factura::saldoClienteMesPrevio(
            $aliadoId,
            $contrato->cedula,
            $existe ? $mesSiguiente  : $mes,
            $existe ? $anioSiguiente : $anio,
            $contrato->id  // ← aislar por contrato, no mezclar con otros contratos de la misma cédula
        );

        // Verificar si hay meses anteriores sin facturar (orden secuencial)
        $gap = $this->verificarOrdenFacturacion(
            $aliadoId,
            $contrato,
            $existe ? $mesSiguiente  : $mes,
            $existe ? $anioSiguiente : $anio
        );

        // ── Préstamos pendientes del cliente ──────────────────────────
        // Retorna facturas en estado=prestamo con saldo restante > 0.
        // El JS del modal usa esto para ofrecer cobrar el préstamo junto a la nueva factura.
        $prestamosRaw = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $contrato->cedula)
            ->prestamoPendiente()
            ->with('abonos')
            ->get();

        $prestamosPendientes = $prestamosRaw
            ->filter(fn($f) => $f->saldo_pendiente_prestamo > 0)
            ->map(fn($f) => [
                'id'     => $f->id,
                'mes'    => $f->mes,
                'anio'   => $f->anio,
                'total'  => (int)$f->total,
                'saldo'  => $f->saldo_pendiente_prestamo,
            ])->values();

        $targetMes  = $existe ? $mesSiguiente  : $mes;
        $targetAnio = $existe ? $anioSiguiente : $anio;
        $diasSugeridos = $this->calcularDias($contrato, $targetMes, $targetAnio);

        return response()->json([
            'pagado'                   => $existe,
            'mes'                      => $targetMes,
            'anio'                     => $targetAnio,
            'dias_sugeridos'           => $diasSugeridos,
            'saldo_a_favor'            => $saldo['a_favor']   ?? 0,
            'saldo_pendiente'          => $saldo['pendiente'] ?? 0,
            // Información de gap para advertencia en UI
            'tiene_gap'                => !is_null($gap),
            'gap_mes'                  => $gap['mes']     ?? null,
            'gap_anio'                 => $gap['anio']    ?? null,
            'gap_mensaje'              => $gap['mensaje'] ?? null,
            // Préstamos pendientes del cliente
            'tiene_prestamo_pendiente' => $prestamosPendientes->isNotEmpty(),
            'prestamos_pendientes'     => $prestamosPendientes,
            // ── Mora pre-calculada para el modal ─────────────────────────
            // El JS la inyecta en el campo editable con MF.setMora()
            ...$this->_calcularMoraParaModal($aliadoId, $contrato, $targetMes, $targetAnio),
        ]);
    }


    // ─── API: N_PLANO actual de una razón social ─────────────────────
    public function planoActual(Request $request, int $razonSocialId)
    {
        $aliadoId = session('aliado_id_activo');
        $rs = DB::table('razones_sociales')->find($razonSocialId);
        $actual = DB::table('planos')
            ->where('aliado_id', $aliadoId)
            ->max('n_plano') ?? 0;

        return response()->json([
            'n_plano_actual'    => $actual,
            'n_plano_siguiente' => $actual + 1,
            'razon_social'      => $rs?->razon_social ?? '',
        ]);
    }

    // ─── Helpers privados ────────────────────────────────────────────

    /**
     * Verifica que no exista un "gap" (mes sin facturar) antes del período solicitado.
     *
     * Regla: el primer mes facturable es el mes de fecha_ingreso.
     * Cada mes siguiente debe tener al menos una factura registrada antes de permitir
     * facturar el mes actual.
     *
     * @return array|null  null si todo OK; ['mes','anio','mensaje'] si hay gap.
     */
    private function verificarOrdenFacturacion(int $aliadoId, Contrato $contrato, int $mes, int $anio): ?array
    {
        // Convertir a entero YYYYMM para comparación simple
        $periodoTarget = $anio * 100 + $mes;

        // Obtener todos los períodos (YYYYMM) que ya tienen factura para este contrato
        $periodosBilled = Factura::where('aliado_id', $aliadoId)
            ->where('contrato_id', $contrato->id)
            ->whereIn('estado', ['pagada', 'pre_factura', 'abono', 'prestamo'])
            ->get(['mes', 'anio'])
            ->map(fn($f) => (int)$f->anio * 100 + (int)$f->mes)
            ->unique()
            ->sort()
            ->values();

        // Si no hay ninguna factura previa, permitir facturar libremente
        if ($periodosBilled->isEmpty()) {
            return null;
        }

        // El período máximo ya facturado
        $ultimoPeriodo = $periodosBilled->max();

        // Si el período solicitado ya existe o es el siguiente natural, OK
        if ($periodosBilled->contains($periodoTarget)) {
            return null; // ya facturado (anti-duplicado lo manejará después)
        }

        // Calcular el "siguiente esperado" al último facturado
        $ultimoMes  = $ultimoPeriodo % 100;
        $ultimoAnio = (int)($ultimoPeriodo / 100);
        $sigMes     = $ultimoMes  === 12 ? 1  : $ultimoMes  + 1;
        $sigAnio    = $ultimoMes  === 12 ? $ultimoAnio + 1 : $ultimoAnio;
        $siguientePeriodo = $sigAnio * 100 + $sigMes;

        // Si el target ES el siguiente esperado, está perfecto
        if ($periodoTarget === $siguientePeriodo) {
            return null;
        }

        // Si el target es MENOR al último, puede ser retro-facturación de un hueco puntual — permitir
        if ($periodoTarget < $ultimoPeriodo) {
            return null;
        }

        // Hay un salto: el período solicitado está más de 1 mes adelante del último facturado
        // Exigir que se facture el mes inmediatamente siguiente al último
        $nombreFaltante = \Carbon\Carbon::create($sigAnio, $sigMes, 1)->translatedFormat('F Y');
        $nombreTarget   = \Carbon\Carbon::create($anio, $mes, 1)->translatedFormat('F Y');

        return [
            'mes'     => $sigMes,
            'anio'    => $sigAnio,
            'mensaje' => "Debe facturar {$nombreFaltante} antes de continuar con {$nombreTarget}.",
        ];
    }


    /**
     * Calcula el n_plano para una razón social en un período dado.
     *
     * Regla de negocio:
     * - Si el período facturado (mes/anio) coincide con el mes activo de la RS
     *   (rs.mes_pagos / rs.anio_pagos), se usa rs.n_plano (ej: P3).
     * - Para cualquier otro mes —futuro (junio, julio…) o pasado (abril)—
     *   siempre retorna 1: las facturas se acumulan en el primer lote del período
     *   hasta que ese mes se convierta en el mes activo y el aliado avance el contador.
     */
    private static function _nPlanoParaRS(int $aliadoId, ?int $razonSocialId, int $mes, int $anio): int
    {
        if (!$razonSocialId) {
            return 1;
        }

        $rs = \App\Models\RazonSocial::find($razonSocialId);
        if (!$rs) {
            return 1;
        }

        // Solo el mes activo de la RS usa su n_plano actual.
        // Cualquier otro mes (pasado o futuro) siempre empieza en 1.
        if ((int)$rs->mes_pagos === $mes && (int)$rs->anio_pagos === $anio) {
            return (int)($rs->n_plano ?? 1);
        }

        return 1;
    }

    /**
     * Pre-calcula la mora al cliente para inyectarla en el modal individual.
     * Se llama desde mesPagado() y los datos se incluyen en la respuesta JSON.
     *
     * El JS usa MF.setMora(mora, infoTexto) para rellenar el campo editable.
     *
     * @return array{mora_cliente: int, mora_dias: int, mora_fecha_vence: string|null, mora_dia_habil: int, mora_info: string}
     */
    private function _calcularMoraParaModal(int $aliadoId, Contrato $contrato, int $mes, int $anio): array
    {
        try {
            $rs      = $contrato->razonSocial;
            $esIndependiente = $contrato->esIndependiente() || ($rs && $rs->es_independiente);
            $rsNit   = $esIndependiente ? (int)$contrato->cedula : ($rs ? (int)($rs->nit ?: $rs->id) : 0);
            $rsDiaH  = $esIndependiente ? null : ($rs ? ($rs->dia_habil ?? null) : null);

            if (!$rsNit) {
                return ['mora_cliente' => 0, 'mora_dias' => 0, 'mora_fecha_vence' => null, 'mora_dia_habil' => 0, 'mora_info' => ''];
            }

            // Detectar si el contrato es afiliación para este período
            $esIndependiente = $contrato->tipoModalidad?->esIndependiente() ?? false;
            $esIndAct        = (int)($contrato->tipo_modalidad_id) === 11;
            $esArlModalidad  = (int)($contrato->tipo_modalidad_id) === 15;
            $esMesIngreso    = $contrato->fecha_ingreso
                && (int)$contrato->fecha_ingreso->month === $mes
                && (int)$contrato->fecha_ingreso->year  === $anio;
            $esAfiliacion    = $esArlModalidad
                || ($esMesIngreso && !$esIndependiente)
                || ($esMesIngreso && $esIndependiente && !$esIndAct);

            // Las afiliaciones nunca tienen mora (no hay pago de planilla)
            if ($esAfiliacion) {
                return ['mora_cliente' => 0, 'mora_real' => 0, 'mora_dias' => 0, 'mora_fecha_vence' => null, 'mora_dia_habil' => 0, 'mora_info' => '✅ Afiliación — sin mora', 'mora_aplica' => false];
            }

            // Usar la última factura de este contrato para obtener el total_ss real
            // Si aún no hay factura (estamos creando la primera), calcular desde cotización
            $ultimaFact = Factura::where('aliado_id', $aliadoId)
                ->where('contrato_id', $contrato->id)
                ->where('mes', $mes)->where('anio', $anio)
                ->whereNotIn('estado', ['anulada'])
                ->first();

            if ($ultimaFact) {
                $totalSS = (float) $ultimaFact->total_ss;
            } else {
                // Estimar total SS desde cotización (30 días)
                $cotiz   = $contrato->calcularCotizacion(30);
                $totalSS = (float)($cotiz['ss'] ?? 0);
            }

            if ($totalSS <= 0) {
                return ['mora_cliente' => 0, 'mora_dias' => 0, 'mora_fecha_vence' => null, 'mora_dia_habil' => 0, 'mora_info' => ''];
            }

            $info = MoraClienteService::calcular($aliadoId, $rsNit, $rsDiaH, $totalSS, $mes, $anio);

            $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            $vence = $info['fecha_vence'] ? $info['fecha_vence']->format('d') . ' ' . ($meses[$info['fecha_vence']->month] ?? '') . ' ' . $info['fecha_vence']->year : null;
            $infoTexto = $info['aplica']
                ? "⚠️ {$info['dias_mora']} días mora · día hábil {$info['dia_habil']} · vence {$vence}"
                : "✅ Sin mora hasta día hábil {$info['dia_habil']}" . ($vence ? " ($vence)" : '');

            return [
                'mora_cliente'      => $info['mora'],
                'mora_real'         => (int) round($info['mora_real'] ?? 0),  // sin tramos: solo para Retiro
                'mora_dias'         => $info['dias_mora'],
                'mora_fecha_vence'  => $info['fecha_vence'] ? $info['fecha_vence']->toDateString() : null,
                'mora_dia_habil'    => $info['dia_habil'],
                'mora_info'         => $infoTexto,
                'mora_aplica'       => $info['aplica'],
            ];
        } catch (\Throwable $e) {
            // No interrumpir la carga del modal por error en mora
            return ['mora_cliente' => 0, 'mora_real' => 0, 'mora_dias' => 0, 'mora_fecha_vence' => null, 'mora_dia_habil' => 0, 'mora_info' => '', 'mora_aplica' => false];
        }
    }

    private function calcularDias(Contrato $contrato, int $mes, int $anio): int
    {
        // ── GESTIÓN ARL (id=15): SIEMPRE días=0, nunca planilla ─────────────
        // Los contratos ARL se facturan como afiliación todos los meses.
        // No hay SS que cotizar, por ende días_cotizados = 0 siempre.
        if ((int)$contrato->tipo_modalidad_id === 15) {
            return 0;
        }

        // Tiempo Parcial: se devuelve 30 porque ARL cotiza mensual completo.
        // AFP y CAJA usan sus propios días; ver calcularSS().
        $mod = $contrato->tipoModalidad;
        if ($mod && $mod->esTiempoParcial()) {
            return 30;
        }

        if (!$contrato->fecha_ingreso) return 30;
        $fIng        = $contrato->fecha_ingreso;
        $mesIngreso  = (int)$fIng->month;
        $anioIngreso = (int)$fIng->year;

        // ① Mes de ingreso → afiliación, no hay días de planilla
        if ($mesIngreso === $mes && $anioIngreso === $anio) {
            return 0;
        }

        // ② Mes siguiente al ingreso → primera planilla: días activos del mes de ingreso
        $mesAnterior  = $mes === 1 ? 12 : $mes - 1;
        $anioAnterior = $mes === 1 ? $anio - 1 : $anio;
        if ($mesIngreso === $mesAnterior && $anioIngreso === $anioAnterior) {
            // EXCEPCIÓN 1: Independiente Activo (11) ya cobró su planilla en el mes de ingreso.
            if ((int)$contrato->tipo_modalidad_id === 11) {
                return 30;
            }
            // EXCEPCIÓN 2: Si ya existe una factura de tipo 'planilla' pagada para el mes de ingreso.
            $existePlanillaIngreso = Factura::where('aliado_id', $contrato->aliado_id)
                ->where('contrato_id', $contrato->id)
                ->where('mes', $mesIngreso)
                ->where('anio', $anioIngreso)
                ->where('tipo', 'planilla')
                ->whereIn('estado', ['pagada', 'pre_factura', 'abono', 'prestamo'])
                ->exists();
            if ($existePlanillaIngreso) {
                return 30;
            }

            return max(1, 30 - $fIng->day + 1);
        }

        // ③ Mes normal
        return 30;
    }

    private function calcularSS(Contrato $contrato, int $dias): array
    {
        $aliadoId = session('aliado_id_activo');
        $ibc      = (int)($contrato->ibc ?? $contrato->salario ?? 0);
        $nArl     = (int)($contrato->n_arl ?? 1);
        $plan     = $contrato->plan;
        $mod      = $contrato->tipoModalidad;
        $esTP     = $mod && $mod->esTiempoParcial();
        $esIndep  = $contrato->esIndependiente(); // detectar modalidad real

        // CRÍTICO: usar porcentajes según modalidad.
        // Antes usaba siempre dependiente → SS incorrecto para I ACT/I VENC.
        // Eso causaba mismatch entre granTotal y sum(totales_reales) en ~$75k.
        if ($esIndep) {
            $pctEps = \App\Models\ConfiguracionBrynex::pctSaludIndependiente();
            $pctPen = \App\Models\ConfiguracionBrynex::pctPensionIndependiente();
            $pctCaj = (float)($contrato->porcentaje_caja
                       ?? \App\Models\ConfiguracionBrynex::pctCajaIndependienteAlto());
        } else {
            $pctEps = \App\Models\ConfiguracionBrynex::pctSaludDependiente();
            $pctPen = \App\Models\ConfiguracionBrynex::pctPensionDependiente();
            $pctCaj = \App\Models\ConfiguracionBrynex::pctCajaDependiente();
        }
        $pctArl = \App\Models\ArlTarifa::porcentajePara($nArl, $aliadoId);

        $r = fn($v) => (int)(ceil($v / 100) * 100);

        if ($esTP) {
            // Tiempo Parcial: IBC diferente por entidad
            // ARL  = SM_completo × tasaArl
            // AFP  = SM × factor_afp × pctPen
            // CAJA = SM × factor_caja × pctCaja
            $diasP      = $mod->diasPorEntidad();
            $factorMap  = [7 => 0.25, 14 => 0.50, 21 => 0.75, 30 => 1.00];
            $factorAfp  = $factorMap[$diasP['afp']]  ?? 1.0;
            $factorCaja = $factorMap[$diasP['caja']] ?? 1.0;

            $sm      = (float) \App\Models\ConfiguracionBrynex::obtener('salario_minimo', 1423500);
            $ibcArl  = $sm;
            $ibcAfp  = round($sm * $factorAfp);
            $ibcCaja = round($sm * $factorCaja);

            return [
                'eps'  => 0,
                'arl'  => ($plan?->incluye_arl)     ? $r($ibcArl  * $pctArl / 100) : 0,
                'afp'  => ($plan?->incluye_pension) ? $r($ibcAfp  * $pctPen / 100) : 0,
                'caja' => ($plan?->incluye_caja)    ? $r($ibcCaja * $pctCaj / 100) : 0,
            ];
        }

        // Normal: mes completo → round() igual que calcularCotizacion() del modelo.
        // El saldo_proximo en batches empresa se fija directamente a -credit_i,
        // por lo que el balance de empresa es correcto con round() o ceil().
        // Usar round() hace que total almacenado = estimación UI → recibo exacto.
        $epsMes  = ($plan?->incluye_eps)     ? (int) round($ibc * $pctEps / 100) : 0;
        $arlMes  = ($plan?->incluye_arl)     ? (int) round($ibc * $pctArl / 100) : 0;
        $afpMes  = ($plan?->incluye_pension) ? (int) round($ibc * $pctPen / 100) : 0;
        $cajaMes = ($plan?->incluye_caja)    ? (int) round($ibc * $pctCaj / 100) : 0;

        if ($dias < 30) {
            // EPS: ceil al centena superior; ARL/AFP/CAJA: round al centena más cercano.
            // Mismo criterio que Contrato::calcularCotizacion() (fuente de verdad).
            $rRound = fn($v) => (int)(round($v / 100) * 100);
            return [
                'eps'  => $r($epsMes       * $dias / 30),
                'arl'  => $rRound($arlMes  * $dias / 30),
                'afp'  => $rRound($afpMes  * $dias / 30),
                'caja' => $rRound($cajaMes * $dias / 30),
            ];
        }

        // ── Cargo sin-CCF: dependiente E o Ingreso-Retiro sin caja ───────
        // Se cobra $100 fijos a la caja cuando el plan no incluye CCF.
        // Solo aplica en planilla (dias > 0, garantizado porque dias=30 aqui).
        if ($cajaMes === 0 && $contrato->aplicaCargoSinCcf()) {
            $cajaMes = \App\Models\Contrato::CARGO_SIN_CCF;
        }

        return ['eps' => $epsMes, 'arl' => $arlMes, 'afp' => $afpMes, 'caja' => $cajaMes];
    }

    // ─── API: Saldos para N contratos (modo masivo empresa) ─────────
    // GET /admin/facturacion/api/saldos-contratos?contratos[]=1&contratos[]=2&mes=5&anio=2026
    public function saldosContratos(Request $request)
    {
        $aliadoId  = session('aliado_id_activo');
        $contratoIds = array_filter((array)$request->contratos);
        $mes  = (int)($request->mes  ?? now()->month);
        $anio = (int)($request->anio ?? now()->year);

        // ── Obtener empresa_id desde los contratos seleccionados ───────
        // Todos los contratos del modal masivo pertenecen a la misma empresa.
        // Calculamos el saldo neto de la empresa (SUM saldo_proximo de TODOS los
        // contratos de la empresa, no solo los seleccionados) para que el
        // anticipo de un trabajador compense la cartera de otro.
        $empresaId = null;
        if (!empty($contratoIds)) {
            $primerContrato = Contrato::where('aliado_id', $aliadoId)
                ->whereIn('id', $contratoIds)
                ->with('cliente')
                ->first();
            if ($primerContrato) {
                // Buscar empresa_id desde clientes.cod_empresa
                $empresaId = DB::table('clientes')
                    ->where('cedula', $primerContrato->cedula)
                    ->value('cod_empresa');
            }
        }

        // ── Saldo neto REAL de la empresa ────────────────────────────────
        // Se suma TODOS los saldo_proximo de empresa_id sin restricción de fecha.
        // Razón: al facturar un lote parcial (ej. 5 de 14 contratos en Mayo),
        // los 9 ya facturados de Mayo tienen saldo_proximo negativo que DEBEN
        // compensar el saldo positivo de Abril. Si filtramos por mes < actual,
        // excluimos esos negativos de Mayo y el saldo queda inflado.
        //
        // Lógica:
        //   saldoNeto > 0 → empresa tiene anticipo a favor
        //   saldoNeto < 0 → empresa tiene cartera pendiente
        //   saldoNeto = 0 → empresa está al día (caso Fabio Arroyave)
        $saldoNeto = 0;
        if ($empresaId) {
            $saldoNeto = (int) Factura::where('aliado_id', $aliadoId)
                ->where('empresa_id', $empresaId)
                ->whereIn('estado', ['pagada', 'prestamo', 'abono'])
                ->whereNotNull('saldo_proximo')
                ->whereNull('deleted_at')
                ->sum('saldo_proximo');
        } else {
            // Fallback: sumar por contratos individuales si no hay empresa_id
            foreach ($contratoIds as $cId) {
                $contrato = Contrato::where('aliado_id', $aliadoId)->find($cId);
                if (!$contrato) continue;
                $saldo = Factura::saldoClienteMesPrevio($aliadoId, $contrato->cedula, $mes, $anio, $cId);
                $saldoNeto += ($saldo['a_favor'] ?? 0) - ($saldo['pendiente'] ?? 0);
            }
        }

        // Convertir saldo neto a a_favor / pendiente para compatibilidad con el JS
        $totalAFavor    = $saldoNeto > 0 ? $saldoNeto : 0;
        $totalPendiente = $saldoNeto < 0 ? abs($saldoNeto) : 0;

        return response()->json([
            'total_a_favor'   => $totalAFavor,
            'total_pendiente' => $totalPendiente,
            'saldo_neto'      => $saldoNeto,   // neto para debugging
        ]);
    }

    // ─── Historial de pagos del cliente ─────────────────────────────
    public function historial(Request $request, int $cedula)
    {
        $aliadoId = session('aliado_id_activo');

        // Buscar el cliente directamente por cédula (no depende de contratos)
        $cliente = \App\Models\Cliente::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->first();

        // Si no existe el cliente en este aliado, abortar
        abort_if(!$cliente, 404, 'Cliente no encontrado.');

        // Traemos el contrato de referencia (solo para contexto visual del header)
        $contrato = Contrato::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->whereIn('estado', ['vigente', 'activo'])
            ->with(['cliente', 'razonSocial'])
            ->orderByDesc('created_at')
            ->first();

        // Si no hay contrato vigente, buscar cualquiera
        if (!$contrato) {
            $contrato = Contrato::where('aliado_id', $aliadoId)
                ->where('cedula', $cedula)
                ->with(['cliente', 'razonSocial'])
                ->orderByDesc('created_at')
                ->first();
        }

        // Filtros opcionales
        $filtroAnio = $request->integer('anio', 0);
        $filtroRs   = $request->get('razon_social_id', '');

        // Query principal con eager loading optimizado para contrato y razón social
        $query = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->with(['contrato.razonSocial', 'razonSocial', 'empresa', 'plano.razonSocial', 'usuario'])
            ->orderByDesc('anio')
            ->orderByDesc('mes');

        if ($filtroAnio > 0) {
            $query->where('anio', $filtroAnio);
        }
        if ($filtroRs !== '') {
            $query->where('razon_social_id', $filtroRs);
        }

        // Sin filtros activos: últimas 20
        $sinFiltros = !$filtroAnio && $filtroRs === '';
        if ($sinFiltros) {
            $facturas = $query->limit(20)->get();
        } else {
            $facturas = $query->get();
        }

        // Agrupar: [contrato_label → [anio → [facturas]]]
        $agrupado = [];
        foreach ($facturas as $f) {
            if ($f->contrato_id) {
                $rsName = $f->contrato?->razonSocial?->razon_social
                       ?? $f->razonSocial?->razon_social
                       ?? $f->plano?->razonSocial?->razon_social
                       ?? 'Sin razón social';
                $grupoKey = "Contrato #{$f->contrato_id} — {$rsName}";
            } else {
                $grupoKey = "Sin contrato";
            }
            $anio = $f->anio;
            $agrupado[$grupoKey][$anio][] = $f;
        }

        // Mapa razon_social_name → contrato_ids (todos los contratos del cliente, agrupados por RS)
        $todosContratos = Contrato::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->with('razonSocial')
            ->orderBy('id')
            ->get();

        $contratosporRS = []; // ["Nombre RS" => [id1, id2, ...]]
        foreach ($todosContratos as $c) {
            $rsNombre = $c->razonSocial?->razon_social ?? 'Sin razón social';
            $contratosporRS[$rsNombre][] = $c->id;
        }

        // Para filtros: años y razones sociales disponibles del cliente
        $aniosDisp = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->select('anio')->distinct()->orderByDesc('anio')
            ->pluck('anio');

        $rsSocDisp = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->with('razonSocial')
            ->select('razon_social_id')->distinct()
            ->get()
            ->map(fn($f) => [
                'id'    => $f->razon_social_id,
                'label' => $f->razonSocial?->razon_social ?? 'Sin razón social',
            ])->unique('id');

        $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                       'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        // ─── Comprobantes de pago planilla (batch, una sola query) ───────────
        // Para cada factura con plano.numero_planilla, buscamos el gasto pago_planilla
        // correspondiente y obtenemos su imagen (soporte del pago al operador).
        $numeroPlanillas = $facturas
            ->map(fn($f) => $f->plano?->numero_planilla)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $soportesPlanilla = collect();
        $operadoresPlanillaInfo = collect();
        $gastosPlanilla = collect();
        if (!empty($numeroPlanillas)) {
            $gastosPlanilla = \App\Models\Gasto::where('aliado_id', $aliadoId)
                ->where('tipo', 'pago_planilla')
                ->whereIn('numero_planilla', $numeroPlanillas)
                ->get(['numero_planilla', 'imagen_path', 'descripcion', 'pagado_a'])
                ->keyBy('numero_planilla');

            // Filtrar los que tienen soporte con imagen
            $soportesPlanilla = $gastosPlanilla->filter(fn($g) => !empty($g->imagen_path));

            // Cargar operadores (ID y Nombre) desde la API de planillas
            $operadoresPlanillaInfo = \DB::table('operador_planillas_api')
                ->where('operador_planillas_api.aliado_id', $aliadoId)
                ->whereIn('operador_planillas_api.numero_planilla', $numeroPlanillas)
                ->join('operadores_planilla', 'operadores_planilla.id', '=', 'operador_planillas_api.operador_planilla_id')
                ->select('operadores_planilla.id', 'operadores_planilla.nombre', 'operador_planillas_api.numero_planilla')
                ->get()
                ->keyBy('numero_planilla');
        }

        $operadoresTodosMap = \DB::table('operadores_planilla')->pluck('id', 'nombre');

        return view('admin.facturacion.historial', compact(
            'cliente', 'contrato', 'cedula', 'agrupado',
            'filtroAnio', 'filtroRs', 'sinFiltros',
            'aniosDisp', 'rsSocDisp', 'meses', 'contratosporRS',
            'soportesPlanilla', 'operadoresPlanillaInfo', 'gastosPlanilla',
            'operadoresTodosMap'
        ));
    }


    // ─── Imagen de consignación ────────────────────────────────────────
    /**
     * POST /admin/facturacion/consignacion/{id}/imagen
     * Sube la imagen/PDF de soporte de una consignación y guarda la ruta.
     */
    public function subirImagenConsignacion(Request $request, int $id)
    {
        $aliadoId = session('aliado_id_activo');

        $consig = \App\Models\Consignacion::where('id', $id)
            ->where('aliado_id', $aliadoId)
            ->firstOrFail();

        $request->validate([
            'imagen' => 'required|file|mimes:jpg,jpeg,png,pdf,webp|max:8192', // 8 MB
        ]);

        // Eliminar imagen anterior si existe
        if ($consig->imagen_path && \Storage::disk('public')->exists($consig->imagen_path)) {
            \Storage::disk('public')->delete($consig->imagen_path);
        }

        $file = $request->file('imagen');
        $ext  = $file->getClientOriginalExtension();
        $path = $file->storeAs(
            "consignaciones/{$aliadoId}/{$consig->factura_id}",
            "{$id}.{$ext}",
            'public'
        );

        $consig->update(['imagen_path' => $path]);

        return response()->json([
            'ok'  => true,
            'url' => \Storage::url($path),
        ]);
    }

    /**
     * GET /admin/facturacion/consignacion/{id}/imagen
     * Redirige a la URL pública de la imagen de soporte.
     */
    public function verImagenConsignacion(int $id)
    {
        $aliadoId = session('aliado_id_activo');

        $consig = \App\Models\Consignacion::where('id', $id)
            ->where('aliado_id', $aliadoId)
            ->firstOrFail();

        if (!$consig->imagen_path || !\Storage::disk('public')->exists($consig->imagen_path)) {
            abort(404, 'Imagen no encontrada.');
        }

        return redirect(\Storage::url($consig->imagen_path));
    }

    // ─── Facturar Otro Ingreso (Trámite) ─────────────────────────────
    /**
     * POST /admin/facturacion/otro-ingreso
     * Crea una factura de tipo 'otro_ingreso' (trámites, servicios adicionales).
     * NO genera plano PILA. IVA aplica si cliente OR empresa tiene iva='SI'.
     * Asesor se toma del campo asesor del cliente o de la empresa.
     */
    public function facturarOtroIngreso(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'cedula'              => 'required|integer',
            'descripcion_tramite' => 'required|string|max:300',
            'mes'                 => 'required|integer|min:1|max:12',
            'anio'                => 'required|integer|min:2000|max:2100',
            'valor_admon'         => 'required|numeric|min:0',
            'valor_asesor'        => 'nullable|numeric|min:0',
            'forma_pago'          => 'required|in:efectivo,consignacion,mixto,prestamo',
            'estado'              => 'required|in:pre_factura,pagada,prestamo',
            'valor_efectivo'      => 'nullable|numeric|min:0',
            'valor_prestamo'      => 'nullable|numeric|min:0',
            'consignaciones'                   => 'nullable|array',
            'consignaciones.*.banco_cuenta_id' => 'required_with:consignaciones|integer',
            'consignaciones.*.valor'           => 'required_with:consignaciones|numeric|min:0',
            'consignaciones.*.fecha'           => 'nullable|date',
            'consignaciones.*.referencia'      => 'nullable|string|max:100',
            'empresa_id'          => 'nullable|integer',
            'observacion'         => 'nullable|string|max:500',
        ]);

        $cedula  = (int)$validated['cedula'];
        $mes     = (int)$validated['mes'];
        $anio    = (int)$validated['anio'];

        // ── IVA: aplica si cliente OR empresa tienen iva='SI' ─────────
        $clienteIva  = DB::table('clientes')->where('cedula', $cedula)->value('iva');
        $empresaId   = $validated['empresa_id'] ?? null;
        $empresaIva  = $empresaId
            ? DB::table('empresas')->where('id', $empresaId)->value('iva')
            : null;

        $aplicaIva = strtoupper(trim($clienteIva ?? '')) === 'SI'
                  || strtoupper(trim($empresaIva ?? '')) === 'SI';

        $valorAdmon  = (int)($validated['valor_admon']  ?? 0);
        $valorAsesor = (int)($validated['valor_asesor'] ?? 0);
        $ivaBase     = $valorAdmon + $valorAsesor;

        $iva = 0;
        if ($aplicaIva && $ivaBase > 0) {
            $cfgIva = \App\Models\ConfiguracionBrynex::porcentajeIva();
            $iva    = (int) ceil($ivaBase * $cfgIva / 100 / 100) * 100;
        }

        $total = $valorAdmon + $valorAsesor + $iva;

        // ── Pagos ──────────────────────────────────────────────────────
        $consignacionesData = $validated['consignaciones'] ?? [];
        $totalConsig  = array_sum(array_column($consignacionesData, 'valor'));
        $totalEfectivo = (int)($validated['valor_efectivo'] ?? 0);
        $totalPrestamo = (int)($validated['valor_prestamo'] ?? 0);

        // ── Saldo previo del cliente ────────────────────────────────────
        $saldo = Factura::saldoClienteMesPrevio($aliadoId, $cedula, $mes, $anio);

        $factura = DB::transaction(function () use (
            $aliadoId, $cedula, $mes, $anio, $validated,
            $valorAdmon, $valorAsesor, $iva, $total,
            $totalConsig, $totalEfectivo, $totalPrestamo,
            $consignacionesData, $saldo, $empresaId
        ) {
            $numeroFactura = Factura::siguienteNumero($aliadoId);

            $factura = Factura::create([
                'aliado_id'           => $aliadoId,
                'numero_factura'      => $numeroFactura,
                'tipo'                => Factura::TIPO_OTRO_INGRESO,
                'cedula'              => $cedula,
                'contrato_id'         => null,
                'empresa_id'          => $empresaId,
                'mes'                 => $mes,
                'anio'                => $anio,
                'fecha_pago'          => now()->toDateString(),
                'estado'              => $validated['estado'],
                'es_prestamo'         => $validated['estado'] === 'prestamo',
                'forma_pago'          => $validated['forma_pago'],
                'valor_consignado'    => (int)$totalConsig,
                'valor_efectivo'      => $totalEfectivo,
                'valor_prestamo'      => $totalPrestamo,
                // SS = 0 (no es planilla)
                'dias_cotizados'      => 0,
                'v_eps'               => 0,
                'v_arl'               => 0,
                'v_afp'               => 0,
                'v_caja'              => 0,
                'total_ss'            => 0,
                // Valores administrativos
                'admon'               => $valorAdmon,
                'admin_asesor'        => 0,     // planilla: no aplica
                'admon_asesor_oi'     => $valorAsesor,
                'iva'                 => $iva,
                'total'               => max(0, $total),
                'seguro'              => 0,
                'afiliacion'          => 0,
                'mensajeria'          => 0,
                'otros'               => 0,
                // Descripción del trámite
                'descripcion_tramite' => $validated['descripcion_tramite'],
                'observacion'         => $validated['observacion'] ?? null,
                'usuario_id'          => Auth::id(),
            ]);

            // ── saldo_proximo ──────────────────────────────────────────
            // Siempre: pagadoReal - total. Negativo = saldo pendiente.
            // Para préstamos con pago parcial esto refleja el saldo real
            // adeudado (no el total bruto completo).
            $pagadoReal   = (int)$factura->valor_consignado + (int)$factura->valor_efectivo;
            $saldoProximo = $pagadoReal - (int)$factura->total;
            $factura->update(['saldo_proximo' => $saldoProximo]);

            // ── Consignaciones ─────────────────────────────────────────
            foreach ($consignacionesData as $cs) {
                $valorCs = (int)$cs['valor'];
                if ($valorCs <= 0) continue;
                \App\Models\Consignacion::create([
                    'aliado_id'       => $aliadoId,
                    'factura_id'      => $factura->id,
                    'banco_cuenta_id' => (int)$cs['banco_cuenta_id'],
                    'fecha'           => $cs['fecha'] ?? now()->toDateString(),
                    'valor'           => $valorCs,
                    'referencia'      => $cs['referencia'] ?? null,
                    'confirmado'      => false,
                    'usuario_id'      => Auth::id(),
                ]);
            }

            // ── NO se genera plano PILA ────────────────────────────────
            return $factura;
        });

        return response()->json([
            'ok'              => true,
            'mensaje'         => 'Otro ingreso registrado correctamente. Recibo #' . $factura->numero_factura,
            'factura_id'      => $factura->id,
            'recibo_url'      => route('admin.facturacion.recibo', $factura->id),
            'consignacion_ids' => \App\Models\Consignacion::where('factura_id', $factura->id)
                ->orderBy('id')->pluck('id')->values()->all(),
        ]);
    }
    // ─── Historial de facturación de una empresa ────────────────────
    public function historialEmpresa(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        $facturas = Factura::where('aliado_id', $aliadoId)
            ->where('empresa_id', $empresaId)
            ->with(['usuario', 'consignaciones.bancoCuenta'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->get();

        // Agrupar por numero_factura (cada NP puede tener varios trabajadores con el mismo número)
        $grupos = $facturas->groupBy('numero_factura')->map(function ($grupo) {
            $primera = $grupo->first();

            // Saldo generado por este NP → positivo = sobró (va al siguiente mes),
            // negativo = consumió saldo previo, cero = equilibrado.
            $saldoProximoTotal = $grupo->sum(fn($f) => (int)($f->saldo_proximo ?? 0));

            return (object)[
                'id'                  => $primera->id,
                'np'                  => $primera->np,
                'tipo'                => $primera->tipo,
                'numero_factura'      => $primera->numero_factura,
                'fecha_pago'          => $primera->fecha_pago,
                'mes'                 => $primera->mes,
                'anio'                => $primera->anio,
                'estado'              => $primera->estado,
                'descripcion_tramite' => $primera->descripcion_tramite,
                'total'               => $grupo->sum(fn($f) => (int)$f->total),
                'cantidad'            => $grupo->count(),
                'usuario'             => $primera->usuario,
                // ── Saldo ──────────────────────────────────────────────────
                // saldo_proximo > 0 → generó anticipo para el siguiente mes
                // saldo_proximo < 0 → consumió saldo que venía de meses anteriores
                // saldo_proximo = 0 → equilibrado
                'saldo_proximo'       => $saldoProximoTotal,
                'saldo_a_favor_aplicado' => 0, // columna eliminada — ya no disponible
            ];
        })->values();

        return view('admin.facturacion.historial_empresa', compact('empresa', 'grupos', 'facturas'));
    }

    // ─── Crear empresa ──────────────────────────────────────────────
    public function createEmpresa()
    {
        $aliadoId = session('aliado_id_activo');
        $asesores = \App\Models\Asesor::where('aliado_id', $aliadoId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('admin.facturacion.empresa_create', compact('asesores'));
    }

    // ─── Guardar empresa nueva ──────────────────────────────────────────────
    public function storeEmpresa(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'empresa'    => 'required|string|max:255',
            'nit'        => 'nullable|numeric',
            'contacto'   => 'nullable|string|max:255',
            'telefono'   => 'nullable|string|max:50',
            'celular'    => 'nullable|string|max:50',
            'correo'     => 'nullable|email|max:150',
            'direccion'  => 'nullable|string|max:255',
            'iva'        => 'nullable|string|max:20',
            'asesor_id'  => 'nullable|exists:asesores,id',
            'observacion'=> 'nullable|string|max:500',
        ]);

        $validated['aliado_id'] = $aliadoId;

        // Workaround para tabla legacy de SQL Server sin IDENTITY
        $maxId = \App\Models\Empresa::max('id');
        $validated['id'] = $maxId ? $maxId + 1 : 1;

        $empresa = \App\Models\Empresa::create($validated);

        return redirect()->route('admin.facturacion.index')
                         ->with('success', 'Empresa creada exitosamente.');
    }

    // ─── Editar empresa ──────────────────────────────────────────────
    public function editEmpresa(int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);
        $asesores = \App\Models\Asesor::where('aliado_id', $aliadoId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('admin.facturacion.empresa_edit', compact('empresa', 'asesores'));
    }

    // ─── Actualizar empresa ──────────────────────────────────────────
    public function updateEmpresa(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        $validated = $request->validate([
            'empresa'    => 'required|string|max:255',
            'nit'        => 'nullable|numeric',
            'contacto'   => 'nullable|string|max:255',
            'telefono'   => 'nullable|string|max:50',
            'celular'    => 'nullable|string|max:50',
            'correo'     => 'nullable|email|max:150',
            'direccion'  => 'nullable|string|max:255',
            'iva'        => 'nullable|string|max:20',
            'asesor_id'  => 'nullable|exists:asesores,id',
            'observacion'=> 'nullable|string|max:500',
        ]);

        $empresa->update($validated);

        return redirect()
            ->route('admin.facturacion.empresa', [
                'id'  => $empresaId,
                'mes' => now()->month,
                'anio'=> now()->year,
            ])
            ->with('success', 'Empresa actualizada correctamente.');
    }

    // ─── Cuenta de Cobro ─────────────────────────────────────────────
    public function cuentaCobroPreview(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $aliado   = \App\Models\Aliado::find($aliadoId);

        $contratoIds = $request->input('contratos', []);
        $mes         = (int) $request->input('mes',  now()->month);
        $anio        = (int) $request->input('anio', now()->year);
        $empresaId   = (int) $request->input('empresa_id');
        $tipo        = $request->input('tipo', 'simple'); // simple | detallada

        $empresa = Empresa::where('aliado_id', $aliadoId)->find($empresaId);
        $admonRetiroCompleta = $request->input('admon_retiro_completa', '1') === '1'; // checkbox de admon en retiros

        // Cuentas bancarias marcadas para cobro
        $cuentasCobro = BancoCuenta::paraCobro($aliadoId);

        // Contratos seleccionados con sus relaciones
        $contratos = Contrato::where('aliado_id', $aliadoId)
            ->whereIn('id', $contratoIds)
            ->with(['cliente', 'tipoModalidad', 'razonSocial', 'eps', 'arl', 'pension', 'caja'])
            ->get();

        // Facturas existentes para el período — indexadas por contrato_id
        $facturasExistentes = Factura::where('aliado_id', $aliadoId)
            ->periodo($mes, $anio)
            ->whereIn('tipo', ['planilla', 'afiliacion'])
            ->whereIn('cedula', $contratos->pluck('cedula'))
            ->whereNotNull('contrato_id')
            ->where('numero_factura', '>', 0)  // excluir factura temporal de retiro (número 0)
            ->get()
            ->keyBy('contrato_id');

        // Facturas de retiro 0 pendientes (para retiros aún no facturados formalmente)
        $facturasRetiro0 = Factura::where('aliado_id', $aliadoId)
            ->whereIn('contrato_id', $contratos->pluck('id'))
            ->where('numero_factura', 0)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('contrato_id');

        $r100 = fn($v) => (int)(ceil(($v ?? 0) / 100) * 100);

        $items = $contratos->map(function ($c) use ($mes, $anio, $facturasExistentes, $facturasRetiro0, $admonRetiroCompleta, $r100, $aliadoId) {
            $fact         = $facturasExistentes->get($c->id);
            $factRetiro0  = $facturasRetiro0->get($c->id);
            $nombre = $c->cliente?->nombre_completo
                      ?? trim(($c->cliente?->primer_nombre ?? '') . ' ' .
                              ($c->cliente?->segundo_nombre ?? '') . ' ' .
                              ($c->cliente?->primer_apellido ?? '') . ' ' .
                              ($c->cliente?->segundo_apellido ?? ''))
                      ?: '—';

            $diasCotizar = 30;
            $esIndActPrimerMes = false;

            $esArlModalidad = (int)($c->tipo_modalidad_id) === 15;
            if ($esArlModalidad) {
                $fArl = $c->fecha_arl ?? $c->fecha_ingreso;
                if ($fArl) {
                    $mesArl  = (int)$fArl->month;
                    $anioArl = (int)$fArl->year;
                    if ($mesArl === $mes && $anioArl === $anio) {
                        $diasCotizar = 0;
                    } else {
                        $diasCotizar = 0;
                    }
                } else {
                    $diasCotizar = 0;
                }
            } elseif ($c->fecha_ingreso) {
                $fIng = $c->fecha_ingreso;
                $mesIngreso  = (int)$fIng->month;
                $anioIngreso = (int)$fIng->year;
                $esIndAct = (int)($c->tipo_modalidad_id) === 11;

                if ($mesIngreso === $mes && $anioIngreso === $anio) {
                    if ($esIndAct) {
                        $esIndActPrimerMes = true;
                        $diasCotizar = max(1, 30 - $fIng->day + 1);
                    } else {
                        $diasCotizar = 0;
                    }
                } else {
                    $mesAnterior  = $mes === 1 ? 12 : $mes - 1;
                    $anioAnterior = $mes === 1 ? $anio - 1 : $anio;

                    if ($mesIngreso === $mesAnterior && $anioIngreso === $anioAnterior) {
                        $diasCotizar = max(1, 30 - $fIng->day + 1);
                    }
                }
            }

            // ¿Es afiliación pura?
            $esAfil = false;
            if ($esArlModalidad) {
                $esAfil = true;
            } elseif ($c->fecha_ingreso) {
                $fIngC = $c->fecha_ingreso;
                if ((int)$fIngC->month === $mes && (int)$fIngC->year === $anio) {
                    if (!$esIndActPrimerMes) {
                        $esAfil = true;
                    }
                }
            }
            if ($fact) {
                $esAfil = $fact->tipo === 'afiliacion' && !($fact->afiliacion > 0 && $fact->total_ss > 0);
            }

            // ── Retiro Pendiente: sobreescribir días si el contrato vigente tiene fecha_retiro_pendiente ──
            if ($c->estado === 'vigente' && $c->fecha_retiro_pendiente) {
                $diasCotizar = (int) $c->fecha_retiro_pendiente->day;
            }

            $esRetirado = $c->estado === 'retirado';


            if ($fact) {
                $vEps  = $r100($fact->v_eps);
                $vArl  = $r100($fact->v_arl);
                $vAFP  = $r100($fact->v_afp);
                $vCaja = $r100($fact->v_caja);
                $vAdm  = (int)($fact->admon + $fact->admin_asesor);
                $vIva  = $r100($fact->iva);
                $vTot  = (int)$fact->total;
                $estado = $fact->estado;
                $diasCotizar = (int)$fact->dias_cotizados;
            } elseif ($esRetirado && $factRetiro0) {
                // Retiro pendiente de facturar: usar valores de la factura temporal
                $vEps  = $r100($factRetiro0->v_eps);
                $vArl  = $r100($factRetiro0->v_arl);
                $vAFP  = $r100($factRetiro0->v_afp);
                $vCaja = $r100($factRetiro0->v_caja);
                $vIva  = $r100($factRetiro0->iva);
                $diasCotizar = (int)$factRetiro0->dias_cotizados;
                $vAdmonBase = (int)(($c->administracion ?? 0) + ($c->admon_asesor ?? 0));
                $vAdm = $admonRetiroCompleta
                    ? $vAdmonBase
                    : ($diasCotizar <= 3 ? 0 : $vAdmonBase);
                $vTot  = $r100($factRetiro0->total_ss) + $vAdm + $vIva;
                $estado = 'sin_factura';
            } elseif ($esRetirado) {
                $vEps = $vArl = $vAFP = $vCaja = $vIva = $vAdm = 0;
                $vTot = 0;
                $estado = 'sin_factura';
            } elseif ($esArlModalidad && !$esAfil) {
                $vEps = $vArl = $vAFP = $vCaja = $vIva = $vAdm = 0;
                $vTot = 0;
                $estado = 'sin_factura';
            } elseif ($esIndActPrimerMes) {
                $tieneIva = $c->cliente ? (strtoupper(trim($c->cliente->iva ?? '')) === 'SI') : false;
                $cotiz = $c->calcularCotizacion($diasCotizar, $tieneIva);
                $vEps  = $r100($cotiz['eps']??0);
                $vArl  = $r100($cotiz['arl']??0);
                $vAFP  = $r100($cotiz['pen']??0);
                $vCaja = $r100($cotiz['caja']??0);
                $vIva  = $r100($cotiz['iva']??0);
                $vSS   = $r100($cotiz['ss']);
                $vAdm  = (int)(($c->administracion??0) + ($c->admon_asesor??0));
                $vTot  = $vSS + $vAdm + $vIva + (int)(($c->costo_afiliacion ?? 0) + ($c->seguro ?? 0));
                $estado = 'sin_factura';
            } elseif ($esAfil) {
                $vEps = $vArl = $vAFP = $vCaja = $vIva = $vAdm = 0;
                $vTot = (int)(($c->costo_afiliacion ?? 0) + ($c->seguro ?? 0));
                $estado = 'sin_factura';
            } else {
                $tieneIva = $c->cliente ? (strtoupper(trim($c->cliente->iva ?? '')) === 'SI') : false;
                $cotiz = $c->calcularCotizacion($diasCotizar, $tieneIva);
                $vEps  = $r100($cotiz['eps']  ?? 0);
                $vArl  = $r100($cotiz['arl']  ?? 0);
                $vAFP  = $r100($cotiz['pen']  ?? 0);
                $vCaja = $r100($cotiz['caja'] ?? 0);
                $vAdm  = (int)(($c->administracion ?? 0) + ($c->admon_asesor ?? 0));
                $vIva  = $r100($cotiz['iva']  ?? 0);
                $vSS   = $r100($cotiz['ss']   ?? 0);
                $vTot  = $vSS + $vAdm + $vIva;
                $estado = 'sin_factura';
            }

            return (object)[
                'cedula'         => $c->cedula,
                'nombre'         => $nombre,
                'fecha_ingreso'  => $c->fecha_ingreso,
                'razon_social'   => $this->limpiarRazonSocialCuentaCobro($c->razonSocial?->razon_social),
                'modalidad'      => $c->tipoModalidad?->tipo_modalidad ?? '—',
                'eps_nombre'     => $c->eps?->nombre ?? '—',

                'arl_nombre'     => $c->arl?->nombre ?? '—',
                'n_arl'          => $c->n_arl ?? 1,
                'afp_nombre'     => $c->pension?->nombre ?? '—',
                'caja_nombre'    => $c->caja?->nombre ?? '—',
                'es_afil'        => $esAfil,
                'dias'           => $diasCotizar,
                'v_eps'          => $vEps,
                'v_arl'          => $vArl,
                'v_afp'          => $vAFP,
                'v_caja'         => $vCaja,
                'v_admon'        => $vAdm,
                'v_iva'          => $vIva,
                'v_total'        => $vTot,
                'estado'         => $estado,
                'saldo_proximo'  => (int) Factura::saldoClienteMesPrevio(
                    $aliadoId, $c->cedula, $mes, $anio, $c->id
                )['a_favor'] - (int) Factura::saldoClienteMesPrevio(
                    $aliadoId, $c->cedula, $mes, $anio, $c->id
                )['pendiente'],
            ];
        });

        $totalGeneral   = $items->sum('v_total');
        // Saldo neto de la empresa derivado de saldo_proximo acumulado
        $saldoNetoEmpresaCC = (int) Factura::where('aliado_id', $aliadoId)
            ->where('empresa_id', $empresa->id)
            ->whereIn('estado', ['pagada', 'prestamo', 'abono'])
            ->whereNotNull('saldo_proximo')
            ->whereNull('deleted_at')
            ->sum('saldo_proximo');
        $totalFavor     = $saldoNetoEmpresaCC > 0 ? $saldoNetoEmpresaCC : 0;
        $totalPendiente = $saldoNetoEmpresaCC < 0 ? abs($saldoNetoEmpresaCC) : 0;

        $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $vista = $tipo === 'detallada'
            ? 'admin.facturacion.cuenta_cobro_detallada'
            : 'admin.facturacion.cuenta_cobro_simple';

        // ─── Cobros adicionales a incluir en la cuenta de cobro ────────────────
        $cobrosAdicionalesIds = $request->input('cobros_adicionales_ids', []);
        $cobrosAdicionalesCC  = collect();
        if ($empresa) {
            $qCobros = \App\Models\CobrosAdicionalEmpresa::where('aliado_id', $aliadoId)
                ->where('empresa_id', $empresa->id)
                ->where('activo', true);
            if (!empty($cobrosAdicionalesIds)) {
                $qCobros->whereIn('id', $cobrosAdicionalesIds);
            }
            $cobrosAdicionalesCC = $qCobros->get();
        }
        $totalCobrosAdicionales = (int)$cobrosAdicionalesCC->sum('valor');
        $totalGeneral += $totalCobrosAdicionales;

        return view($vista, compact(
            'aliado','empresa','items','cuentasCobro',
            'mes','anio','meses','totalGeneral','totalFavor','totalPendiente',
            'cobrosAdicionalesCC', 'totalCobrosAdicionales'
        ));
    }

    // ─── CRUD: Cobros Adicionales por Empresa ────────────────────────────

    /** Listar cobros adicionales de una empresa (JSON). */
    public function cobrosAdicionalesIndex(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);
        $cobros   = \App\Models\CobrosAdicionalEmpresa::where('aliado_id', $aliadoId)
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->orderBy('tipo')->orderBy('descripcion')->get();
        return response()->json(['ok' => true, 'cobros' => $cobros]);
    }

    /** Crear un cobro adicional para una empresa. */
    public function cobrosAdicionalesStore(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);
        $validated = $request->validate([
            'descripcion' => 'required|string|max:300',
            'valor'       => 'required|numeric|min:0',
            'tipo'        => 'required|in:unica_vez,recurrente',
        ]);
        $cobro = \App\Models\CobrosAdicionalEmpresa::create([
            'aliado_id'   => $aliadoId,
            'empresa_id'  => $empresa->id,
            'descripcion' => trim($validated['descripcion']),
            'valor'       => (float)$validated['valor'],
            'tipo'        => $validated['tipo'],
            'activo'      => true,
        ]);
        Bitacora::registrar(
            accion: 'created',
            modelo: 'CobrosAdicionalEmpresa',
            registroId: $cobro->id,
            descripcion: "Cobro adicional '{$cobro->descripcion}' ({$cobro->tipo}) creado para empresa #{$empresa->id}.",
            detalle: $cobro->toArray(),
            alidoId: $aliadoId
        );
        return response()->json(['ok' => true, 'cobro' => $cobro]);
    }

    /**
     * Eliminar un cobro adicional.
     * Recurrentes → desactivar (mantener historial). Únicos → eliminar.
     */
    public function cobrosAdicionalesDestroy(int $cobroId)
    {
        $aliadoId = session('aliado_id_activo');
        $cobro    = \App\Models\CobrosAdicionalEmpresa::where('aliado_id', $aliadoId)
            ->findOrFail($cobroId);
        Bitacora::registrar(
            accion: 'deleted',
            modelo: 'CobrosAdicionalEmpresa',
            registroId: $cobro->id,
            descripcion: "Cobro adicional '{$cobro->descripcion}' eliminado de empresa #{$cobro->empresa_id}.",
            detalle: $cobro->toArray(),
            alidoId: $aliadoId
        );
        if ($cobro->tipo === \App\Models\CobrosAdicionalEmpresa::TIPO_RECURRENTE) {
            $cobro->update(['activo' => false]);
        } else {
            $cobro->delete();
        }
        return response()->json(['ok' => true]);
    }

    // ─── Retiro Pendiente desde Vista Empresa ──────────────────────────────────
    // Guarda o limpia la fecha de retiro pendiente de un contrato vigente.
    // El estado del contrato NO cambia aquí; lo hace al momento de facturar.
    public function guardarRetiroPendiente(Request $request, int $contrato): \Illuminate\Http\JsonResponse
    {
        $aliadoId = session('aliado_id_activo');

        $c = \App\Models\Contrato::where('aliado_id', $aliadoId)
            ->where('id', $contrato)
            ->firstOrFail();

        // Seguridad: solo se puede registrar retiro pendiente en contratos vigentes
        if ($c->estado !== 'vigente') {
            return response()->json(['ok' => false, 'mensaje' => 'Solo se puede registrar retiro pendiente en contratos vigentes.'], 422);
        }

        $fechaStr = $request->input('fecha_retiro'); // null para limpiar

        if ($fechaStr === null || $fechaStr === '') {
            // Limpiar retiro pendiente
            $c->update([
                'fecha_retiro_pendiente'        => null,
                'retiro_pendiente_cobrar_admon' => null,
            ]);

            \App\Models\Bitacora::registrar(
                accion: 'updated',
                modelo: 'Contrato',
                registroId: $c->id,
                descripcion: "Retiro pendiente cancelado para contrato #{$c->id} (cédula {$c->cedula}).",
                detalle: [],
                alidoId: $aliadoId
            );

            return response()->json(['ok' => true, 'mensaje' => 'Retiro pendiente cancelado.', 'dias' => 30]);
        }

        $request->validate([
            'fecha_retiro'       => 'required|date',
            'cobrar_admon'       => 'required|boolean',
        ]);

        $fecha = \Carbon\Carbon::parse($fechaStr);
        $dias  = (int) $fecha->day; // día del mes = días trabajados (incluye el último)
        $cobrarAdmon = (bool) $request->input('cobrar_admon');

        $c->update([
            'fecha_retiro_pendiente'        => $fecha->toDateString(),
            'retiro_pendiente_cobrar_admon' => $cobrarAdmon ? 1 : 0,
        ]);

        \App\Models\Bitacora::registrar(
            accion: 'updated',
            modelo: 'Contrato',
            registroId: $c->id,
            descripcion: "Retiro pendiente registrado para contrato #{$c->id} (cédula {$c->cedula}): fecha {$fecha->toDateString()}, días {$dias}, admon " . ($cobrarAdmon ? 'SÍ' : 'NO') . ".",
            detalle: [
                'fecha_retiro_pendiente'        => $fecha->toDateString(),
                'dias'                          => $dias,
                'retiro_pendiente_cobrar_admon' => $cobrarAdmon,
            ],
            alidoId: $aliadoId
        );

        return response()->json([
            'ok'    => true,
            'dias'  => $dias,
            'fecha' => $fecha->format('d/m/Y'),
            'cobrar_admon' => $cobrarAdmon,
        ]);
    }

    // Limpia y recorta sufijos o términos de tipos de sociedad en las razones sociales para simplificar cuentas de cobro
    private function limpiarRazonSocialCuentaCobro(?string $razon): string
    {
        if (!$razon) return '—';
        $patrones = [
            '/\bSOCIEDAD POR ACCIONES SIMPLIFICADAS\b/i',
            '/\bSOCIADAD POR ACCIONES SIMPLIFICADAS\b/i', // typo común
            '/\bSOCIEDAD ANONIMA\b/i',
            '/\bSOCIADAD ANONIMA\b/i', // typo común
            '/\bS\.A\.S\.\b/i',
            '/\bS\.A\.S\b/i',
            '/\bSAS\b/i',
            '/\bS\.A\.\b/i',
            '/\bS\.A\b/i',
            '/\bSA\b/i',
            '/\bLTDA\b/i',
            '/\bLIMITADA\b/i',
        ];
        $limpio = preg_replace($patrones, '', $razon);
        $limpio = trim(preg_replace('/\s+/', ' ', $limpio));
        return rtrim($limpio, ', -') ?: '—';
    }
}

