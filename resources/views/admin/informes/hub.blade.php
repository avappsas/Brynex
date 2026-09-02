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
    @can('informes.ver')
    <h2 style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:.75rem;">Operaciones</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:1rem;margin-bottom:1.75rem;">

        @php
        $cards = [
            ['icono'=>'👥','label'=>'Clientes Activos','val'=>$kpis['clientes_unicos'].' / '.$kpis['clientes_activos'],'color'=>'#3b82f6','url'=>route('admin.informes.clientes_activos'),'desc'=>'Clientes únicos / Contratos'],
            ['icono'=>'🏢','label'=>'Por Razón Social','val'=>$kpis['razones_sociales'],'color'=>'#06b6d4','url'=>route('admin.informes.por_razon_social'),'desc'=>'RS activas'],
            ['icono'=>'📥','label'=>'Afiliaciones del Mes','val'=>$kpis['afiliaciones_mes_nuevas'].' + '.($kpis['afiliaciones_mes_total'] - $kpis['afiliaciones_mes_nuevas']),'color'=>'#8b5cf6','url'=>route('admin.informes.afiliaciones_retiros'),'desc'=>'Nuevos + Reingresos'],
            ['icono'=>'🏭','label'=>'Empresas Clientes','val'=>$kpis['empresas'],'color'=>'#0ea5e9','url'=>route('admin.informes.empresas_clientes'),'desc'=>'Empresas registradas'],
            ['icono'=>'🏥','label'=>'Por Entidades','val'=>'EPS/AFP/ARL','color'=>'#10b981','url'=>route('admin.informes.por_entidades'),'desc'=>'Ver distribución'],
            ['icono'=>'🚪','label'=>'Retirados del Mes','val'=>$kpis['retiros_mes_sin_renovar'].' / '.$kpis['retiros_mes_total'],'color'=>'#f59e0b','url'=>route('admin.informes.retirados_mes'),'desc'=>'Sin renovar / Total'],
            ['icono'=>'🏨','label'=>'Incapacidades','val'=>$kpis['incapacidades'],'color'=>'#ef4444','url'=>route('admin.informes.incapacidades'),'desc'=>'Casos activos'],
            ['icono'=>'📌','label'=>'Tareas','val'=>$kpis['tareas'],'color'=>'#f97316','url'=>route('admin.informes.tareas'),'desc'=>'Tareas activas'],
            ['icono'=>'✅','label'=>'Cierre de Operación','val'=>$kpis['cierre_operacion']['lotes'].' tandas','color'=>$kpis['cierre_operacion']['total'] > 0 ? '#ef4444' : '#10b981','url'=>route('admin.informes.cierre_operacion'),'desc'=>$kpis['cierre_operacion']['vigentes'].' sin facturar · '.$kpis['cierre_operacion']['afiliaciones'].' afiliaciones'],
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
    @endcan

    <h2 style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:.75rem;">Financiero</h2>

    {{-- Estado Financiero: solo superadmin y contable (informes.financiero).
         Un admin ve la conciliación de abajo pero no la utilidad del aliado. --}}
    @if($esFinanciero)
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

    {{-- Conciliación de Bancos --}}
    <a href="{{ route('admin.informes.conciliacion_bancos') }}" style="display:flex;align-items:center;gap:1.25rem;background:linear-gradient(135deg,#0369a1,#075985);border-radius:14px;padding:1.5rem 1.75rem;text-decoration:none;border:2px solid transparent;box-shadow:0 4px 20px rgba(3,105,161,.3);transition:all .18s;margin-top:1rem;"
       onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 12px 32px rgba(3,105,161,.4)'"
       onmouseout="this.style.transform='';this.style.boxShadow='0 4px 20px rgba(3,105,161,.3)'">
        <div style="font-size:2.5rem;">🏦</div>
        <div>
            <div style="font-size:1rem;font-weight:700;color:#fff;">Conciliación de Bancos</div>
            <div style="font-size:.82rem;color:rgba(255,255,255,.7);margin-top:.2rem;">Movimientos del mes por cuenta · Confirmar consignaciones · Saldos</div>
        </div>
    </a>

    {{-- Distribución de Afiliaciones: entrar con `comisiones.ver` (admin,
         contable y superadmin), repartir con `comisiones.gestionar` (solo
         admin y superadmin, que es quien puede editar). --}}
    @can('comisiones.ver')
    <a href="{{ route('admin.informes.comisiones.afiliaciones', ['mes' => now()->month, 'anio' => now()->year]) }}" style="display:flex;align-items:center;gap:1.25rem;background:linear-gradient(135deg,#7c3aed,#5b21b6);border-radius:14px;padding:1.5rem 1.75rem;text-decoration:none;border:2px solid transparent;box-shadow:0 4px 20px rgba(124,58,237,.3);transition:all .18s;margin-top:1rem;"
       onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 12px 32px rgba(124,58,237,.4)'"
       onmouseout="this.style.transform='';this.style.boxShadow='0 4px 20px rgba(124,58,237,.3)'">
        <div style="font-size:2.5rem;">📋</div>
        <div>
            <div style="font-size:1rem;font-weight:700;color:#fff;">Distribución de Afiliaciones</div>
            <div style="font-size:.82rem;color:rgba(255,255,255,.7);margin-top:.2rem;">Repartir el valor de cada afiliación entre los asesores · Comisiones del mes</div>
        </div>
    </a>
    @endcan

    {{-- Historial --}}
    @can('informes.ver')
    <h2 style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-top:1.5rem;margin-bottom:.75rem;">Historial Informes</h2>

    {{-- Reporte de Ingresos y Retiros (Consolidado Mensual) --}}
    <a href="{{ route('admin.informes.consolidado_mensual') }}" style="display:flex;align-items:center;gap:1.25rem;background:linear-gradient(135deg,#0d9488,#0f766e);border-radius:14px;padding:1.5rem 1.75rem;text-decoration:none;border:2px solid transparent;box-shadow:0 4px 20px rgba(13,148,136,.3);transition:all .18s;"
       onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 12px 32px rgba(13,148,136,.4)'"
       onmouseout="this.style.transform='';this.style.boxShadow='0 4px 20px rgba(13,148,136,.3)'">
        <div style="font-size:2.5rem;">📈</div>
        <div>
            <div style="font-size:1rem;font-weight:700;color:#fff;">Reporte de Ingresos y Retiros</div>
            <div style="font-size:.82rem;color:rgba(255,255,255,.8);margin-top:.2rem;">Administración y afiliaciones históricas por mes · 6 meses de tendencia</div>
        </div>
    </a>
    @endcan

</div>
@endsection
