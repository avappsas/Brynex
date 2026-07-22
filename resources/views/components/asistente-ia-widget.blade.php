{{-- Widget flotante del Asistente Virtual IA --}}
<div
    x-data="asistenteIaWidget()"
    x-cloak
    style="position:fixed; right:1.25rem; bottom:1.25rem; z-index:9999;"
>
    {{-- Botón flotante --}}
    <button
        @click="abierto = !abierto"
        style="width:56px; height:56px; border-radius:50%; border:none; cursor:pointer;
               background: linear-gradient(135deg, var(--azul-btn), var(--acento));
               color:#fff; font-size:1.5rem; box-shadow:0 4px 14px rgba(0,0,0,.25);
               display:flex; align-items:center; justify-content:center;"
        title="{{ $nombreBot ?? 'Asistente Virtual IA' }}"
    >
        <span x-show="!abierto">🤖</span>
        <span x-show="abierto" x-cloak>✕</span>
    </button>

    {{-- Panel de chat --}}
    <div
        x-show="abierto"
        x-cloak
        style="position:absolute; bottom:68px; right:0; width:340px; max-width:88vw; height:460px;
               background:#fff; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,.25);
               display:flex; flex-direction:column; overflow:hidden; border:1px solid #e2e8f0;"
    >
        <div style="background:linear-gradient(90deg, var(--azul-oscuro), var(--azul-medio)); color:#fff; padding:.75rem 1rem;">
            <div style="font-weight:600; font-size:.9rem;">🤖 {{ $nombreBot ?? 'Asistente Virtual' }}</div>
            <div style="font-size:.7rem; opacity:.75;">Precios, seguridad social y navegación</div>
        </div>

        <div x-ref="lista" style="flex:1; overflow-y:auto; padding:.75rem; display:flex; flex-direction:column; gap:.5rem; background:#f8fafc;">
            <template x-if="mensajes.length === 0">
                <div style="font-size:.8rem; color:#64748b; text-align:center; margin-top:1rem;">
                    Pregúntame por ejemplo:<br>
                    <em>"¿Cuánto cuesta el plan integral para un dependiente con salario 2.000.000?"</em><br>
                    <em>"¿Dónde reviso las facturas anuladas?"</em>
                </div>
            </template>

            <template x-for="(m, idx) in mensajes" :key="idx">
                <div :style="m.rol === 'user'
                        ? 'align-self:flex-end; background:var(--azul-btn); color:#fff; padding:.5rem .75rem; border-radius:10px 10px 2px 10px; max-width:85%; font-size:.82rem; white-space:pre-wrap;'
                        : 'align-self:flex-start; background:#e2e8f0; color:#0f172a; padding:.5rem .75rem; border-radius:10px 10px 10px 2px; max-width:85%; font-size:.82rem; white-space:pre-wrap;'">
                    <span x-text="m.texto"></span>
                    <template x-if="m.acciones && m.acciones.length">
                        <div style="margin-top:.4rem; display:flex; flex-direction:column; gap:.3rem;">
                            <template x-for="a in m.acciones" :key="a.url">
                                <a :href="a.url" style="font-size:.75rem; color:#1e40af; background:#fff; border:1px solid #cbd5e1; border-radius:6px; padding:.3rem .5rem; text-decoration:none; text-align:center;">
                                    Abrir: <span x-text="a.nombre"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            <div x-show="cargando" style="align-self:flex-start; font-size:.8rem; color:#64748b;">Escribiendo…</div>
        </div>

        <form @submit.prevent="enviar" style="display:flex; border-top:1px solid #e2e8f0; padding:.5rem;">
            <input
                type="text"
                x-model="entrada"
                placeholder="Escribe tu pregunta…"
                :disabled="cargando"
                style="flex:1; border:1px solid #e2e8f0; border-radius:8px; padding:.5rem .6rem; font-size:.82rem; outline:none;"
            >
            <button type="submit" :disabled="cargando || !entrada.trim()"
                style="margin-left:.4rem; background:var(--azul-btn); color:#fff; border:none; border-radius:8px; padding:0 .8rem; cursor:pointer;">
                ➤
            </button>
        </form>
    </div>
</div>

<script>
function asistenteIaWidget() {
    return {
        abierto: false,
        entrada: '',
        cargando: false,
        mensajes: [],
        async enviar() {
            const texto = this.entrada.trim();
            if (!texto || this.cargando) return;

            this.mensajes.push({ rol: 'user', texto });
            this.entrada = '';
            this.cargando = true;
            this.$nextTick(() => this.scrollAbajo());

            try {
                const resp = await fetch('{{ route('asistente_ia.chat') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ mensaje: texto }),
                });
                const data = await resp.json();

                if (!resp.ok) {
                    this.mensajes.push({ rol: 'assistant', texto: data.error || 'Ocurrió un error, intenta de nuevo.' });
                } else {
                    this.mensajes.push({ rol: 'assistant', texto: data.respuesta, acciones: data.acciones || [] });
                }
            } catch (e) {
                this.mensajes.push({ rol: 'assistant', texto: 'No pude conectarme con el asistente. Intenta de nuevo.' });
            } finally {
                this.cargando = false;
                this.$nextTick(() => this.scrollAbajo());
            }
        },
        scrollAbajo() {
            const el = this.$refs.lista;
            if (el) el.scrollTop = el.scrollHeight;
        },
    };
}
</script>
