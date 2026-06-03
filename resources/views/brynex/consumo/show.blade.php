@extends('layouts.app')
@section('modulo', 'BryNex Detalle Consumo')
@section('contenido')

<div style="max-width:1200px;margin:0 auto;">

    {{-- Botón para regresar --}}
    <div style="margin-bottom:1rem;">
        <a href="{{ route('brynex.consumo.index', ['mes'=>$mes, 'anio'=>$anio]) }}" style="color:#64748b;font-size:0.8rem;text-decoration:none;font-weight:700;">
            ← Volver al Dashboard
        </a>
    </div>

    {{-- Cabecera de Detalle --}}
    <div style="background:#fff;border-radius:16px;padding:1.5rem;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;margin-bottom:1.5rem;display:flex;justify-content:between;align-items:center;flex-wrap:wrap;gap:1.5rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <h1 style="font-size:1.4rem;font-weight:800;color:#0d2550;margin:0;">{{ $aliado->nombre }}</h1>
                @if($cobro_cerrado)
                    <span style="background:#f0fdf4;color:#16a34a;padding:0.2rem 0.5rem;border-radius:6px;font-size:0.68rem;font-weight:700;border:1px solid #bbf7d0;">
                        Cobro Cerrado
                    </span>
                @else
                    <span style="background:#eff6ff;color:#1d4ed8;padding:0.2rem 0.5rem;border-radius:6px;font-size:0.68rem;font-weight:700;border:1px solid #bfdbfe;">
                        Borrador / Abierto
                    </span>
                @endif
            </div>
            <p style="color:#64748b;font-size:0.82rem;margin:0.3rem 0 0 0;">
                NIT/Cédula: <strong>{{ $aliado->nit }}</strong> · Período: <strong>{{ $periodo }}</strong>
            </p>
        </div>

        <div style="display:flex;gap:0.5rem;">
            @if(!$cobro_cerrado)
                {{-- Botón Cerrar Cobro --}}
                <form action="{{ route('brynex.consumo.cerrar', [$aliado->id, $mes, $anio]) }}" method="POST" onsubmit="return confirm('¿Está seguro de cerrar y congelar el cobro de este mes? Se enviará una notificación al aliado y se congelarán los valores.')">
                    @csrf
                    <button type="submit" style="background:#1e3a8a;color:#fff;border:none;padding:0.6rem 1.25rem;border-radius:8px;font-size:0.8rem;font-weight:700;cursor:pointer;transition:all 0.15s;" onmouseover="this.style.background='#172554'" onmouseout="this.style.background='#1e3a8a'">
                        🔒 Cerrar y Congelar Cobro
                    </button>
                </form>
            @else
                {{-- Botón PDF Cuenta de Cobro --}}
                <a href="{{ route('brynex.consumo.pdf', $cobro_cerrado->id) }}" style="background:#dc2626;color:#fff;text-decoration:none;padding:0.6rem 1.25rem;border-radius:8px;font-size:0.8rem;font-weight:700;display:flex;align-items:center;gap:0.4rem;transition:all 0.15s;" onmouseover="this.style.background='#b91c1c'" onmouseout="this.style.background='#dc2626'">
                    📄 Descargar Cuenta de Cobro (PDF)
                </a>
            @endif
        </div>
    </div>

    {{-- Grid de Contenido Principal --}}
    <div style="display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start;margin-bottom:2rem;@media(max-width:960px){grid-template-columns:1fr;}">
        
        {{-- Bloque Izquierdo: Desglose del Período --}}
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
            
            {{-- Detalle de los Módulos --}}
            <div style="background:#fff;border-radius:16px;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;overflow:hidden;">
                <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #f1f5f9;background:#f8fafc;">
                    <h3 style="font-size:0.9rem;font-weight:800;color:#0d2550;margin:0;">Detalle de Consumos y Tarifas</h3>
                </div>

                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;text-align:left;font-size:0.82rem;">
                        <thead>
                            <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;color:#475569;font-weight:700;text-transform:uppercase;font-size:0.7rem;letter-spacing:0.04em;">
                                <th style="padding:1rem 1.5rem;">Módulo / Descripción</th>
                                <th style="padding:1rem 0.5rem;text-align:center;width:100px;">Cantidad</th>
                                <th style="padding:1rem 0.5rem;text-align:right;width:150px;">Tarifa Aplicada</th>
                                <th style="padding:1rem 1.5rem;text-align:right;width:120px;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody style="color:#334155;">
                            @foreach($modulos as $mod)
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:1.2rem 1.5rem;">
                                        <div style="font-weight:700;color:#0f172a;font-size:0.85rem;">
                                            {{ $mod['modulo_nombre'] }}
                                        </div>
                                        <div style="color:#64748b;font-size:0.72rem;margin-top:0.15rem;">
                                            {{ $mod['descripcion'] }}
                                        </div>
                                    </td>
                                    <td style="padding:1.2rem 0.5rem;text-align:center;font-weight:700;font-size:0.85rem;color:#0f172a;">
                                        {{ number_format($mod['cant_unidades'], 0, ',', '.') }}
                                    </td>
                                    <td style="padding:1.2rem 0.5rem;text-align:right;color:#475569;">
                                        @if($mod['tarifa_unidad'] > 0)
                                            $ {{ number_format($mod['tarifa_unidad'], 0, ',', '.') }} por contrato
                                        @elseif($mod['tarifa_minima'] > 0)
                                            $ {{ number_format($mod['tarifa_minima'], 0, ',', '.') }} (Mínimo General)
                                        @else
                                            Personalizada
                                        @endif
                                    </td>
                                    <td style="padding:1.2rem 1.5rem;text-align:right;font-weight:800;font-size:0.88rem;color:#0f172a;">
                                        $ {{ number_format($mod['subtotal'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background:#f8fafc;font-weight:800;font-size:0.9rem;border-top:1.5px solid #cbd5e1;">
                                <td colspan="3" style="padding:1.2rem 1.5rem;text-align:right;color:#475569;">Total Período:</td>
                                <td style="padding:1.2rem 1.5rem;text-align:right;color:#1e3a8a;font-size:1rem;">
                                    $ {{ number_format($total, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Sección de Pagos / Abonos Registrados --}}
            @if($cobro_cerrado)
                <div style="background:#fff;border-radius:16px;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;overflow:hidden;">
                    <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #f1f5f9;background:#f8fafc;display:flex;justify-content:between;align-items:center;">
                        <h3 style="font-size:0.9rem;font-weight:800;color:#0d2550;margin:0;">Historial de Pagos de este Período</h3>
                        <span style="font-size:0.75rem;font-weight:700;color:#16a34a;">
                            Total Recaudado: $ {{ number_format($cobro_cerrado->total_pagado, 0, ',', '.') }}
                        </span>
                    </div>

                    <div style="padding:1rem 1.5rem;">
                        @if($cobro_cerrado->pagos->isEmpty())
                            <div style="padding:2rem;text-align:center;color:#64748b;font-size:0.8rem;">
                                No se han registrado abonos o pagos para este mes.
                            </div>
                        @else
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;font-size:0.8rem;text-align:left;">
                                    <thead>
                                        <tr style="border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:700;">
                                            <th style="padding:0.5rem 0;">Fecha</th>
                                            <th style="padding:0.5rem 0;">Forma de Pago / Banco</th>
                                            <th style="padding:0.5rem 0;text-align:right;">Valor Recibido</th>
                                            <th style="padding:0.5rem 0;text-align:center;width:100px;">Soporte</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($cobro_cerrado->pagos as $pago)
                                            <tr style="border-bottom:1px solid #f1f5f9;">
                                                <td style="padding:0.75rem 0;font-weight:500;">
                                                    {{ \Carbon\Carbon::parse($pago->fecha_pago)->format('d/m/Y') }}
                                                </td>
                                                <td style="padding:0.75rem 0;">
                                                    <span style="text-transform:capitalize;font-weight:600;color:#334155;">{{ $pago->forma_pago }}</span>
                                                    @if($pago->banco)
                                                        <br><span style="color:#64748b;font-size:0.7rem;">{{ $pago->banco }}</span>
                                                    @endif
                                                    @if($pago->observacion)
                                                        <br><span style="color:#94a3b8;font-size:0.7rem;font-style:italic;">Obs: {{ $pago->observacion }}</span>
                                                    @endif
                                                </td>
                                                <td style="padding:0.75rem 0;text-align:right;font-weight:700;color:#10b981;">
                                                    $ {{ number_format($pago->valor, 0, ',', '.') }}
                                                </td>
                                                <td style="padding:0.75rem 0;text-align:center;">
                                                    @if($pago->soporte_url)
                                                        <a href="{{ $pago->soporte_url }}" target="_blank" style="background:#f1f5f9;color:#3b82f6;border:1px solid #cbd5e1;padding:0.25rem 0.5rem;border-radius:4px;text-decoration:none;font-weight:600;font-size:0.7rem;">
                                                            Ver Soporte
                                                        </a>
                                                    @else
                                                        <span style="color:#94a3b8;font-size:0.7rem;">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- Bloque Derecho: Cobrar Pago / Resumen Financiero general del Aliado --}}
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
            
            {{-- Formulario para Registrar Pago --}}
            @if($cobro_cerrado && $cobro_cerrado->saldo_pendiente > 0)
                <div style="background:#fff;border-radius:16px;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;padding:1.5rem;">
                    <h3 style="font-size:0.9rem;font-weight:800;color:#0d2550;margin:0 0 1rem 0;">🧾 Registrar Recaudo / Pago</h3>
                    
                    <form action="{{ route('brynex.consumo.pago', $cobro_cerrado->id) }}" method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:0.75rem;">
                        @csrf
                        
                        <div>
                            <label style="font-size:0.72rem;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:0.25rem;">Valor del Pago ($)</label>
                            <input type="number" name="valor" value="{{ $cobro_cerrado->saldo_pendiente }}" min="1" step="any" required style="width:100%;font-size:0.85rem;padding:0.45rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;color:#0f172a;font-weight:700;">
                            <span style="font-size:0.65rem;color:#64748b;margin-top:0.15rem;display:block;">El pago se distribuirá de forma cronológica desde el mes más antiguo.</span>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
                            <div>
                                <label style="font-size:0.72rem;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:0.25rem;">Fecha</label>
                                <input type="date" name="fecha_pago" value="{{ now()->toDateString() }}" required style="width:100%;font-size:0.8rem;padding:0.4rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;color:#334155;">
                            </div>
                            <div>
                                <label style="font-size:0.72rem;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:0.25rem;">Forma de Pago</label>
                                <select name="forma_pago" required style="width:100%;font-size:0.8rem;padding:0.4rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;color:#334155;">
                                    <option value="transferencia">Transferencia</option>
                                    <option value="efectivo">Efectivo</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label style="font-size:0.72rem;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:0.25rem;">Banco Destino</label>
                            <input type="text" name="banco" placeholder="Ej: Bancolombia" style="width:100%;font-size:0.8rem;padding:0.4rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;color:#334155;">
                        </div>

                        <div>
                            <label style="font-size:0.72rem;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:0.25rem;">Soporte de Pago (Imagen/PDF)</label>
                            <input type="file" name="soporte" accept="image/*,application/pdf" style="width:100%;font-size:0.75rem;color:#64748b;">
                        </div>

                        <div>
                            <label style="font-size:0.72rem;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:0.25rem;">Observaciones</label>
                            <textarea name="observacion" rows="2" placeholder="Notas adicionales..." style="width:100%;font-size:0.8rem;padding:0.4rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;color:#334155;resize:vertical;"></textarea>
                        </div>

                        <button type="submit" style="background:#10b981;color:#fff;border:none;padding:0.55rem;border-radius:8px;font-size:0.82rem;font-weight:700;cursor:pointer;margin-top:0.5rem;transition:background 0.15s;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'">
                            💾 Registrar y Aplicar Pago
                        </button>
                    </form>
                </div>
            @endif

            {{-- Historial de Cobros del Aliado --}}
            <div style="background:#fff;border-radius:16px;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;padding:1.5rem;">
                <h3 style="font-size:0.9rem;font-weight:800;color:#0d2550;margin:0 0 1rem 0;">📅 Historial de Cobros</h3>

                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    @forelse($historial as $h)
                        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:0.75rem;background:#f8fafc;display:flex;justify-content:between;align-items:center;">
                            <div>
                                <div style="font-weight:700;font-size:0.8rem;color:#0f172a;">{{ $h->periodo }}</div>
                                <div style="font-size:0.7rem;color:#64748b;margin-top:0.15rem;">
                                    Total: <strong>${{ number_format($h->total_cobrado, 0, ',', '.') }}</strong> · Saldo: <strong style="color:{{ $h->saldo_pendiente > 0 ? '#ef4444' : '#16a34a' }}">${{ number_format($h->saldo_pendiente, 0, ',', '.') }}</strong>
                                </div>
                            </div>
                            <div style="text-align:right;display:flex;flex-direction:column;align-items:end;gap:0.25rem;">
                                @if($h->estado === 'pendiente')
                                    <span style="background:#fee2e2;color:#991b1b;font-size:0.6rem;font-weight:700;padding:0.15rem 0.4rem;border-radius:4px;">Por Pagar</span>
                                @elseif($h->estado === 'parcial')
                                    <span style="background:#fef3c7;color:#92400e;font-size:0.6rem;font-weight:700;padding:0.15rem 0.4rem;border-radius:4px;">Abonado</span>
                                @elseif($h->estado === 'pagado')
                                    <span style="background:#dcfce7;color:#166534;font-size:0.6rem;font-weight:700;padding:0.15rem 0.4rem;border-radius:4px;">Pagado</span>
                                @endif
                                <a href="{{ route('brynex.consumo.pdf', $h->id) }}" style="font-size:0.68rem;color:#2563eb;text-decoration:none;font-weight:600;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                    Descargar PDF
                                </a>
                            </div>
                        </div>
                    @empty
                        <div style="text-align:center;color:#94a3b8;font-size:0.75rem;padding:1rem 0;">
                            No hay cobros cerrados registrados para este aliado.
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

</div>

@endsection
