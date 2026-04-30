@extends('layouts.app')
@section('modulo','Estado Financiero')
@push('styles')
<style>
.fin-kpi{background:#fff;border-radius:14px;padding:1.25rem 1.5rem;box-shadow:0 1px 8px rgba(0,0,0,.06);border-left:4px solid var(--c);}
.fin-kpi .val{font-size:1.6rem;font-weight:800;color:var(--c);line-height:1.1;}
.fin-kpi .lab{font-size:.8rem;font-weight:600;color:#334155;margin-top:.3rem;}
.fin-kpi .sub{font-size:.72rem;color:#94a3b8;margin-top:.15rem;}
.dia-row{display:grid;grid-template-columns:50px 1fr 1fr 1fr 1fr 1fr;gap:.5rem;padding:.5rem .75rem;border-bottom:1px solid #f1f5f9;font-size:.8rem;align-items:center;cursor:pointer;transition:background .12s;}
.dia-row:hover{background:#f8fafc;}
.dia-row.dia-head{background:#f8fafc;border-bottom:2px solid #e2e8f0;font-size:.7rem;text-transform:uppercase;color:#64748b;font-weight:700;cursor:default;}
.bank-card{background:#fff;border-radius:12px;padding:1rem 1.25rem;box-shadow:0 1px 6px rgba(0,0,0,.06);border-top:3px solid #3b82f6;cursor:pointer;transition:all .18s;}
.bank-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.1);}
</style>
@endpush
@section('contenido')
@php
$mesesEs=['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$years=range(now()->year-3,now()->year);
$fmt=fn($v)=>'$ '.number_format($v,0,',','.');
@endphp
<div style="max-width:1200px;margin:0 auto;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">💰 Estado Financiero — {{ $mesesEs[$mes] }} {{ $anio }}</h1>
        <form method="GET" style="display:flex;gap:.5rem;align-items:center;">
            <select name="mes" style="padding:.4rem .65rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;">
                @foreach($mesesEs as $n=>$nm) @if($n>0)<option value="{{ $n }}" {{ $mes==$n?'selected':'' }}>{{ $nm }}</option>@endif @endforeach
            </select>
            <select name="anio" style="padding:.4rem .65rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;">
                @foreach($years as $y)<option value="{{ $y }}" {{ $anio==$y?'selected':'' }}>{{ $y }}</option>@endforeach
            </select>
            <button type="submit" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:.4rem 1rem;font-size:.82rem;cursor:pointer;">Ver</button>
            <a href="?mes={{ $mes }}&anio={{ $anio }}&excel=1" style="background:#16a34a;color:#fff;border-radius:8px;padding:.4rem .9rem;font-size:.78rem;font-weight:600;text-decoration:none;">📥 Excel</a>
        </form>
    </div>

    {{-- KPIs principales --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
        <div class="fin-kpi" style="--c:#2563eb;">
            <div class="val">{{ $fmt($ingresos['total']) }}</div>
            <div class="lab">Ingresos Totales</div>
            <div class="sub">Planillas + Afil. + Trámites</div>
        </div>
        <div class="fin-kpi" style="--c:#ef4444;">
            <div class="val">{{ $fmt($egresos['total']) }}</div>
            <div class="lab">Egresos Operativos</div>
            <div class="sub">Gastos + Comisiones asesor</div>
        </div>
        <div class="fin-kpi" style="{{ $utilidad>=0?'--c:#10b981':'--c:#ef4444' }};">
            <div class="val">{{ $fmt($utilidad) }}</div>
            <div class="lab">Utilidad Neta</div>
            <div class="sub">Ingresos − Egresos</div>
        </div>
        <div class="fin-kpi" style="--c:#8b5cf6;">
            <div class="val">{{ $fmt($saldoSS) }}</div>
            <div class="lab">Saldo SS Terceros</div>
            <div class="sub">Recaudado − Pagado planillas</div>
        </div>
    </div>

    {{-- Desglose ingresos --}}
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;margin-bottom:1.5rem;">
        <div style="background:#fff;border-radius:14px;padding:1.25rem;box-shadow:0 1px 8px rgba(0,0,0,.06);">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:.85rem;">Desglose de Ingresos</div>
            @foreach([['Planillas SS (admon, seguro, otros)',$ingresos['planillas'],'#3b82f6'],['Afiliaciones',$ingresos['afiliaciones'],'#8b5cf6'],['Trámites',$ingresos['tramites'],'#10b981']] as [$l,$v,$c])
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem;">
                <div>
                    <div style="font-size:.83rem;font-weight:600;color:#334155;">{{ $l }}</div>
                    <div style="height:5px;border-radius:3px;background:{{ $c }};width:{{ $ingresos['total']>0?round($v/$ingresos['total']*200).'px':'4px' }};margin-top:.25rem;transition:width .4s;"></div>
                </div>
                <div style="font-size:.9rem;font-weight:700;color:{{ $c }};">{{ $fmt($v) }}</div>
            </div>
            @endforeach
            <div style="border-top:1px solid #e2e8f0;margin-top:.5rem;padding-top:.5rem;display:flex;justify-content:space-between;">
                <span style="font-size:.83rem;font-weight:700;color:#0d2550;">Total Ingresos</span>
                <span style="font-size:.9rem;font-weight:800;color:#2563eb;">{{ $fmt($ingresos['total']) }}</span>
            </div>
        </div>

        <div style="background:#fff;border-radius:14px;padding:1.25rem;box-shadow:0 1px 8px rgba(0,0,0,.06);">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:.85rem;">Desglose Egresos</div>
            @foreach([['Gastos Operativos',$egresos['operativos'],'#ef4444'],['Comisiones Asesor',$egresos['comisiones'],'#f59e0b'],['Pagado SS',$pagadoSS,'#8b5cf6']] as [$l,$v,$c])
            <div style="display:flex;justify-content:space-between;margin-bottom:.65rem;">
                <span style="font-size:.82rem;color:#334155;">{{ $l }}</span>
                <span style="font-weight:700;color:{{ $c }};">{{ $fmt($v) }}</span>
            </div>
            @endforeach
            <div style="border-top:1px solid #e2e8f0;padding-top:.5rem;margin-top:.25rem;">
                <div style="display:flex;justify-content:space-between;">
                    <span style="font-size:.8rem;font-weight:600;color:#64748b;">SS Recaudado</span>
                    <span style="font-size:.82rem;color:#64748b;">{{ $fmt($recaudoSS) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Gráficas --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem;">
        <div style="background:#fff;border-radius:14px;padding:1.25rem;box-shadow:0 1px 8px rgba(0,0,0,.06);">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:.85rem;">Tendencia 6 Meses</div>
            <canvas id="chartTendencia" height="180"></canvas>
        </div>
        <div style="background:#fff;border-radius:14px;padding:1.25rem;box-shadow:0 1px 8px rgba(0,0,0,.06);">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:.85rem;">Mes Actual vs Anterior</div>
            <canvas id="chartComparacion" height="180"></canvas>
        </div>
    </div>

    {{-- Gráfica distribución ingresos --}}
    <div style="display:grid;grid-template-columns:300px 1fr;gap:1.25rem;margin-bottom:1.5rem;">
        <div style="background:#fff;border-radius:14px;padding:1.25rem;box-shadow:0 1px 8px rgba(0,0,0,.06);">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:.85rem;">Distribución Ingresos</div>
            <canvas id="chartDona" height="200"></canvas>
        </div>

        {{-- Bancos --}}
        <div style="background:#fff;border-radius:14px;padding:1.25rem;box-shadow:0 1px 8px rgba(0,0,0,.06);">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:.85rem;">Cuentas Bancarias</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem;">
                @foreach($bancos as $b)
                <div class="bank-card" onclick="verMovimientosBanco({{ $b->id }},'{{ addslashes($b->etiqueta) }}')" title="Clic para ver movimientos">
                    <div style="font-size:.72rem;color:#64748b;margin-bottom:.25rem;">{{ $b->banco }}</div>
                    <div style="font-size:.82rem;font-weight:600;color:#0d2550;margin-bottom:.5rem;">{{ $b->nombre }}</div>
                    <div style="font-size:1rem;font-weight:800;color:#2563eb;">{{ $fmt($b->saldo_actual) }}</div>
                    <div style="display:flex;justify-content:space-between;margin-top:.35rem;font-size:.72rem;color:#94a3b8;">
                        <span>↑ {{ $fmt($b->entradas_mes) }}</span>
                        <span>↓ {{ $fmt($b->salidas_mes) }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Tabla diaria --}}
    <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;margin-bottom:1.5rem;">
        <div style="padding:.85rem 1.25rem;background:#f8fafc;border-bottom:2px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#64748b;">Desglose Diario — {{ $mesesEs[$mes] }} {{ $anio }}</span>
            <span style="font-size:.72rem;color:#94a3b8;">Clic en un día para ver detalle</span>
        </div>
        <div class="dia-row dia-head">
            <span>Día</span><span>Planillas</span><span>Afiliaciones</span><span>Trámites</span><span>Gastos</span><span>Utilidad</span>
        </div>
        @php $totDia=['planillas'=>0,'afiliaciones'=>0,'tramites'=>0,'gastos'=>0,'utilidad'=>0]; @endphp
        @foreach($diario as $d)
        @php foreach(['planillas','afiliaciones','tramites','gastos','utilidad'] as $k) $totDia[$k]+=$d[$k]; @endphp
        <div class="dia-row" onclick="verDetalleDia({{ $d['dia'] }},{{ $mes }},{{ $anio }})">
            <span style="font-weight:700;color:#0d2550;">{{ str_pad($d['dia'],2,'0',STR_PAD_LEFT) }}</span>
            <span style="color:#3b82f6;">{{ $d['planillas']>0?$fmt($d['planillas']):'—' }}</span>
            <span style="color:#8b5cf6;">{{ $d['afiliaciones']>0?$fmt($d['afiliaciones']):'—' }}</span>
            <span style="color:#10b981;">{{ $d['tramites']>0?$fmt($d['tramites']):'—' }}</span>
            <span style="color:#ef4444;">{{ $d['gastos']>0?'- '.$fmt($d['gastos']):'—' }}</span>
            <span style="font-weight:700;color:{{ $d['utilidad']>=0?'#10b981':'#ef4444' }};">{{ $fmt($d['utilidad']) }}</span>
        </div>
        @endforeach
        <div class="dia-row" style="background:#f8fafc;font-weight:700;border-top:2px solid #e2e8f0;">
            <span style="color:#0d2550;">TOTAL</span>
            <span style="color:#2563eb;">{{ $fmt($totDia['planillas']) }}</span>
            <span style="color:#8b5cf6;">{{ $fmt($totDia['afiliaciones']) }}</span>
            <span style="color:#10b981;">{{ $fmt($totDia['tramites']) }}</span>
            <span style="color:#ef4444;">- {{ $fmt($totDia['gastos']) }}</span>
            <span style="color:{{ $totDia['utilidad']>=0?'#10b981':'#ef4444' }};">{{ $fmt($totDia['utilidad']) }}</span>
        </div>
    </div>
</div>

{{-- Modal detalle día --}}
<div id="modalDia" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:1.5rem;min-width:420px;max-width:600px;max-height:80vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 id="modalDiaTitulo" style="font-size:1rem;font-weight:700;color:#0d2550;"></h3>
            <button onclick="document.getElementById('modalDia').style.display='none'" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:#64748b;">✕</button>
        </div>
        <div id="modalDiaBody" style="font-size:.84rem;color:#475569;">Cargando…</div>
    </div>
</div>

{{-- Modal movimientos banco --}}
<div id="modalBanco" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:1.5rem;min-width:500px;max-width:700px;max-height:80vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 id="modalBancoTitulo" style="font-size:1rem;font-weight:700;color:#0d2550;"></h3>
            <button onclick="document.getElementById('modalBanco').style.display='none'" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:#64748b;">✕</button>
        </div>
        <div id="modalBancoBody" style="font-size:.84rem;color:#475569;">Cargando…</div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
// ── Datos de tendencia ──
const tendencia = @json($tendencia);
const anterior  = @json($anterior);
const ingresos  = @json($ingresos);
const fmt = v => '$ '+Math.round(v).toLocaleString('es-CO');

// Gráfica tendencia
new Chart(document.getElementById('chartTendencia'), {
    type:'line',
    data:{
        labels: tendencia.map(t=>t.label),
        datasets:[
            {label:'Ingresos',data:tendencia.map(t=>t.ingresos),borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,.1)',tension:.4,fill:true},
            {label:'Egresos',data:tendencia.map(t=>t.egresos),borderColor:'#ef4444',backgroundColor:'rgba(239,68,68,.05)',tension:.4},
            {label:'Utilidad',data:tendencia.map(t=>t.utilidad),borderColor:'#10b981',backgroundColor:'rgba(16,185,129,.08)',tension:.4,fill:true},
        ]
    },
    options:{responsive:true,plugins:{legend:{labels:{font:{size:11}}}},scales:{y:{ticks:{callback:v=>'$'+Math.round(v/1000)+'k',font:{size:10}}}}}
});

// Gráfica comparación
new Chart(document.getElementById('chartComparacion'), {
    type:'bar',
    data:{
        labels:['Ingresos','Egresos','Utilidad'],
        datasets:[
            {label:'Mes actual',data:[tendencia.at(-1)?.ingresos,tendencia.at(-1)?.egresos,tendencia.at(-1)?.utilidad],backgroundColor:['#3b82f6','#ef4444','#10b981']},
            {label:'Mes anterior',data:[anterior.ingresos,anterior.egresos,anterior.utilidad],backgroundColor:['#93c5fd','#fca5a5','#6ee7b7']},
        ]
    },
    options:{responsive:true,plugins:{legend:{labels:{font:{size:11}}}},scales:{y:{ticks:{callback:v=>'$'+Math.round(v/1000)+'k',font:{size:10}}}}}
});

// Gráfica dona distribución
new Chart(document.getElementById('chartDona'), {
    type:'doughnut',
    data:{
        labels:['Planillas','Afiliaciones','Trámites'],
        datasets:[{data:[ingresos.planillas,ingresos.afiliaciones,ingresos.tramites],backgroundColor:['#3b82f6','#8b5cf6','#10b981'],borderWidth:2}]
    },
    options:{responsive:true,cutout:'60%',plugins:{legend:{labels:{font:{size:11}}}}}
});

// Modal detalle día
function verDetalleDia(dia,mes,anio) {
    const m = document.getElementById('modalDia');
    const meses=['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    document.getElementById('modalDiaTitulo').textContent = 'Detalle Día '+dia+' — '+meses[mes]+' '+anio;
    document.getElementById('modalDiaBody').innerHTML = 'Cargando…';
    m.style.display = 'flex';

    const diario = @json($diario);
    const d = diario.find(x=>x.dia===dia);
    if (!d) { document.getElementById('modalDiaBody').innerHTML='Sin movimientos'; return; }

    document.getElementById('modalDiaBody').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
            <div style="background:#eff6ff;border-radius:10px;padding:.85rem;text-align:center;"><div style="font-weight:800;color:#2563eb;font-size:1.1rem;">${fmt(d.planillas)}</div><div style="font-size:.75rem;color:#64748b;">Planillas</div></div>
            <div style="background:#f5f3ff;border-radius:10px;padding:.85rem;text-align:center;"><div style="font-weight:800;color:#7c3aed;font-size:1.1rem;">${fmt(d.afiliaciones)}</div><div style="font-size:.75rem;color:#64748b;">Afiliaciones</div></div>
            <div style="background:#f0fdf4;border-radius:10px;padding:.85rem;text-align:center;"><div style="font-weight:800;color:#16a34a;font-size:1.1rem;">${fmt(d.tramites)}</div><div style="font-size:.75rem;color:#64748b;">Trámites</div></div>
            <div style="background:#fef2f2;border-radius:10px;padding:.85rem;text-align:center;"><div style="font-weight:800;color:#dc2626;font-size:1.1rem;">- ${fmt(d.gastos)}</div><div style="font-size:.75rem;color:#64748b;">Gastos</div></div>
        </div>
        <div style="margin-top:1rem;background:${d.utilidad>=0?'#f0fdf4':'#fef2f2'};border-radius:10px;padding:.85rem;text-align:center;">
            <div style="font-weight:800;color:${d.utilidad>=0?'#16a34a':'#dc2626'};font-size:1.25rem;">${fmt(d.utilidad)}</div>
            <div style="font-size:.78rem;color:#64748b;">Utilidad del día</div>
        </div>`;
}

// Modal movimientos banco
function verMovimientosBanco(bancoId, label) {
    const m = document.getElementById('modalBanco');
    document.getElementById('modalBancoTitulo').textContent = label;
    document.getElementById('modalBancoBody').innerHTML = 'Cargando…';
    m.style.display='flex';

    fetch(`{{ route('admin.informes.financiero.bancos') }}?banco_id=${bancoId}&mes={{ $mes }}&anio={{ $anio }}`)
        .then(r=>r.json()).then(data=>{
            let html = '<div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#10b981;margin-bottom:.5rem;">Entradas</div>';
            if (!data.entradas.length) html+='<p style="color:#94a3b8;font-size:.82rem;">Sin entradas</p>';
            else data.entradas.forEach(e=>{
                html+=`<div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid #f1f5f9;font-size:.82rem;">
                    <span>${e.fecha} ${e.tipo}${e.numero_factura?' #'+e.numero_factura:''} ${e.referencia||''}</span>
                    <span style="font-weight:700;color:#10b981;">${fmt(e.valor)}</span></div>`;
            });
            html+='<div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#ef4444;margin:.85rem 0 .5rem;">Salidas</div>';
            if (!data.salidas.length) html+='<p style="color:#94a3b8;font-size:.82rem;">Sin salidas</p>';
            else data.salidas.forEach(s=>{
                html+=`<div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid #f1f5f9;font-size:.82rem;">
                    <span>${s.fecha} — ${s.descripcion}</span>
                    <span style="font-weight:700;color:#ef4444;">- ${fmt(s.valor)}</span></div>`;
            });
            document.getElementById('modalBancoBody').innerHTML=html;
        }).catch(()=>{ document.getElementById('modalBancoBody').innerHTML='Error al cargar movimientos.'; });
}

// Cerrar modales al clic fuera
['modalDia','modalBanco'].forEach(id=>{
    document.getElementById(id).addEventListener('click',function(e){
        if(e.target===this) this.style.display='none';
    });
});
</script>
@endpush
@endsection
