@extends('layouts.app')
@section('modulo','Resumen Incapacidades')
@section('contenido')
@php
$tiposLabel=['enfermedad_general'=>'Enfermedad General','licencia_maternidad'=>'Licencia Maternidad','licencia_paternidad'=>'Licencia Paternidad','accidente_transito'=>'Acc. Tránsito','accidente_laboral'=>'Acc. Laboral'];
$estadosLabel=['recibido'=>'Recibido','radicado'=>'Radicado','en_tramite'=>'En Trámite','autorizado'=>'Autorizado','liquidado'=>'Liquidado','pagado_afiliado'=>'Pagado','rechazado'=>'Rechazado','cerrado'=>'Cerrado'];
$entidadLabel=['eps'=>'EPS','arl'=>'ARL','afp'=>'AFP/Pensión'];
@endphp
<div style="max-width:1100px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">🏨 Resumen de Incapacidades</h1>
    </div>

    {{-- KPIs --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
        @foreach([['Total','#64748b',$kpis['total'],'casos'],['Activas','#ef4444',$kpis['activas'],'sin cerrar'],['Días','#8b5cf6',$kpis['dias'],'días totales'],['Valor Esperado','#10b981','$ '.number_format($kpis['v_esperado'],0,',','.'),'reclamación']] as [$l,$c,$v,$d])
        <div style="background:#fff;border-radius:12px;padding:1.25rem;box-shadow:0 1px 6px rgba(0,0,0,.06);border-left:4px solid {{ $c }};">
            <div style="font-size:1.5rem;font-weight:800;color:{{ $c }};">{{ $v }}</div>
            <div style="font-size:.82rem;font-weight:600;color:#334155;margin-top:.25rem;">{{ $l }}</div>
            <div style="font-size:.72rem;color:#94a3b8;">{{ $d }}</div>
        </div>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.25rem;">
        {{-- Por tipo --}}
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
            <div style="padding:.85rem 1rem;background:#f8fafc;border-bottom:2px solid #e2e8f0;font-size:.72rem;font-weight:700;text-transform:uppercase;color:#64748b;">Por Tipo</div>
            @foreach($porTipo as $r)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 1rem;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:.83rem;color:#334155;">{{ $tiposLabel[$r->tipo_incapacidad]??$r->tipo_incapacidad }}</span>
                <span style="font-weight:700;color:#ef4444;">{{ $r->total }}</span>
            </div>
            @endforeach
        </div>

        {{-- Por estado --}}
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
            <div style="padding:.85rem 1rem;background:#f8fafc;border-bottom:2px solid #e2e8f0;font-size:.72rem;font-weight:700;text-transform:uppercase;color:#64748b;">Por Estado</div>
            @foreach($porEstado as $r)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 1rem;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:.83rem;color:#334155;">{{ $estadosLabel[$r->estado]??$r->estado }}</span>
                <span style="font-weight:700;color:#2563eb;">{{ $r->total }}</span>
            </div>
            @endforeach
        </div>

        {{-- Por entidad --}}
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
            <div style="padding:.85rem 1rem;background:#f8fafc;border-bottom:2px solid #e2e8f0;font-size:.72rem;font-weight:700;text-transform:uppercase;color:#64748b;">Por Entidad</div>
            @foreach($porEntidad as $r)
            <div style="padding:.6rem 1rem;border-bottom:1px solid #f1f5f9;">
                <div style="display:flex;justify-content:space-between;">
                    <span style="font-size:.83rem;color:#334155;">{{ $entidadLabel[$r->tipo_entidad]??$r->tipo_entidad }}</span>
                    <span style="font-weight:700;color:#8b5cf6;">{{ $r->total }}</span>
                </div>
                <div style="font-size:.75rem;color:#94a3b8;">$ {{ number_format($r->valor,0,',','.') }} esperado</div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
