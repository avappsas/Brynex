@extends('layouts.app')
@section('modulo', 'Niveles de Asesores')

@section('contenido')
<div style="max-width:1100px;margin:0 auto;" x-data="{ editando: null, creando: {{ $niveles->isEmpty() ? 'true' : 'false' }} }">

{{-- Encabezado --}}
<div style="margin-bottom:1.1rem;">
  <a href="{{ route('admin.configuracion.hub') }}"
     style="font-size:.73rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.35rem">
      ← Volver a Configuración
  </a>
  <h1 style="font-size:1.2rem;font-weight:700;color:#0f172a;margin:0;">🎚️ Niveles de Asesores</h1>
  <p style="font-size:0.78rem;color:#64748b;margin:0;">
    Plantillas de comisión por tamaño de cartera. Al asignarle un nivel a un asesor, sus valores se
    copian y desde ahí puedes ajustarlos uno por uno.
  </p>
</div>

{{-- El mensaje de éxito lo pinta el layout (layouts/app: .flash success). --}}
@if(session('error'))
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">⚠️ {{ session('error') }}</div>
@endif
@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
  <strong>Corrige:</strong> @foreach($errors->all() as $e) · {{ $e }} @endforeach
</div>
@endif

{{-- Cómo funciona --}}
<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.8rem 1rem;margin-bottom:1rem;font-size:0.75rem;color:#0c4a6e;line-height:1.6;">
  <strong>Cómo se llena rápido:</strong> el precio de afiliación, el retiro y los "otros" salen de
  <a href="{{ route('admin.configuracion.index') }}" style="color:#0369a1;font-weight:600;">Parámetros</a>
  y aquí solo escribes <strong>cuánto gana el asesor</strong> por cada plan — lo del aliado es el resto.
  La administración mensual es <strong>una sola por nivel</strong>, igual en todos los planes.
  Para crear el nivel 2, duplica el 1 y ajusta.
</div>

{{-- Listado --}}
@forelse($niveles as $n)
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:11px;padding:0.9rem 1.1rem;margin-bottom:0.7rem;">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
    <div style="min-width:0;">
      <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
        <span style="font-weight:700;color:#0f172a;font-size:0.95rem;">{{ $n->nombre }}</span>
        @if(! $n->activo)
        <span style="background:#f1f5f9;color:#64748b;font-size:0.62rem;font-weight:700;padding:0.1rem 0.45rem;border-radius:999px;">Inactivo</span>
        @endif
        <span style="background:#eff6ff;color:#1e40af;font-size:0.65rem;font-weight:600;padding:0.1rem 0.5rem;border-radius:999px;">{{ $n->rangoLabel() }}</span>
      </div>
      @if($n->descripcion)
      <div style="font-size:0.73rem;color:#64748b;margin-top:0.2rem;">{{ $n->descripcion }}</div>
      @endif
      <div style="font-size:0.73rem;color:#475569;margin-top:0.35rem;">
        Admon mensual del asesor: <strong style="color:#0891b2;">${{ number_format($n->admon_asesor, 0, ',', '.') }}</strong>
        <span style="color:#cbd5e1;margin:0 0.35rem;">·</span>
        @if($n->tarifas_count >= $totalCeldas)
        <span style="color:#166534;font-weight:600;">{{ $n->tarifas_count }}/{{ $totalCeldas }} celdas ✓</span>
        @else
        <span style="color:#b45309;font-weight:600;">{{ $n->tarifas_count }}/{{ $totalCeldas }} celdas</span>
        @endif
        <span style="color:#cbd5e1;margin:0 0.35rem;">·</span>
        {{ $n->asesores_count }} asesor(es)
      </div>
    </div>

    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
      <a href="{{ route('admin.configuracion.niveles.matriz', $n->id) }}"
         style="padding:0.42rem 0.9rem;background:linear-gradient(135deg,#0369a1,#075985);border-radius:7px;color:#fff;text-decoration:none;font-size:0.78rem;font-weight:600;">
        📊 Valores por plan
      </a>
      @can('asesores.gestionar')
      <button type="button" @click="editando = (editando === {{ $n->id }} ? null : {{ $n->id }})"
          style="padding:0.42rem 0.8rem;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#475569;font-size:0.78rem;cursor:pointer;">✏️ Editar</button>
      <form method="POST" action="{{ route('admin.configuracion.niveles.duplicar', $n->id) }}" style="display:inline;">
        @csrf
        <button type="submit"
            style="padding:0.42rem 0.8rem;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#475569;font-size:0.78rem;cursor:pointer;"
            title="Crea un nivel nuevo con los mismos valores">⧉ Duplicar</button>
      </form>
      <form method="POST" action="{{ route('admin.configuracion.niveles.destroy', $n->id) }}" style="display:inline;"
            onsubmit="return confirm('¿Eliminar el nivel «{{ $n->nombre }}» y sus valores por plan?')">
        @csrf @method('DELETE')
        <button type="submit"
            style="padding:0.42rem 0.7rem;border:1px solid #fecaca;border-radius:7px;background:#fff;color:#dc2626;font-size:0.78rem;cursor:pointer;">🗑</button>
      </form>
      @endcan
    </div>
  </div>

  {{-- Edición en línea --}}
  @can('asesores.gestionar')
  <div x-show="editando === {{ $n->id }}" x-cloak style="margin-top:0.9rem;padding-top:0.9rem;border-top:1px dashed #e2e8f0;">
    <form method="POST" action="{{ route('admin.configuracion.niveles.update', $n->id) }}">
      @csrf @method('PUT')
      @include('admin.configuracion.niveles._campos', ['nivel' => $n])
      <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.8rem;">
        <button type="button" @click="editando = null"
            style="padding:0.45rem 1rem;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#475569;font-size:0.8rem;cursor:pointer;">Cancelar</button>
        <button type="submit"
            style="padding:0.45rem 1.2rem;border:none;border-radius:7px;background:#0369a1;color:#fff;font-size:0.8rem;font-weight:700;cursor:pointer;">Guardar</button>
      </div>
    </form>
  </div>
  @endcan
