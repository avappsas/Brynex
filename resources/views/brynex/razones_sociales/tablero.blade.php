@extends('layouts.app')
@section('modulo', 'Vencimientos tributarios')
@section('contenido')

{{-- Pantalla de entrada del contador: qué se venció y qué está por vencer, de
     todas las razones sociales en seguimiento. --}}

<div style="max-width:1400px;margin:0 auto;">

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <a href="{{ route('brynex.razones.index') }}" style="color:#64748b;font-size:0.78rem;text-decoration:none;">← Razones sociales</a>
            <h1 style="font-size:1.5rem;font-weight:800;color:#0d2550;margin:0.3rem 0 0 0;">🚨 Vencimientos tributarios</h1>
            <p style="color:#64748b;font-size:0.83rem;margin:0.2rem 0 0 0;">
                Solo las razones sociales en seguimiento y con ficha activa.
            </p>
        </div>
        <form method="GET" style="display:flex;gap:0.5rem;align-items:center;">
            <label style="font-size:0.8rem;color:#475569;font-weight:600;">Ver lo que vence en:</label>
            <select name="dias" onchange="this.form.submit()" style="padding:0.4rem 0.6rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;">
                @foreach([15, 30, 60, 90] as $d)
                    <option value="{{ $d }}" @selected($dias === $d)>{{ $d }} días</option>
                @endforeach
            </select>
        </form>
    </div>

    @include('brynex.razones_sociales._alertas')

    {{-- ── Vencidas ────────────────────────────────────────────────── --}}
    <div style="background:#fff;border:1px solid {{ $vencidas->count() ? '#fecaca' : '#e2e8f0' }};border-radius:12px;overflow:hidden;margin-bottom:1.25rem;">
        <div style="background:{{ $vencidas->count() ? '#fef2f2' : '#f8fafc' }};padding:0.8rem 1rem;border-bottom:1px solid #f1f5f9;">
            <h2 style="font-size:0.95rem;font-weight:800;color:{{ $vencidas->count() ? '#991b1b' : '#334155' }};margin:0;">
                🔴 Vencidas sin presentar ({{ $vencidas->count() }})
            </h2>
        </div>
        @if($vencidas->count())
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.84rem;">
                    <thead>
                        <tr style="background:#f8fafc;color:#475569;font-size:0.7rem;text-transform:uppercase;text-align:left;">
                            <th style="padding:0.6rem 0.9rem;font-weight:700;">Razón social</th>
                            <th style="padding:0.6rem;font-weight:700;">Obligación</th>
                            <th style="padding:0.6rem;font-weight:700;">Período</th>
                            <th style="padding:0.6rem;font-weight:700;">Venció</th>
                            <th style="padding:0.6rem 0.9rem;font-weight:700;">Atraso</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($vencidas as $o)
                        <tr style="border-top:1px solid #f1f5f9;">
                            <td style="padding:0.55rem 0.9rem;">
                                <a href="{{ route('brynex.razones.show', $o->ficha_id) }}?anio={{ $o->anio }}"
                                   style="color:#0f172a;font-weight:600;text-decoration:none;">{{ $o->razon_social }}</a>
                                <div style="color:#94a3b8;font-size:0.72rem;">NIT {{ number_format($o->nit, 0, ',', '.') }}</div>
                            </td>
                            <td style="padding:0.55rem;color:#334155;">{{ $catalogo[$o->obligacion_codigo]->nombre ?? $o->obligacion_codigo }}</td>
                            <td style="padding:0.55rem;color:#64748b;">{{ $o->periodo_etiqueta }} · {{ $o->anio }}</td>
                            <td style="padding:0.55rem;color:#b91c1c;font-weight:600;">{{ $o->fecha_vencimiento->format('d/m/Y') }}</td>
                            <td style="padding:0.55rem 0.9rem;color:#b91c1c;font-weight:700;">
                                {{ abs($o->diasParaVencer()) }} días
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p style="padding:1.5rem;text-align:center;color:#94a3b8;margin:0;">Nada vencido. 👌</p>
        @endif
    </div>

    {{-- ── Por vencer ──────────────────────────────────────────────── --}}
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:1.25rem;">
        <div style="background:#fffbeb;padding:0.8rem 1rem;border-bottom:1px solid #f1f5f9;">
            <h2 style="font-size:0.95rem;font-weight:800;color:#92400e;margin:0;">
                🟡 Vencen en los próximos {{ $dias }} días ({{ $porVencer->count() }})
            </h2>
        </div>
        @if($porVencer->count())
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.84rem;">
                    <thead>
                        <tr style="background:#f8fafc;color:#475569;font-size:0.7rem;text-transform:uppercase;text-align:left;">
                            <th style="padding:0.6rem 0.9rem;font-weight:700;">Razón social</th>
                            <th style="padding:0.6rem;font-weight:700;">Obligación</th>
                            <th style="padding:0.6rem;font-weight:700;">Período</th>
                            <th style="padding:0.6rem;font-weight:700;">Vence</th>
                            <th style="padding:0.6rem;font-weight:700;">Faltan</th>
                            <th style="padding:0.6rem 0.9rem;font-weight:700;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($porVencer as $o)
                        <tr style="border-top:1px solid #f1f5f9;">
                            <td style="padding:0.55rem 0.9rem;">
                                <a href="{{ route('brynex.razones.show', $o->ficha_id) }}?anio={{ $o->anio }}"
                                   style="color:#0f172a;font-weight:600;text-decoration:none;">{{ $o->razon_social }}</a>
                                <div style="color:#94a3b8;font-size:0.72rem;">NIT {{ number_format($o->nit, 0, ',', '.') }}</div>
                            </td>
                            <td style="padding:0.55rem;color:#334155;">{{ $catalogo[$o->obligacion_codigo]->nombre ?? $o->obligacion_codigo }}</td>
                            <td style="padding:0.55rem;color:#64748b;">{{ $o->periodo_etiqueta }} · {{ $o->anio }}</td>
                            <td style="padding:0.55rem;color:#334155;font-weight:600;">{{ $o->fecha_vencimiento->format('d/m/Y') }}</td>
                            <td style="padding:0.55rem;color:#b45309;font-weight:700;">{{ $o->diasParaVencer() }} días</td>
                            <td style="padding:0.55rem 0.9rem;color:#64748b;">{{ \App\Models\BrynexObligacion::ESTADOS[$o->estado] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p style="padding:1.5rem;text-align:center;color:#94a3b8;margin:0;">Nada vence en los próximos {{ $dias }} días.</p>
        @endif
    </div>

    {{-- ── Firmas electrónicas ─────────────────────────────────────── --}}
    @if($firmas->count())
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
            <div style="background:#f8fafc;padding:0.8rem 1rem;border-bottom:1px solid #f1f5f9;">
                <h2 style="font-size:0.95rem;font-weight:800;color:#334155;margin:0;">✍️ Firmas electrónicas por caducar ({{ $firmas->count() }})</h2>
                <p style="color:#64748b;font-size:0.76rem;margin:0.2rem 0 0 0;">Si caduca, no se puede declarar nada por más al día que esté el checklist.</p>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:0.84rem;">
                @foreach($firmas as $f)
                    @php $d = now()->startOfDay()->diffInDays($f->firma_electronica_vence, false); @endphp
                    <tr style="border-top:1px solid #f1f5f9;">
                        <td style="padding:0.55rem 0.9rem;">
                            <a href="{{ route('brynex.razones.show', $f->id) }}" style="color:#0f172a;font-weight:600;text-decoration:none;">{{ $f->razon_social }}</a>
                            <span style="color:#94a3b8;font-size:0.75rem;">· NIT {{ number_format($f->nit, 0, ',', '.') }}</span>
                        </td>
                        <td style="padding:0.55rem;color:#334155;">{{ $f->firma_electronica_vence->format('d/m/Y') }}</td>
                        <td style="padding:0.55rem 0.9rem;font-weight:700;color:{{ $d < 0 ? '#b91c1c' : '#b45309' }};">
                            {{ $d < 0 ? 'Vencida hace ' . abs($d) . ' días' : 'Faltan ' . $d . ' días' }}
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</div>

@endsection
