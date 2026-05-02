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
.audit-row{cursor:pointer;transition:background .12s;}
.audit-row:hover{background:#faf5ff !important;}
.audit-row .audit-hint{font-size:.68rem;color:#a78bfa;margin-top:.15rem;}
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
            <div class="sub">Cobrado en {{ $mesesEs[$mes] }} (base caja)</div>
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
            <div class="sub">Recaudado en mes − Pagado planillas</div>
        </div>
    </div>

    {{-- Desglose ingresos --}}
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;margin-bottom:1.5rem;">
        <div style="background:#fff;border-radius:14px;padding:1.25rem;box-shadow:0 1px 8px rgba(0,0,0,.06);">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:.85rem;">Desglose de Ingresos <span style="color:#0ea5e9;font-weight:400;">(cobrados en {{ $mesesEs[$mes] }})</span></div>
            @foreach([['Planillas (admon, seguro, otros)',$ingresos['planillas'],'#3b82f6'],['Afiliaciones',$ingresos['afiliaciones'],'#8b5cf6'],['Trámites',$ingresos['tramites'],'#10b981']] as [$l,$v,$c])
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
                <div class="bank-card" onclick="verMovimientosBanco({{ $b->id }},'{{ addslashes($b->nombre) }}')" title="Clic para ver movimientos">
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

    {{-- Banner Anticipos / Cobros adelantados --}}
    @if($anticipos['cant'] > 0 || $cobradosAntes['cant'] > 0)
    <div style="display:grid;grid-template-columns:{{ ($anticipos['cant']>0 && $cobradosAntes['cant']>0) ? '1fr 1fr' : '1fr' }};gap:1rem;margin-bottom:1.25rem;">

        {{-- Anticipos cobrados este mes para períodos futuros --}}
        @if($anticipos['cant'] > 0)
        <div style="background:linear-gradient(135deg,#fff7ed,#ffedd5);border-radius:14px;padding:1rem 1.25rem;border-left:4px solid #f59e0b;box-shadow:0 1px 6px rgba(0,0,0,.05);">
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.65rem;">
                <span style="font-size:1.1rem;">📥</span>
                <div>
                    <div style="font-size:.82rem;font-weight:700;color:#92400e;">Anticipos cobrados este mes</div>
                    <div style="font-size:.7rem;color:#b45309;">{{ $anticipos['cant'] }} factura(s) de períodos futuros pagadas en {{ $mesesEs[$mes] }}</div>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;">
                <div style="background:rgba(255,255,255,.7);border-radius:8px;padding:.5rem .75rem;text-align:center;">
                    <div style="font-size:.68rem;font-weight:600;color:#92400e;margin-bottom:.15rem;">Admon (ingreso)</div>
                    <div style="font-size:.88rem;font-weight:800;color:#d97706;">{{ $fmt($anticipos['admon']) }}</div>
                </div>
                <div style="background:rgba(255,255,255,.7);border-radius:8px;padding:.5rem .75rem;text-align:center;">
                    <div style="font-size:.68rem;font-weight:600;color:#92400e;margin-bottom:.15rem;">SS (reservar)</div>
                    <div style="font-size:.88rem;font-weight:800;color:#d97706;">{{ $fmt($anticipos['ss']) }}</div>
                </div>
                <div style="background:rgba(245,158,11,.15);border-radius:8px;padding:.5rem .75rem;text-align:center;">
                    <div style="font-size:.68rem;font-weight:600;color:#92400e;margin-bottom:.15rem;">Total recibido</div>
                    <div style="font-size:.88rem;font-weight:800;color:#b45309;">{{ $fmt($anticipos['total']) }}</div>
                </div>
            </div>
            <div style="margin-top:.6rem;font-size:.68rem;color:#b45309;background:rgba(255,255,255,.5);border-radius:6px;padding:.35rem .65rem;">
                ⚠️ La SS de estos anticipos debe guardarse — se pagará cuando corresponda el período facturado.
            </div>
        </div>
        @endif

        {{-- Facturas del período cobradas en meses anteriores --}}
        @if($cobradosAntes['cant'] > 0)
        <div style="background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border-radius:14px;padding:1rem 1.25rem;border-left:4px solid #0ea5e9;box-shadow:0 1px 6px rgba(0,0,0,.05);">
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.65rem;">
                <span style="font-size:1.1rem;">📤</span>
                <div>
                    <div style="font-size:.82rem;font-weight:700;color:#0c4a6e;">Ingresos del período cobrados antes</div>
                    <div style="font-size:.7rem;color:#0369a1;">{{ $cobradosAntes['cant'] }} factura(s) de {{ $mesesEs[$mes] }} pagadas en meses anteriores</div>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;">
                <div style="background:rgba(255,255,255,.7);border-radius:8px;padding:.5rem .75rem;text-align:center;">
                    <div style="font-size:.68rem;font-weight:600;color:#0c4a6e;margin-bottom:.15rem;">Admon cobrado</div>
                    <div style="font-size:.88rem;font-weight:800;color:#0369a1;">{{ $fmt($cobradosAntes['admon']) }}</div>
                </div>
                <div style="background:rgba(255,255,255,.7);border-radius:8px;padding:.5rem .75rem;text-align:center;">
                    <div style="font-size:.68rem;font-weight:600;color:#0c4a6e;margin-bottom:.15rem;">SS cobrada</div>
                    <div style="font-size:.88rem;font-weight:800;color:#0369a1;">{{ $fmt($cobradosAntes['ss']) }}</div>
                </div>
                <div style="background:rgba(14,165,233,.1);border-radius:8px;padding:.5rem .75rem;text-align:center;">
                    <div style="font-size:.68rem;font-weight:600;color:#0c4a6e;margin-bottom:.15rem;">Total anticipado</div>
                    <div style="font-size:.88rem;font-weight:800;color:#0c4a6e;">{{ $fmt($cobradosAntes['total']) }}</div>
                </div>
            </div>
            <div style="margin-top:.6rem;font-size:.68rem;color:#0369a1;background:rgba(255,255,255,.5);border-radius:6px;padding:.35rem .65rem;">
                ℹ️ Este dinero ya entró en caja en meses anteriores — incluido aquí por devengado del período.
            </div>
        </div>
        @endif

    </div>
    @endif

    {{-- ══ SEGURIDAD SOCIAL ══ --}}
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:1.25rem;margin-bottom:1.5rem;">

        {{-- Ingresos SS --}}
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
            <div style="background:linear-gradient(135deg,#0e7490,#06b6d4);padding:.85rem 1.25rem;display:flex;align-items:center;gap:.75rem;">
                <span style="font-size:1.2rem;">🏥</span>
                <div style="flex:1;">
                    <div style="color:#fff;font-weight:700;font-size:.9rem;">Ingresos SS</div>
                    <div style="color:rgba(255,255,255,.7);font-size:.75rem;">Seguridad Social — cobrado en {{ $mesesEs[$mes] }} {{ $anio }}</div>
                </div>
                <div style="font-weight:800;color:#fff;font-size:1.1rem;">{{ $fmt($ingresosSS['total_ss']) }}</div>
            </div>

            {{-- Lista simple --}}
            @php
                $filas = [
                    ['EPS',              $ingresosSS['eps'],           '#0ea5e9'],
                    ['ARL',              $ingresosSS['arl'],           '#8b5cf6'],
                    ['Caja',             $ingresosSS['caja'],          '#f59e0b'],
                    ['Pensión',          $ingresosSS['afp'],           '#10b981'],
                    ['SS meses anteriores', $ingresosSS['ss_anteriores'], '#64748b'],
                    ['Retiro (fee afiliación)', $ingresosSS['retiro_campo'], '#ef4444'],
                ];
            @endphp
            @foreach($filas as [$lbl, $val, $color])
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.55rem 1.25rem;border-bottom:1px solid #f1f5f9;">
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <div style="width:7px;height:7px;border-radius:50%;background:{{ $color }};flex-shrink:0;"></div>
                    <span style="font-size:.82rem;color:#334155;">{{ $lbl }}</span>
                </div>
                <span style="font-weight:700;color:{{ $color }};font-size:.85rem;">{{ $fmt($val) }}</span>
            </div>
            @endforeach

            {{-- Total --}}
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.65rem 1.25rem;background:#ecfeff;">
                <span style="font-size:.83rem;font-weight:700;color:#0e7490;">Total recogido</span>
                <span style="font-size:.95rem;font-weight:800;color:#0e7490;">{{ $fmt($ingresosSS['total_ss']) }}</span>
            </div>
        </div>


        {{-- Desglose Egresos SS --}}
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
            <div style="background:linear-gradient(135deg,#7c3aed,#8b5cf6);padding:.85rem 1.25rem;display:flex;align-items:center;gap:.75rem;">
                <span style="font-size:1.2rem;">💸</span>
                <div>
                    <div style="color:#fff;font-weight:700;font-size:.9rem;">Desglose Egresos SS</div>
                    <div style="color:rgba(255,255,255,.7);font-size:.75rem;">Pagos planillas realizados</div>
                </div>
                <div style="margin-left:auto;display:flex;align-items:center;gap:.75rem;">
                    {{-- Botón nuevo: ver todas las facturas por planilla --}}
                    <a href="{{ route('admin.informes.financiero.ss_planillas', ['mes'=>$mes,'anio'=>$anio]) }}"
                       style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.5);color:#fff;border-radius:8px;padding:.3rem .75rem;font-size:.72rem;font-weight:700;text-decoration:none;white-space:nowrap;"
                       title="Ver todas las facturas vinculadas a cada planilla del mes">
                        📋 Facturas por planilla
                    </a>
                    {{-- Ordenar --}}
                    <div style="display:flex;gap:.4rem;">
                        <button onclick="sortEgresos('fecha')" id="sortFecha"
                            style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:7px;padding:.25rem .65rem;font-size:.72rem;cursor:pointer;font-weight:600;transition:background .15s;">
                            📅 Fecha
                        </button>
                        <button onclick="sortEgresos('valor')" id="sortValor"
                            style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:7px;padding:.25rem .65rem;font-size:.72rem;cursor:pointer;font-weight:600;transition:background .15s;">
                            💰 Valor
                        </button>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-weight:800;color:#fff;font-size:1.1rem;">{{ $fmt($pagadoSS) }}</div>
                        <div style="font-size:.7rem;color:rgba(255,255,255,.6);">Saldo: {{ $fmt($saldoSS) }}</div>
                    </div>
                </div>
            </div>
            @if($egresosSSDetalle->isEmpty())
            <div style="padding:2rem;text-align:center;color:#94a3b8;font-size:.84rem;">Sin pagos de planilla este mes</div>
            @else
            {{-- Cabecera tabla --}}
            <div style="display:grid;grid-template-columns:90px 1fr 130px 120px 110px;gap:.4rem;padding:.4rem 1rem;background:#f8fafc;border-bottom:2px solid #e2e8f0;font-size:.67rem;font-weight:700;text-transform:uppercase;color:#64748b;">
                <span>Fecha</span>
                <span>Descripción / Planilla</span>
                <span>Banco / Cuenta</span>
                <span style="text-align:right;color:#10b981;">SS Cobrado</span>
                <span style="text-align:right;">Valor</span>
            </div>
            <div id="egresosSSList" style="max-height:320px;overflow-y:auto;">
                @foreach($egresosSSDetalle as $eg)
                @php
                    $numPlan     = $eg->numero_planilla ?? null;
                    $fechaEg     = sqldate($eg->fecha);
                    $fechaStr    = $fechaEg ? $fechaEg->format('d/m/Y') : '—';
                    $fechaIso    = $fechaEg ? $fechaEg->format('Y-m-d') : '';
                    $ssCobrado   = (float)($eg->ss_cobrado_facturas ?? 0);
                    $ssPagado    = (float)($eg->total ?? 0);
                    $ssDiff      = abs($ssCobrado - $ssPagado);
                    $esAdvertencia = $numPlan && $ssCobrado > 0 && $ssDiff > 50000;
                @endphp
                @php $saldoFila = $ssCobrado - $ssPagado; @endphp
                <div class="audit-row egreso-ss-row"
                    data-fecha="{{ $fechaIso }}"
                    data-valor="{{ $eg->total }}"
                    style="display:grid;grid-template-columns:90px 1fr 130px 120px 110px;gap:.4rem;padding:.55rem 1rem;border-bottom:1px solid #f1f5f9;align-items:start;{{ $saldoFila < -1000 ? 'background:#fff7ed;border-left:3px solid #f59e0b;' : '' }}"
                    @if($numPlan)
                        onclick="auditarPlanilla('{{ addslashes($numPlan) }}','{{ addslashes($eg->descripcion ?? $eg->pagado_a) }}')"
                        title="🔍 Clic para auditar planilla {{ $numPlan }}"
                    @endif>
                    {{-- Fecha --}}
                    <div>
                        <div style="font-size:.8rem;font-weight:700;color:#0d2550;">{{ $fechaStr }}</div>
                    </div>
                    {{-- Descripción --}}
                    <div>
                        <div style="font-size:.78rem;color:#334155;line-height:1.4;word-break:break-word;">{{ $eg->descripcion ?: $eg->pagado_a }}</div>
                    </div>
                    {{-- Banco / Cuenta --}}
                    @php
                        $bancoLabel = $eg->banco_nombre
                            ? $eg->banco_nombre . ($eg->banco_titular ? ' — ' . $eg->banco_titular : '')
                            : null;
                    @endphp
                    <div>
                        @if($bancoLabel)
                            <div style="font-size:.76rem;font-weight:600;color:#1e40af;">{{ $bancoLabel }}</div>
                        @else
                            <div style="font-size:.76rem;color:#94a3b8;">Efectivo</div>
                        @endif
                    </div>
                    {{-- SS Cobrado --}}
                    <div style="text-align:right;">
                        <div style="font-weight:600;color:{{ $ssCobrado > 0 ? '#10b981' : '#94a3b8' }};font-size:.85rem;">{{ $ssCobrado > 0 ? $fmt($ssCobrado) : '—' }}</div>
                    </div>
                    {{-- Valor pagado --}}
                    <div style="text-align:right;">
                        <div style="font-weight:700;color:#7c3aed;font-size:.88rem;">{{ $fmt($eg->total) }}</div>
                        @if($eg->cantidad > 1)
                        <div style="font-size:.67rem;color:#94a3b8;">{{ $eg->cantidad }} reg.</div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            {{-- Fila de totales y saldo --}}
            @php
                $totalSsCobradoEgresos = $egresosSSDetalle->sum('ss_cobrado_facturas');
                $saldoEgresos = $totalSsCobradoEgresos - $pagadoSS;
            @endphp
            <div style="display:grid;grid-template-columns:90px 1fr 130px 120px 110px;gap:.4rem;padding:.55rem 1rem;background:#f5f3ff;border-top:2px solid #ddd6fe;font-size:.78rem;font-weight:700;">
                <span></span>
                <span style="color:#7c3aed;">{{ $egresosSSDetalle->count() }} planilla(s)</span>
                <span></span>
                <span style="text-align:right;color:#10b981;">{{ $fmt($totalSsCobradoEgresos) }}</span>
                <span style="text-align:right;color:#7c3aed;">{{ $fmt($pagadoSS) }}</span>
            </div>
            <div style="display:grid;grid-template-columns:90px 1fr 130px 120px 110px;gap:.4rem;padding:.5rem 1rem;background:{{ $saldoEgresos >= 0 ? '#f0fdf4' : '#fef2f2' }};border-top:1px solid {{ $saldoEgresos >= 0 ? '#bbf7d0' : '#fecaca' }};">
                <span></span>
                <span style="font-size:.75rem;color:#64748b;">SS Cobrado − Valor Pagado</span>
                <span></span>
                <span></span>
                <span style="text-align:right;font-size:.9rem;font-weight:800;color:{{ $saldoEgresos >= 0 ? '#15803d' : '#dc2626' }};">{{ $saldoEgresos >= 0 ? '+' : '' }}{{ $fmt($saldoEgresos) }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Tabla diaria --}}


    <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;margin-bottom:1.5rem;">
        <div style="padding:.85rem 1.25rem;background:#f8fafc;border-bottom:2px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#64748b;">Desglose Diario — {{ $mesesEs[$mes] }} {{ $anio }}</span>
            <span style="font-size:.72rem;color:#94a3b8;">Clic en un día para ver detalle</span>
        </div>
        {{-- Cabecera --}}
        <div style="display:grid;grid-template-columns:40px 48px 1fr 48px 1fr 1fr 1fr 1fr;gap:.4rem;padding:.5rem .75rem;background:#f8fafc;border-bottom:2px solid #e2e8f0;font-size:.68rem;text-transform:uppercase;color:#64748b;font-weight:700;">
            <span>Día</span>
            <span style="color:#3b82f6;text-align:center;">#</span>
            <span style="color:#3b82f6;">Planillas</span>
            <span style="color:#8b5cf6;text-align:center;">#</span>
            <span style="color:#8b5cf6;">Afiliaciones</span>
            <span style="color:#10b981;">Trámites</span>
            <span style="color:#ef4444;">Gastos</span>
            <span>Utilidad</span>
        </div>
        @php $totDia=['planillas'=>0,'afiliaciones'=>0,'tramites'=>0,'gastos'=>0,'utilidad'=>0,'cant_planillas'=>0,'cant_afiliaciones'=>0]; @endphp
        @foreach($diario as $d)
        @php
            foreach(['planillas','afiliaciones','tramites','gastos','utilidad'] as $k) $totDia[$k]+=$d[$k];
            $totDia['cant_planillas']+=$d['cant_planillas'];
            $totDia['cant_afiliaciones']+=$d['cant_afiliaciones'];
            $hayData = $d['planillas']>0 || $d['afiliaciones']>0 || $d['tramites']>0 || $d['gastos']>0;
        @endphp
        @if($hayData)
        <div style="display:grid;grid-template-columns:40px 48px 1fr 48px 1fr 1fr 1fr 1fr;gap:.4rem;padding:.48rem .75rem;border-bottom:1px solid #f1f5f9;font-size:.8rem;align-items:center;cursor:pointer;transition:background .12s;"
             onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''" onclick="verDetalleDia({{ $d['dia'] }},{{ $mes }},{{ $anio }})">
            <span style="font-weight:700;color:#0d2550;">{{ str_pad($d['dia'],2,'0',STR_PAD_LEFT) }}</span>
            <span style="text-align:center;background:#dbeafe;color:#1e40af;border-radius:6px;padding:.1rem .3rem;font-size:.72rem;font-weight:700;">{{ $d['cant_planillas']>0?$d['cant_planillas']:'' }}</span>
            <span style="color:#3b82f6;">{{ $d['planillas']>0?$fmt($d['planillas']):'—' }}</span>
            <span style="text-align:center;background:#ede9fe;color:#7c3aed;border-radius:6px;padding:.1rem .3rem;font-size:.72rem;font-weight:700;">{{ $d['cant_afiliaciones']>0?$d['cant_afiliaciones']:'' }}</span>
            <span style="color:#8b5cf6;">{{ $d['afiliaciones']>0?$fmt($d['afiliaciones']):'—' }}</span>
            <span style="color:#10b981;">{{ $d['tramites']>0?$fmt($d['tramites']):'—' }}</span>
            <span style="color:#ef4444;">{{ $d['gastos']>0?'- '.$fmt($d['gastos']):'—' }}</span>
            <span style="font-weight:700;color:{{ $d['utilidad']>=0?'#10b981':'#ef4444' }};">{{ $fmt($d['utilidad']) }}</span>
        </div>
        @endif
        @endforeach
        {{-- Totales --}}
        <div style="display:grid;grid-template-columns:40px 48px 1fr 48px 1fr 1fr 1fr 1fr;gap:.4rem;padding:.6rem .75rem;background:#f8fafc;font-weight:700;border-top:2px solid #e2e8f0;font-size:.8rem;">
            <span style="color:#0d2550;">TOT</span>
            <span style="text-align:center;background:#dbeafe;color:#1e40af;border-radius:6px;padding:.1rem .3rem;font-size:.72rem;">{{ $totDia['cant_planillas'] }}</span>
            <span style="color:#2563eb;">{{ $fmt($totDia['planillas']) }}</span>
            <span style="text-align:center;background:#ede9fe;color:#7c3aed;border-radius:6px;padding:.1rem .3rem;font-size:.72rem;">{{ $totDia['cant_afiliaciones'] }}</span>
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

