@extends('layouts.app')
@section('modulo', 'Caja Menor')

@php
$fmt = fn($v) => '$'.number_format($v ?? 0, 0, ',', '.');
$esAdmin = auth()->user()->hasRole(['admin','superadmin']);
@endphp

@section('contenido')
<style>
.cm-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1rem}
table.tbl{width:100%;border-collapse:collapse;font-size:.8rem}
.tbl th{background:#0f172a;color:#94a3b8;font-size:.63rem;text-transform:uppercase;padding:.4rem .6rem;position:sticky;top:0}
.tbl td{padding:.38rem .6rem;border-bottom:1px solid #f1f5f9}
.tbl tr:hover td{background:#f8fafc}
.num{text-align:right;font-family:monospace}
</style>

<div class="cm-header">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
        <div>
            <a href="{{ route('admin.cuadre-diario.index') }}" style="color:#94a3b8;font-size:.78rem;text-decoration:none">← Cuadre Diario</a>
            <div style="font-size:1.1rem;font-weight:800;margin-top:.2rem">💵 Gestión Caja Menor</div>
        </div>
        @if($esAdmin)
        <button onclick="document.getElementById('modal-cm').style.display='flex'"
                style="padding:.4rem .9rem;background:#f59e0b;color:#fff;border:none;border-radius:7px;font-size:.8rem;font-weight:700;cursor:pointer">
            + Asignar Caja Menor
        </button>
        @endif
    </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;color:#166534;padding:.6rem 1rem;border-radius:8px;margin-bottom:.8rem;font-size:.83rem">
    ✅ {{ session('success') }}
</div>
@endif

<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden">
    @if($asignaciones->isEmpty())
    <div style="padding:2rem;text-align:center;color:#94a3b8">Sin asignaciones de caja menor</div>
    @else
    <div style="overflow-x:auto">
    <table class="tbl">
        <thead><tr>
            <th>Usuario</th>
            <th class="num">Monto</th>
            <th>Desde</th>
            <th>Asignado por</th>
            <th>Estado</th>
            <th>Observación</th>
        </tr></thead>
        <tbody>
        @foreach($asignaciones as $a)
        <tr>
            <td style="font-weight:600">{{ $a->usuario?->nombre ?? '—' }}</td>
            <td class="num" style="font-weight:800;font-size:.9rem;color:#15803d">{{ $fmt($a->monto) }}</td>
            <td style="font-size:.75rem">{{ $a->fecha->format('d/m/Y') }}</td>
            <td style="font-size:.72rem;color:#64748b">{{ $a->asignadoPor?->nombre ?? '—' }}</td>
            <td>
                @if($a->activo)
                <span style="padding:.1rem .4rem;border-radius:20px;font-size:.67rem;font-weight:700;background:#dcfce7;color:#15803d">✅ Activa</span>
                @else
                <span style="padding:.1rem .4rem;border-radius:20px;font-size:.67rem;font-weight:700;background:#f1f5f9;color:#64748b">Inactiva</span>
                @endif
            </td>
            <td style="font-size:.75rem;color:#64748b">{{ $a->observacion ?? '—' }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>

{{-- Modal: Asignar Caja Menor --}}
@if($esAdmin)
<div id="modal-cm"
     onclick="if(event.target.id==='modal-cm')this.style.display='none'"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:14px;width:min(420px,96vw);box-shadow:0 20px 50px rgba(0,0,0,.3)">
        <div style="background:#0f172a;padding:.8rem 1.1rem;border-radius:14px 14px 0 0;display:flex;justify-content:space-between;align-items:center">
            <span style="color:#fff;font-weight:700">💵 Asignar Caja Menor</span>
            <button onclick="document.getElementById('modal-cm').style.display='none'"
                    style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:5px;width:26px;height:26px;cursor:pointer;font-weight:700">×</button>
        </div>
        <form method="POST" action="{{ route('admin.caja-menor.store') }}" style="padding:1.2rem;display:flex;flex-direction:column;gap:.9rem">
            @csrf
            <div>
                <label style="font-size:.76rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem">Usuario *</label>
                <select name="usuario_id" required
                        style="width:100%;padding:.4rem .6rem;border:1.5px solid #e2e8f0;border-radius:7px;font-size:.82rem">
                    <option value="">— Seleccionar —</option>
                    @foreach($usuarios as $u)
                    <option value="{{ $u->id }}">{{ $u->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem">
                <div>
                    <label style="font-size:.76rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem">Monto *</label>
                    <input type="number" name="monto" required min="0" placeholder="200000"
                           style="width:100%;padding:.4rem .6rem;border:1.5px solid #e2e8f0;border-radius:7px;font-size:.82rem">
                </div>
                <div>
                    <label style="font-size:.76rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem">Desde *</label>
                    <input type="date" name="fecha" value="{{ today()->toDateString() }}" required
                           style="width:100%;padding:.4rem .6rem;border:1.5px solid #e2e8f0;border-radius:7px;font-size:.82rem">
                </div>
            </div>
            <div>
                <label style="font-size:.76rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem">Observación</label>
                <input type="text" name="observacion" placeholder="Ej: Caja semanal"
                       style="width:100%;padding:.4rem .6rem;border:1.5px solid #e2e8f0;border-radius:7px;font-size:.82rem">
            </div>
            <button type="submit"
                    style="background:#065f46;color:#fff;border:none;border-radius:8px;padding:.6rem;font-size:.88rem;font-weight:700;cursor:pointer">
                ✅ Asignar Caja Menor
            </button>
        </form>
    </div>
</div>
@endif
@endsection
