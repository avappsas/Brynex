@extends('layouts.app')
@section('modulo', 'Calendario tributario')
@section('contenido')

{{--
    Mantenimiento del calendario. Aquí se cargan a mano las fechas que la DIAN
    no publica en el calendario tributario (la exógena sale por resolución
    aparte) y las del ICA, que las fija cada municipio.

    Al guardar, la fecha se propaga a los renglones del checklist que sigan
    abiertos, para que entren al semáforo. Los ya pagados no se tocan.
--}}

<div style="max-width:1300px;margin:0 auto;"
     x-data="{
        // Objeto vacío y no null: x-show no desmonta el modal, así que el
        // x-model de adentro se evalúa aunque esté oculto.
        ver: false,
        editar: { obligacion_codigo: '', periodo: 1, depende_nit: '1' }
     }">

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <a href="{{ route('brynex.razones.index') }}" style="color:#64748b;font-size:0.78rem;text-decoration:none;">← Razones sociales</a>
            <h1 style="font-size:1.5rem;font-weight:800;color:#0d2550;margin:0.3rem 0 0 0;">📅 Calendario tributario {{ $anio }}</h1>
            <p style="color:#64748b;font-size:0.83rem;margin:0.2rem 0 0 0;">
                La DIAN vence por el último dígito del NIT, sin contar el dígito de verificación.
            </p>
        </div>
        <form method="GET" style="display:flex;gap:0.5rem;align-items:center;">
            <label style="font-size:0.8rem;color:#475569;font-weight:600;">Año gravable:</label>
            <select name="anio" onchange="this.form.submit()" style="padding:0.4rem 0.6rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;">
                @foreach($anios as $a)
                    <option value="{{ $a }}" @selected($anio == $a)>{{ $a }}</option>
                @endforeach
                @if(! $anios->contains($anio))
                    <option value="{{ $anio }}" selected>{{ $anio }}</option>
                @endif
            </select>
        </form>
    </div>

    @include('brynex.razones_sociales._alertas')

    <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:0.75rem 0.9rem;border-radius:10px;font-size:0.8rem;margin-bottom:1.25rem;">
        ℹ️ Las fechas de la DIAN vienen cargadas del calendario oficial 2026. Lo que hay que cargar a mano es la
        <strong>información exógena</strong> (la fija una resolución aparte) y el <strong>ICA</strong> de cada municipio.
    </div>

    @can('brynex_razones.gestionar')
        <div style="margin-bottom:1rem;">
            <button type="button" @click="ver = true; editar = { obligacion_codigo: '', periodo: 1, depende_nit: '1' }"
                    style="background:#047857;color:#fff;border:none;padding:0.45rem 0.9rem;border-radius:8px;font-size:0.82rem;font-weight:600;cursor:pointer;">
                + Cargar / corregir fechas
            </button>
        </div>
    @endcan

    @forelse($vencimientos as $codigo => $filas)
        @php $cat = $catalogo[$codigo] ?? null; @endphp
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:1rem;">
            <div style="background:#f8fafc;padding:0.7rem 1rem;border-bottom:1px solid #f1f5f9;">
                <h2 style="font-size:0.9rem;font-weight:800;color:#0d2550;margin:0;">
                    {{ \App\Models\BrynexObligacionCatalogo::ENTIDADES[$cat?->entidad] ?? '' }}
                    {{ $cat?->nombre ?? $codigo }}
                    @if($cat?->formulario)
                        <span style="color:#94a3b8;font-weight:400;font-size:0.78rem;">· formulario {{ $cat->formulario }}</span>
                    @endif
                </h2>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.82rem;">
                    <thead>
                        <tr style="background:#fff;color:#475569;font-size:0.7rem;text-transform:uppercase;text-align:center;">
                            <th style="padding:0.5rem 0.9rem;font-weight:700;text-align:left;">Período</th>
                            @foreach([1,2,3,4,5,6,7,8,9,0] as $d)
                                <th style="padding:0.5rem;font-weight:700;">…{{ $d }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($filas->groupBy('periodo') as $periodo => $delPeriodo)
                        @php $porDigito = $delPeriodo->keyBy(fn ($v) => $v->ultimo_digito ?? 'todos'); @endphp
                        <tr style="border-top:1px solid #f1f5f9;text-align:center;">
                            <td style="padding:0.5rem 0.9rem;text-align:left;font-weight:600;color:#334155;">
                                {{ $cat?->etiquetaPeriodo((int) $periodo) ?? ('Período ' . $periodo) }}
                                @can('brynex_razones.gestionar')
                                    <button type="button"
                                            @click="ver = true; editar = { obligacion_codigo: '{{ $codigo }}', periodo: {{ $periodo }}, depende_nit: '{{ $porDigito->has('todos') ? '0' : '1' }}' }"
                                            style="background:none;border:none;color:#1e3a8a;cursor:pointer;font-size:0.72rem;">✏️</button>
                                @endcan
                            </td>
                            @if($porDigito->has('todos'))
                                <td colspan="10" style="padding:0.5rem;color:#334155;font-weight:600;">
                                    {{ $porDigito['todos']->fecha_vencimiento->format('d/m/Y') }}
                                    <span style="color:#94a3b8;font-weight:400;font-size:0.72rem;">· igual para todos los NIT</span>
                                </td>
                            @else
                                @foreach([1,2,3,4,5,6,7,8,9,0] as $d)
                                    <td style="padding:0.5rem;color:#475569;font-variant-numeric:tabular-nums;">
                                        {{ $porDigito->has($d) ? $porDigito[$d]->fecha_vencimiento->format('d/m') : '—' }}
                                    </td>
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:2rem;text-align:center;color:#94a3b8;">
            No hay fechas cargadas para {{ $anio }}.
        </div>
    @endforelse

    {{-- ── Modal: cargar fechas ────────────────────────────────────── --}}
    <div x-show="ver" x-cloak
         style="position:fixed;inset:0;background:rgba(15,23,42,0.55);display:flex;align-items:center;justify-content:center;z-index:60;padding:1rem;"
         @click.self="ver = false">
        <div style="background:#fff;border-radius:16px;max-width:600px;width:100%;max-height:90vh;overflow-y:auto;padding:1.5rem;">
            <h2 style="font-size:1.05rem;font-weight:800;color:#0d2550;margin:0 0 1rem 0;">Cargar fechas de vencimiento</h2>

            <form method="POST" action="{{ route('brynex.razones.calendario.guardar') }}">
                @csrf
                <input type="hidden" name="anio" value="{{ $anio }}">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Obligación *
                        <select name="obligacion_codigo" x-model="editar.obligacion_codigo" required
                                style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                            <option value="">— Elegir —</option>
                            @foreach($catalogo as $codigo => $c)
                                <option value="{{ $codigo }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Período *
                        <input type="number" name="periodo" x-model="editar.periodo" min="1" max="12" required
                               style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                        <span style="display:block;font-weight:400;color:#94a3b8;font-size:0.7rem;margin-top:0.15rem;">
                            Bimestre 1-6, mes 1-12, cuatrimestre 1-3, anual 1.
                        </span>
                    </label>
                </div>

                <label style="font-size:0.78rem;font-weight:700;color:#334155;display:block;margin-top:0.9rem;">
                    ¿La fecha depende del NIT? *
                    <select name="depende_nit" x-model="editar.depende_nit" required
                            style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                        <option value="1">Sí — una fecha por último dígito (DIAN)</option>
                        <option value="0">No — la misma para todos (cámara de comercio, algunos ICA)</option>
                    </select>
                </label>

                <div x-show="editar.depende_nit === '0'" style="margin-top:0.9rem;">
                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Fecha única
                        <input type="date" name="fecha_unica"
                               style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                    </label>
                </div>

                <div x-show="editar.depende_nit === '1'" style="margin-top:0.9rem;">
                    <div style="font-size:0.78rem;font-weight:700;color:#334155;margin-bottom:0.4rem;">Fecha por último dígito del NIT</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:0.5rem;">
                        @foreach([1,2,3,4,5,6,7,8,9,0] as $d)
                            <label style="font-size:0.74rem;color:#64748b;">
                                Termina en {{ $d }}
                                <input type="date" name="fechas[{{ $d }}]"
                                       style="width:100%;margin-top:0.15rem;padding:0.35rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.78rem;">
                            </label>
                        @endforeach
                    </div>
                    <p style="color:#94a3b8;font-size:0.72rem;margin:0.5rem 0 0 0;">
                        Los que dejes vacíos conservan la fecha que ya tengan.
                    </p>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:0.6rem;margin-top:1.3rem;">
                    <button type="button" @click="ver = false" style="background:#f1f5f9;color:#334155;border:none;padding:0.5rem 1rem;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;">Cancelar</button>
                    <button type="submit" style="background:#047857;color:#fff;border:none;padding:0.5rem 1.2rem;border-radius:8px;font-size:0.85rem;font-weight:700;cursor:pointer;">Guardar fechas</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