{{-- Modal Auditoría Planilla --}}
<div id="modalAudit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:flex-start;justify-content:center;padding-top:3vh;overflow-y:auto;">
    <div style="background:#fff;border-radius:18px;min-width:660px;max-width:920px;width:94%;box-shadow:0 25px 60px rgba(0,0,0,.22);">
        {{-- Header --}}
        <div style="background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:18px 18px 0 0;padding:1.1rem 1.5rem;display:flex;align-items:center;gap:.85rem;">
            <span style="font-size:1.4rem;">🔍</span>
            <div style="flex:1;">
                <div style="color:#fff;font-weight:700;font-size:1rem;" id="auditTitulo">Auditoría Planilla</div>
                <div style="color:rgba(255,255,255,.7);font-size:.74rem;" id="auditSubtitulo">Comparativa SS cobrado vs pagado</div>
            </div>
            <button onclick="document.getElementById('modalAudit').style.display='none'" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:8px;width:32px;height:32px;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;">✕</button>
        </div>
        {{-- Body --}}
        <div id="auditBody" style="padding:1.25rem 1.5rem 1.5rem;">
            <div style="text-align:center;padding:2rem;color:#94a3b8;">Cargando auditoría…</div>
        </div>
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
['modalDia','modalBanco','modalAudit'].forEach(id=>{
    document.getElementById(id).addEventListener('click',function(e){
        if(e.target===this) this.style.display='none';
    });
});

