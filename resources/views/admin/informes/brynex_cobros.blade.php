@extends('layouts.app')
@section('modulo', 'Cobros Brynex')
@section('contenido')

<div style="max-width:1000px;margin:0 auto;">

    {{-- Botón de regreso al Hub de Informes --}}
    <div style="margin-bottom:1rem;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:0.8rem;text-decoration:none;font-weight:700;">
            ← Volver al Centro de Informes
        </a>
    </div>

    {{-- Cabecera --}}
    <div style="background:#fff;border-radius:16px;padding:1.5rem 2rem;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;margin-bottom:1.5rem;display:flex;justify-content:between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.4rem;font-weight:800;color:#0d2550;margin:0;">📋 Cuentas de Cobro BryNex</h1>
            <p style="color:#64748b;font-size:0.82rem;margin:0.2rem 0 0 0;">
                Historial de cobros mensuales de BryNex a tu empresa por concepto de uso del sistema.
            </p>
        </div>

        @php
            $carteraTotal = $cobros->sum(fn($c) => $c->saldo_pendiente);
        @endphp

        <div style="text-align:right;background:#fee2e2;border:1px solid #fca5a5;border-radius:12px;padding:0.6rem 1.25rem;">
            <div style="font-size:0.65rem;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:0.04em;">Deuda Total Pendiente</div>
            <div style="font-size:1.2rem;font-weight:800;color:#b91c1c;">
                $ {{ number_format($carteraTotal, 0, ',', '.') }}
            </div>
        </div>
    </div>

    {{-- Tabla de Cobros --}}
    <div style="background:#fff;border-radius:16px;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;text-align:left;font-size:0.82rem;">
                <thead>
                    <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;color:#475569;font-weight:700;text-transform:uppercase;font-size:0.7rem;letter-spacing:0.04em;">
                        <th style="padding:1rem 1.5rem;">Período</th>
                        <th style="padding:1rem 1rem;text-align:center;">Fecha Cierre</th>
                        <th style="padding:1rem 1rem;text-align:right;">Total Cobrado</th>
                        <th style="padding:1rem 1rem;text-align:right;">Recaudado (Abonos)</th>
                        <th style="padding:1rem 1rem;text-align:right;">Saldo Pendiente</th>
                        <th style="padding:1rem 1rem;text-align:center;">Estado</th>
                        <th style="padding:1rem 1.5rem;text-align:center;width:120px;">Cuenta de Cobro</th>
                    </tr>
                </thead>
                <tbody style="color:#334155;">
                    @forelse($cobros as $cobro)
                        <tr style="border-bottom:1px solid #f1f5f9;transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <td style="padding:1rem 1.5rem;font-weight:700;color:#0f172a;font-size:0.85rem;">
                                {{ $cobro->periodo }}
                            </td>
                            <td style="padding:1rem 1rem;text-align:center;color:#475569;">
                                {{ \Carbon\Carbon::parse($cobro->fecha_cierre)->format('d/m/Y') }}
                            </td>
                            <td style="padding:1rem 1rem;text-align:right;font-weight:600;color:#0f172a;">
                                $ {{ number_format($cobro->total_cobrado, 0, ',', '.') }}
                            </td>
                            <td style="padding:1rem 1rem;text-align:right;color:#16a34a;font-weight:600;">
                                $ {{ number_format($cobro->total_pagado, 0, ',', '.') }}
                            </td>
                            <td style="padding:1rem 1rem;text-align:right;font-weight:700;color:{{ $cobro->saldo_pendiente > 0 ? '#ef4444' : '#16a34a' }};">
                                $ {{ number_format($cobro->saldo_pendiente, 0, ',', '.') }}
                            </td>
                            <td style="padding:1rem 1rem;text-align:center;">
                                @if($cobro->estado === 'pendiente')
                                    <span style="background:#fee2e2;color:#991b1b;padding:0.2rem 0.5rem;border-radius:6px;font-size:0.7rem;font-weight:700;border:1px solid #fca5a5;">
                                        Por Pagar
                                    </span>
                                @elseif($cobro->estado === 'parcial')
                                    <span style="background:#fef3c7;color:#92400e;padding:0.2rem 0.5rem;border-radius:6px;font-size:0.7rem;font-weight:700;border:1px solid #fde68a;">
                                        Abonado
                                    </span>
                                @elseif($cobro->estado === 'pagado')
                                    <span style="background:#dcfce7;color:#166534;padding:0.2rem 0.5rem;border-radius:6px;font-size:0.7rem;font-weight:700;border:1px solid #bbf7d0;">
                                        Pagado
                                    </span>
                                @endif
                            </td>
                            <td style="padding:1rem 1.5rem;text-align:center;">
                                <a href="{{ route('admin.informes.brynex_cobros.pdf', $cobro->id) }}" style="background:#dc2626;color:#fff;text-decoration:none;padding:0.35rem 0.65rem;border-radius:6px;font-weight:600;font-size:0.75rem;display:inline-flex;align-items:center;gap:0.25rem;transition:background 0.15s;" onmouseover="this.style.background='#b91c1c'" onmouseout="this.style.background='#dc2626'">
                                    📄 PDF
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding:3rem;text-align:center;color:#64748b;">
                                No hay cuentas de cobro generadas por BryNex para tu empresa.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Instrucciones rápidas --}}
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:1.25rem;margin-top:1.5rem;">
        <h4 style="font-size:0.82rem;font-weight:700;color:#1e40af;margin:0 0 0.4rem 0;">ℹ️ Instrucciones de Pago de Facturas de Brynex</h4>
        <p style="font-size:0.75rem;color:#1e3a8a;margin:0;line-height:1.45;">
            Los cobros por uso son facturados de forma mensual y se calculan automáticamente con base en el número de contratos de cotizantes activos en el mes y el consumo de la API de WhatsApp Business. 
            Realiza el pago transfiriendo a la cuenta bancaria de Brynex indicada en el PDF y repórtalo enviando el comprobante.
        </p>
    </div>

</div>

@endsection
