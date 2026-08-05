@extends('layouts.app')
@section('modulo','Cierre de Operación')
@section('contenido')
@php
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $porConfirmar = $lotes->filter(fn($l) => $l->api)->count();
    $sinLiquidar  = $lotes->count() - $porConfirmar;
    $afiSinFactura = $afiliaciones->where('sin_factura', 1)->count();
    $afiSinCobro   = $afiliaciones->count() - $afiSinFactura;

    $tabs = [
        'planillas' => ['📄 Planillas sin confirmar', $lotes->count()],
        'vigentes'  => ['👥 Vigentes sin facturar',   $vigentes->count()],
        'afiliados' => ['📥 Afiliaciones del mes',    $afiliaciones->count()],
    ];
@endphp

<div style="max-width:1200px;margin:0 auto;" x-data="{tab:'planillas'}">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">✅ Cierre de Operación</h1>

        <form method="GET" style="display:flex;gap:.5rem;align-items:center;">
            <select name="mes" style="padding:.4rem .6rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.82rem;">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($m === $mes)>{{ $meses[$m] }}</option>
                @endfor
            </select>
            <select name="anio" style="padding:.4rem .6rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.82rem;">
                @for($a = now()->year - 2; $a <= now()->year + 1; $a++)
                    <option value="{{ $a }}" @selected($a === $anio)>{{ $a }}</option>
                @endfor
            </select>
            <button type="submit" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:.45rem .9rem;font-size:.8rem;font-weight:700;cursor:pointer;">Ver</button>
        </form>
    </div>

    {{-- Resumen --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;margin-bottom:1.1rem;">
        @foreach([
            ['Liquidadas sin confirmar', $porConfirmar, '#f59e0b', 'Ya tienen N° en el operador'],
            ['Tandas sin liquidar', $sinLiquidar, $sinLiquidar > 0 ? '#ef4444' : '#10b981', 'Sin número de planilla'],
            ['Vigentes sin facturar', $vigentes->count(), $vigentes->count() > 0 ? '#ef4444' : '#10b981', $meses[$mes].' '.$anio],
            ['Afiliaciones pendientes', $afiliaciones->count(), $afiliaciones->count() > 0 ? '#f59e0b' : '#10b981', $afiSinFactura.' sin factura · '.$afiSinCobro.' sin cobro'],
        ] as [$label, $valor, $color, $pie])
        <div style="background:#fff;border-radius:12px;padding:.9rem 1rem;box-shadow:0 1px 6px rgba(0,0,0,.06);">
            <div style="font-size:1.5rem;font-weight:800;color:{{ $color }};line-height:1;">{{ number_format($valor) }}</div>
            <div style="font-size:.76rem;font-weight:600;color:#334155;margin-top:.25rem;">{{ $label }}</div>
            <div style="font-size:.7rem;color:#94a3b8;margin-top:.1rem;">{{ $pie }}</div>
        </div>
        @endforeach
    </div>

    {{-- Tabs --}}
    <div style="display:flex;gap:.5rem;margin-bottom:1rem;border-bottom:2px solid #e2e8f0;flex-wrap:wrap;">
        @foreach($tabs as $key => [$label, $n])
        <button @click="tab='{{ $key }}'" :style="tab==='{{ $key }}'?'border-bottom:3px solid #2563eb;color:#2563eb;margin-bottom:-2px;':'color:#64748b;'"
            style="background:none;border:none;padding:.5rem 1rem;font-size:.84rem;font-weight:600;cursor:pointer;">
            {{ $label }} <span style="color:#94a3b8;font-weight:700;">({{ $n }})</span>
        </button>
        @endforeach
    </div>

    {{-- ── Planillas sin confirmar ─────────────────────────────────────── --}}
    <div x-show="tab==='planillas'" style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
        <div style="padding:.8rem 1rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.76rem;color:#475569;line-height:1.45;">
            Tandas cuyos planos no tienen número de planilla. El número se estampa al
            <strong>confirmar el pago</strong> en el módulo de planos, así que una tanda ya liquidada en el
            operador sigue apareciendo aquí hasta que se registre el pago.
            <a href="{{ route('admin.informes.cierre_operacion', ['mes'=>$mes,'anio'=>$anio,'excel'=>'lotes']) }}"
               style="color:#0f766e;font-weight:700;text-decoration:none;">· Excel</a>
        </div>
        <div style="max-height:60vh;overflow:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                <thead><tr style="background:#f8fafc;position:sticky;top:0;">
                    <th style="padding:.55rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Razón social</th>
                    <th style="padding:.55rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Período</th>
                    <th style="padding:.55rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Tanda</th>
                    <th style="padding:.55rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Cotizantes</th>
                    <th style="padding:.55rem;text-align:right;font-size:.7rem;text-transform:uppercase;color:#64748b;">Valor SS</th>
                    <th style="padding:.55rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Estado</th>
                </tr></thead>
                <tbody>
                    @forelse($lotes as $l)
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:.5rem 1rem;font-weight:600;color:#0d2550;">{{ $l->razon_social }}</td>
                        <td style="padding:.5rem;text-align:center;color:#475569;white-space:nowrap;">
                            {{ $meses[(int) $l->mes_plano] }} {{ $l->anio_plano }}
                            <div style="font-size:.68rem;color:#94a3b8;">pago {{ $meses[$l->mes_pago] }}</div>
                        </td>
                        <td style="padding:.5rem;text-align:center;color:#64748b;">{{ $l->n_plano }}</td>
                        <td style="padding:.5rem;text-align:center;font-weight:700;color:#334155;">{{ $l->cotizantes }}</td>
                        <td style="padding:.5rem;text-align:right;color:#334155;">$ {{ number_format((float) $l->valor_ss) }}</td>
                        <td style="padding:.5rem 1rem;">
                            @if($l->api)
                                <span style="color:#b45309;font-weight:700;">Liquidada · falta confirmar el pago</span>
                                <div style="font-size:.72rem;color:#94a3b8;">
                                    {{ $l->api->operador ?? 'Operador' }} · N° {{ $l->api->numero_planilla }}
                                    @if($l->api->valor_total) · $ {{ number_format((float) $l->api->valor_total) }} @endif
                                </div>
                                @if($l->api->url_pago)
                                    <a href="{{ $l->api->url_pago }}" target="_blank" rel="noopener"
                                       style="font-size:.72rem;color:#15803d;font-weight:700;">Ir a pagar en PSE →</a>
                                @endif
                            @else
                                <span style="color:#b91c1c;font-weight:700;">Sin liquidar</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:2rem;text-align:center;color:#94a3b8;">Todo confirmado. Nada pendiente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:.6rem 1rem;font-size:.7rem;color:#94a3b8;border-top:1px solid #f1f5f9;">
            Cuenta desde abril de 2026, que es cuando el número de planilla empezó a registrarse.
            Lo anterior queda fuera a propósito.
        </div>
    </div>

    {{-- ── Vigentes sin facturar / Afiliaciones ────────────────────────── --}}
    @foreach([
        ['vigentes',  $vigentes,     'vigentes',     'Contratos vigentes sin factura de '.$meses[$mes].' '.$anio.'.'],
        ['afiliados', $afiliaciones, 'afiliaciones', 'Afiliaciones de '.$meses[$mes].' '.$anio.' sin factura, o facturadas sin cobrar el valor de afiliación.'],
    ] as [$key, $filas, $exp, $ayuda])
    <div x-show="tab==='{{ $key }}'" style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
        <div style="padding:.8rem 1rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.76rem;color:#475569;">
            {{ $ayuda }}
            <a href="{{ route('admin.informes.cierre_operacion', ['mes'=>$mes,'anio'=>$anio,'excel'=>$exp]) }}"
               style="color:#0f766e;font-weight:700;text-decoration:none;">· Excel</a>
        </div>
        <div style="max-height:60vh;overflow:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                <thead><tr style="background:#f8fafc;position:sticky;top:0;">
                    <th style="padding:.55rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Cédula</th>
                    <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Nombre</th>
                    <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Razón social</th>
                    <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Plan</th>
                    <th style="padding:.55rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Ingreso</th>
                    @if($key === 'afiliados')
                    <th style="padding:.55rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Pendiente</th>
                    @endif
                </tr></thead>
                <tbody>
                    @forelse($filas as $f)
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:.5rem 1rem;font-weight:600;color:#334155;">{{ $f->cedula }}</td>
                        <td style="padding:.5rem;color:#0d2550;">{{ $f->nombre ?: '—' }}</td>
                        <td style="padding:.5rem;color:#64748b;">{{ $f->razon_social }}</td>
                        <td style="padding:.5rem;color:#64748b;">{{ $f->plan_nombre ?? '—' }}</td>
                        <td style="padding:.5rem;text-align:center;color:#64748b;">{{ $f->fecha_ingreso ?? '—' }}</td>
                        @if($key === 'afiliados')
                        <td style="padding:.5rem 1rem;">
                            @if((int) $f->sin_factura === 1)
                                <span style="color:#b91c1c;font-weight:700;">Sin factura</span>
                            @else
                                <span style="color:#b45309;font-weight:700;">Sin cobro de afiliación</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:2rem;text-align:center;color:#94a3b8;">Nada pendiente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</div>
@endsection