// ── Ordenar Egresos SS ──────────────────────────────────────────────
let egresosSort = { campo: 'valor', asc: false }; // default: valor desc
function sortEgresos(campo) {
    const list = document.getElementById('egresosSSList');
    if (!list) return;
    // Alternar dirección si mismo campo
    if (egresosSort.campo === campo) {
        egresosSort.asc = !egresosSort.asc;
    } else {
        egresosSort.campo = campo;
        egresosSort.asc = campo === 'fecha'; // fecha: asc por defecto, valor: desc
    }
    // Resaltar botón activo
    ['sortFecha','sortValor'].forEach(id => {
        const btn = document.getElementById(id);
        if (!btn) return;
        btn.style.background = 'rgba(255,255,255,.15)';
        btn.style.borderColor = 'rgba(255,255,255,.3)';
    });
    const activeId = campo === 'fecha' ? 'sortFecha' : 'sortValor';
    const activeBtn = document.getElementById(activeId);
    if (activeBtn) {
        activeBtn.style.background = 'rgba(255,255,255,.35)';
        activeBtn.style.borderColor = 'rgba(255,255,255,.8)';
        activeBtn.textContent = (campo === 'fecha' ? '📅 Fecha' : '💰 Valor')
            + (egresosSort.asc ? ' ↑' : ' ↓');
    }
    // Ordenar filas
    const rows = Array.from(list.querySelectorAll('.egreso-ss-row'));
    rows.sort((a, b) => {
        let va = a.dataset[campo] ?? '';
        let vb = b.dataset[campo] ?? '';
        if (campo === 'valor') { va = parseFloat(va) || 0; vb = parseFloat(vb) || 0; }
        if (va < vb) return egresosSort.asc ? -1 : 1;
        if (va > vb) return egresosSort.asc ? 1 : -1;
        return 0;
    });
    rows.forEach(r => list.appendChild(r));
}

