@extends('layouts.app')
@section('modulo', 'Razones Sociales BryNex')
@section('contenido')

{{--
    Listado de TODAS las razones sociales de TODOS los aliados, agrupadas por
    NIT. De aquí se escoge a cuáles hacerles seguimiento: al seguir una se crea
    la ficha maestra y se le genera el checklist tributario.
--}}

<div style="max-width:1500px;margin:0 auto;" x-data="{ seguir: null }">

    {{-- ── Cabecera ────────────────────────────────────────────────── --}}
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:#0d2550;margin:0;">🏛️ Razones Sociales de BryNex</h1>
            <p style="color:#64748b;font-size:0.83rem;margin:0.2rem 0 0 0;">
                Agrupadas por NIT: una misma empresa puede estar en varios aliados y sus obligaciones ante la DIAN son una sola.
            </p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a href="{{ route('brynex.razones.tablero') }}" style="background:#b91c1c;color:#fff;text-decoration:none;padding:0.5rem 0.9rem;border-radius:8px;font-size:0.82rem;font-weight:600;">🚨 Vencimientos</a>
            <a href="{{ route('brynex.razones.calendario') }}" style="background:#fff;color:#334155;text-decoration:none;padding:0.5rem 0.9rem;border-radius:8px;font-size:0.82rem;font-weight:600;border:1px solid #cbd5e1;">📅 Calendario</a>
        </div>
    </div>

    {{-- ── KPIs ────────────────────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem;">
        @php
            $tarjetas = [
                ['Razones sociales', number_format($resumen['total'], 0, ',', '.'), 'NIT distintos en toda la plataforma', '#1e3a8a', '🏢'],
                ['En seguimiento',   number_format($resumen['seguidas'], 0, ',', '.'), 'Con ficha y checklist activo', '#047857', '✅'],
                ['Propias de BryNex', number_format($resumen['propias'], 0, ',', '.'), 'De la casa, no de terceros', '#7c3aed', '🏠'],
                ['Afiliados vigentes', number_format($resumen['afiliados'], 0, ',', '.'), 'Sumando todos los aliados', '#b45309', '👥'],
            ];
        @endphp
        @foreach($tarjetas as [$titulo, $valor, $pie, $color, $icono])
            <div style="background:#fff;border-radius:14px;padding:1.1rem;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                    <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;font-weight:700;">{{ $titulo }}</div>
                    <span style="font-size:1.1rem;opacity:0.5;">{{ $icono }}</span>
                </div>
                <div style="font-size:1.6rem;font-weight:800;color:{{ $color }};line-height:1.2;margin-top:0.3rem;">{{ $valor }}</div>
                <div style="font-size:0.7rem;color:#94a3b8;margin-top:0.2rem;">{{ $pie }}</div>
            </div>
        @endforeach
    </div>

    {{-- ── Filtros ─────────────────────────────────────────────────── --}}
    <form method="GET" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:0.85rem;display:flex;gap:0.6rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem;">
        <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Buscar por nombre o NIT…"
               style="flex:1;min-width:220px;padding:0.45rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;">

        <select name="filtro" style="padding:0.45rem 0.6rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;">
            <option value="todas"      @selected($filtro==='todas')>Todas</option>
            <option value="seguidas"   @selected($filtro==='seguidas')>En seguimiento</option>
            <option value="sin_seguir" @selected($filtro==='sin_seguir')>Sin seguir</option>
            <option value="propias"    @selected($filtro==='propias')>Propias de BryNex</option>
        </select>

        <select name="aliado" style="padding:0.45rem 0.6rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;">
            <option value="">Cualquier aliado</option>
            @foreach($aliados as $a)
                <option value="{{ $a->id }}" @selected((string)$alidoId === (string)$a->id)>{{ $a->nombre }}</option>
            @endforeach
        </select>

        <button type="submit" style="background:#1e3a8a;color:#fff;border:none;padding:0.45rem 1rem;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;">Filtrar</button>
        @if($buscar || $filtro !== 'todas' || $alidoId)
            <a href="{{ route('brynex.razones.index') }}" style="color:#64748b;font-size:0.8rem;text-decoration:none;">Limpiar</a>
        @endif
    </form>

    @include('brynex.razones_sociales._alertas')

    {{-- ── Tabla ───────────────────────────────────────────────────── --}}
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                <thead>
                    <tr style="background:#f8fafc;text-align:left;color:#475569;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;">
                        <th style="padding:0.7rem 0.9rem;font-weight:700;">Razón social</th>
                        <th style="padding:0.7rem;font-weight:700;">NIT</th>
                        <th style="padding:0.7rem;font-weight:700;text-align:center;">Aliados</th>
                        <th style="padding:0.7rem;font-weight:700;text-align:right;">Afiliados vigentes</th>
                        <th style="padding:0.7rem;font-weight:700;">Régimen</th>
                        <th style="padding:0.7rem;font-weight:700;">Estado</th>
                        <th style="padding:0.7rem;font-weight:700;"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($razones as $r)
                    <tr style="border-top:1px solid #f1f5f9;">
                        <td style="padding:0.65rem 0.9rem;font-weight:600;color:#0f172a;">
                            {{ $r->razon_social }}
                            @if($r->propiedad === 'brynex')
                                <span style="background:#ede9fe;color:#6d28d9;font-size:0.65rem;font-weight:700;padding:0.1rem 0.4rem;border-radius:20px;margin-left:0.3rem;">PROPIA</span>
                            @endif
                        </td>
                        <td style="padding:0.65rem 0.7rem;color:#475569;font-variant-numeric:tabular-nums;">{{ number_format($r->nit, 0, ',', '.') }}</td>
                        <td style="padding:0.65rem 0.7rem;text-align:center;">
                            <span title="{{ $r->n_activas }} fila(s) activa(s) de {{ $r->n_aliados }} aliado(s)"
                                  style="background:{{ $r->n_aliados > 1 ? '#fef3c7' : '#f1f5f9' }};color:{{ $r->n_aliados > 1 ? '#92400e' : '#475569' }};font-weight:700;font-size:0.75rem;padding:0.15rem 0.5rem;border-radius:20px;">
                                {{ $r->n_aliados }}
                            </span>
                        </td>
                        <td style="padding:0.65rem 0.7rem;text-align:right;font-weight:700;color:{{ $r->afiliados > 0 ? '#0f172a' : '#cbd5e1' }};font-variant-numeric:tabular-nums;">
                            {{ number_format($r->afiliados, 0, ',', '.') }}
                        </td>
                        <td style="padding:0.65rem 0.7rem;color:#475569;font-size:0.78rem;">
                            {{ $r->regimen ? ($r->regimen === 'RST' ? 'Simple' : 'Ordinario') : '—' }}
                        </td>
                        <td style="padding:0.65rem 0.7rem;">
                            @if($r->seguida)
                                <span style="color:#047857;font-weight:700;font-size:0.75rem;">✅ En seguimiento</span>
                            @elseif($r->ficha)
                                <span style="color:#94a3b8;font-weight:600;font-size:0.75rem;">⏸ Pausada</span>
                            @else
                                <span style="color:#cbd5e1;font-size:0.75rem;">Sin ficha</span>
                            @endif
                        </td>
                        <td style="padding:0.65rem 0.9rem;text-align:right;white-space:nowrap;">
                            @if($r->ficha)
                                <a href="{{ route('brynex.razones.show', $r->ficha->id) }}"
                                   style="background:#1e3a8a;color:#fff;text-decoration:none;padding:0.3rem 0.7rem;border-radius:6px;font-size:0.75rem;font-weight:600;">Abrir</a>
                            @else
                                @can('brynex_razones.gestionar')
                                    <button type="button"
                                            @click="seguir = { nit: '{{ $r->nit }}', razon_social: @js($r->razon_social) }"
                                            style="background:#047857;color:#fff;border:none;padding:0.3rem 0.7rem;border-radius:6px;font-size:0.75rem;font-weight:600;cursor:pointer;">
                                        + Seguir
                                    </button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:2rem;text-align:center;color:#94a3b8;">No hay razones sociales que coincidan con el filtro.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($razones->hasPages())
            <div style="padding:0.8rem;border-top:1px solid #f1f5f9;">{{ $razones->links() }}</div>
        @endif
    </div>

    {{-- ── Modal: poner en seguimiento ─────────────────────────────── --}}
    <div x-show="seguir" x-cloak
         style="position:fixed;inset:0;background:rgba(15,23,42,0.55);display:flex;align-items:center;justify-content:center;z-index:60;padding:1rem;"
         @click.self="seguir = null">
        <div style="background:#fff;border-radius:16px;max-width:640px;width:100%;max-height:90vh;overflow-y:auto;padding:1.5rem;">
            <h2 style="font-size:1.15rem;font-weight:800;color:#0d2550;margin:0 0 0.2rem 0;">Poner en seguimiento</h2>
            <p style="color:#64748b;font-size:0.8rem;margin:0 0 1.1rem 0;" x-text="seguir?.razon_social"></p>

            <form method="POST" action="{{ route('brynex.razones.seguir') }}">
                @csrf
                <input type="hidden" name="nit" :value="seguir?.nit">
                <input type="hidden" name="razon_social" :value="seguir?.razon_social">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.9rem;">
                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Dígito de verificación
                        <input type="number" name="dv" min="0" max="9" style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                    </label>

                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        ¿De quién es? *
                        <select name="propiedad" required style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                            <option value="tercero">🤝 De un tercero</option>
                            <option value="brynex">🏠 Propia de BryNex</option>
                        </select>
                    </label>

                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Régimen tributario *
                        <select name="regimen" required style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                            <option value="">— Elegir —</option>
                            <option value="RST">Régimen Simple (RST)</option>
                            <option value="ORDINARIO">Régimen Ordinario</option>
                        </select>
                    </label>

                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        IVA *
                        <select name="periodicidad_iva" required style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                            <option value="no_responsable">No responsable de IVA</option>
                            <option value="bimestral">Bimestral (≥ 92.000 UVT el año anterior)</option>
                            <option value="cuatrimestral">Cuatrimestral (&lt; 92.000 UVT)</option>
                            <option value="anual">Anual (régimen simple)</option>
                        </select>
                    </label>

                    <label style="font-size:0.78rem;font-weight:700;color:#334155;grid-column:span 2;">
                        Fecha de constitución *
                        <input type="date" name="fecha_constitucion" required max="{{ now()->toDateString() }}"
                               style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                        <span style="display:block;font-weight:400;color:#94a3b8;font-size:0.72rem;margin-top:0.2rem;">
                            Desde este año se genera el checklist hacia atrás, para poder ponerse al día con los soportes viejos.
                        </span>
                    </label>

                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Municipio (ICA)
                        <input type="text" name="municipio_ica" placeholder="Cali" style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                    </label>

                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Periodicidad del ICA
                        <select name="periodicidad_ica" style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                            <option value="">— No se controla —</option>
                            <option value="bimestral">Bimestral</option>
                            <option value="anual">Anual</option>
                        </select>
                    </label>

                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Vence la firma electrónica
                        <input type="date" name="firma_electronica_vence" style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                    </label>

                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Contador responsable
                        <select name="contador_id" style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                            <option value="">— Sin asignar —</option>
                            @foreach(\App\Models\User::where('es_brynex', true)->where('activo', true)->orderBy('nombre')->get(['id','nombre']) as $u)
                                <option value="{{ $u->id }}">{{ $u->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:0.6rem;margin-top:1.3rem;">
                    <button type="button" @click="seguir = null" style="background:#f1f5f9;color:#334155;border:none;padding:0.5rem 1rem;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;">Cancelar</button>
                    <button type="submit" style="background:#047857;color:#fff;border:none;padding:0.5rem 1.2rem;border-radius:8px;font-size:0.85rem;font-weight:700;cursor:pointer;">Crear ficha y generar checklist</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
