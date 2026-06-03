@extends('layouts.app')
@section('modulo', 'BryNex Contabilidad')
@section('contenido')

<div style="max-width:1200px;margin:0 auto;">

    {{-- Botón para regresar --}}
    <div style="margin-bottom:1rem;">
        <a href="{{ route('brynex.consumo.index') }}" style="color:#64748b;font-size:0.8rem;text-decoration:none;font-weight:700;">
            ← Volver al Dashboard
        </a>
    </div>

    {{-- Encabezado --}}
    <div style="margin-bottom:1.5rem;">
        <h1 style="font-size:1.5rem;font-weight:800;color:#0d2550;margin:0;">📊 Contabilidad y KPIs de Cobros BryNex</h1>
        <p style="color:#64748b;font-size:0.83rem;margin:0.2rem 0 0 0;">Análisis del recaudo general de Brynex facturado a aliados comerciales.</p>
    </div>

    {{-- Fila de KPIs Consolidados --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.25rem;margin-bottom:2rem;">
        
        {{-- KPI Facturado Total --}}
        <div style="background:#fff;border-radius:16px;padding:1.5rem;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;display:flex;align-items:center;gap:1.25rem;">
            <div style="font-size:2rem;background:#eff6ff;color:#1d4ed8;width:55px;height:55px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                💼
            </div>
            <div>
                <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;font-weight:700;margin-bottom:0.2rem;">Total Facturado Histórico</div>
                <div style="font-size:1.4rem;font-weight:800;color:#0f172a;letter-spacing:-0.02em;">
                    $ {{ number_format($totalCobrado, 0, ',', '.') }}
                </div>
            </div>
        </div>

        {{-- KPI Recaudado Total --}}
        <div style="background:#fff;border-radius:16px;padding:1.5rem;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;display:flex;align-items:center;gap:1.25rem;">
            <div style="font-size:2rem;background:#f0fdf4;color:#16a34a;width:55px;height:55px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                💵
            </div>
            <div>
                <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;font-weight:700;margin-bottom:0.2rem;">Total Recaudado</div>
                <div style="font-size:1.4rem;font-weight:800;color:#16a34a;letter-spacing:-0.02em;">
                    $ {{ number_format($totalRecaudado, 0, ',', '.') }}
                </div>
            </div>
        </div>

        {{-- KPI Cartera Pendiente --}}
        <div style="background:#fff;border-radius:16px;padding:1.5rem;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;display:flex;align-items:center;gap:1.25rem;">
            <div style="font-size:2rem;background:#fef2f2;color:#ef4444;width:55px;height:55px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                🚨
            </div>
            <div>
                <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;font-weight:700;margin-bottom:0.2rem;">Cartera Pendiente</div>
                <div style="font-size:1.4rem;font-weight:800;color:#ef4444;letter-spacing:-0.02em;">
                    $ {{ number_format($saldoPendiente, 0, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Detalle de bancos y formas de pago --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;@media(max-width:850px){grid-template-columns:1fr;}">
        
        {{-- Recaudo por Banco --}}
        <div style="background:#fff;border-radius:16px;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;padding:1.5rem;">
            <h3 style="font-size:0.9rem;font-weight:800;color:#0d2550;margin:0 0 1rem 0;">🏦 Recaudo por Banco Destino</h3>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.8rem;text-align:left;">
                    <thead>
                        <tr style="border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:700;">
                            <th style="padding:0.5rem 0;">Banco</th>
                            <th style="padding:0.5rem 0;text-align:right;">Total Recibido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($porBanco as $b)
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:0.6rem 0;font-weight:600;color:#334155;">{{ $b->banco ?: 'No especificado' }}</td>
                                <td style="padding:0.6rem 0;text-align:right;font-weight:700;color:#10b981;">$ {{ number_format($b->total, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" style="padding:1rem 0;text-align:center;color:#94a3b8;">No hay registros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recaudo por Forma de Pago --}}
        <div style="background:#fff;border-radius:16px;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;padding:1.5rem;">
            <h3 style="font-size:0.9rem;font-weight:800;color:#0d2550;margin:0 0 1rem 0;">💳 Recaudo por Forma de Pago</h3>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.8rem;text-align:left;">
                    <thead>
                        <tr style="border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:700;">
                            <th style="padding:0.5rem 0;">Forma de Pago</th>
                            <th style="padding:0.5rem 0;text-align:right;">Total Recibido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($porForma as $f)
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:0.6rem 0;font-weight:600;color:#334155;text-transform:capitalize;">{{ $f->forma_pago }}</td>
                                <td style="padding:0.6rem 0;text-align:right;font-weight:700;color:#10b981;">$ {{ number_format($f->total, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" style="padding:1rem 0;text-align:center;color:#94a3b8;">No hay registros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Historial Mensual Agregado --}}
    <div style="background:#fff;border-radius:16px;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;overflow:hidden;margin-bottom:2rem;">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #f1f5f9;background:#f8fafc;">
            <h3 style="font-size:0.9rem;font-weight:800;color:#0d2550;margin:0;">Historial Financiero por Meses</h3>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;text-align:left;font-size:0.8rem;">
                <thead>
                    <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;color:#475569;font-weight:700;text-transform:uppercase;font-size:0.7rem;letter-spacing:0.04em;">
                        <th style="padding:1rem 1.5rem;">Período</th>
                        <th style="padding:1rem 1rem;text-align:right;">Cobrado</th>
                        <th style="padding:1rem 1rem;text-align:right;">Recaudado</th>
                        <th style="padding:1rem 1rem;text-align:right;">Pendiente</th>
                        <th style="padding:1rem 1.5rem;text-align:center;width:250px;">Eficacia de Recaudo</th>
                    </tr>
                </thead>
                <tbody style="color:#334155;">
                    @forelse($historialMensual as $row)
                        @php
                            $porcentaje = $row->cobrado > 0 ? min(100, ($row->pagado / $row->cobrado) * 100) : 0;
                            $colorBarra = $porcentaje >= 95 ? '#16a34a' : ($porcentaje >= 50 ? '#d97706' : '#dc2626');
                        @endphp
                        <tr style="border-bottom:1px solid #f1f5f9;transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <td style="padding:1rem 1.5rem;font-weight:700;color:#0f172a;">{{ $row->periodo }}</td>
                            <td style="padding:1rem 1rem;text-align:right;font-weight:600;">$ {{ number_format($row->cobrado, 0, ',', '.') }}</td>
                            <td style="padding:1rem 1rem;text-align:right;color:#16a34a;font-weight:600;">$ {{ number_format($row->pagado, 0, ',', '.') }}</td>
                            <td style="padding:1rem 1rem;text-align:right;color:#ef4444;font-weight:600;">$ {{ number_format($row->pendiente, 0, ',', '.') }}</td>
                            <td style="padding:1rem 1.5rem;text-align:center;">
                                <div style="display:flex;align-items:center;gap:0.5rem;justify-content:center;">
                                    <div style="width:120px;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                                        <div style="width:{{ $porcentaje }}%;height:100%;background:{{ $colorBarra }};border-radius:4px;"></div>
                                    </div>
                                    <span style="font-size:0.75rem;font-weight:700;color:#475569;">{{ number_format($porcentaje, 1) }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding:3rem;text-align:center;color:#64748b;">
                                No hay registros contables en el sistema.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection
