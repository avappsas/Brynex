@extends('layouts.app')
@section('modulo','Liquidación Asesores')

@section('contenido')
<div style="background:#fff;border-radius:14px;padding:1.5rem;box-shadow:0 1px 8px rgba(0,0,0,0.06);">

    @include('admin.partials.table-header', [
        'titulo'    => '📈 Reporte de Liquidación de Comisiones',
    ])

    {{-- Filtro de Mes/Año --}}
    <form method="GET" action="{{ route('admin.asesores.reporte_mensual') }}" style="background:#f8fafc;padding:1.25rem;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:1.5rem;display:flex;gap:1rem;align-items:flex-end;">
        <div>
            <label style="display:block;font-size:0.75rem;font-weight:600;color:#475569;margin-bottom:0.3rem;">Mes</label>
            <select name="mes" style="padding:0.5rem 1rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.9rem;background:#fff;min-width:150px;">
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ $m == $mes ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null,$m)->locale('es')->isoFormat('MMMM') }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="display:block;font-size:0.75rem;font-weight:600;color:#475569;margin-bottom:0.3rem;">Año</label>
            <select name="anio" style="padding:0.5rem 1rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.9rem;background:#fff;min-width:100px;">
                @foreach(range(now()->year - 2, now()->year + 1) as $a)
                    <option value="{{ $a }}" {{ $a == $anio ? 'selected' : '' }}>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit" style="background:#2563eb;color:#fff;border:none;padding:0.6rem 1.5rem;border-radius:6px;font-size:0.85rem;font-weight:600;cursor:pointer;">
                🔍 Filtrar
            </button>
        </div>
    </form>

    {{-- Resumen Total --}}
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1.5rem;margin-bottom:2rem;">
        <div style="background:#f1f5f9;padding:1rem;border-radius:10px;border-left:4px solid #8b5cf6;">
            <div style="font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;">T. Afiliaciones</div>
            <div style="font-size:1.3rem;font-weight:700;color:#0f172a;">${{ number_format($totalAfiliacion, 0, ',', '.') }}</div>
            <div style="font-size:0.7rem;color:#94a3b8;text-transform:capitalize;">{{ $periodoLabel }}</div>
        </div>
        <div style="background:#f1f5f9;padding:1rem;border-radius:10px;border-left:4px solid #0891b2;">
            <div style="font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;">T. Administración</div>
            <div style="font-size:1.3rem;font-weight:700;color:#0f172a;">${{ number_format($totalAdmon, 0, ',', '.') }}</div>
            <div style="font-size:0.7rem;color:#94a3b8;text-transform:capitalize;">{{ $periodoLabel }}</div>
        </div>
        <div style="background:#f1f5f9;padding:1rem;border-radius:10px;border-left:4px solid #16a34a;">
            <div style="font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;">Ya Pagado</div>
            <div style="font-size:1.3rem;font-weight:700;color:#16a34a;">${{ number_format($totalPagado, 0, ',', '.') }}</div>
            <div style="font-size:0.7rem;color:#94a3b8;">Del periodo</div>
        </div>
        <div style="background:#fff;padding:1rem;border-radius:10px;border-left:4px solid #dc2626;box-shadow:0 2px 10px rgba(220,38,38,0.15);">
            <div style="font-size:0.75rem;font-weight:600;color:#dc2626;text-transform:uppercase;">Total Pendiente</div>
            <div style="font-size:1.4rem;font-weight:800;color:#dc2626;">${{ number_format($totalPendiente, 0, ',', '.') }}</div>
            <div style="font-size:0.7rem;color:#94a3b8;">Del periodo</div>
        </div>
    </div>

    {{-- Detalle por Asesor --}}
    <h3 style="font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:1rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.5rem;">Detalle por Asesor ({{ ucfirst($periodoLabel) }})</h3>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:0.75rem 1rem;text-align:left;color:#475569;font-weight:600;">Asesor</th>
                    <th style="padding:0.75rem 1rem;text-align:center;color:#475569;font-weight:600;">Gen. Afil</th>
                    <th style="padding:0.75rem 1rem;text-align:center;color:#475569;font-weight:600;">Gen. Admin</th>
                    <th style="padding:0.75rem 1rem;text-align:right;color:#475569;font-weight:600;">Total Generado</th>
                    <th style="padding:0.75rem 1rem;text-align:right;color:#475569;font-weight:600;">Pendiente Pago</th>
                    <th style="padding:0.75rem 1rem;text-align:center;color:#475569;font-weight:600;">Ver</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($asesores as $asesor)
                    @php
                        $genAfil = $asesor->comisiones->where('tipo', 'afiliacion')->sum('valor_comision');
                        $genAdmon = $asesor->comisiones->where('tipo', 'administracion')->sum('valor_comision');
                        $totalGen = $genAfil + $genAdmon;
                        $pdte = $asesor->comisiones->where('pagado', false)->sum('valor_comision');
                    @endphp
                    @if($totalGen > 0)
                    <tr style="border-bottom:1px solid #f1f5f9;transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <td style="padding:0.75rem 1rem;">
                            <div style="font-weight:600;color:#0f172a;">{{ $asesor->nombre }}</div>
                            <div style="font-size:0.75rem;color:#64748b;">{{ $asesor->cuenta_bancaria ?? 'Sin cuenta' }}</div>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;color:#64748b;">
                            {{ $genAfil > 0 ? '$'.number_format($genAfil, 0, ',', '.') : '-' }}
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;color:#64748b;">
                            {{ $genAdmon > 0 ? '$'.number_format($genAdmon, 0, ',', '.') : '-' }}
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;font-weight:700;color:#0f172a;">
                            ${{ number_format($totalGen, 0, ',', '.') }}
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;font-weight:700;color:{{ $pdte > 0 ? '#dc2626' : '#16a34a' }};">
                            ${{ number_format($pdte, 0, ',', '.') }}
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            <a href="{{ route('admin.asesores.show', $asesor) }}" style="color:#2563eb;text-decoration:none;font-size:0.8rem;font-weight:600;background:#dbeafe;padding:0.3rem 0.6rem;border-radius:6px;">
                                Detalles →
                            </a>
                        </td>
                    </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:2rem;color:#94a3b8;">No hay asesores con datos.</td>
                    </tr>
                @endforelse
                @if($asesores->count() > 0 && $totalAfiliacion + $totalAdmon == 0)
                    <tr>
                        <td colspan="6" style="text-align:center;padding:2rem;color:#94a3b8;">No hubo comisiones generadas en este periodo.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
