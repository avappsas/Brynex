@extends('layouts.app')
@section('titulo', 'WhatsApp')
@section('modulo', 'Chat — ' . $conversacion->nombreMostrar())

@push('styles')
<style>
.chat-layout { display:flex; height:calc(100vh - 120px); background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.1); overflow:hidden; }

/* Sidebar */
.chat-sidebar { width:280px; min-width:240px; border-right:1px solid #f1f5f9; display:flex; flex-direction:column; }
.sidebar-header { padding:.75rem .9rem; border-bottom:1px solid #f1f5f9; }
.sidebar-title { font-size:.88rem; font-weight:700; color:#0f172a; margin-bottom:.5rem; }
.conv-list { flex:1; overflow-y:auto; }
.conv-list::-webkit-scrollbar { width:4px; }
.conv-list::-webkit-scrollbar-thumb { background:#e2e8f0; border-radius:999px; }
.conv-item { display:flex; align-items:center; gap:.65rem; padding:.65rem .85rem; cursor:pointer; text-decoration:none; border-bottom:1px solid #f8fafc; transition:background .1s; }
.conv-item:hover { background:#f8fafc; }
.conv-item.activa { background:#eff6ff; border-right:3px solid #2563eb; }
.conv-avatar { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,#2563eb,#7c3aed); display:flex; align-items:center; justify-content:center; font-size:.9rem; color:#fff; font-weight:700; flex-shrink:0; }
.conv-info { flex:1; min-width:0; }
.conv-name { font-size:.8rem; font-weight:600; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.conv-preview { font-size:.7rem; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.conv-unread { background:#ef4444; color:#fff; font-size:.62rem; font-weight:700; padding:.1rem .38rem; border-radius:999px; min-width:17px; text-align:center; }

/* Chat principal */
.chat-main { flex:1; display:flex; flex-direction:column; min-width:0; }
.chat-header { padding:.75rem 1rem; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:.75rem; background:#fff; flex-shrink:0; }
.chat-contact-info { flex:1; }
.chat-contact-name { font-size:.9rem; font-weight:700; color:#0f172a; }
.chat-contact-sub { font-size:.72rem; color:#94a3b8; }
.header-actions { display:flex; gap:.4rem; flex-shrink:0; }
.btn-sm { padding:.3rem .65rem; border-radius:7px; font-size:.75rem; font-weight:600; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:.3rem; text-decoration:none; transition:opacity .15s; }
.btn-sm:hover { opacity:.87; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-outline { background:transparent; border:1px solid #cbd5e1; color:#475569; }
.btn-danger { background:#ef4444; color:#fff; }
.btn-success { background:#10b981; color:#fff; }

/* Ventana activa / inactiva */
.ventana-bar { padding:.4rem 1rem; font-size:.75rem; font-weight:600; text-align:center; flex-shrink:0; }
.ventana-activa { background:#d1fae5; color:#065f46; }
.ventana-inactiva { background:#fef3c7; color:#92400e; }

/* Área de mensajes */
.messages-area { flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:.6rem; background:#f8fafc; }
.messages-area::-webkit-scrollbar { width:6px; }
.messages-area::-webkit-scrollbar-thumb { background:#e2e8f0; border-radius:999px; }

.msg-bubble { max-width:70%; padding:.55rem .85rem; border-radius:12px; font-size:.83rem; line-height:1.45; word-break:break-word; }
.msg-entrante { background:#fff; color:#1e293b; border-radius:0 12px 12px 12px; box-shadow:0 1px 4px rgba(0,0,0,.07); align-self:flex-start; }
.msg-saliente { background:#2563eb; color:#fff; border-radius:12px 0 12px 12px; align-self:flex-end; }
.msg-meta { font-size:.65rem; opacity:.6; margin-top:.25rem; text-align:right; }
.msg-entrante .msg-meta { text-align:left; }
.msg-wrap { display:flex; flex-direction:column; }
.msg-wrap.saliente { align-items:flex-end; }
.msg-sender { font-size:.68rem; color:#94a3b8; margin-bottom:.15rem; }

/* Media */
.msg-image img { max-width:220px; border-radius:8px; cursor:pointer; display:block; }
.msg-audio audio { max-width:250px; }
.msg-document { display:flex; align-items:center; gap:.5rem; }
.msg-document .doc-icon { font-size:1.4rem; }

/* Entrada de mensajes */
.chat-input-area { border-top:1px solid #f1f5f9; padding:.75rem 1rem; background:#fff; flex-shrink:0; }
.input-row { display:flex; gap:.5rem; align-items:flex-end; }
.input-text { flex:1; padding:.55rem .85rem; border:1px solid #e2e8f0; border-radius:12px; font-size:.85rem; resize:none; outline:none; max-height:120px; transition:border-color .15s; }
.input-text:focus { border-color:#2563eb; }
.btn-enviar { background:#2563eb; color:#fff; border:none; border-radius:10px; padding:.55rem .85rem; cursor:pointer; font-size:1rem; transition:opacity .15s; }
.btn-enviar:hover { opacity:.87; }
.btn-adjuntar { background:transparent; border:none; font-size:1.1rem; cursor:pointer; padding:.45rem; border-radius:8px; color:#64748b; transition:background .12s; }
.btn-adjuntar:hover { background:#f1f5f9; }

/* Template sender */
.template-selector { border:1px solid #e2e8f0; border-radius:10px; padding:.65rem; margin-bottom:.5rem; background:#f8fafc; }
.template-selector select { width:100%; border:none; background:transparent; font-size:.83rem; outline:none; color:#0f172a; }
.template-params { display:grid; gap:.4rem; margin-top:.5rem; }
.template-params input { padding:.4rem .65rem; border:1px solid #e2e8f0; border-radius:7px; font-size:.8rem; outline:none; }

/* Modal */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:9999; display:flex; align-items:center; justify-content:center; }
.modal-box { background:#fff; border-radius:14px; padding:1.5rem; width:420px; max-width:95vw; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.modal-title { font-size:1rem; font-weight:700; color:#0f172a; margin-bottom:1rem; }
.form-group { margin-bottom:.85rem; }
.form-label { font-size:.78rem; font-weight:600; color:#374151; display:block; margin-bottom:.3rem; }
.form-control { width:100%; padding:.45rem .7rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.83rem; outline:none; }
.form-control:focus { border-color:#2563eb; }
</style>
@endpush

@section('contenido')
<div style="padding:0 1.5rem 1.5rem">
<div class="chat-layout" x-data="chatApp()" x-init="init()">

    {{-- Sidebar con lista de conversaciones (igual al index) --}}
    <aside class="chat-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">💬 WhatsApp</div>
        </div>
        <div class="conv-list" id="convList">
            {{-- Se carga vía fetch o se replica el mismo listado --}}
            <a href="{{ route('admin.whatsapp.chat.index') }}"
               style="display:flex;align-items:center;gap:.5rem;padding:.65rem .85rem;color:#2563eb;text-decoration:none;font-size:.78rem;border-bottom:1px solid #f1f5f9">
                ← Ver todas las conversaciones
            </a>
            <a href="{{ route('admin.whatsapp.chat.show', $conversacion->id) }}" class="conv-item activa">
                <div class="conv-avatar">{{ mb_strtoupper(mb_substr($conversacion->nombreMostrar(), 0, 1)) }}</div>
                <div class="conv-info">
                    <div class="conv-name">{{ $conversacion->nombreMostrar() }}</div>
                    <div class="conv-preview">{{ $conversacion->wa_contact_id }}</div>
                </div>
                @if($conversacion->total_mensajes_no_leidos > 0)
                    <span class="conv-unread">{{ $conversacion->total_mensajes_no_leidos }}</span>
                @endif
            </a>
        </div>
    </aside>

    {{-- Chat principal --}}
    <main class="chat-main">

        {{-- Header del chat --}}
        <div class="chat-header">
            <div class="conv-avatar" style="width:42px;height:42px">
                {{ mb_strtoupper(mb_substr($conversacion->nombreMostrar(), 0, 1)) }}
            </div>
            <div class="chat-contact-info">
                <div class="chat-contact-name">{{ $conversacion->nombreMostrar() }}</div>
                <div class="chat-contact-sub">
                    {{ $conversacion->wa_contact_id }}
                    @if($conversacion->estado === 'asignada' && $conversacion->asignado)
                        · Asignada a <strong>{{ $conversacion->asignado->nombre }}</strong>
                    @else
                        · <span style="color:#94a3b8">Inbox general</span>
                    @endif
                </div>
            </div>
            <div class="header-actions">
                <button class="btn-sm btn-outline" @click="modalAsignar = true">👤 Asignar</button>
                <button class="btn-sm btn-danger" @click="cerrarConversacion()">✕ Cerrar</button>
            </div>
        </div>

        {{-- Barra de ventana activa/inactiva --}}
        @if($conversacion->ventanaActiva())
            <div class="ventana-bar ventana-activa">
                ✅ Ventana activa — {{ $conversacion->minutosVentanaRestante() }} minutos restantes para enviar mensajes libres
            </div>
        @else
            <div class="ventana-bar ventana-inactiva">
                ⚠️ Ventana expirada — Solo puedes enviar plantillas aprobadas para iniciar la conversación
            </div>
        @endif

        {{-- Área de mensajes --}}
        <div class="messages-area" id="messagesArea">
            @foreach($mensajes as $msg)
                @php $esEntrante = $msg->esEntrante(); @endphp
                <div class="msg-wrap {{ $esEntrante ? 'entrante' : 'saliente' }}">
                    @if(!$esEntrante && $msg->usuario)
                        <div class="msg-sender">{{ $msg->usuario->nombre }}</div>
                    @endif

                    <div class="msg-bubble msg-{{ $esEntrante ? 'entrante' : 'saliente' }}">

                        @if($msg->tipo === 'text' || $msg->tipo === 'template')
                            {!! nl2br(e($msg->contenido)) !!}
                            @if($msg->tipo === 'template' && $msg->plantilla)
                                <div style="font-size:.68rem;opacity:.6;margin-top:.2rem">📋 {{ $msg->plantilla->nombre_display }}</div>
                            @endif

                        @elseif($msg->tipo === 'image')
                            <div class="msg-image">
                                @if($msg->tieneMedia())
                                    <img src="{{ $msg->urlMedia() }}" alt="Imagen" loading="lazy"
                                         onclick="window.open(this.src,'_blank')">
                                @else
                                    <div style="padding:.5rem;color:rgba(255,255,255,.6)">📷 Descargando imagen...</div>
                                @endif
                                @if($msg->contenido) <div style="margin-top:.3rem">{{ $msg->contenido }}</div> @endif
                            </div>

                        @elseif($msg->tipo === 'audio')
                            <div class="msg-audio">
                                @if($msg->tieneMedia())
                                    <audio controls preload="none" style="max-width:240px">
                                        <source src="{{ $msg->urlMedia() }}" type="{{ $msg->media_mime_type ?? 'audio/ogg' }}">
                                    </audio>
                                @else
                                    <span style="opacity:.7">🎵 Descargando audio...</span>
                                @endif
                            </div>

                        @elseif($msg->tipo === 'document')
                            <div class="msg-document">
                                <span class="doc-icon">📄</span>
                                <div>
                                    <div style="font-size:.8rem;font-weight:600">{{ $msg->media_nombre ?? 'Documento' }}</div>
                                    @if($msg->tieneMedia())
                                        <a href="{{ $msg->urlMedia() }}" target="_blank"
                                           style="font-size:.72rem;color:{{ $esEntrante ? '#2563eb' : '#bfdbfe' }};text-decoration:underline">
                                            Descargar
                                        </a>
                                    @else
                                        <span style="font-size:.72rem;opacity:.6">Descargando...</span>
                                    @endif
                                </div>
                            </div>

                        @elseif($msg->tipo === 'video')
                            @if($msg->tieneMedia())
                                <video controls style="max-width:220px;border-radius:6px">
                                    <source src="{{ $msg->urlMedia() }}" type="{{ $msg->media_mime_type }}">
                                </video>
                            @else
                                <span>🎥 Descargando video...</span>
                            @endif
                        @endif

                        <div class="msg-meta">
                            {{ $msg->created_at->format('H:i') }}
                            @if(!$esEntrante) {{ $msg->iconoEstado() }} @endif
                        </div>
                    </div>
                </div>
            @endforeach
            {{-- Mensajes nuevos via Reverb se agregan aquí --}}
            <template x-for="msg in mensajesNuevos" :key="msg.mensaje_id">
                <div class="msg-wrap" :class="msg.direccion === 'entrante' ? 'entrante' : 'saliente'">
                    <div class="msg-bubble" :class="msg.direccion === 'entrante' ? 'msg-entrante' : 'msg-saliente'"
                         x-text="msg.contenido || ('📎 ' + msg.tipo)">
                    </div>
                </div>
            </template>
        </div>

        {{-- Área de entrada --}}
        <div class="chat-input-area">
            {{-- Ventana activa: texto libre + adjuntos --}}
            @if($conversacion->ventanaActiva())
                <div class="input-row">
                    <label class="btn-adjuntar" title="Adjuntar imagen/documento">
                        📎
                        <input type="file" style="display:none" accept="image/*,application/pdf,audio/*"
                               @change="adjuntarArchivo($event)">
                    </label>
                    <textarea class="input-text" rows="1" placeholder="Escribe un mensaje..."
                              x-model="textoMensaje" @keydown.enter.prevent="enviarTexto()"
                              @input="autoResize($event)"></textarea>
                    <button class="btn-enviar" @click="enviarTexto()" :disabled="enviando">
                        <span x-show="!enviando">➤</span>
                        <span x-show="enviando">⏳</span>
                    </button>
                </div>
            @else
                {{-- Ventana inactiva: solo plantillas --}}
                <div class="template-selector">
                    <div style="font-size:.75rem;font-weight:600;color:#92400e;margin-bottom:.4rem">
                        📋 Selecciona una plantilla para iniciar
                    </div>
                    <select x-model="plantillaSeleccionada" @change="cargarParamsPlantilla()" style="width:100%;border:none;background:transparent;font-size:.83rem;outline:none">
                        <option value="">— Elige una plantilla aprobada —</option>
                        @foreach($plantillas as $plt)
                            <option value="{{ $plt->id }}"
                                    data-vars="{{ $plt->cantidadVariables() }}"
                                    data-preview="{{ e($plt->cuerpo) }}">
                                {{ $plt->nombre_display }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="template-params" x-show="paramsPlantilla.length > 0">
                    <template x-for="(p, idx) in paramsPlantilla" :key="idx">
                        <input type="text" class="template-params input" x-model="paramsPlantilla[idx]"
                               :placeholder="'Variable {{' + (idx+1) + '}}: ' + (getMapaVar(idx) || '')">
                    </template>
                </div>
                <div class="input-row" style="margin-top:.5rem">
                    <button class="btn-sm btn-primary" style="width:100%;justify-content:center"
                            @click="enviarTemplate()" :disabled="!plantillaSeleccionada || enviando">
                        <span x-show="!enviando">📤 Enviar plantilla</span>
                        <span x-show="enviando">⏳ Enviando...</span>
                    </button>
                </div>
            @endif

            {{-- Mensajes de error/éxito --}}
            <div x-show="mensajeError" style="color:#ef4444;font-size:.75rem;margin-top:.4rem" x-text="mensajeError"></div>
        </div>
    </main>

{{-- Modal de asignación --}}
<div class="modal-overlay" x-show="modalAsignar" x-cloak @click.self="modalAsignar = false">
    <div class="modal-box">
        <div class="modal-title">👤 Asignar conversación</div>
        <div class="form-group">
            <label class="form-label">Usuario responsable</label>
            <select x-model="usuarioAsignar" class="form-control">
                <option value="">— Inbox general (sin asignar) —</option>
                @foreach($usuarios as $u)
                    <option value="{{ $u->id }}" {{ $conversacion->asignado_a == $u->id ? 'selected' : '' }}>
                        {{ $u->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1rem">
            <button class="btn-sm btn-outline" @click="modalAsignar = false">Cancelar</button>
            <button class="btn-sm btn-primary" @click="asignarConversacion()">✅ Asignar</button>
        </div>
    </div>
</div>
</div>
</div>
@endsection

@push('scripts')
<script>
function chatApp() {
    return {
        textoMensaje: '',
        plantillaSeleccionada: '',
        paramsPlantilla: [],
        mapaVars: {},
        enviando: false,
        mensajeError: '',
        modalAsignar: false,
        usuarioAsignar: '{{ $conversacion->asignado_a ?? '' }}',
        mensajesNuevos: [],
        alidoId: '{{ session('aliado_id_activo') }}',
        convId: {{ $conversacion->id }},

        init() {
            this.scrollBottom();
            this.conectarReverb();
        },

        scrollBottom() {
            this.$nextTick(() => {
                const area = document.getElementById('messagesArea');
                if (area) area.scrollTop = area.scrollHeight;
            });
        },

        async enviarTexto() {
            if (!this.textoMensaje.trim() || this.enviando) return;
            this.enviando = true;
            this.mensajeError = '';

            try {
                const resp = await fetch(`/admin/whatsapp/chat/{{ $conversacion->id }}/mensaje`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ tipo: 'text', contenido: this.textoMensaje })
                });

                const data = await resp.json();
                if (data.ok) {
                    this.mensajesNuevos.push({
                        mensaje_id: data.mensaje?.id || Date.now(),
                        direccion: 'saliente',
                        tipo: 'text',
                        contenido: this.textoMensaje,
                    });
                    this.textoMensaje = '';
                    this.scrollBottom();
                } else {
                    this.mensajeError = data.error || 'Error al enviar el mensaje.';
                }
            } catch (e) {
                this.mensajeError = 'Error de conexión.';
            } finally {
                this.enviando = false;
            }
        },

        async enviarTemplate() {
            if (!this.plantillaSeleccionada || this.enviando) return;
            this.enviando = true;
            this.mensajeError = '';

            try {
                const resp = await fetch(`/admin/whatsapp/chat/{{ $conversacion->id }}/mensaje`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        tipo: 'template',
                        plantilla_id: this.plantillaSeleccionada,
                        parametros: this.paramsPlantilla,
                    })
                });
                const data = await resp.json();
                if (data.ok) {
                    this.mensajesNuevos.push({
                        mensaje_id: Date.now(),
                        direccion: 'saliente',
                        tipo: 'template',
                        contenido: '📋 Plantilla enviada',
                    });
                    this.plantillaSeleccionada = '';
                    this.paramsPlantilla = [];
                    this.scrollBottom();
                    // Recargar la página para mostrar la ventana activa actualizada
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.mensajeError = data.error || 'Error al enviar la plantilla.';
                }
            } catch(e) {
                this.mensajeError = 'Error de conexión.';
            } finally {
                this.enviando = false;
            }
        },

        async adjuntarArchivo(event) {
            const file = event.target.files[0];
            if (!file) return;

            this.enviando = true;
            this.mensajeError = '';

            const tipo = file.type.startsWith('image/') ? 'image'
                : file.type === 'application/pdf' ? 'document'
                : file.type.startsWith('audio/') ? 'audio' : 'document';

            const formData = new FormData();
            formData.append('tipo', tipo);
            formData.append('archivo', file);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            try {
                const resp = await fetch(`/admin/whatsapp/chat/{{ $conversacion->id }}/mensaje`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData,
                });
                const data = await resp.json();
                if (data.ok) {
                    this.mensajesNuevos.push({
                        mensaje_id: Date.now(),
                        direccion: 'saliente',
                        tipo: tipo,
                        contenido: '📎 ' + file.name,
                    });
                    this.scrollBottom();
                } else {
                    this.mensajeError = data.error || 'Error al enviar el archivo.';
                }
            } catch(e) {
                this.mensajeError = 'Error de conexión.';
            } finally {
                this.enviando = false;
                event.target.value = '';
            }
        },

        cargarParamsPlantilla() {
            const sel = document.querySelector(`option[value="${this.plantillaSeleccionada}"]`);
            const cantVars = sel ? parseInt(sel.dataset.vars || 0) : 0;
            this.paramsPlantilla = Array(cantVars).fill('');
        },

        getMapaVar(idx) {
            return this.mapaVars[idx + 1] || '';
        },

        async asignarConversacion() {
            try {
                const resp = await fetch(`/admin/whatsapp/chat/{{ $conversacion->id }}/asignar`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ user_id: this.usuarioAsignar || null })
                });
                const data = await resp.json();
                if (data.ok) { this.modalAsignar = false; location.reload(); }
                else alert(data.error || 'Error al asignar');
            } catch(e) { alert('Error de conexión'); }
        },

        async cerrarConversacion() {
            if (!confirm('¿Cerrar esta conversación? Se archivará y no aparecerá en el inbox.')) return;
            const resp = await fetch(`/admin/whatsapp/chat/{{ $conversacion->id }}/cerrar`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            });
            const data = await resp.json();
            if (data.ok) window.location = '{{ route('admin.whatsapp.chat.index') }}';
        },

        conectarReverb() {
            // Verificar si Laravel Echo está disponible
            if (typeof window.Echo === 'undefined') return;

            window.Echo.private(`whatsapp-aliado.${this.alidoId}`)
                .listen('.mensaje.nuevo', (e) => {
                    if (e.conversacion_id == this.convId && e.direccion === 'entrante') {
                        this.mensajesNuevos.push(e);
                        this.scrollBottom();
                        // Marcar como leído
                        fetch(`/admin/whatsapp/chat/${this.convId}/leer`, {
                            method: 'PATCH',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                        });
                    }
                });
        },

        autoResize(event) {
            event.target.style.height = 'auto';
            event.target.style.height = Math.min(event.target.scrollHeight, 120) + 'px';
        },
    };
}
</script>
@endpush