</div>
@empty
<div style="background:#fff;border:1.5px dashed #cbd5e1;border-radius:11px;padding:2rem;text-align:center;color:#64748b;margin-bottom:0.7rem;">
  <div style="font-size:2rem;margin-bottom:0.4rem;">🎚️</div>
  <div style="font-weight:600;color:#0f172a;margin-bottom:0.2rem;">Aún no hay niveles</div>
  <div style="font-size:0.8rem;">Crea el primero abajo. Por ejemplo: <em>Nivel 1 — de 1 a 10 contratos</em>.</div>
</div>
@endforelse

{{-- Alta --}}
@can('asesores.gestionar')
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:11px;padding:0.9rem 1.1rem;">
  <button type="button" @click="creando = !creando" x-show="!creando"
      style="width:100%;padding:0.6rem;border:1.5px dashed #cbd5e1;border-radius:8px;background:#f8fafc;color:#475569;font-size:0.85rem;font-weight:600;cursor:pointer;">
    + Crear nivel
  </button>

  <div x-show="creando" x-cloak>
    <div style="font-size:0.8rem;font-weight:700;color:#0f172a;margin-bottom:0.75rem;">Nuevo nivel</div>
    <form method="POST" action="{{ route('admin.configuracion.niveles.store') }}">
      @csrf
      @include('admin.configuracion.niveles._campos', ['nivel' => null])
      <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.8rem;">
        <button type="button" @click="creando = false" x-show="{{ $niveles->isEmpty() ? 'false' : 'true' }}"
            style="padding:0.45rem 1rem;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#475569;font-size:0.8rem;cursor:pointer;">Cancelar</button>
        <button type="submit"
            style="padding:0.45rem 1.3rem;border:none;border-radius:7px;background:linear-gradient(135deg,#0369a1,#075985);color:#fff;font-size:0.8rem;font-weight:700;cursor:pointer;">
          Crear y definir valores →
        </button>
      </div>
    </form>
  </div>
</div>
@endcan

{{-- Asesores y el nivel que les correspondería --}}
@if($asesores->isNotEmpty())
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:11px;padding:0.9rem 1.1rem;margin-top:1rem;">
  <div style="font-size:0.72rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.3rem;">
    Asesores y su nivel
  </div>
  <div style="font-size:0.72rem;color:#94a3b8;margin-bottom:0.75rem;">
    La sugerencia sale de los contratos vigentes. El sistema <strong>no cambia el nivel solo</strong>:
    lo asignas tú desde la ficha del asesor.
  </div>
  <div style="overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:0.79rem;">
    <thead>
      <tr style="background:#f8fafc;border-bottom:1.5px solid #e2e8f0;">
        <th style="padding:0.45rem 0.6rem;text-align:left;color:#475569;font-size:0.68rem;">ASESOR</th>
        <th style="padding:0.45rem 0.6rem;text-align:right;color:#475569;font-size:0.68rem;">VIGENTES</th>
        <th style="padding:0.45rem 0.6rem;text-align:left;color:#475569;font-size:0.68rem;">NIVEL ACTUAL</th>
        <th style="padding:0.45rem 0.6rem;text-align:left;color:#475569;font-size:0.68rem;">SUGERIDO</th>
      </tr>
    </thead>
    <tbody>
      @foreach($asesores as $a)
      @php
        $vig       = (int) ($vigentesPorAsesor[$a->id] ?? 0);
        $sugerido  = $niveles->firstWhere(fn($n) => $n->activo && $n->cubreCantidad($vig));
        $coincide  = $sugerido && (int) $a->nivel_id === (int) $sugerido->id;
      @endphp
      <tr style="border-bottom:1px solid #f1f5f9;">
        <td style="padding:0.4rem 0.6rem;">
          <a href="{{ route('admin.asesores.edit', $a->id) }}" style="color:#0369a1;text-decoration:none;font-weight:600;">{{ $a->nombre }}</a>
        </td>
        <td style="padding:0.4rem 0.6rem;text-align:right;font-family:monospace;color:#475569;">{{ $vig }}</td>
        <td style="padding:0.4rem 0.6rem;">
          @if($a->nivel_id)
            {{ $niveles->firstWhere('id', $a->nivel_id)?->nombre ?? '—' }}
          @else
            <span style="color:#b45309;font-size:0.72rem;">sin nivel · usa su comisión actual</span>
          @endif
        </td>
        <td style="padding:0.4rem 0.6rem;">
          @if(! $sugerido)
            <span style="color:#cbd5e1;">—</span>
          @elseif($coincide)
            <span style="color:#166534;font-size:0.72rem;">✓ {{ $sugerido->nombre }}</span>
          @else
            <span style="background:#fef9c3;color:#92400e;font-size:0.68rem;font-weight:700;padding:0.1rem 0.45rem;border-radius:999px;">{{ $sugerido->nombre }}</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  </div>
</div>
@endif

</div>
@endsection
