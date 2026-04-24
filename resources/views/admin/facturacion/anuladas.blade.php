@extends('layouts.app')
@section('modulo','Recibos Anulados')

@php
use Carbon\Carbon;
$meses=['Enero','Febrero','Marzo','Abril','Mayo','Junio',
        'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$fmt = fn($v) => '$'.number_format($v ?? 0, 0, ',', '.');
@endphp

@section('contenido')
<style>
.an-wrap { max-width:1100px;margin:0 auto }
.an-hdr  { background:linear-gradient(135deg,#7f1d1d,#991b1b);color:#fff;
           border-radius:12px 12px 0 0;padding:.85rem 1.2rem;
           display:flex;justify-content:space-between;align-items:center }
.an-body { background:#fff;border-radius:0 0 12px 12px;
           box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden }
.tbl { width:100%;border-collapse:collapse;font-size:.78rem }
.tbl th { background:#1e293b;color:#94a3b8;font-size:.64rem;text-transform:uppercase;
          padding:.4rem .6rem;white-space:nowrap }
.tbl td { padding:.38rem .6rem;border-bottom:1px solid #f1f5f9;vertical-align:middle }
.tbl tbody tr:hover td { background:#fef2f2 }
.n-r { text-align:right;font-family:monospace }
.badge-anul { background:#fee2e2;color:#991b1b;padding:.18rem .55rem;
              border-radius:20px;font-size:.68rem;font-weight:700 }
.btn-a { padding:.35rem .8rem;border-radius:6px;border:none;font-weight:600;
         cursor:pointer;font-size:.78rem;text-decoration:none;display:inline-block }
.filtro { background:#f8fafc;padding:.75rem 1rem;display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end }
.filtro input,.filtro select { border:1px solid #e2e8f0;border-radius:6px;
    padding:.32rem .6rem;font-size:.8rem;background:#fff }
</style>

<div class="an-wrap no-print" style="margin-bottom:.6rem;display:flex;justify-content:flex-end;gap:.5rem">
    <a href="{{ route('admin.facturacion.index') }}" class="btn-a" style="background:#f1f5f9;color:#475569">← Volver al listado</a>
</div>

<div class="an-wrap">
<div class="an-hdr">
    <div>
        <div style="font-size:.72rem;color:#fca5a5;margin-bottom:.1rem">RECIBOS ANULADOS</div>
        <div style="font-weight:800;font-size:1.05rem">🗑 Historial de Anulaciones</div>
    </div>
    <span class="badge-anul">{{ $facturas->total() }} registros</span>
</div>

{{-- Filtros --}}
<form method="GET" action="{{ route('admin.facturacion.anuladas') }}" class="filtro">
    <div>
        <label style="font-size:.68rem;font-weight:700;color:#64748b;display:block;margin-bottom:.18rem">Buscar</label>
        <input name="buscar" placeholder="N° recibo, cédula o motivo..." value="{{ request('buscar') }}" style="width:220px">
    </div>
    <div>
        <label style="font-size:.68rem;font-weight:700;color:#64748b;display:block;margin-bottom:.18rem">Mes</label>
        <select name="mes">
            <option value="">Todos</option>
            @foreach($meses as $i => $m)
            <option value="{{ $i+1 }}" {{ request('mes') == $i+1 ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label style="font-size:.68rem;font-weight:700;color:#64748b;display:block;margin-bottom:.18rem">Año</label>
        <input name="anio" type="number" value="{{ request('anio', date('Y')) }}" style="width:80px">
    </div>
    <button type="submit" class="btn-a" style="background:#0f172a;color:#fff">🔍 Filtrar</button>
    <a href="{{ route('admin.facturacion.anuladas') }}" class="btn-a" style="background:#f1f5f9;color:#475569">✕ Limpiar</a>
</form>

<div class="an-body">
@if($facturas->count() === 0)
<div style="padding:2.5rem;text-align:center;color:#94a3b8">
    <div style="font-size:2rem;margin-bottom:.5rem">🎉</div>
    <div style="font-weight:700">No hay recibos anulados</div>
    <div style="font-size:.8rem;margin-top:.25rem">Ninguna factura ha sido anulada con los filtros actuales.</div>
</div>
@else
<div style="overflow-x:auto">
<table class="tbl">
<thead>
<tr>
    <th>N° Recibo</th>
    <th>Período</th>
    <th>Cédula</th>
    <th style="text-align:left">Trabajador</th>
    <th style="text-align:left">Razón Social</th>
    <th class="n-r">Total</th>
    <th style="text-align:left">Motivo</th>
    <th style="text-align:left">Anulado</th>
    <th style="text-align:left">Por</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
@foreach($facturas as $f)
@php
$cli  = $f->contrato?->cliente;
$nom  = trim(($cli?->primer_nombre ?? '').' '.($cli?->primer_apellido ?? '')) ?: 'CC '.$f->cedula;
$rs   = $f->razonSocial?->razon_social ?? $f->contrato?->razonSocial?->razon_social ?? '—';
$per  = ($meses[$f->mes - 1] ?? '?').' '.$f->anio;
$anulador = \App\Models\User::find($f->anulado_por);
@endphp
<tr>
    <td style="font-weight:800;color:#991b1b">{{ str_pad($f->numero_factura,6,'0',STR_PAD_LEFT) }}</td>
    <td style="white-space:nowrap;color:#475569">{{ $per }}</td>
    <td style="font-family:monospace;font-size:.74rem">{{ number_format($f->cedula,0,'','.') }}</td>
    <td>
        <div style="font-weight:600;font-size:.8rem">{{ $nom }}</div>
        <div style="font-size:.66rem;color:#94a3b8">{{ ucfirst($f->tipo ?? '') }}</div>
    </td>
    <td style="font-size:.76rem;color:#1d4ed8;font-weight:600">{{ $rs }}</td>
    <td class="n-r" style="font-weight:700">{{ $fmt($f->total) }}</td>
    <td style="max-width:220px;font-size:.74rem;color:#991b1b">{{ $f->motivo_anulacion ?? '—' }}</td>
    <td style="white-space:nowrap;font-size:.73rem;color:#64748b">
        {{ $f->deleted_at ? sqldate($f->deleted_at)->format('d/m/Y H:i') : '—' }}
    </td>
    <td style="font-size:.74rem">{{ $anulador?->nombre ?? $anulador?->name ?? 'Sistema' }}</td>
    <td style="white-space:nowrap">
        @if(auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('superadmin'))
        <button class="btn-a" style="background:#dcfce7;color:#15803d;font-size:.72rem"
                onclick="restaurar({{ $f->id }}, '{{ str_pad($f->numero_factura,6,'0',STR_PAD_LEFT) }}')">
            🔄 Restaurar
        </button>
        @endif
        {{-- Ver el recibo (con trashed) --}}
        <a href="{{ route('admin.facturacion.recibo', $f->id) }}?trashed=1"
           class="btn-a" style="background:#f1f5f9;color:#475569;font-size:.72rem"
           target="_blank">👁 Ver</a>
    </td>
</tr>
@endforeach
</tbody>
</table>
</div>

{{-- Paginación --}}
<div style="padding:.75rem 1rem">
    {{ $facturas->links() }}
</div>
@endif
</div>{{-- /an-body --}}
</div>{{-- /an-wrap --}}

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

async function restaurar(id, num) {
    if (!confirm(`¿Restaurar el recibo ${num}? Volverá a aparecer en el historial normal.`)) return;
    const res  = await fetch(`/admin/facturacion/${id}/restaurar`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
    });
    const data = await res.json();
    if (data.ok) {
        alert(data.mensaje);
        window.location.reload();
    } else {
        alert(data.message || 'Error al restaurar.');
    }
}
</script>
@endsection
