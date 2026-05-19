@extends('layouts.app')
@section('title', 'Distribución Afiliaciones')

@push('styles')
<style>
    :root {
        --c-bg:      #0d1b2e;
        --c-surface: #111e33;
        --c-border:  rgba(59,130,246,.18);
        --c-blue:    #3b82f6;
        --c-green:   #10b981;
        --c-yellow:  #f59e0b;
        --c-red:     #ef4444;
        --c-purple:  #8b5cf6;
        --c-text:    #e2e8f0;
        --c-muted:   rgba(226,232,240,.45);
    }
    .af-wrap { max-width: 1300px; margin: 0 auto; }

    .af-header {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 1.25rem; flex-wrap: wrap; gap: .75rem;
    }
    .af-title { font-size: 1.35rem; font-weight: 700; color: var(--c-text); display: flex; align-items: center; gap: .5rem; }
    .btn-back {
        background: transparent; color: var(--c-muted);
        border: 1px solid var(--c-border); border-radius: 7px;
        padding: .4rem .85rem; font-size: .8rem; text-decoration: none;
        transition: border-color .15s, color .15s;
    }
    .btn-back:hover { border-color: var(--c-blue); color: var(--c-blue); }

    /* Filtros */
    .filtros-bar {
        background: var(--c-surface); border: 1px solid var(--c-border);
        border-radius: 12px; padding: .85rem 1.1rem;
        display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
        margin-bottom: 1rem;
    }
    .filtros-bar label { font-size: .75rem; color: var(--c-muted); }
    .filtros-bar select { background: #0d1b2e; border: 1px solid var(--c-border); color: var(--c-text); border-radius: 7px; padding: .4rem .65rem; font-size: .82rem; outline: none; }
    .btn-filtrar { background: var(--c-blue); color: #fff; border: none; border-radius: 7px; padding: .42rem .9rem; font-size: .82rem; font-weight: 600; cursor: pointer; }
    .btn-filtrar:hover { opacity: .85; }

    /* Badge sin distribuir */
    .badge-sin { background: rgba(239,68,68,.15); color: #fca5a5; border: 1px solid rgba(239,68,68,.3); display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 20px; font-size: .7rem; font-weight: 700; }
    .badge-ok  { background: rgba(16,185,129,.12); color: #6ee7b7; border: 1px solid rgba(16,185,129,.25); display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 20px; font-size: .7rem; font-weight: 700; }

    /* Alerta contador */
    .alerta-sin {
        background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.25);
        border-radius: 10px; padding: .65rem 1rem;
        font-size: .82rem; color: #fca5a5; margin-bottom: 1rem;
        display: flex; align-items: center; gap: .5rem;
    }

    /* Panel tabla */
    .panel { background: var(--c-surface); border: 1px solid var(--c-border); border-radius: 14px; overflow: hidden; }
    .panel-head { padding: .7rem 1rem; border-bottom: 1px solid var(--c-border); font-size: .78rem; font-weight: 700; color: var(--c-muted); text-transform: uppercase; letter-spacing: .06em; display: flex; align-items: center; justify-content: space-between; }
    .tabla-wrap { overflow-x: auto; }
    table.af-table { width: 100%; border-collapse: collapse; }
    .af-table th { padding: .55rem .65rem; text-align: left; font-size: .7rem; font-weight: 700; color: var(--c-muted); text-transform: uppercase; letter-spacing: .06em; border-bottom: 1px solid var(--c-border); white-space: nowrap; }
    .af-table td { padding: .6rem .65rem; font-size: .82rem; color: var(--c-text); border-bottom: 1px solid rgba(59,130,246,.06); }
    .af-table tr.sin-dist td { background: rgba(239,68,68,.04); }
    .af-table tr:hover td { background: rgba(59,130,246,.06); }
    .af-table tr.sin-dist:hover td { background: rgba(239,68,68,.08); }
    .af-table tr:last-child td { border-bottom: none; }

    /* Inputs de distribución */
    .dist-input {
        width: 90px; background: var(--c-bg);
        border: 1px solid var(--c-border); color: var(--c-text);
        border-radius: 6px; padding: .3rem .45rem;
        font-size: .8rem; text-align: right; outline: none;
        transition: border-color .15s;
    }
    .dist-input:focus { border-color: var(--c-blue); }
    .dist-input.error { border-color: var(--c-red); }

    /* Botón editar/guardar fila */
    .btn-edit {
        background: rgba(59,130,246,.15); color: #93c5fd;
        border: 1px solid rgba(59,130,246,.25); border-radius: 6px;
        padding: .28rem .65rem; font-size: .75rem; font-weight: 600;
        cursor: pointer; transition: background .12s;
        white-space: nowrap;
    }
    .btn-edit:hover { background: rgba(59,130,246,.3); }
    .btn-save {
        background: var(--c-green); color: #fff;
        border: none; border-radius: 6px;
        padding: .28rem .65rem; font-size: .75rem; font-weight: 700;
        cursor: pointer; transition: opacity .12s;
        white-space: nowrap;
    }
    .btn-save:hover { opacity: .85; }
    .btn-cancel-row {
        background: transparent; color: var(--c-muted);
        border: 1px solid var(--c-border); border-radius: 6px;
        padding: .28rem .5rem; font-size: .72rem; cursor: pointer;
    }

    /* Suma residuo */
    .residuo { font-size: .72rem; }
    .residuo.ok { color: var(--c-green); }
    .residuo.err { color: var(--c-red); font-weight: 700; }

    .empty-state { padding: 3rem; text-align: center; color: var(--c-muted); font-size: .85rem; }
    .empty-state .icon { font-size: 2.5rem; margin-bottom: .5rem; }
</style>
@endpush

@section('contenido')
<div class="af-wrap">

    <div class="af-header">
        <div class="af-title">
            📋 Distribución de Afiliaciones
        </div>
        <a href="{{ route('admin.informes.comisiones.index', ['mes' => $mes, 'anio' => $anio]) }}" class="btn-back">
            ← Volver a Comisiones
        </a>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('admin.informes.comisiones.afiliaciones') }}" class="filtros-bar">
        <div>
            <label>Asesor</label>
            <select name="asesor_id">
                <option value="">— Todos —</option>
                @foreach($asesores as $a)
                    <option value="{{ $a->id }}" {{ $asesorId == $a->id ? 'selected' : '' }}>{{ $a->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Mes</label>
            <select name="mes">
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ $mes == $m ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create()->month($m)->locale('es')->monthName }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Año</label>
            <select name="anio">
                @foreach(range(2025, now()->year) as $y)
                    <option value="{{ $y }}" {{ $anio == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Mostrar</label>
            <select name="filtro">
                <option value="todas"           {{ $filtro === 'todas'           ? 'selected' : '' }}>Todas</option>
                <option value="sin_distribuir"  {{ $filtro === 'sin_distribuir'  ? 'selected' : '' }}>Sin distribuir</option>
            </select>
        </div>
        <button type="submit" class="btn-filtrar">🔍 Filtrar</button>
    </form>

    {{-- Alerta si hay sin distribuir --}}
    @if($totalSinDistribuir > 0)
        <div class="alerta-sin">
            ⚠️ Hay <strong>{{ $totalSinDistribuir }}</strong>
            {{ $totalSinDistribuir === 1 ? 'afiliación sin distribuir' : 'afiliaciones sin distribuir' }}
            en el período seleccionado.
        </div>
    @endif

    {{-- Tabla --}}
    <div class="panel">
        <div class="panel-head">
            Afiliaciones —
            {{ \Carbon\Carbon::create()->month($mes)->locale('es')->monthName }} {{ $anio }}
            <span style="color:var(--c-blue)">{{ $facturas->count() }} registros</span>
        </div>

        @if($facturas->isEmpty())
            <div class="empty-state">
                <div class="icon">📭</div>
                Sin afiliaciones en este período
            </div>
        @else
        <div class="tabla-wrap">
            <table class="af-table" id="tablaAfil">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Empresa</th>
                        <th>Asesor</th>
                        <th style="text-align:right">Afiliación</th>
                        <th style="text-align:right">💼 Asesor</th>
                        <th style="text-align:right">🔒 Retiro</th>
                        <th style="text-align:right">🏢 Gastos/Admin</th>
                        <th style="text-align:right">📊 Utilidad</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($facturas as $f)
                    <tr class="{{ !$f->distribuida ? 'sin-dist' : '' }}" id="row-{{ $f->id }}" data-id="{{ $f->id }}" data-afil="{{ (int)$f->afiliacion }}">
                        <td>
                            <strong>#{{ $f->numero_factura }}</strong>
                            <div style="font-size:.68rem; color:var(--c-muted)">{{ $f->fecha_pago }}</div>
                        </td>
                        <td>
                            <div>{{ trim($f->nombre_cliente) ?: $f->cedula }}</div>
                            <div style="font-size:.7rem; color:var(--c-muted)">{{ $f->cedula }}</div>
                        </td>
                        <td style="font-size:.78rem">{{ $f->empresa_nombre }}</td>
                        <td style="font-size:.78rem">{{ $f->asesor_nombre }}</td>
                        <td style="text-align:right; font-weight:700">${{ number_format($f->afiliacion) }}</td>

                        {{-- Campos dist (modo lectura) --}}
                        <td style="text-align:right" class="td-asesor">
                            <span class="val-asesor">${{ number_format($f->dist_asesor) }}</span>
                            <input class="dist-input" name="dist_asesor" value="{{ (int)$f->dist_asesor }}" style="display:none">
                        </td>
                        <td style="text-align:right" class="td-retiro">
                            <span class="val-retiro">${{ number_format($f->dist_retiro) }}</span>
                            <input class="dist-input" name="dist_retiro" value="{{ (int)$f->dist_retiro }}" style="display:none">
                        </td>
                        <td style="text-align:right" class="td-admon">
                            <span class="val-admon">${{ number_format($f->dist_admon) }}</span>
                            <input class="dist-input" name="dist_admon" value="{{ (int)$f->dist_admon }}" style="display:none">
                        </td>
                        <td style="text-align:right" class="td-util">
                            <span class="val-util">${{ number_format($f->dist_utilidad) }}</span>
                            <input class="dist-input" name="dist_utilidad" value="{{ (int)$f->dist_utilidad }}" style="display:none">
                        </td>

                        <td>
                            @if($f->distribuida)
                                <span class="badge-ok">✅ Distribuida</span>
                            @else
                                <span class="badge-sin">🔴 Sin distribuir</span>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex; gap:.4rem; align-items:center;">
                                <button class="btn-edit" onclick="editarFila({{ $f->id }})">✏️ Editar</button>
                                <button class="btn-save" id="save-{{ $f->id }}" onclick="guardarFila({{ $f->id }})" style="display:none">💾 Guardar</button>
                                <button class="btn-cancel-row" id="cancel-{{ $f->id }}" onclick="cancelarFila({{ $f->id }})" style="display:none">✕</button>
                            </div>
                            <div class="residuo" id="residuo-{{ $f->id }}"></div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

function editarFila(id) {
    const row = document.getElementById('row-' + id);
    // Mostrar inputs, ocultar spans
    row.querySelectorAll('.val-asesor,.val-retiro,.val-admon,.val-util').forEach(s => s.style.display = 'none');
    row.querySelectorAll('.dist-input').forEach(i => i.style.display = 'inline-block');
    row.querySelector('.btn-edit').style.display = 'none';
    document.getElementById('save-' + id).style.display = '';
    document.getElementById('cancel-' + id).style.display = '';

    // Evento de cálculo residuo
    row.querySelectorAll('.dist-input').forEach(input => {
        input.addEventListener('input', () => calcularResiduo(id));
    });
    calcularResiduo(id);
}

function cancelarFila(id) {
    const row = document.getElementById('row-' + id);
    row.querySelectorAll('.val-asesor,.val-retiro,.val-admon,.val-util').forEach(s => s.style.display = '');
    row.querySelectorAll('.dist-input').forEach(i => i.style.display = 'none');
    row.querySelector('.btn-edit').style.display = '';
    document.getElementById('save-' + id).style.display = 'none';
    document.getElementById('cancel-' + id).style.display = 'none';
    document.getElementById('residuo-' + id).textContent = '';
}

function calcularResiduo(id) {
    const row   = document.getElementById('row-' + id);
    const afil  = parseInt(row.dataset.afil) || 0;
    const inputs = row.querySelectorAll('.dist-input');
    let suma = 0;
    inputs.forEach(i => suma += parseInt(i.value) || 0);
    const diff = afil - suma;
    const el   = document.getElementById('residuo-' + id);
    if (diff === 0) {
        el.textContent = '✅ Cuadra';
        el.className = 'residuo ok';
    } else {
        el.textContent = (diff > 0 ? `Falta $${diff.toLocaleString()}` : `Excede $${Math.abs(diff).toLocaleString()}`);
        el.className = 'residuo err';
    }
}

async function guardarFila(id) {
    const row     = document.getElementById('row-' + id);
    const afil    = parseInt(row.dataset.afil) || 0;
    const inputs  = {};
    row.querySelectorAll('.dist-input').forEach(i => inputs[i.name] = parseInt(i.value) || 0);
    const suma = Object.values(inputs).reduce((a,b) => a+b, 0);

    if (suma !== afil) {
        alert(`La suma (${suma.toLocaleString()}) debe ser igual al valor de afiliación (${afil.toLocaleString()}).`);
        return;
    }

    const btn = document.getElementById('save-' + id);
    btn.disabled = true; btn.textContent = '⏳';

    try {
        const res = await fetch(`/admin/informes/comisiones/afiliaciones/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ...inputs, _method: 'POST' }),
        });
        const json = await res.json();
        if (json.ok) {
            // Actualizar spans con nuevos valores
            row.querySelector('.val-asesor').textContent = '$' + inputs.dist_asesor.toLocaleString();
            row.querySelector('.val-retiro').textContent = '$' + inputs.dist_retiro.toLocaleString();
            row.querySelector('.val-admon').textContent  = '$' + inputs.dist_admon.toLocaleString();
            row.querySelector('.val-util').textContent   = '$' + inputs.dist_utilidad.toLocaleString();
            // Actualizar badge
            const badge = row.querySelector('.badge-sin, .badge-ok');
            if (badge) { badge.className = 'badge-ok'; badge.textContent = '✅ Distribuida'; }
            row.classList.remove('sin-dist');
            cancelarFila(id);
        } else {
            alert(json.error || 'Error al guardar.');
            btn.disabled = false; btn.textContent = '💾 Guardar';
        }
    } catch(e) {
        alert('Error de red.');
        btn.disabled = false; btn.textContent = '💾 Guardar';
    }
}
</script>
@endpush
