@extends('layouts.app')
@section('modulo','Validación de Cierre')
@section('contenido')
@php
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $totVigentes   = $resumen->sum('vigentes');
    $totConPlano   = $resumen->sum('con_plano');
    $totPendientes = $resumen->sum('pendientes');
@endphp

<div style="max-width:1200px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">🧾 Validación de Cierre</h1>

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
            <a href="{{ route('admin.informes.validacion_cierre', ['mes' => $mes, 'anio' => $anio, 'razon_social_id' => $rsId, 'excel' => 1]) }}"
               style="background:#0f766e;color:#fff;border-radius:8px;padding:.45rem .9rem;font-size:.8rem;font-weight:700;text-decoration:none;">Excel</a>
        </form>
    </div>

    {{-- Qué se está midiendo: el mes de PAGO y el período que llevan los planos --}}
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:.85rem 1rem;margin-bottom:1.1rem;font-size:.78rem;color:#1e3a8a;line-height:1.45;">
        Pago de <strong>{{ $meses[$periodo['mes_pago']] }} {{ $periodo['anio_pago'] }}</strong> —
        planilla del período <strong>{{ $meses[$periodo['mes_vencido']] }}</strong> para dependientes y
        <strong>{{ $meses[$periodo['mes_pago']] }}</strong> para independientes.
        Se cuenta como pendiente todo contrato <strong>vigente</strong> sin plano de planilla ni de retiro en ese período.
        Con el mes en curso es normal que haya pendientes: la nómina se liquida por tandas.
        <strong>Al cerrar el mes, el que siga aquí es un retiro que nunca se registró.</strong>
    </div>

    {{-- Totales --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;margin-bottom:1.1rem;">
        @foreach([
            ['Contratos vigentes', $totVigentes, '#3b82f6'],
            ['En planilla', $totConPlano, '#10b981'],
            ['Pendientes', $totPendientes, $totPendientes > 0 ? '#ef4444' : '#10b981'],
        ] as [$label, $valor, $color])
        <div style="background:#fff;border-radius:12px;padding:.9rem 1rem;box-shadow:0 1px 6px rgba(0,0,0,.06);">
            <div style="font-size:1.5rem;font-weight:800;color:{{ $color }};line-height:1;">{{ number_format($valor) }}</div>
            <div style="font-size:.76rem;font-weight:600;color:#64748b;margin-top:.25rem;">{{ $label }}</div>
        </div>
        @endforeach
    </div>

    {{-- Resumen por razón social --}}
    <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;margin-bottom:1.25rem;">
        <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
            <thead><tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Razón social</th>
                <th style="padding:.65rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Vigentes</th>
                <th style="padding:.65rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">En planilla</th>
                <th style="padding:.65rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Pendientes</th>
                <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Liquidación por API</th>
                <th style="padding:.65rem;"></th>
            </tr></thead>
            <tbody>
                @forelse($resumen as $r)
                @php $pct = $r->vigentes > 0 ? round($r->con_plano / $r->vigentes * 100) : 0; @endphp
                <tr style="border-bottom:1px solid #f1f5f9;{{ (int) $rsId === (int) $r->razon_social_id ? 'background:#eff6ff;' : '' }}">
                    <td style="padding:.6rem 1rem;font-weight:600;color:#0d2550;">
                        {{ $r->razon_social }}
                        @if($r->nit)<span style="color:#94a3b8;font-weight:400;font-size:.74rem;"> · NIT {{ $r->nit }}</span>@endif
                        <div style="height:5px;background:#e2e8f0;border-radius:3px;margin-top:.35rem;max-width:220px;">
                            <div style="height:100%;width:{{ $pct }}%;background:{{ $pct === 100 ? '#10b981' : '#f59e0b' }};border-radius:3px;"></div>
                        </div>
                    </td>
                    <td style="padding:.6rem;text-align:center;font-weight:700;color:#334155;">{{ $r->vigentes }}</td>
                    <td style="padding:.6rem;text-align:center;font-weight:700;color:#10b981;">{{ $r->con_plano }}</td>
                    <td style="padding:.6rem;text-align:center;font-size:1.05rem;font-weight:800;color:{{ $r->pendientes > 0 ? '#ef4444' : '#10b981' }};">
                        {{ $r->pendientes }}
                    </td>
                    <td style="padding:.6rem 1rem;color:#475569;font-size:.78rem;">
                        @if($r->api)
                            <strong>{{ $r->api->liquidadas }}</strong> de {{ $r->api->intentos }} planilla(s)
                            @if($r->api->valor_liquidado > 0)
                                · $ {{ number_format((float) $r->api->valor_liquidado) }}
                            @endif
                            <div style="color:#94a3b8;font-size:.72rem;">
                                {{ $r->api->operador ?? 'Operador' }} · última {{ \Carbon\Carbon::parse($r->api->ultima_fecha)->format('d/m/Y H:i') }}
                            </div>
                        @else
                            <span style="color:#94a3b8;">Sin liquidar por API este período</span>
                        @endif
                    </td>
                    <td style="padding:.6rem 1rem;text-align:right;white-space:nowrap;">
                        @if($r->pendientes > 0)
                        <a href="{{ route('admin.informes.validacion_cierre', ['mes' => $mes, 'anio' => $anio, 'razon_social_id' => $r->razon_social_id]) }}#detalle"
                           style="color:#2563eb;font-size:.76rem;font-weight:700;text-decoration:none;">Ver quiénes →</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="padding:2rem;text-align:center;color:#94a3b8;">No hay contratos vigentes en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Detalle de la razón social abierta --}}
    @if($rsId)
    <div id="detalle" style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
        <div style="padding:.85rem 1rem;border-bottom:2px solid #e2e8f0;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
            <h2 style="font-size:.92rem;font-weight:700;color:#0d2550;flex:1;">
                Pendientes de {{ $detalle->first()->razon_social ?? 'la razón social' }}
                <span style="color:#ef4444;">({{ $detalle->count() }})</span>
            </h2>
            <a href="{{ route('admin.informes.validacion_cierre', ['mes' => $mes, 'anio' => $anio]) }}"
               style="color:#64748b;font-size:.78rem;text-decoration:none;">Cerrar detalle ✕</a>
        </div>
        <div style="max-height:60vh;overflow:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                <thead><tr style="background:#f8fafc;position:sticky;top:0;">
                    <th style="padding:.55rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Cédula</th>
                    <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Nombre</th>
                    <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Plan</th>
                    <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Modalidad</th>
                    <th style="padding:.55rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Ingreso</th>
                    <th style="padding:.55rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Última planilla</th>
                </tr></thead>
                <tbody>
                    @forelse($detalle as $d)
                    @php
                        $ult = $d->ultimo_periodo
                            ? $meses[(int) substr((string) $d->ultimo_periodo, 4, 2)].' '.substr((string) $d->ultimo_periodo, 0, 4)
                            : null;
                    @endphp
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:.5rem 1rem;font-weight:600;color:#334155;">{{ $d->cedula }}</td>
                        <td style="padding:.5rem;color:#0d2550;">{{ $d->nombre ?: '—' }}</td>
                        <td style="padding:.5rem;color:#64748b;">{{ $d->plan_nombre ?? '—' }}</td>
                        <td style="padding:.5rem;color:#64748b;">{{ $d->modalidad ?? '—' }}</td>
                        <td style="padding:.5rem;text-align:center;color:#64748b;">{{ $d->fecha_ingreso ?? '—' }}</td>
                        <td style="padding:.5rem 1rem;text-align:center;">
                            @if($ult)
                                <span style="color:#475569;">{{ $ult }}</span>
                            @else
                                <span style="color:#b45309;font-weight:700;">Nunca</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:2rem;text-align:center;color:#94a3b8;">Sin pendientes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
