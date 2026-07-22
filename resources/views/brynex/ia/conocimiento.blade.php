@extends('layouts.app')
@section('titulo', 'BryNex')
@section('modulo', 'Entrenamiento del Asistente IA')

@push('styles')
<style>
.ia-wrap { max-width: 1000px; margin: 0 auto; }
.form-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.5rem 1.75rem; margin-bottom:1.5rem; }
.form-group { margin-bottom:1rem; }
.form-label { display:block; font-size:.8rem; font-weight:600; color:#374151; margin-bottom:.3rem; }
.form-control { width:100%; padding:.5rem .7rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.83rem; color:#0f172a; }
.form-control:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
textarea.form-control { min-height:90px; resize:vertical; }
.form-hint { font-size:.72rem; color:#94a3b8; margin-top:.25rem; }
.btn { padding:.45rem 1rem; border-radius:8px; font-size:.8rem; font-weight:600; cursor:pointer; border:none; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-success { background:#16a34a; color:#fff; }
.btn-danger { background:#fff; color:#dc2626; border:1px solid #fecaca; }
.btn-outline { background:transparent; border:1px solid #cbd5e1; color:#475569; }
.section-title { font-size:1.02rem; font-weight:700; color:#0f172a; margin-bottom:1rem; }
.item-card { border:1px solid #e2e8f0; border-radius:10px; padding:1rem 1.25rem; margin-bottom:.9rem; }
.item-card.pendiente { border-color:#fde68a; background:#fffbeb; }
.item-card.pregunta { border-color:#bfdbfe; background:#eff6ff; }
.meta { font-size:.72rem; color:#64748b; margin-bottom:.5rem; }
.row-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:.75rem; }
.empty { font-size:.83rem; color:#94a3b8; padding:1rem 0; }
table.aprobados { width:100%; border-collapse:collapse; font-size:.8rem; }
table.aprobados th, table.aprobados td { text-align:left; padding:.5rem .6rem; border-bottom:1px solid #f1f5f9; vertical-align:top; }
table.aprobados th { color:#64748b; font-weight:600; font-size:.72rem; text-transform:uppercase; }
</style>
@endpush

@section('contenido')
<div class="ia-wrap">

    <div style="margin-bottom:1rem; display:flex; gap:1rem;">
        <a href="{{ route('brynex.ia.index') }}" style="color:#2563eb;text-decoration:none;font-size:.83rem">← Configuración IA</a>
        <a href="{{ route('brynex.hub') }}" style="color:#2563eb;text-decoration:none;font-size:.83rem">Panel BryNex</a>
    </div>

    @if(session('success'))
        <div class="flash success" style="margin-bottom:1rem;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="flash" style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.83rem;">
            @foreach($errors->all() as $e) <div>❌ {{ $e }}</div> @endforeach
        </div>
    @endif

    <h1 style="font-size:1.3rem;font-weight:700;color:#0f172a;margin-bottom:.25rem;">🧠 Entrenamiento del Asistente IA</h1>
    <p style="font-size:.85rem;color:#64748b;margin-bottom:1.5rem;">Aprueba lo que la IA encontró en internet, responde sus preguntas pendientes, y edita el conocimiento manualmente.</p>

    {{-- ── Preguntas pendientes del entrenador ─────────────────────────── --}}
    <div class="form-card">
        <div class="section-title">❓ Preguntas pendientes ({{ $preguntas->count() }})</div>

        @forelse($preguntas as $p)
            <div class="item-card pregunta">
                <div class="meta">{{ $p->aliado->nombre ?? 'General' }} · {{ $p->created_at->diffForHumans() }}</div>
                <div style="font-weight:600; color:#0f172a; margin-bottom:.75rem;">{{ $p->pregunta }}</div>
                <form method="POST" action="{{ route('brynex.ia.conocimiento.preguntas.responder', $p->id) }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Tu respuesta (quedará como conocimiento aprobado)</label>
                        <textarea name="respuesta" class="form-control" required></textarea>
                    </div>
                    <div class="row-grid" style="margin-bottom:.75rem;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Aplica a</label>
                            <select name="aliado_id" class="form-control">
                                <option value="">General (todos los aliados)</option>
                                @foreach($aliados as $a)
                                    <option value="{{ $a->id }}" @selected($p->aliado_id == $a->id)>{{ $a->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Categoría (opcional)</label>
                            <input type="text" name="categoria" class="form-control" placeholder="seguridad_social, modulos...">
                        </div>
                    </div>
                    <div style="display:flex; gap:.5rem;">
                        <button type="submit" class="btn btn-success">Guardar respuesta</button>
                        <button type="submit" formaction="{{ route('brynex.ia.conocimiento.preguntas.descartar', $p->id) }}" class="btn btn-danger">Descartar</button>
                    </div>
                </form>
            </div>
        @empty
            <div class="empty">No hay preguntas pendientes.</div>
        @endforelse
    </div>

    {{-- ── Conocimiento pendiente de aprobar (internet) ─────────────────── --}}
    <div class="form-card">
        <div class="section-title">🌐 Pendiente de aprobar — encontrado en internet ({{ $pendientes->count() }})</div>

        @forelse($pendientes as $c)
            <div class="item-card pendiente">
                <div class="meta">Fuente: internet · {{ $c->created_at->diffForHumans() }}</div>
                <form method="POST" action="{{ route('brynex.ia.conocimiento.aprobar', $c->id) }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" value="{{ $c->titulo }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contenido</label>
                        <textarea name="contenido" class="form-control" required>{{ $c->contenido }}</textarea>
                    </div>
                    <div class="row-grid" style="margin-bottom:.75rem;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Aplica a</label>
                            <select name="aliado_id" class="form-control">
                                <option value="">General (todos los aliados)</option>
                                @foreach($aliados as $a)
                                    <option value="{{ $a->id }}" @selected($c->aliado_id == $a->id)>{{ $a->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Categoría</label>
                            <input type="text" name="categoria" class="form-control" value="{{ $c->categoria }}">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Vigente desde</label>
                            <input type="date" name="vigente_desde" class="form-control" value="{{ $c->vigente_desde?->toDateString() ?? now()->toDateString() }}">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Reemplaza a (opcional)</label>
                            <select name="reemplaza_a" class="form-control">
                                <option value="">— Ninguno —</option>
                                @foreach($aprobados as $ap)
                                    <option value="{{ $ap->id }}">{{ $ap->titulo }}</option>
                                @endforeach
                            </select>
                            <div class="form-hint">Cierra la vigencia del anterior en la fecha de este.</div>
                        </div>
                    </div>
                    <div style="display:flex; gap:.5rem;">
                        <button type="submit" class="btn btn-success">✓ Aprobar</button>
                        <button type="submit" formaction="{{ route('brynex.ia.conocimiento.rechazar', $c->id) }}" class="btn btn-danger">✕ Rechazar</button>
                    </div>
                </form>
            </div>
        @empty
            <div class="empty">No hay conocimiento pendiente de internet.</div>
        @endforelse
    </div>

    {{-- ── Crear conocimiento manualmente ───────────────────────────────── --}}
    <div class="form-card">
        <div class="section-title">✍️ Añadir conocimiento manualmente</div>
        <form method="POST" action="{{ route('brynex.ia.conocimiento.guardar') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Contenido</label>
                <textarea name="contenido" class="form-control" required></textarea>
            </div>
            <div class="row-grid" style="margin-bottom:1rem;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Aplica a</label>
                    <select name="aliado_id" class="form-control">
                        <option value="">General (todos los aliados)</option>
                        @foreach($aliados as $a)
                            <option value="{{ $a->id }}">{{ $a->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Categoría</label>
                    <input type="text" name="categoria" class="form-control" placeholder="seguridad_social, modulos...">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Vigente desde</label>
                    <input type="date" name="vigente_desde" class="form-control" value="{{ now()->toDateString() }}">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Reemplaza a (opcional)</label>
                    <select name="reemplaza_a" class="form-control">
                        <option value="">— Ninguno —</option>
                        @foreach($aprobados as $ap)
                            <option value="{{ $ap->id }}">{{ $ap->titulo }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar conocimiento</button>
        </form>
    </div>

    {{-- ── Conocimiento aprobado (histórico reciente) ───────────────────── --}}
    <div class="form-card">
        <div class="section-title">✅ Conocimiento aprobado (últimos 100)</div>
        @if($aprobados->isEmpty())
            <div class="empty">Todavía no hay conocimiento aprobado.</div>
        @else
            <div style="overflow-x:auto;">
                <table class="aprobados">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Aplica a</th>
                            <th>Categoría</th>
                            <th>Fuente</th>
                            <th>Vigente desde</th>
                            <th>Vigente hasta</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($aprobados as $ap)
                            <tr>
                                <td style="max-width:280px;">{{ $ap->titulo }}</td>
                                <td>{{ $ap->aliado->nombre ?? 'General' }}</td>
                                <td>{{ $ap->categoria ?? '—' }}</td>
                                <td>{{ $ap->fuente }}</td>
                                <td>{{ $ap->vigente_desde?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $ap->vigente_hasta?->format('d/m/Y') ?? 'Vigente' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('brynex.ia.conocimiento.eliminar', $ap->id) }}" onsubmit="return confirm('¿Retirar este conocimiento?')">
                                        @csrf
                                        <button type="submit" class="btn btn-danger" style="padding:.25rem .6rem;font-size:.72rem;">Retirar</button>
                                    </form>
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
