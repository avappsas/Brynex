@extends('layouts.app')
@section('modulo','Validación de Cierre')
@section('contenido')
@php
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $totVigentes    = $resumen->sum('vigentes');
    $totCotizan     = $resumen->sum('deben_cotizar');
    $totSinPlanilla = $resumen->sum('sin_planilla');
    $totConPlano    = $resumen->sum('con_plano');
    $totPendientes  = $resumen->sum('pendientes');
    $aliadoPrincipal = \App\Services\CierrePeriodoService::ALIADO_PRINCIPAL;
    // Con el filtro puesto no hay dos bloques que separar: todo es del mismo aliado.
    $separaBloques = ! $fAliado && $resumen->contains('es_principal', true) && $resumen->contains('es_principal', false);
    $verOtros = false;
@endphp

<div style="max-width:1340px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">🧾 Validación de Cierre</h1>

        <form method="GET" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            <select name="mes" style="padding:.4rem .6rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.82rem;">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($m === $mes)>{{ $meses[$m] }}</option>
                @endfor
            </select>
            <select name="anio" style="padding:.4rem .6rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.82rem;">
                @for($a = now()->year - 2; $a <= now()->year + 1; $a++)
                    <option value="{{ $a }}" @selected($a === $anio)>{{ $a }}</option>
                @endfor
            </select>
            <select name="aliado_id" style="padding:.4rem .6rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.82rem;max-width:200px;">
                <option value="">Todos los aliados</option>
                @foreach($aliados as $al)
                    <option value="{{ $al->id }}" @selected($fAliado === (int) $al->id)>{{ $al->nombre }}</option>
                @endforeach
            </select>
            <button type="submit" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:.45rem .9rem;font-size:.8rem;font-weight:700;cursor:pointer;">Ver</button>
            <a href="{{ route('admin.informes.validacion_cierre', ['mes' => $mes, 'anio' => $anio, 'aliado_id' => $fAliado, 'razon_social_id' => $rsId, 'excel' => 1]) }}"
               style="background:#0f766e;color:#fff;border-radius:8px;padding:.45rem .9rem;font-size:.8rem;font-weight:700;text-decoration:none;">Excel</a>
        </form>
    </div>

    {{-- Totales --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;margin-bottom:1.1rem;">
        @foreach([
            ['Contratos vigentes', $totVigentes, '#94a3b8'],
            ['Deben cotizar', $totCotizan, '#3b82f6'],
            ['Sin planilla este mes', $totSinPlanilla, '#a78bfa'],
            ['En planilla', $totConPlano, '#10b981'],
            ['Pendientes', $totPendientes, $totPendientes > 0 ? '#ef4444' : '#10b981'],
        ] as [$label, $valor, $color])
        <div style="background:#fff;border-radius:12px;padding:.9rem 1rem;box-shadow:0 1px 6px rgba(0,0,0,.06);">
            <div style="font-size:1.5rem;font-weight:800;color:{{ $color }};line-height:1;">{{ number_format($valor) }}</div>
            <div style="font-size:.76rem;font-weight:600;color:#64748b;margin-top:.25rem;">{{ $label }}</div>
        </div>
        @endforeach
    </div>

    {{-- Resumen por razón social (una fila por NIT, aunque viva en varios aliados) --}}
    <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:auto;margin-bottom:1.25rem;">
        <table style="width:100%;border-collapse:collapse;font-size:.82rem;min-width:1080px;">
            <thead><tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Razón social</th>
                <th style="padding:.65rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Aliado</th>
                <th style="padding:.65rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Vigentes</th>
                <th style="padding:.65rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Deben cotizar</th>
                <th style="padding:.65rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Sin planilla</th>
                <th style="padding:.65rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">En planilla</th>
                <th style="padding:.65rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Pendientes</th>
                <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Liquidación por API</th>
                <th style="padding:.65rem;"></th>
            </tr></thead>
            <tbody>
                @forelse($resumen as $g)
                @php
                    $pct = $g->deben_cotizar > 0 ? round($g->con_plano / $g->deben_cotizar * 100) : 100;
                    $abierta = $rsId && in_array((int) $rsId, $g->razon_social_ids, true);
                @endphp

                @if($separaBloques && ! $g->es_principal && ! $verOtros)
                    @php $verOtros = true; @endphp
                    <tr><td colspan="9" style="padding:.5rem 1rem;background:#f1f5f9;font-size:.7rem;font-weight:700;text-transform:uppercase;color:#475569;letter-spacing:.03em;">
                        Razones sociales de los demás aliados
                    </td></tr>
                @elseif($separaBloques && $g->es_principal && $loop->first)
                    <tr><td colspan="9" style="padding:.5rem 1rem;background:#eef2ff;font-size:.7rem;font-weight:700;text-transform:uppercase;color:#3730a3;letter-spacing:.03em;">
                        BRYGAR — razones sociales principales
                    </td></tr>
                @endif

                <tr style="border-bottom:1px solid #f1f5f9;{{ $abierta ? 'background:#eff6ff;' : '' }}">
                    <td style="padding:.6rem 1rem;font-weight:600;color:#0d2550;">
                        {{ $g->razon_social }}
                        @if($g->nit)<span style="color:#94a3b8;font-weight:400;font-size:.74rem;"> · NIT {{ $g->nit }}</span>@endif
                        <div style="height:5px;background:#e2e8f0;border-radius:3px;margin-top:.35rem;max-width:220px;">
                            <div style="height:100%;width:{{ $pct }}%;background:{{ $pct === 100 ? '#10b981' : '#f59e0b' }};border-radius:3px;"></div>
                        </div>
                    </td>
                    <td style="padding:.6rem;">
                        <div style="display:flex;flex-wrap:wrap;gap:.25rem;max-width:230px;">
                            @foreach($g->aliados as $al)
                                @php $esPpal = (int) $al->aliado_id === $aliadoPrincipal; @endphp
                                <span title="{{ $al->razon_social }} — {{ $al->pendientes }} pendiente(s)"
                                      style="font-size:.68rem;font-weight:700;padding:.15rem .45rem;border-radius:999px;
                                             background:{{ $esPpal ? '#e0e7ff' : '#f1f5f9' }};color:{{ $esPpal ? '#3730a3' : '#475569' }};">
                                    {{ $al->aliado }}@if($g->aliados->count() > 1 && $al->pendientes > 0) · {{ $al->pendientes }}@endif
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td style="padding:.6rem;text-align:center;color:#94a3b8;">{{ $g->vigentes }}</td>
                    <td style="padding:.6rem;text-align:center;font-weight:700;color:#334155;">{{ $g->deben_cotizar }}</td>
                    <td style="padding:.6rem;text-align:center;">
                        @if($g->sin_planilla > 0)
                            <button type="button" class="vc-sin-planilla"
                                    data-ids="{{ implode(',', $g->razon_social_ids) }}"
                                    data-rs="{{ $g->razon_social }}"
                                    style="background:#f5f3ff;border:1px solid #ddd6fe;color:#6d28d9;font-weight:800;font-size:.82rem;
                                           border-radius:8px;padding:.2rem .6rem;cursor:pointer;">
                                {{ $g->sin_planilla }}
                            </button>
                        @else
                            <span style="color:#cbd5e1;">0</span>
                        @endif
                    </td>
                    <td style="padding:.6rem;text-align:center;font-weight:700;color:#10b981;">{{ $g->con_plano }}</td>
                    <td style="padding:.6rem;text-align:center;font-size:1.05rem;font-weight:800;color:{{ $g->pendientes > 0 ? '#ef4444' : '#10b981' }};">
                        {{ $g->pendientes }}
                    </td>
                    <td style="padding:.6rem 1rem;color:#475569;font-size:.78rem;">
                        @if($g->api)
                            <strong>{{ $g->api->liquidadas }}</strong> de {{ $g->api->intentos }} planilla(s)
                            @if($g->api->valor_liquidado > 0)
                                · $ {{ number_format((float) $g->api->valor_liquidado) }}
                            @endif
                            <div style="color:#94a3b8;font-size:.72rem;">
                                {{ $g->api->operador ?? 'Operador' }} · última {{ \Carbon\Carbon::parse($g->api->ultima_fecha)->format('d/m/Y H:i') }}
                            </div>
                        @else
                            <span style="color:#94a3b8;">Sin liquidar por API este período</span>
                        @endif
                    </td>
                    <td style="padding:.6rem 1rem;text-align:right;white-space:nowrap;">
                        @if($g->pendientes > 0)
                        <a href="{{ route('admin.informes.validacion_cierre', ['mes' => $mes, 'anio' => $anio, 'aliado_id' => $fAliado, 'razon_social_id' => $g->razon_social_id]) }}#detalle"
                           style="color:#2563eb;font-size:.76rem;font-weight:700;text-decoration:none;">Ver quiénes →</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="padding:2rem;text-align:center;color:#94a3b8;">No hay contratos vigentes en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Detalle de la razón social abierta --}}
    @if($grupo)
    <div id="detalle" style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;margin-bottom:1.25rem;">
        <div style="padding:.85rem 1rem;border-bottom:2px solid #e2e8f0;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
            <h2 style="font-size:.92rem;font-weight:700;color:#0d2550;flex:1;">
                Pendientes de {{ $grupo->razon_social }}
                <span style="color:#ef4444;">({{ $detalle->count() }})</span>
                @if($grupo->aliados->count() > 1)
                    <span style="font-weight:500;color:#64748b;font-size:.78rem;"> · {{ $grupo->aliados->count() }} aliados comparten este NIT</span>
                @endif
            </h2>
            <a href="{{ route('admin.informes.validacion_cierre', ['mes' => $mes, 'anio' => $anio, 'aliado_id' => $fAliado]) }}"
               style="color:#64748b;font-size:.78rem;text-decoration:none;">Cerrar detalle ✕</a>
        </div>
        <div style="max-height:60vh;overflow:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                <thead><tr style="background:#f8fafc;position:sticky;top:0;">
                    <th style="padding:.55rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Cédula</th>
                    <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Nombre</th>
                    <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Aliado</th>
                    <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Plan</th>
                    <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Modalidad</th>
                    <th style="padding:.55rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Ingreso</th>
                    <th style="padding:.55rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Última planilla</th>
                </tr></thead>
                <tbody>
                    @forelse($detalle as $d)
                    @php
                        $ult = $d->ultimo_periodo
                            ? $meses[(int) substr((string) $d->ultimo_periodo, 4, 2)].' '.substr((string) $d->ultimo_periodo, 0, 4)
                            : null;
                    @endphp
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:.5rem 1rem;font-weight:600;">
                            <a href="{{ route('admin.informes.validacion_cierre.ficha', ['aliado_id' => $d->aliado_id, 'cedula' => $d->cedula]) }}"
                               target="_blank" rel="noopener"
                               title="Abrir la ficha del cliente en {{ $d->aliado }} (deja ese aliado como activo)"
                               style="color:#2563eb;text-decoration:none;">{{ $d->cedula }}</a>
                        </td>
                        <td style="padding:.5rem;color:#0d2550;">{{ $d->nombre ?: '—' }}</td>
                        <td style="padding:.5rem;">
                            <span style="font-size:.7rem;font-weight:700;padding:.15rem .45rem;border-radius:999px;
                                         background:{{ (int) $d->aliado_id === $aliadoPrincipal ? '#e0e7ff' : '#f1f5f9' }};
                                         color:{{ (int) $d->aliado_id === $aliadoPrincipal ? '#3730a3' : '#475569' }};">{{ $d->aliado }}</span>
                        </td>
                        <td style="padding:.5rem;color:#64748b;">{{ $d->plan_nombre ?? '—' }}</td>
                        <td style="padding:.5rem;color:#64748b;">{{ $d->modalidad ?? '—' }}</td>
                        <td style="padding:.5rem;text-align:center;color:#64748b;">{{ $d->fecha_ingreso ?? '—' }}</td>
                        <td style="padding:.5rem 1rem;text-align:center;">
                            @if($ult)
                                <span style="color:#475569;">{{ $ult }}</span>
                            @else
                                <span style="color:#b45309;font-weight:700;">Nunca</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="padding:2rem;text-align:center;color:#94a3b8;">Sin pendientes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Qué se está midiendo: el mes de PAGO y el período que llevan los planos --}}
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:.85rem 1rem;font-size:.78rem;color:#1e3a8a;line-height:1.45;">
        Pago de <strong>{{ $meses[$periodo['mes_pago']] }} {{ $periodo['anio_pago'] }}</strong> —
        planilla del período <strong>{{ $meses[$periodo['mes_vencido']] }}</strong> para dependientes y
        <strong>{{ $meses[$periodo['mes_pago']] }}</strong> para independientes.
        Se cuenta como pendiente el contrato vigente <strong>al que le toca cotizar</strong> y no tiene plano de
        planilla ni de retiro. Quedan fuera los de <strong>Gestión ARL</strong> —que por definición no son planilla
        mensual— y los <strong>afiliados dentro del período o después</strong>, porque el mes de la afiliación no se
        paga: su primera planilla es la del mes siguiente. Los <strong>retiros sí cuentan</strong>: a quien se retira
        hay que reportarlo.
        Con el mes en curso es normal que haya pendientes: la nómina se liquida por tandas.
        <strong>Al cerrar el mes, el que siga aquí es un retiro que nunca se registró.</strong>
    </div>
