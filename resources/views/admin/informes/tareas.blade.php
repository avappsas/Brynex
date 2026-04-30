@extends('layouts.app')
@section('modulo','Resumen Tareas')
@section('contenido')
@php
$tiposLabel=['traslado_eps'=>'Traslado EPS','inclusion_beneficiarios'=>'Inclusión Benef.','exclusion'=>'Exclusión','subsidios'=>'Subsidios','actualizar_documentos'=>'Actualizar Docs.','devolucion_aportes'=>'Dev. Aportes','solicitud_documentos'=>'Solicitud Docs.','otros'=>'Otros'];
@endphp
<div style="max-width:1000px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">📌 Resumen de Tareas</h1>
    </div>

    {{-- KPIs --}}
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:1.5rem;">
        @foreach([['Total',$kpis['total'],'#64748b'],['Pendientes',$kpis['pendiente'],'#f59e0b'],['En Gestión',$kpis['en_gestion'],'#3b82f6'],['En Espera',$kpis['en_espera'],'#f97316'],['Cerradas',$kpis['cerradas'],'#10b981']] as [$l,$v,$c])
        <div style="background:#fff;border-radius:12px;padding:1rem;box-shadow:0 1px 6px rgba(0,0,0,.06);border-left:4px solid {{ $c }};text-align:center;">
            <div style="font-size:1.6rem;font-weight:800;color:{{ $c }};">{{ $v }}</div>
            <div style="font-size:.78rem;font-weight:600;color:#334155;margin-top:.2rem;">{{ $l }}</div>
        </div>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
        {{-- Por tipo --}}
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
            <div style="padding:.85rem 1rem;background:#f8fafc;border-bottom:2px solid #e2e8f0;font-size:.72rem;font-weight:700;text-transform:uppercase;color:#64748b;">Por Tipo (activas)</div>
            @forelse($porTipo as $r)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.65rem 1rem;border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                <span style="font-size:.83rem;color:#334155;">{{ $tiposLabel[$r->tipo]??ucfirst($r->tipo) }}</span>
                <span style="background:#dbeafe;color:#1e40af;padding:.2rem .65rem;border-radius:999px;font-size:.78rem;font-weight:700;">{{ $r->total }}</span>
            </div>
            @empty
            <div style="padding:1.5rem;text-align:center;color:#94a3b8;font-size:.84rem;">Sin tareas activas</div>
            @endforelse
        </div>

        {{-- Por encargado --}}
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
            <div style="padding:.85rem 1rem;background:#f8fafc;border-bottom:2px solid #e2e8f0;font-size:.72rem;font-weight:700;text-transform:uppercase;color:#64748b;">Por Encargado (activas)</div>
            @forelse($porEncargado as $r)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.65rem 1rem;border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                <span style="font-size:.83rem;color:#334155;">{{ $r->nombre }}</span>
                <span style="background:#fef3c7;color:#92400e;padding:.2rem .65rem;border-radius:999px;font-size:.78rem;font-weight:700;">{{ $r->total }}</span>
            </div>
            @empty
            <div style="padding:1.5rem;text-align:center;color:#94a3b8;font-size:.84rem;">Sin datos</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
