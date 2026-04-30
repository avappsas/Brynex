@extends('layouts.app')
@section('modulo', 'Informes')
@section('contenido')

<div style="max-width:1200px;margin:0 auto;">

    {{-- Header --}}
    <div style="margin-bottom:1.5rem;">
        <h1 style="font-size:1.4rem;font-weight:700;color:#0d2550;">📊 Centro de Informes</h1>
        <p style="color:#64748b;font-size:0.85rem;margin-top:0.25rem;">Resumen ejecutivo · {{ now()->isoFormat('MMMM [de] YYYY') }}</p>
    </div>

    {{-- Grid KPIs operativos --}}
    @role('admin|superadmin')
    <h2 style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:.75rem;">Operaciones</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:1rem;margin-bottom:1.75rem;">

        @php
        $cards = [
            ['icono'=>'👥','label'=>'Clientes Activos','val'=>$kpis['clientes_activos'],'color'=>'#3b82f6','url'=>route('admin.informes.clientes_activos'),'desc'=>'Contratos vigentes'],
            ['icono'=>'🏢','label'=>'Por Razón Social','val'=>$kpis['razones_sociales'],'color'=>'#06b6d4','url'=>route('admin.informes.por_razon_social'),'desc'=>'RS activas'],
            ['icono'=>'📥','label'=>'Afiliaciones/Retiros','val'=>$kpis['afiliaciones_mes'].' / '.$kpis['retiros_mes'],'color'=>'#8b5cf6','url'=>route('admin.informes.afiliaciones_retiros'),'desc'=>'Este mes'],
            ['icono'=>'🏭','label'=>'Empresas Clientes','val'=>$kpis['empresas'],'color'=>'#0ea5e9','url'=>route('admin.informes.empresas_clientes'),'desc'=>'Empresas registradas'],
            ['icono'=>'🏥','label'=>'Por Entidades','val'=>'EPS/AFP/ARL','color'=>'#10b981','url'=>route('admin.informes.por_entidades'),'desc'=>'Ver distribución'],
            ['icono'=>'🚪','label'=>'Retirados del Mes','val'=>$kpis['retiros_mes'],'color'=>'#f59e0b','url'=>route('admin.informes.retirados_mes'),'desc'=>'Este mes'],
            ['icono'=>'🏨','label'=>'Incapacidades','val'=>$kpis['incapacidades'],'color'=>'#ef4444','url'=>route('admin.informes.incapacidades'),'desc'=>'Casos activos'],
            ['icono'=>'📌','label'=>'Tareas','val'=>$kpis['tareas'],'color'=>'#f97316','url'=>route('admin.informes.tareas'),'desc'=>'Tareas activas'],
        ];
        @endphp

        @foreach($cards as $c)
        <a href="{{ $c['url'] }}" style="display:flex;flex-direction:column;gap:.6rem;background:#fff;border-radius:14px;padding:1.25rem 1rem;text-decoration:none;border:2px solid transparent;box-shadow:0 1px 6px rgba(0,0,0,.06);transition:all .18s;cursor:pointer;"
           onmouseover="this.style.borderColor='{{ $c['color'] }}';this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.1)'"
           onmouseout="this.style.borderColor='transparent';this.style.transform='';this.style.boxShadow='0 1px 6px rgba(0,0,0,.06)'">
            <div style="font-size:1.6rem;">{{ $c['icono'] }}</div>
            <div style="font-size:1.5rem;font-weight:800;color:{{ $c['color'] }};line-height:1;">{{ $c['val'] }}</div>
            <div style="font-size:.8rem;font-weight:700;color:#1e293b;">{{ $c['label'] }}</div>
            <div style="font-size:.72rem;color:#94a3b8;">{{ $c['desc'] }}</div>
        </a>
        @endforeach
    </div>
    @endrole

    {{-- Estado Financiero --}}
    @if($esFinanciero)
    <h2 style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:.75rem;">Financiero</h2>
    <a href="{{ route('admin.informes.financiero') }}" style="display:flex;align-items:center;gap:1.25rem;background:linear-gradient(135deg,#0d2550,#1e40af);border-radius:14px;padding:1.5rem 1.75rem;text-decoration:none;border:2px solid transparent;box-shadow:0 4px 20px rgba(30,64,175,.3);transition:all .18s;"
       onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 12px 32px rgba(30,64,175,.4)'"
       onmouseout="this.style.transform='';this.style.boxShadow='0 4px 20px rgba(30,64,175,.3)'">
        <div style="font-size:2.5rem;">💰</div>
        <div>
            <div style="font-size:1rem;font-weight:700;color:#fff;">Estado Financiero</div>
            <div style="font-size:.82rem;color:rgba(255,255,255,.6);margin-top:.2rem;">Ingresos · Egresos · Utilidad · Bancos · Gráficas de tendencia</div>
        </div>
        @if(isset($kpis['ingresos_mes']))
        <div style="margin-left:auto;text-align:right;">
            <div style="font-size:1.4rem;font-weight:800;color:#93c5fd;">$ {{ number_format($kpis['ingresos_mes'],0,',','.') }}</div>
            <div style="font-size:.72rem;color:rgba(255,255,255,.5);">Ingresos este mes</div>
        </div>
        @endif
    </a>
    @endif

</div>
@endsection