</div>

{{-- Modal: los vigentes que este período no deben cotizar --}}
<div id="vc-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:1000;align-items:center;justify-content:center;padding:1.5rem;">
    <div style="background:#fff;border-radius:14px;max-width:1000px;width:100%;max-height:85vh;display:flex;flex-direction:column;overflow:hidden;">
        <div style="padding:.9rem 1.1rem;border-bottom:2px solid #e2e8f0;display:flex;align-items:center;gap:.75rem;">
            <h3 id="vc-modal-titulo" style="font-size:.92rem;font-weight:700;color:#0d2550;flex:1;margin:0;"></h3>
            <button type="button" id="vc-modal-cerrar"
                    style="background:none;border:none;color:#64748b;font-size:1.1rem;cursor:pointer;line-height:1;">✕</button>
        </div>
        <div id="vc-modal-cuerpo" style="overflow:auto;padding:0;">
            <div style="padding:2rem;text-align:center;color:#94a3b8;font-size:.85rem;">Cargando…</div>
        </div>
    </div>
</div>

<script>
(function () {
    const modal   = document.getElementById('vc-modal');
    const titulo  = document.getElementById('vc-modal-titulo');
    const cuerpo  = document.getElementById('vc-modal-cuerpo');
    const baseUrl = @json(route('admin.informes.validacion_cierre.sin_planilla'));
    const filtros = @json(['mes' => $mes, 'anio' => $anio, 'aliado_id' => $fAliado]);

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => (
        {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]
    ));

    function cerrar() { modal.style.display = 'none'; }

    document.getElementById('vc-modal-cerrar').addEventListener('click', cerrar);
    modal.addEventListener('click', (e) => { if (e.target === modal) cerrar(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrar(); });

    document.querySelectorAll('.vc-sin-planilla').forEach((btn) => {
        btn.addEventListener('click', async () => {
            titulo.textContent = 'Sin planilla este período — ' + btn.dataset.rs;
            cuerpo.innerHTML = '<div style="padding:2rem;text-align:center;color:#94a3b8;font-size:.85rem;">Cargando…</div>';
            modal.style.display = 'flex';

            const params = new URLSearchParams({ ids: btn.dataset.ids, mes: filtros.mes, anio: filtros.anio });
            if (filtros.aliado_id) params.set('aliado_id', filtros.aliado_id);

            try {
                const res  = await fetch(baseUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();

                if (!data.filas.length) {
                    cuerpo.innerHTML = '<div style="padding:2rem;text-align:center;color:#94a3b8;font-size:.85rem;">No hay contratos por fuera de la planilla.</div>';
                    return;
                }

                cuerpo.innerHTML = `
                    <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                        <thead><tr style="background:#f8fafc;position:sticky;top:0;">
                            <th style="padding:.55rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Cédula</th>
                            <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Nombre</th>
                            <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Aliado</th>
                            <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Plan</th>
                            <th style="padding:.55rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Modalidad</th>
                            <th style="padding:.55rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Ingreso</th>
                            <th style="padding:.55rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Por qué no cotiza</th>
                        </tr></thead>
                        <tbody>${data.filas.map((f) => `
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:.5rem 1rem;font-weight:600;">
                                    <a href="${esc(f.ficha)}" target="_blank" rel="noopener"
                                       title="Abrir la ficha en ${esc(f.aliado)} (deja ese aliado como activo)"
                                       style="color:#2563eb;text-decoration:none;">${esc(f.cedula)}</a>
                                </td>
                                <td style="padding:.5rem;color:#0d2550;">${esc(f.nombre)}</td>
                                <td style="padding:.5rem;color:#475569;">${esc(f.aliado)}</td>
                                <td style="padding:.5rem;color:#64748b;">${esc(f.plan)}</td>
                                <td style="padding:.5rem;color:#64748b;">${esc(f.modalidad)}</td>
                                <td style="padding:.5rem;text-align:center;color:#64748b;">${esc(f.fecha_ingreso)}</td>
                                <td style="padding:.5rem 1rem;">
                                    <span style="font-size:.7rem;font-weight:700;padding:.15rem .5rem;border-radius:999px;background:#f5f3ff;color:#6d28d9;">${esc(f.motivo)}</span>
                                </td>
                            </tr>`).join('')}
                        </tbody>
                    </table>`;
            } catch (err) {
                cuerpo.innerHTML = '<div style="padding:2rem;text-align:center;color:#b91c1c;font-size:.85rem;">No se pudo cargar el detalle.</div>';
            }
        });
    });
})();
</script>
@endsection
