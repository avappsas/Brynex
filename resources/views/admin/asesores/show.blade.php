@extends('layouts.app')
@section('modulo','Detalle Asesor')

@section('contenido')
<div style="display:grid;grid-template-columns:300px 1fr;gap:2rem;">

    {{-- Columna Izquierda: Info Asesor --}}
    <div>
        <div style="background:#fff;border-radius:14px;padding:1.5rem;box-shadow:0 1px 8px rgba(0,0,0,0.06);margin-bottom:1.5rem;">
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
                <div style="height:50px;width:50px;border-radius:12px;background:linear-gradient(135deg,#c4b5fd,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;">
                    {{ substr($asesor->nombre, 0, 1) }}
                </div>
                <div>
                    <h2 style="font-size:1.1rem;font-weight:700;color:#0f172a;margin:0;">{{ $asesor->nombre }}</h2>
                    <div style="font-size:0.8rem;color:#64748b;">CC: {{ $asesor->cedula }}</div>
                </div>
            </div>

            <div style="font-size:0.85rem;color:#475569;margin-bottom:0.5rem;">📱 {{ $asesor->celular ?? $asesor->telefono ?? 'Sin teléfono' }}</div>
            <div style="font-size:0.85rem;color:#475569;margin-bottom:0.5rem;">📧 {{ $asesor->correo ?? 'Sin correo' }}</div>
            <div style="font-size:0.85rem;color:#475569;margin-bottom:1.5rem;">📍 {{ $asesor->ciudad ?? 'Ciudad N/D' }}</div>

            <div style="background:#f8fafc;padding:1rem;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:1rem;">
                <div style="font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;margin-bottom:0.5rem;">Comisiones Configuradas</div>
                <div style="font-size:1.05rem;font-weight:700;color:#8b5cf6;margin-bottom:0.2rem;">{{ $asesor->comisionAfiliacionLabel() }} <span style="font-size:0.75rem;font-weight:400;color:#64748b;">/ afil</span></div>
                <div style="font-size:1.05rem;font-weight:700;color:#0891b2;">{{ $asesor->comisionAdmonLabel() }} <span style="font-size:0.75rem;font-weight:400;color:#64748b;">/ admin (mensual)</span></div>
            </div>

            <a href="{{ route('admin.asesores.edit', $asesor) }}" style="display:block;text-align:center;background:#f1f5f9;color:#334155;padding:0.6rem;border-radius:8px;text-decoration:none;font-size:0.85rem;font-weight:600;">
                ✏️ Editar Asesor
            </a>
        </div>

        {{-- Resumen Financiero --}}
        <div style="background:#fff;border-radius:14px;padding:1.5rem;box-shadow:0 1px 8px rgba(0,0,0,0.06);">
            <h3 style="font-size:0.95rem;font-weight:700;color:#0f172a;margin-bottom:1rem;">Resumen Financiero</h3>
            
            <div style="margin-bottom:1rem;">
                <div style="font-size:0.75rem;font-weight:600;color:#64748b;">Total Pendiente Pagar</div>
                <div style="font-size:1.5rem;font-weight:700;color:#dc2626;">${{ number_format($asesor->totalPendiente(), 0, ',', '.') }}</div>
            </div>
            
            <div>
                <div style="font-size:0.75rem;font-weight:600;color:#64748b;">Histórico Pagado</div>
                <div style="font-size:1.25rem;font-weight:700;color:#16a34a;">${{ number_format($asesor->totalPagado(), 0, ',', '.') }}</div>
            </div>
            
            @if($asesor->cuenta_bancaria)
            <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid #f1f5f9;font-size:0.8rem;color:#475569;">
                <strong>🏦 Cuenta:</strong> {{ $asesor->cuenta_bancaria }}
            </div>
            @endif
        </div>
    </div>

    {{-- Columna Derecha: Historial Comisiones y Formulario Manual --}}
    <div>
        @if(session('success'))
            <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:8px;color:#065f46;padding:0.6rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
                ✅ {{ session('success') }}
            </div>
        @endif

        {{-- Formulario Registro Manual --}}
        <div style="background:#fff;border-radius:14px;padding:1.5rem;box-shadow:0 1px 8px rgba(0,0,0,0.06);margin-bottom:1.5rem;">
            <h3 style="font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:1rem;">➕ Registrar Comisión Manual</h3>
            <form method="POST" action="{{ route('admin.asesores.comisiones.store', $asesor) }}">
                @csrf
                <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end;">
                    <div style="flex:1;min-width:150px;">
                        <label style="display:block;font-size:0.75rem;font-weight:600;color:#475569;margin-bottom:0.3rem;">Periodo (Mes/Año) *</label>
                        <input type="date" name="periodo" value="{{ now()->startOfMonth()->format('Y-m-d') }}" required
                            style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;">
                    </div>
                    <div style="flex:1;min-width:150px;">
                        <label style="display:block;font-size:0.75rem;font-weight:600;color:#475569;margin-bottom:0.3rem;">Tipo *</label>
                        <select name="tipo" required style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;background:#fff;">
                            <option value="afiliacion">Afiliación</option>
                            <option value="administracion">Administración</option>
                        </select>
                    </div>
                    <div style="flex:1;min-width:120px;">
                        <label style="display:block;font-size:0.75rem;font-weight:600;color:#475569;margin-bottom:0.3rem;">Valor Comisión $ *</label>
                        <input type="number" step="0.01" min="0" name="valor_comision" required
                            style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;">
                        <input type="hidden" name="valor_base" value="0">
                        <input type="hidden" name="tipo_calculo" value="fijo">
                    </div>
                    <div style="flex:2;min-width:200px;">
                        <label style="display:block;font-size:0.75rem;font-weight:600;color:#475569;margin-bottom:0.3rem;">Contrato Ref / Observación</label>
                        <input type="text" name="observacion" placeholder="Opcional"
                            style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;">
                    </div>
                    <div>
                        <button type="submit" style="background:#2563eb;color:#fff;border:none;padding:0.55rem 1rem;border-radius:6px;font-size:0.85rem;font-weight:600;cursor:pointer;">
                            Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Historial --}}
        <div style="background:#fff;border-radius:14px;padding:1.5rem;box-shadow:0 1px 8px rgba(0,0,0,0.06);">
            <h3 style="font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:1rem;">📋 Historial de Comisiones</h3>
            
            <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                <thead>
                    <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                        <th style="padding:0.75rem 1rem;text-align:left;color:#475569;font-weight:600;">Periodo / Tipo</th>
                        <th style="padding:0.75rem 1rem;text-align:right;color:#475569;font-weight:600;">Comisión</th>
                        <th style="padding:0.75rem 1rem;text-align:center;color:#475569;font-weight:600;">Estado</th>
                        <th style="padding:0.75rem 1rem;text-align:center;color:#475569;font-weight:600;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($comisiones as $com)
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:0.75rem 1rem;">
                            <div style="font-weight:600;color:#0f172a;text-transform:capitalize;">{{ $com->periodoLabel() }}</div>
                            <div style="display:flex;align-items:center;gap:0.4rem;margin-top:0.2rem;">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $com->tipoColor() }};"></span>
                                <span style="font-size:0.75rem;color:#64748b;">{{ $com->tipoLabel() }}</span>
                                @if($com->contrato_ref)
                                <span style="font-size:0.7rem;background:#e2e8f0;padding:1px 4px;border-radius:4px;">Ref: {{ $com->contrato_ref }}</span>
                                @endif
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;font-weight:700;color:#0f172a;">
                            ${{ number_format($com->valor_comision, 0, ',', '.') }}
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            @if($com->pagado)
                                <span style="background:#dcfce7;color:#16a34a;padding:0.2rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:600;">✅ Pagado ({{ \Carbon\Carbon::parse($com->fecha_pago)->format('d/m/Y') }})</span>
                            @else
                                <span style="background:#fee2e2;color:#dc2626;padding:0.2rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:600;">❌ Pendiente</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            @if(!$com->pagado)
                            <form method="POST" action="{{ route('admin.asesores.comisiones.pagar', $com) }}" style="display:inline;" onsubmit="return confirm('¿Marcar como pagada?');">
                                @csrf @method('PATCH')
                                <button title="Marcar pagado" style="background:#dbeafe;color:#2563eb;border:none;padding:0.3rem 0.6rem;border-radius:6px;font-size:0.75rem;font-weight:600;cursor:pointer;">
                                    💰 Pagar
                                </button>
                            </form>
                            @else
                                <span style="color:#cbd5e1;">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center;padding:2rem;color:#94a3b8;">No hay comisiones registradas.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            
            <div style="margin-top:1rem;">
                {{ $comisiones->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
