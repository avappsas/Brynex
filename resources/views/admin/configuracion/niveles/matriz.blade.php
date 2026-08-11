@extends('layouts.app')
@section('modulo', $titulo)

@section('contenido')
<style>
/* Selector de plan: mismas tarjetas que el tarifario de Parámetros. */
.btn-plan {
    display:flex; flex-direction:column; align-items:flex-start; gap:0.15rem;
    padding:0.45rem 0.75rem; border-radius:9px; cursor:pointer; text-align:left;
    border:1.5px solid; transition:all .12s ease; min-width:130px; line-height:1.25;
}
.btn-plan-nombre { font-size:0.76rem; font-weight:700; white-space:nowrap; }
.btn-plan-meta   { font-size:0.62rem; opacity:.9; white-space:nowrap; }

.btn-plan.plan-off       { background:#fff; color:#475569; border-color:#e2e8f0; }
.btn-plan.plan-off:hover { border-color:#93c5fd; background:#f8fafc; transform:translateY(-1px); }
.btn-plan.plan-on        { background:linear-gradient(135deg,#0369a1,#075985); color:#fff;
                           border-color:#075985; box-shadow:0 3px 10px rgba(3,105,161,.28); }
.btn-plan.plan-on .btn-plan-meta span { color:#bae6fd !important; }
</style>
<div style="max-width:1150px;margin:0 auto;">

{{-- Encabezado --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;">
  <div>
    <a href="{{ $volverUrl }}"
       style="font-size:.73rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.35rem">
        ← {{ $volverTexto }}
    </a>
    <h1 style="font-size:1.2rem;font-weight:700;color:#0f172a;margin:0;">📊 {{ $titulo }}</h1>
    <p style="font-size:0.78rem;color:#64748b;margin:0;">
      Escribe <strong>cuánto gana el asesor</strong> por cada afiliación. Lo demás se hereda de Parámetros.
    </p>
  </div>
  <div style="font-size:0.73rem;color:#64748b;text-align:right;">
    @if($contexto === 'nivel')
      {{ $nivel->rangoLabel() }}<br>
      <span style="color:#94a3b8;">{{ $nivel->asesores()->count() }} asesor(es) con este nivel</span>
    @else
      @if($nivelDelAsesor)
        Copiado de <strong>{{ $nivelDelAsesor->nombre }}</strong><br>
      @endif
      <span style="color:#94a3b8;">Estos valores son solo de este asesor</span>
    @endif
  </div>
</div>

{{-- El mensaje de éxito lo pinta el layout (layouts/app: .flash success); repetirlo aquí
     lo mostraba dos veces. --}}
@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
  <strong>Corrige:</strong> @foreach($errors->all() as $e) · {{ $e }} @endforeach
</div>
@endif

<form method="POST" action="{{ $rutaGuarda }}" id="formMatriz">
@csrf

{{-- Admon del nivel + ayudas globales --}}
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:11px;padding:0.9rem 1.1rem;margin-bottom:1rem;">
  <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:1.5rem;flex-wrap:wrap;">
    <div style="min-width:230px;">
      <label style="display:block;font-size:0.65rem;font-weight:700;color:#0891b2;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.3rem;">
        💼 Administración mensual del asesor
      </label>
      <div style="display:flex;align-items:center;gap:0.3rem;">
        <span style="color:#0891b2;font-size:0.95rem;font-weight:700;">$</span>
        <input type="number" name="admon_asesor" min="0" step="500"
            value="{{ intval($admonValor) }}"
            style="width:150px;padding:0.5rem 0.65rem;border:2px solid #a5f3fc;border-radius:8px;font-size:0.95rem;font-family:monospace;font-weight:700;color:#0891b2;text-align:right;">
      </div>
      <div style="font-size:0.68rem;color:#94a3b8;margin-top:0.3rem;">
        Un solo valor: es igual en todos los planes.
      </div>
    </div>

    <div style="display:flex;gap:0.45rem;flex-wrap:wrap;">
      <button type="button" onclick="aplicarATodo()"
          style="font-size:0.73rem;padding:0.42rem 0.8rem;border:1px solid #bae6fd;border-radius:7px;background:#f0f9ff;color:#0369a1;cursor:pointer;font-weight:600;"
          title="Pone el mismo valor de asesor en todas las celdas de todos los planes">
        ⌸ Poner un valor en todo
      </button>
      <button type="button" onclick="aplicarPorcentaje()"
          style="font-size:0.73rem;padding:0.42rem 0.8rem;border:1px solid #ddd6fe;border-radius:7px;background:#faf5ff;color:#7c3aed;cursor:pointer;font-weight:600;"
          title="Calcula lo del asesor como un % del precio de afiliación de cada celda">
        % Aplicar porcentaje
      </button>
    </div>
  </div>
</div>

{{-- Tarjetas por modalidad → plan → riesgo --}}
@php
  $modsNormales = array_filter($matriz, fn ($t) => ! $t['tiempo_parcial']);
  $modsParcial  = array_filter($matriz, fn ($t) => $t['tiempo_parcial']);
@endphp

<div style="background:#fff;border:1px solid #e2e8f0;border-radius:11px;padding:0.9rem 1.1rem;margin-bottom:1rem;"
     x-data="{ abierto: null, opcion: {} }">
  <div style="font-size:0.7rem;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.6rem;">
    Comisión por modalidad
  </div>
  @foreach($modsNormales as $t)
    @include('admin.configuracion.niveles._modalidad', ['t' => $t])
  @endforeach
</div>

@if(count($modsParcial))
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:11px;padding:0.9rem 1.1rem;margin-bottom:1rem;"
     x-data="{ abierto: null, opcion: {} }">
  <div style="font-size:0.7rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:0.05em;">
    ⏱️ Tiempo Parcial
  </div>
  <div style="font-size:0.7rem;color:#64748b;margin:0.2rem 0 0.7rem 0;">
    Las {{ count($modsParcial) }} variantes, separadas de las modalidades de mes completo.
  </div>
  @foreach($modsParcial as $t)
    @include('admin.configuracion.niveles._modalidad', ['t' => $t])
  @endforeach
</div>
@endif

<div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;">
  <a href="{{ $volverUrl }}"
     style="padding:0.6rem 1.25rem;border:1px solid #cbd5e1;border-radius:8px;color:#475569;text-decoration:none;font-size:0.85rem;">Cancelar</a>
  <button type="submit"
      style="padding:0.6rem 2rem;background:linear-gradient(135deg,#0369a1,#075985);border:none;border-radius:9px;color:#fff;font-size:0.88rem;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(3,105,161,0.3);">
    💾 Guardar valores
  </button>
</div>

</form>
</div>

<script>
// Valores heredados de Parámetros por celda: {clave: {publico, retiro, otros, admon}}.
const BASE = @json($base);

const digitos = v => parseInt(String(v ?? '').replace(/\D/g, '') || '0', 10);
const miles   = n => String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

/** Recalcula lo que le queda al aliado en esa fila y avisa si el reparto se pasó del precio. */
function recalcAliado(input) {
    const clave = input.dataset.clave;
    const b     = BASE[clave];
    const td    = document.querySelector(`[data-aliado="${clave}"]`);
    if (!b || !td) return;

    const resto = b.publico - b.retiro - b.otros - digitos(input.value);
    td.textContent = miles(resto);

    // Rojo = el reparto se pasó del precio de afiliación. No se bloquea: al crear el
    // contrato la parte del aliado se ajusta a 0, pero conviene verlo aquí.
    td.style.color            = resto < 0 ? '#dc2626' : '#166534';
    input.style.borderColor   = resto < 0 ? '#fca5a5' : '#ddd6fe';
    input.style.background    = resto < 0 ? '#fef2f2' : '#fff';

    actualizarResumen(input.dataset.card);
    actualizarAvanceOpcion(input.dataset.opcion);
}

/** Resumen en la cabecera de la tarjeta: celdas llenas y cuántas quedaron descuadradas. */
function actualizarResumen(card) {
    const inputs = document.querySelectorAll(`.celda-asesor[data-card="${card}"]`);
    let llenas = 0, malas = 0;

    inputs.forEach(i => {
        if (i.value.trim() !== '') llenas++;
        const b = BASE[i.dataset.clave];
        if (b && (b.publico - b.retiro - b.otros - digitos(i.value)) < 0) malas++;
    });

    const span = document.querySelector(`[data-resumen-card="${card}"]`);
    if (!span) return;
    span.innerHTML = malas > 0
        ? `<span style="color:#dc2626;font-weight:700;">${malas} sin cuadrar</span> · ${llenas}/${inputs.length} celdas`
        : `${llenas}/${inputs.length} celdas`;
}

/** Contador del botón de opción: cuántos riesgos de esa combinación ya tienen valor. */
function actualizarAvanceOpcion(opcion) {
    const inputs = document.querySelectorAll(`.celda-asesor[data-opcion="${opcion}"]`);
    const span   = document.querySelector(`[data-avance-opcion="${opcion}"]`);
    if (!span || !inputs.length) return;

    const llenas = [...inputs].filter(i => i.value.trim() !== '').length;
    const color  = llenas >= inputs.length ? '#16a34a' : '#b45309';
    const marca  = llenas >= inputs.length ? '✓ ' : '';
    span.innerHTML = `<span style="color:${color};font-weight:700;">${marca}${llenas}/${inputs.length}</span>`;
}

/** Copia lo del asesor del riesgo 1 a los riesgos 2..5 de esa modalidad. */
function replicarNivel(plan, mod) {
    const origen = document.querySelector(`.celda-asesor[data-plan="${plan}"][data-mod="${mod}"][data-nivel="1"]`);
    if (!origen) return;
    for (let n = 2; n <= 5; n++) {
        const d = document.querySelector(`.celda-asesor[data-plan="${plan}"][data-mod="${mod}"][data-nivel="${n}"]`);
        if (!d) continue;
        d.value = origen.value;
        recalcAliado(d);
    }
}

/** Mismo valor de asesor en todas las celdas de todos los planes. */
function aplicarATodo() {
    const v = prompt('¿Cuánto gana el asesor por afiliación, en todas las celdas?\n\nEjemplo: 80000');
    if (v === null) return;
    const n = digitos(v);
    document.querySelectorAll('.celda-asesor').forEach(i => { i.value = miles(n); recalcAliado(i); });
}

/** Lo del asesor como % del precio de afiliación de cada celda, redondeado a miles. */
function aplicarPorcentaje() {
    const p = prompt('¿Qué porcentaje del precio de afiliación se lleva el asesor?\n\nEjemplo: 60');
    if (p === null) return;
    const pct = parseFloat(String(p).replace(',', '.'));
    if (isNaN(pct) || pct < 0 || pct > 100) { alert('Escribe un porcentaje entre 0 y 100.'); return; }

    document.querySelectorAll('.celda-asesor').forEach(i => {
        const b = BASE[i.dataset.clave];
        if (!b) return;
        i.value = miles(Math.round(b.publico * pct / 100 / 1000) * 1000);
        recalcAliado(i);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const inputs = document.querySelectorAll('.input-miles');

    inputs.forEach(input => {
        input.addEventListener('input', function (e) {
            const pos  = e.target.selectionStart;
            const antes = e.target.value.length;
            const limpio = e.target.value.replace(/\D/g, '');
            if (limpio === '') { e.target.value = ''; return; }
            e.target.value = miles(limpio);
            const diff = e.target.value.length - antes;
            e.target.setSelectionRange(pos + diff, pos + diff);
        });
    });

    // Los puntos de miles son solo de pantalla: a Laravel le llegan números limpios.
    document.getElementById('formMatriz')?.addEventListener('submit', function () {
        inputs.forEach(i => { i.value = i.value.replace(/\D/g, ''); });
    });

    // Pintar los resúmenes y los descuadres que ya vienen de la BD.
    document.querySelectorAll('[data-resumen-card]').forEach(s => actualizarResumen(s.dataset.resumenCard));
    document.querySelectorAll('.celda-asesor').forEach(i => { if (i.value.trim() !== '') recalcAliado(i); });
});
</script>
@endsection
