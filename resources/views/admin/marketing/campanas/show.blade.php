@extends('layouts.app')
@section('titulo', 'Marketing')
@section('modulo', $campana->nombre)

@push('styles')
<style>
.page-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.5rem; margin-bottom:1.25rem; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; flex-wrap:wrap; gap:.75rem; }
.page-title { font-size:1.05rem; font-weight:700; color:#0f172a; }
.btn { padding:.45rem 1rem; border-radius:8px; font-size:.82rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-outline { background:transparent; border:1px solid #cbd5e1; color:#475569; }
.form-group { margin-bottom:1rem; }
.form-label { display:block; font-size:.8rem; font-weight:600; color:#374151; margin-bottom:.3rem; }
.form-control { width:100%; padding:.5rem .7rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.83rem; }
.row-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:.75rem; }
.preview-box { background:#f8fafc; border-radius:10px; padding:1rem 1.2rem; margin:1rem 0; font-size:.82rem; }
.preview-row { display:flex; justify-content:space-between; padding:.25rem 0; }
.preview-row.elegibles { font-weight:700; color:#16a34a; border-top:1px solid #e2e8f0; margin-top:.4rem; padding-top:.5rem; }
.wa-table { width:100%; border-collapse:collapse; }
.wa-table th, .wa-table td { padding:.55rem .8rem; border-bottom:1px solid #f1f5f9; font-size:.8rem; }
.wa-table th { background:#f8fafc; font-weight:600; color:#475569; text-align:left; }
.badge { display:inline-flex; padding:.18rem .55rem; border-radius:999px; font-size:.71rem; font-weight:600; }
.badge-success { background:#d1fae5; color:#065f46; }
.badge-warning { background:#fef3c7; color:#92400e; }
.metricas-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:.75rem; }
.metrica-tile { background:#f8fafc; border-radius:10px; padding:.9rem 1rem; text-align:center; }
.metrica-valor { font-size:1.4rem; font-weight:700; color:#0f172a; }
.metrica-label { font-size:.74rem; color:#64748b; margin-top:.2rem; }
.metrica-label small { color:#94a3b8; }
</style>
@endpush

@section('contenido')
<div class="contenido" x-data="lanzarTanda()">
    <div style="margin-bottom:1rem;">
        <a href="{{ route('admin.marketing.campanas.index') }}" style="color:#2563eb;text-decoration:none;font-size:.83rem">← Campañas</a>
    </div>

    @if(session('ok'))<div class="flash success">✅ {{ session('ok') }}</div>@endif

    <div class="page-card">
        <div class="page-header">
            <div>
                <div class="page-title">📣 {{ $campana->nombre }}</div>
                <small style="color:#64748b">{{ $campana->plantilla->nombre_display ?? '—' }} · {{ $campana->etiquetaEstado() }}</small>
            </div>
            <form method="POST" action="{{ route('admin.marketing.campanas.update', $campana->id) }}">
                @csrf @method('PATCH')
                <select name="estado" class="form-control" onchange="this.form.submit()">
                    <option value="activa" @selected($campana->estado === 'activa')>🟢 Activa</option>
                    <option value="pausada" @selected($campana->estado === 'pausada')>⏸️ Pausada</option>
                    <option value="finalizada" @selected($campana->estado === 'finalizada')>⚪ Finalizada</option>
                </select>
            </form>
        </div>
        <p style="font-size:.83rem;color:#374151;margin-bottom:.5rem"><strong>Qué se promociona:</strong> {{ $campana->descripcion_ia }}</p>
        @if($campana->objetivo)
            <p style="font-size:.83rem;color:#374151"><strong>Objetivo:</strong> {{ $campana->objetivo }}</p>
        @endif
    </div>

    <div class="page-card">
        <div class="page-title" style="margin-bottom:1rem">📊 Métricas de la campaña</div>
        <div class="metricas-grid">
            <div class="metrica-tile">
                <div class="metrica-valor">{{ $metricas['enviados'] }}</div>
                <div class="metrica-label">📤 Enviados</div>
            </div>
            <div class="metrica-tile">
                <div class="metrica-valor">{{ $metricas['entregados'] }}</div>
                <div class="metrica-label">✅ Entregados <small>({{ $metricas['tasa_entrega'] }}%)</small></div>
            </div>
            <div class="metrica-tile">
                <div class="metrica-valor">{{ $metricas['leidos'] }}</div>
                <div class="metrica-label">👁️ Vistos/Leídos <small>({{ $metricas['tasa_lectura'] }}%)</small></div>
            </div>
            <div class="metrica-tile">
                <div class="metrica-valor">{{ $metricas['respuestas'] }}</div>
                <div class="metrica-label">💬 Respuestas <small>({{ $metricas['tasa_respuesta'] }}%)</small></div>
            </div>
            <div class="metrica-tile">
                <div class="metrica-valor">{{ $metricas['interacciones'] }}</div>
                <div class="metrica-label">🔘 Interacciones (botón)</div>
            </div>
            <div class="metrica-tile">
                <div class="metrica-valor">{{ $metricas['fallidos'] }}</div>
                <div class="metrica-label">⚠️ Fallidos</div>
            </div>
            <div class="metrica-tile">
                <div class="metrica-valor">{{ $metricas['bloqueados'] }}</div>
                <div class="metrica-label">🚫 Bloqueados</div>
            </div>
        </div>
    </div>

    <div class="page-card">
        <div class="page-title" style="margin-bottom:1rem">🚀 Lanzar tanda</div>

        <div class="row-grid">
            <div class="form-group">
                <label class="form-label">Lista</label>
                <select class="form-control" x-model="filtros.lista_id" @change="previsualizar()">
                    <option value="">Todo el pool</option>
                    @foreach($listas as $l)
                        <option value="{{ $l->id }}">{{ $l->nombre }} ({{ $l->contactos_count }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Departamento</label>
                <select class="form-control" x-model="filtros.departamento" @change="previsualizar()">
                    <option value="">Todos</option>
                    @foreach($departamentos as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Ciudad</label>
                <select class="form-control" x-model="filtros.ciudad" @change="previsualizar()">
                    <option value="">Todas</option>
                    @foreach($ciudades as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Buscar en observación</label>
                <input type="text" class="form-control" x-model="filtros.observacion" @change="previsualizar()" placeholder="Ej: referido">
            </div>
        </div>

        <div class="preview-box" x-show="preview">
            <div class="preview-row"><span>Contactos en el filtro</span><span x-text="preview?.total_en_filtro"></span></div>
            <div class="preview-row"><span>🚫 Bloqueados</span><span x-text="'-' + preview?.bloqueados"></span></div>
            <div class="preview-row"><span>📨 Ya recibieron esta campaña</span><span x-text="'-' + preview?.ya_recibieron_campana"></span></div>
            <div class="preview-row"><span>⏱️ Superan el límite de frecuencia</span><span x-text="'-' + preview?.superan_limite"></span></div>
            <div class="preview-row"><span>👤 Clientes vigentes</span><span x-text="'-' + preview?.clientes_vigentes"></span></div>
            <div class="preview-row elegibles"><span>✅ Elegibles para enviar</span><span x-text="preview?.elegibles"></span></div>
        </div>
        <div x-show="cargandoPreview" style="font-size:.8rem;color:#94a3b8">Calculando elegibles...</div>

        <div class="row-grid" style="max-width:320px; margin-top:1rem;">
            <div class="form-group">
                <label class="form-label">Cantidad a enviar ahora</label>
                <input type="number" class="form-control" x-model.number="cantidad" min="1" :max="preview?.elegibles || 999999">
            </div>
        </div>

        <button type="button" class="btn btn-primary" @click="lanzar()" :disabled="lanzando || !preview?.elegibles">
            <span x-show="!lanzando">🚀 Lanzar tanda</span>
            <span x-show="lanzando">Enviando...</span>
        </button>
        <span x-show="mensaje" x-text="mensaje" style="margin-left:.75rem; font-size:.82rem; color:#16a34a"></span>
    </div>

    <div class="page-card">
        <div class="page-title" style="margin-bottom:1rem">📊 Tandas enviadas</div>
        @if($tandas->isEmpty())
            <p style="color:#94a3b8;font-size:.83rem">Todavía no se ha lanzado ninguna tanda.</p>
        @else
            <table class="wa-table">
                <thead><tr><th>Fecha</th><th>Destinatarios</th><th>Enviados</th><th>Fallidos</th><th>Estado</th></tr></thead>
                <tbody>
                    @foreach($tandas as $t)
                        <tr>
                            <td>{{ $t->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $t->total_destinatarios }}</td>
                            <td>{{ $t->total_enviados }}</td>
                            <td>{{ $t->total_fallidos }}</td>
                            <td><span class="badge {{ $t->estaCompletado() ? 'badge-success' : 'badge-warning' }}">{{ $t->etiquetaEstado() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<script>
function lanzarTanda() {
    return {
        filtros: { lista_id: '', departamento: '', ciudad: '', observacion: '' },
        preview: null,
        cargandoPreview: false,
        cantidad: 1000,
        lanzando: false,
        mensaje: '',
        async previsualizar() {
            this.cargandoPreview = true;
            const params = new URLSearchParams(this.filtros);
            const resp = await fetch(`{{ route('admin.marketing.campanas.previsualizar', $campana->id) }}?${params}`);
            this.preview = await resp.json();
            this.cargandoPreview = false;
        },
        async lanzar() {
            if (!confirm(`¿Lanzar tanda a ${this.cantidad} contactos?`)) return;
            this.lanzando = true;
            this.mensaje = '';
            try {
                const resp = await fetch(`{{ route('admin.marketing.campanas.lanzar_tanda', $campana->id) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ...this.filtros, cantidad: this.cantidad }),
                });
                const data = await resp.json();
                if (data.ok) {
                    this.mensaje = data.mensaje;
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    alert(data.error || 'Error al lanzar la tanda');
                }
            } catch (e) {
                alert('Error de conexión');
            }
            this.lanzando = false;
        },
        init() { this.previsualizar(); },
    };
}
</script>
@endsection