// ── Auditoría de número de planilla ──
function auditarPlanilla(numPlanilla, descripcion) {
    const m = document.getElementById('modalAudit');
    document.getElementById('auditTitulo').textContent = 'Auditoría Planilla ' + numPlanilla;
    document.getElementById('auditSubtitulo').textContent = descripcion || 'SS cobrado a clientes vs pago registrado';
    document.getElementById('auditBody').innerHTML = '<div style="text-align:center;padding:2.5rem;color:#94a3b8;"><div style="font-size:1.6rem;margin-bottom:.5rem;">⏳</div>Consultando datos…</div>';
    m.style.display = 'flex';

    fetch(`{{ route('admin.informes.financiero.auditar_planilla') }}?numero_planilla=${encodeURIComponent(numPlanilla)}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) { document.getElementById('auditBody').innerHTML = `<div style="color:#ef4444;padding:1.5rem;text-align:center;">${data.error}</div>`; return; }

            const fmtN = v => '$ ' + Math.round(v || 0).toLocaleString('es-CO');
            const dif  = data.diferencia;
            const difColor  = dif >= 0 ? '#10b981' : '#ef4444';
            const difBg     = dif >= 0 ? '#f0fdf4' : '#fef2f2';
            const difLabel  = dif >= 0 ? '✅ A favor (exceso cobrado)' : '⚠️ Déficit (cobrado menos de lo pagado)';

            let html = '';

            // ── Alerta pago duplicado ──────────────────────────────────
            if (data.es_duplicado) {
                html += `
                <div style="background:#fef2f2;border:2px solid #fca5a5;border-radius:12px;padding:.85rem 1rem;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.65rem;">
                        <span style="font-size:1.3rem;">🚨</span>
                        <div>
                            <div style="font-size:.88rem;font-weight:800;color:#dc2626;">PAGO DUPLICADO — ${data.cant_gastos} registros encontrados</div>
                            <div style="font-size:.72rem;color:#b91c1c;">La planilla ${data.numero_planilla} tiene más de un gasto registrado. Revisar posible pago doble.</div>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.35rem;">`;
                (data.gastos_detalle || []).forEach((g, i) => {
                    const fecha = g.fecha ? new Date(g.fecha).toLocaleDateString('es-CO') : '—';
                    html += `
                        <div style="background:#fff;border-radius:8px;padding:.45rem .75rem;display:flex;justify-content:space-between;align-items:center;border-left:3px solid #ef4444;">
                            <div>
                                <span style="font-size:.75rem;font-weight:700;color:#dc2626;">#${i+1}</span>
                                <span style="font-size:.75rem;color:#475569;margin-left:.5rem;">${fecha}</span>
                                <span style="font-size:.72rem;color:#94a3b8;margin-left:.5rem;">${g.forma_pago || ''}</span>
                            </div>
                            <span style="font-weight:800;color:#dc2626;font-size:.85rem;">${fmtN(g.valor)}</span>
                        </div>`;
                });
                html += `</div></div>`;
            }

            // ── Resumen top 3 tarjetas ─────────────────────────────────
            html += `
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.85rem;margin-bottom:1.25rem;">
                <div style="background:#ede9fe;border-radius:12px;padding:.9rem;text-align:center;">
                    <div style="font-size:1.1rem;font-weight:800;color:#7c3aed;">${fmtN(data.total_ss_facturas)}</div>
                    <div style="font-size:.72rem;color:#6d28d9;font-weight:600;margin-top:.2rem;">SS Cobrado (facturas)</div>
                    <div style="font-size:.68rem;color:#94a3b8;margin-top:.1rem;">${data.cant_empleados} empleado(s)</div>
                </div>
                <div style="background:${data.es_duplicado ? '#fef2f2' : '#fef3c7'};border-radius:12px;padding:.9rem;text-align:center;${data.es_duplicado ? 'border:2px solid #fca5a5;' : ''}">
                    <div style="font-size:1.1rem;font-weight:800;color:${data.es_duplicado ? '#dc2626' : '#d97706'};">${fmtN(data.gasto_valor)}</div>
                    <div style="font-size:.72rem;color:${data.es_duplicado ? '#b91c1c' : '#b45309'};font-weight:600;margin-top:.2rem;">
                        Pagado ${data.cant_gastos > 1 ? '('+data.cant_gastos+' registros ⚠️)' : '(gasto registrado)'}
                    </div>
                    <div style="font-size:.68rem;color:#94a3b8;margin-top:.1rem;">${data.gasto ? new Date(data.gasto.fecha).toLocaleDateString('es-CO') : '—'}</div>
                </div>
                <div style="background:${difBg};border-radius:12px;padding:.9rem;text-align:center;">
                    <div style="font-size:1.1rem;font-weight:800;color:${difColor};">${fmtN(dif)}</div>
                    <div style="font-size:.72rem;color:${difColor};font-weight:600;margin-top:.2rem;">Diferencia</div>
                    <div style="font-size:.65rem;color:${difColor};margin-top:.1rem;">${difLabel}</div>
                </div>
            </div>`;

            // Desglose por componente SS
            html += `
            <div style="background:#f8fafc;border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;display:flex;gap:1rem;flex-wrap:wrap;">
                <div style="flex:1;min-width:90px;text-align:center;">
                    <div style="font-size:.78rem;font-weight:700;color:#0ea5e9;">EPS</div>
                    <div style="font-size:.9rem;font-weight:700;color:#0284c7;">${fmtN(data.total_eps)}</div>
                </div>
                <div style="flex:1;min-width:90px;text-align:center;">
                    <div style="font-size:.78rem;font-weight:700;color:#10b981;">Pensión AFP</div>
                    <div style="font-size:.9rem;font-weight:700;color:#059669;">${fmtN(data.total_afp)}</div>
                </div>
                <div style="flex:1;min-width:90px;text-align:center;">
                    <div style="font-size:.78rem;font-weight:700;color:#8b5cf6;">ARL</div>
                    <div style="font-size:.9rem;font-weight:700;color:#7c3aed;">${fmtN(data.total_arl)}</div>
                </div>
                <div style="flex:1;min-width:90px;text-align:center;">
                    <div style="font-size:.78rem;font-weight:700;color:#f59e0b;">Caja Comp.</div>
                    <div style="font-size:.9rem;font-weight:700;color:#d97706;">${fmtN(data.total_caja)}</div>
                </div>
            </div>`;

            // Tabla de empleados
            if (!data.planos || data.planos.length === 0) {
                html += '<div style="text-align:center;color:#94a3b8;padding:1.5rem;font-size:.84rem;">No se encontraron registros de planos para esta planilla.</div>';
            } else {
                html += `
                <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:.5rem;">Detalle por empleado — ${data.planos.length} registro(s)</div>
                <div style="border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;">
                    <div style="display:grid;grid-template-columns:30px 1fr 60px 70px 70px 70px 70px 80px;gap:.3rem;padding:.45rem .75rem;background:#f8fafc;font-size:.65rem;font-weight:700;text-transform:uppercase;color:#64748b;border-bottom:2px solid #e2e8f0;">
                        <span>#</span>
                        <span>Empleado</span>
                        <span style="text-align:right;">Días</span>
                        <span style="text-align:right;color:#0ea5e9;">EPS</span>
                        <span style="text-align:right;color:#10b981;">AFP</span>
                        <span style="text-align:right;color:#8b5cf6;">ARL</span>
                        <span style="text-align:right;color:#f59e0b;">Caja</span>
                        <span style="text-align:right;color:#7c3aed;">Total SS</span>
                    </div>
                    <div style="max-height:280px;overflow-y:auto;">`;

                let lastEmpresa = null;
                data.planos.forEach((p, i) => {
                    const bg = i % 2 === 0 ? '#fff' : '#fafafa';
                    const tipoIcon = p.tipo_reg === 'retiro' ? '🔴' : '🟢';
                    const empresa  = p.empresa_nombre || '—';
                    const nit      = p.empresa_nit ? ` <span style="color:#94a3b8;font-size:.63rem;">(${p.empresa_nit})</span>` : '';

                    // Separador de empresa cuando cambia
                    if (empresa !== lastEmpresa) {
                        html += `
                        <div style="background:#e0f2fe;padding:.3rem .75rem;font-size:.68rem;font-weight:700;color:#0c4a6e;border-bottom:1px solid #bae6fd;">
                            🏢 ${empresa}${nit}
                        </div>`;
                        lastEmpresa = empresa;
                    }

                    html += `
                    <div style="display:grid;grid-template-columns:30px 1fr 60px 70px 70px 70px 70px 80px;gap:.3rem;padding:.42rem .75rem;background:${bg};font-size:.73rem;border-bottom:1px solid #f1f5f9;align-items:center;">
                        <span style="color:#94a3b8;font-size:.65rem;">${i+1}</span>
                        <div>
                            <div style="font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;" title="${p.nombre_completo}">${tipoIcon} ${p.nombre_completo || p.no_identifi}</div>
                            <div style="font-size:.65rem;color:#94a3b8;">${p.no_identifi}${p.numero_factura ? ' · Fact. #'+p.numero_factura : ''}</div>
                        </div>
                        <span style="text-align:right;color:#64748b;">${p.num_dias ?? '—'}</span>
                        <span style="text-align:right;color:#0ea5e9;font-weight:600;">${p.v_eps != null ? fmtN(p.v_eps) : '—'}</span>
                        <span style="text-align:right;color:#10b981;font-weight:600;">${p.v_afp != null ? fmtN(p.v_afp) : '—'}</span>
                        <span style="text-align:right;color:#8b5cf6;font-weight:600;">${p.v_arl != null ? fmtN(p.v_arl) : '—'}</span>
                        <span style="text-align:right;color:#f59e0b;font-weight:600;">${p.v_caja != null ? fmtN(p.v_caja) : '—'}</span>
                        <span style="text-align:right;color:#7c3aed;font-weight:700;">${p.total_ss != null ? fmtN(p.total_ss) : '—'}</span>
                    </div>`;
                });

                // Fila total
                html += `
                    <div style="display:grid;grid-template-columns:30px 1fr 60px 70px 70px 70px 70px 80px;gap:.3rem;padding:.5rem .75rem;background:#f5f3ff;font-size:.73rem;border-top:2px solid #e2e8f0;font-weight:700;">
                        <span></span>
                        <span style="color:#7c3aed;">TOTAL</span>
                        <span></span>
                        <span style="text-align:right;color:#0ea5e9;">${fmtN(data.total_eps)}</span>
                        <span style="text-align:right;color:#10b981;">${fmtN(data.total_afp)}</span>
                        <span style="text-align:right;color:#8b5cf6;">${fmtN(data.total_arl)}</span>
                        <span style="text-align:right;color:#f59e0b;">${fmtN(data.total_caja)}</span>
                        <span style="text-align:right;color:#7c3aed;font-size:.82rem;">${fmtN(data.total_ss_facturas)}</span>
                    </div>
                    </div>
                </div>`;
            }

            // Nota si no hay gasto
            if (!data.gasto) {
                html += '<div style="margin-top:.85rem;background:#fff7ed;border-left:3px solid #f59e0b;padding:.65rem 1rem;border-radius:0 8px 8px 0;font-size:.78rem;color:#92400e;">⚠️ No se encontró un gasto <code>pago_planilla</code> asociado a esta planilla. El valor pagado puede estar en otro registro.</div>';
            }

            document.getElementById('auditBody').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('auditBody').innerHTML = '<div style="color:#ef4444;padding:1.5rem;text-align:center;">Error al cargar la auditoría. Intente de nuevo.</div>';
        });
}
</script>
@endpush
@endsection
