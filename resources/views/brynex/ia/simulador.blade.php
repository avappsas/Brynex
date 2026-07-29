@extends('layouts.app')
@section('titulo', 'BryNex')
@section('modulo', 'Simulador de conversación IA')

@push('styles')
<style>
.sim-wrap { max-width: 1200px; margin: 0 auto; padding: 1rem 0; }
.header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem; }
.btn-back-link { display: flex; align-items: center; gap: 0.5rem; color: #64748b; font-size: 0.85rem; text-decoration: none; font-weight: 500; }
.btn-back-link:hover { color: var(--azul-btn); }
.sim-title-gradient {
    font-size: 1.5rem; font-weight: 800;
    background: linear-gradient(135deg, var(--azul-oscuro) 0%, var(--azul-medio) 50%, var(--acento) 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.15rem;
}
.sim-grid { display: grid; grid-template-columns: 1fr 320px; gap: 1.25rem; align-items: start; }
@media (max-width: 900px) { .sim-grid { grid-template-columns: 1fr; } }
.card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; padding: 1.25rem; }
.chat-box { display: flex; flex-direction: column; height: 560px; }
.chat-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; margin-bottom: 0.85rem; flex-wrap: wrap; }
.chat-toolbar select { padding: 0.45rem 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.83rem; }
.chat-scroll { flex: 1; overflow-y: auto; padding: 0.5rem 0.25rem; display: flex; flex-direction: column; gap: 0.6rem; }
.msg { max-width: 78%; padding: 0.6rem 0.85rem; border-radius: 12px; font-size: 0.85rem; line-height: 1.4; white-space: pre-wrap; }
.msg-cliente { align-self: flex-end; background: #dcfce7; color: #14532d; border-bottom-right-radius: 3px; }
.msg-ia { align-self: flex-start; background: #f1f5f9; color: #0f172a; border-bottom-left-radius: 3px; }
.msg-tools { font-size: 0.68rem; color: #94a3b8; margin-top: 0.3rem; }
.msg-flag { background: none; border: none; color: #94a3b8; font-size: 0.72rem; cursor: pointer; margin-top: 0.35rem; padding: 0; }
.msg-flag:hover { color: #dc2626; }
.correccion-box { margin-top: 0.5rem; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 0.6rem; }
.correccion-box textarea { width: 100%; border: 1px solid #fdba74; border-radius: 6px; padding: 0.4rem 0.5rem; font-size: 0.8rem; resize: vertical; min-height: 55px; }
.chat-input-row { display: flex; gap: 0.5rem; margin-top: 0.85rem; }
.chat-input-row input { flex: 1; padding: 0.55rem 0.8rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.85rem; }
.btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.82rem; font-weight: 600; cursor: pointer; border: none; }
.btn-primary { background: var(--azul-btn); color: #fff; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { background: #f1f5f9; color: #475569; }
.btn-danger-ghost { background: rgba(239,68,68,0.08); color: #b91c1c; }
.nota-item { border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.65rem 0.75rem; margin-bottom: 0.6rem; font-size: 0.78rem; }
.nota-item.resuelta { opacity: 0.55; }
.nota-item .etq { font-weight: 700; color: #64748b; font-size: 0.68rem; text-transform: uppercase; }
.badge-pendiente { background: #fef3c7; color: #92400e; border-radius: 999px; padding: 0.1rem 0.5rem; font-size: 0.65rem; font-weight: 700; }
.badge-resuelta { background: #dcfce7; color: #15803d; border-radius: 999px; padding: 0.1rem 0.5rem; font-size: 0.65rem; font-weight: 700; }
.vacio { color: #94a3b8; font-size: 0.82rem; text-align: center; padding: 2rem 1rem; }
</style>
@endpush

@section('contenido')
<div class="sim-wrap" x-data="simulador()" x-init="init()">

    <div class="header-section">
        <div>
            <a href="{{ route('brynex.ia.index') }}" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Volver a Configuración IA
            </a>
            <h1 class="sim-title-gradient">🧪 Simulador de conversación</h1>
            <p style="font-size:.83rem;color:#64748b;margin:0;">Escríbele a la IA real como si fueras un cliente. Nada de esto toca WhatsApp de verdad, ADRES, ni prospectos reales.</p>
        </div>
    </div>

    <div class="sim-grid">
        <div class="card chat-box">
            <div class="chat-toolbar">
                <select x-model="aliadoId" @change="cambiarAliado()">
                    @foreach($aliados as $aliado)
                        <option value="{{ $aliado->id }}">{{ $aliado->nombre }}</option>
                    @endforeach
                </select>
                <button class="btn btn-danger-ghost" @click="reiniciar()">
                    <i class="fas fa-rotate-left"></i> Reiniciar conversación
                </button>
            </div>

            <div class="chat-scroll" x-ref="scroll">
                <template x-if="mensajes.length === 0">
                    <div class="vacio">Escribe abajo como si fueras un cliente para empezar.</div>
                </template>
                <template x-for="(m, idx) in mensajes" :key="idx">
                    <div>
                        <div class="msg" :class="m.rol === 'user' ? 'msg-cliente' : 'msg-ia'" x-text="m.contenido"></div>
                        <template x-if="m.rol === 'assistant'">
                            <div>
                                <div class="msg-tools" x-show="m.tools && m.tools.length" x-text="'tools: ' + (m.tools || []).join(', ')"></div>
                                <button class="msg-flag" @click="m.corrigiendo = !m.corrigiendo">🚩 Marcar corrección</button>
                                <div class="correccion-box" x-show="m.corrigiendo" x-cloak>
                                    <textarea x-model="m.notaTexto" placeholder="¿Qué no debió decir, o qué debió decir en su lugar?"></textarea>
                                    <div style="margin-top:0.4rem; display:flex; gap:0.4rem;">
                                        <button class="btn btn-primary" style="padding:0.35rem 0.7rem;font-size:0.75rem;" @click="guardarCorreccion(idx)">Guardar corrección</button>
                                        <button class="btn btn-secondary" style="padding:0.35rem 0.7rem;font-size:0.75rem;" @click="m.corrigiendo = false">Cancelar</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <div x-show="cargando" style="align-self:flex-start; color:#94a3b8; font-size:0.8rem;">Escribiendo…</div>
            </div>

            <div class="chat-input-row">
                <input type="text" x-model="entrada" @keydown.enter="enviar()" placeholder="Escribe como si fueras el cliente…" :disabled="cargando">
                <button class="btn btn-primary" @click="enviar()" :disabled="cargando || !entrada.trim()">
                    <i class="fas fa-paper-plane"></i> Enviar
                </button>
            </div>
        </div>

        <div class="card">
            <div style="font-weight:700;color:var(--azul-oscuro);margin-bottom:0.75rem;font-size:0.95rem;">📝 Correcciones guardadas</div>
            <div id="lista-notas">
                @forelse($notas as $nota)
                    <div class="nota-item {{ $nota->estado === 'resuelta' ? 'resuelta' : '' }}" id="nota-{{ $nota->id }}">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.3rem;">
                            <span class="etq">{{ $nota->aliado->nombre ?? '—' }}</span>
                            <span class="{{ $nota->estado === 'resuelta' ? 'badge-resuelta' : 'badge-pendiente' }}">{{ $nota->estado }}</span>
                        </div>
                        <div><strong>Cliente:</strong> {{ \Illuminate\Support\Str::limit($nota->mensaje_cliente, 80) }}</div>
                        <div><strong>IA dijo:</strong> {{ \Illuminate\Support\Str::limit($nota->respuesta_ia, 80) }}</div>
                        <div style="margin-top:0.3rem;color:#b45309;"><strong>Corrección:</strong> {{ $nota->correccion }}</div>
                        @if($nota->estado !== 'resuelta')
                            <button class="btn btn-secondary" style="margin-top:0.4rem;padding:0.25rem 0.6rem;font-size:0.7rem;" onclick="resolverNota({{ $nota->id }})">Marcar resuelta</button>
                        @endif
                    </div>
                @empty
                    <div class="vacio">Todavía no hay correcciones guardadas.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<script>
function resolverNota(id) {
    const meta = document.querySelector('meta[name="csrf-token"]');
    fetch(`{{ url('brynex/ia/simulador/nota') }}/${id}/resolver`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': meta.content, 'Accept': 'application/json' },
    }).then(() => {
        const el = document.getElementById('nota-' + id);
        if (el) location.reload();
    });
}

function simulador() {
    return {
        aliadoId: '{{ $aliados->first()->id ?? "" }}',
        mensajes: [],
        entrada: '',
        cargando: false,

        init() {
            if (this.aliadoId) this.cargarHistorial();
        },

        csrf() {
            return document.querySelector('meta[name="csrf-token"]').content;
        },

        cambiarAliado() {
            this.mensajes = [];
            this.cargarHistorial();
        },

        cargarHistorial() {
            fetch(`{{ url('brynex/ia/simulador') }}/${this.aliadoId}/historial`, {
                headers: { 'Accept': 'application/json' },
            })
                .then(r => r.json())
                .then(data => {
                    this.mensajes = (data.mensajes || []).map(m => ({
                        rol: m.rol, contenido: m.contenido, tools: m.tool_name ? m.tool_name.split(',') : [],
                        corrigiendo: false, notaTexto: '',
                    })).filter(m => m.rol === 'user' || m.rol === 'assistant');
                    this.scrollAbajo();
                });
        },

        scrollAbajo() {
            this.$nextTick(() => {
                if (this.$refs.scroll) this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight;
            });
        },

        enviar() {
            const texto = this.entrada.trim();
            if (!texto || this.cargando) return;
            this.mensajes.push({ rol: 'user', contenido: texto, corrigiendo: false, notaTexto: '' });
            this.entrada = '';
            this.cargando = true;
            this.scrollAbajo();

            fetch('{{ route("brynex.ia.simulador.mensaje") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ aliado_id: this.aliadoId, mensaje: texto }),
            })
                .then(async (r) => {
                    const data = await r.json();
                    if (!r.ok) throw new Error(data.error || 'Error');
                    this.mensajes.push({
                        rol: 'assistant', contenido: data.respuesta, tools: data.herramientas_usadas || [],
                        corrigiendo: false, notaTexto: '',
                    });
                })
                .catch((e) => {
                    this.mensajes.push({ rol: 'assistant', contenido: '⚠️ Error: ' + e.message, tools: [], corrigiendo: false, notaTexto: '' });
                })
                .finally(() => {
                    this.cargando = false;
                    this.scrollAbajo();
                });
        },

        guardarCorreccion(idx) {
            const msg = this.mensajes[idx];
            const previo = this.mensajes[idx - 1];
            if (!msg.notaTexto || !msg.notaTexto.trim()) return;

            const contexto = this.mensajes.slice(0, idx).map(m => (m.rol === 'user' ? 'Cliente: ' : 'IA: ') + m.contenido).join('\n');

            fetch('{{ route("brynex.ia.simulador.nota") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    aliado_id: this.aliadoId,
                    mensaje_cliente: previo ? previo.contenido : '(sin mensaje previo)',
                    respuesta_ia: msg.contenido,
                    correccion: msg.notaTexto,
                    contexto: contexto,
                }),
            })
                .then(async (r) => {
                    if (!r.ok) {
                        const data = await r.json().catch(() => ({}));
                        throw new Error(data.message || ('HTTP ' + r.status));
                    }
                    msg.corrigiendo = false;
                    msg.notaTexto = '';
                    location.reload();
                })
                .catch((e) => {
                    alert('No se pudo guardar la corrección: ' + e.message);
                });
        },

        reiniciar() {
            if (!confirm('¿Reiniciar esta conversación de prueba? Se borra el historial del simulador para este aliado.')) return;
            fetch('{{ route("brynex.ia.simulador.reiniciar") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ aliado_id: this.aliadoId }),
            })
                .then((r) => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    this.mensajes = [];
                })
                .catch((e) => {
                    alert('No se pudo reiniciar: ' + e.message);
                });
        },
    };
}
</script>
@endsection
