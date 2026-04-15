@extends('layouts.app')
@section('modulo', 'Contratos')

@section('contenido')
<div style="max-width:1100px;margin:0 auto;">

{{-- Encabezado --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
    <div>
        <h1 style="font-size:1.3rem;font-weight:700;color:#0f172a;margin:0;">📋 Contratos</h1>
        <p style="font-size:0.82rem;color:#64748b;margin:0;">Gestión de contratos de seguridad social</p>
    </div>
    <a href="{{ route('admin.contratos.create') }}"
       style="padding:0.6rem 1.25rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border-radius:8px;text-decoration:none;font-size:0.85rem;font-weight:600;box-shadow:0 3px 10px rgba(37,99,235,0.3);">
        + Nuevo Contrato
    </a>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.85rem;">
    ✅ {{ session('success') }}
</div>
@endif

{{-- Filtros --}}
<form method="GET" action="{{ route('admin.contratos.index') }}"
    style="background:#fff;border-radius:10px;border:1px solid #e2e8f0;padding:1rem;margin-bottom:1.25rem;display:flex;gap:0.75rem;align-items:center;">
    <input type="text" name="q" value="{{ $buscar }}" placeholder="Buscar por cédula o nombre..."
        style="flex:1;padding:0.55rem 0.75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;">
    <select name="estado" style="padding:0.55rem 0.75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;background:#fff;">
        <option value="vigente" {{ $estado==='vigente' ? 'selected' : '' }}>Vigentes</option>
        <option value="retirado" {{ $estado==='retirado' ? 'selected' : '' }}>Retirados</option>
        <option value="todos" {{ $estado==='todos' ? 'selected' : '' }}>Todos</option>
    </select>
    <button type="submit" style="padding:0.55rem 1.25rem;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;">
        Buscar
    </button>
    <a href="{{ route('admin.contratos.index') }}" style="padding:0.55rem 1rem;border:1px solid #cbd5e1;border-radius:8px;color:#475569;text-decoration:none;font-size:0.85rem;">
        Limpiar
    </a>
</form>

{{-- Tabla --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:0.83rem;">
        <thead>
            <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                <th style="padding:0.75rem 1rem;text-align:left;font-weight:600;color:#475569;">#</th>
                <th style="padding:0.75rem 1rem;text-align:left;font-weight:600;color:#475569;">Cliente</th>
                <th style="padding:0.75rem 1rem;text-align:left;font-weight:600;color:#475569;">Razón Social</th>
                <th style="padding:0.75rem 1rem;text-align:left;font-weight:600;color:#475569;">Plan</th>
                <th style="padding:0.75rem 1rem;text-align:left;font-weight:600;color:#475569;">Modalidad</th>
                <th style="padding:0.75rem 1rem;text-align:left;font-weight:600;color:#475569;">F. Ingreso</th>
                <th style="padding:0.75rem 1rem;text-align:center;font-weight:600;color:#475569;">Estado</th>
                <th style="padding:0.75rem 1rem;text-align:center;font-weight:600;color:#475569;">Acción</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contratos as $contrato)
            <tr style="border-bottom:1px solid #f1f5f9;transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                <td style="padding:0.65rem 1rem;font-family:monospace;color:#64748b;">{{ $contrato->id }}</td>
                <td style="padding:0.65rem 1rem;">
                    @if($contrato->cliente)
                    <div style="font-weight:600;color:#0f172a;">
                        {{ $contrato->cliente->primer_nombre }} {{ $contrato->cliente->primer_apellido }}
                    </div>
                    <div style="font-size:0.75rem;color:#64748b;">CC {{ number_format($contrato->cedula,0,',','.') }}</div>
                    @else
                    <span style="color:#94a3b8;">CC {{ number_format($contrato->cedula,0,',','.') }}</span>
                    @endif
                </td>
                <td style="padding:0.65rem 1rem;color:#475569;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    {{ $contrato->razonSocial?->razon_social ?? '—' }}
                </td>
                <td style="padding:0.65rem 1rem;">
                    @if($contrato->plan)
                    <span style="background:#eff6ff;color:#1d4ed8;padding:0.2rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:600;">
                        {{ $contrato->plan->nombre }}
                    </span>
                    @else
                    <span style="color:#94a3b8;">—</span>
                    @endif
                </td>
                <td style="padding:0.65rem 1rem;color:#475569;">{{ $contrato->tipoModalidad?->nombre ?? '—' }}</td>
                <td style="padding:0.65rem 1rem;color:#475569;">
                    {{ $contrato->fecha_ingreso ? $contrato->fecha_ingreso->format('d/m/Y') : '—' }}
                </td>
                <td style="padding:0.65rem 1rem;text-align:center;">
                    <span style="padding:0.2rem 0.75rem;border-radius:999px;font-size:0.73rem;font-weight:700;
                        background:{{ $contrato->estaVigente() ? '#dcfce7' : '#fee2e2' }};
                        color:{{ $contrato->estaVigente() ? '#166534' : '#991b1b' }};">
                        {{ strtoupper($contrato->estado) }}
                    </span>
                </td>
                <td style="padding:0.65rem 1rem;text-align:center;">
                    <a href="{{ route('admin.contratos.edit', $contrato->id) }}"
                        style="font-size:0.8rem;color:#2563eb;text-decoration:none;padding:0.25rem 0.75rem;border:1px solid #bfdbfe;border-radius:6px;">
                        ✏️ Editar
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="padding:3rem;text-align:center;color:#94a3b8;">
                    No hay contratos {{ $estado !== 'todos' ? $estado.'s' : '' }} registrados.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Paginación --}}
@if($contratos->hasPages())
<div style="margin-top:1rem;display:flex;justify-content:center;">
    {{ $contratos->links() }}
</div>
@endif

{{-- Resumen --}}
<div style="margin-top:1rem;font-size:0.8rem;color:#64748b;text-align:right;">
    Mostrando {{ $contratos->firstItem() }}–{{ $contratos->lastItem() }} de {{ $contratos->total() }} contratos
</div>

</div>
@endsection
