{{--
    Partial de modal para envío masivo de WhatsApp desde la vista de Cobros.

    Uso:
        @include('admin.whatsapp.masivo.modal_cobros', [
            'tipoEnvio' => 'individual',   // o 'empresa'
            'mes'       => $mes,
            'anio'      => $anio,
            'empresaIds'=> [],             // solo para tipo empresa
        ])
--}}
<div x-data="waModal(@json($tipoEnvio), @json($mes), @json($anio), @json($empresaIds ?? []))">

    {{-- Botón disparador --}}
    <button type="button" @click="abrirModal()"
            style="background:#25d366;color:#fff;border:none;border-radius:8px;padding:.45rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:opacity .15s"
            onmouseover="this.style.opacity='.87'" onmouseout="this.style.opacity='1'">
        📱 Enviar WhatsApp
    </button>

    {{-- Modal overlay --}}
    <div x-show="abierto" x-cloak
         style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9998;display:flex;align-items:center;justify-content:center"
         @click.self="cerrar()">

        <div style="background:#fff;border-radius:14px;padding:1.5rem;width:480px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)">

            {{-- Header modal --}}
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
                <h3 style="font-size:1rem;font-weight:700;color:#0f172a">📱 Enviar Cobro por WhatsApp</h3>
                <button @click="cerrar()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:#94a3b8">✕</button>
            </div>

            {{-- Período --}}
            <div style="background:#f8fafc;border-radius:8px;padding:.65rem .9rem;margin-bottom:1rem;font-size:.82rem;color:#475569">
                📅 Período: <strong x-text="nombreMes()"></strong>
            </div>

            {{-- Selector de plantilla --}}
            <div style="margin-bottom:1rem">
                <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:.35rem">
                    Plantilla de mensaje <span style="color:#ef4444">*</span>
                </label>
                <select x-model="plantillaId" @change="cargarPlantilla()" style="width:100%;padding:.5rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.83rem;outline:none">
                    <option value="">— Cargando plantillas... —</option>
                    <template x-for="p in plantillas" :key="p.id">
                        <option :value="p.id" x-text="p.nombre_display"></option>
                    </template>
                </select>
                <div x-show="cargandoPlantillas" style="font-size:.73rem;color:#94a3b8;margin-top:.25rem">⏳ Cargando plantillas aprobadas...</div>
            </div>

            {{-- Preview de la plantilla --}}
            <div x-show="plantillaSeleccionada" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.75rem;margin-bottom:1rem;font-size:.82rem;color:#1e40af">
                <div style="font-size:.7rem;font-weight:700;color:#3b82f6;margin-bottom:.35rem">Vista previa</div>
                <div x-text="plantillaSeleccionada?.preview || ''"></div>
            </div>

            {{-- Variables de la plantilla --}}
            <div x-show="plantillaSeleccionada && plantillaSeleccionada.cant_variables > 0" style="margin-bottom:1rem">
                <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:.4rem">
                    Variables del mensaje
                </label>
                <template x-for="(v, idx) in parametros" :key="idx">
                    <div style="margin-bottom:.4rem">
                        <input type="text" x-model="parametros[idx]"
                               :placeholder="'{{' + (idx+1) + '}} — ' + (plantillaSeleccionada?.variables_mapa?.[idx+1] || 'valor')"
                               style="width:100%;padding:.42rem .7rem;border:1px solid #cbd5e1;border-radius:7px;font-size:.82rem;outline:none">
                    </div>
                </template>
                <div style="font-size:.72rem;color:#94a3b8">Estos valores reemplazarán las variables en el mensaje enviado a cada cliente.</div>
            </div>

            {{-- Resumen de destinatarios --}}
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.65rem .9rem;margin-bottom:1rem;font-size:.82rem;color:#065f46">
                <div>
                    📊 Se enviará a <strong>{{ $tipoEnvio === 'empresa' ? count($empresaIds ?? []) : 'todos los' }}</strong>
                    {{ $tipoEnvio === 'empresa' ? 'empresa(s) seleccionada(s)' : 'clientes individuales con pago pendiente' }}
                </div>
                <div style="margin-top:.25rem;font-size:.72rem;color:#15803d">
                    ⚠️ No se enviará a quienes ya recibieron esta plantilla hoy (anti-spam automático).
                </div>
            </div>

            {{-- Mensaje de resultado --}}
            <div x-show="resultado.mensaje" :style="'background:' + (resultado.ok ? '#d1fae5' : '#fee2e2') + ';border-radius:8px;padding:.65rem .9rem;margin-bottom:1rem;font-size:.82rem;color:' + (resultado.ok ? '#065f46' : '#991b1b')"
                 x-text="resultado.mensaje"></div>

            {{-- Botones --}}
            <div style="display:flex;gap:.65rem;justify-content:flex-end">
                <button @click="cerrar()" type="button"
                        style="padding:.45rem 1rem;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:1px solid #cbd5e1;background:transparent;color:#475569">
                    Cancelar
                </button>
                <button @click="enviar()" type="button" :disabled="!plantillaId || enviando"
                        style="padding:.45rem 1.2rem;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:none;background:#25d366;color:#fff;display:flex;align-items:center;gap:.4rem"
                        :style="(!plantillaId || enviando) ? 'opacity:.5;cursor:not-allowed' : ''">
                    <span x-show="!enviando">📤 Confirmar envío</span>
                    <span x-show="enviando">⏳ Enviando...</span>
                </button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function waModal(tipoEnvio, mes, anio, empresaIds) {
    return {
        abierto: false,
        tipoEnvio, mes, anio, empresaIds,
        plantillas: [],
        plantillaId: '',
        plantillaSeleccionada: null,
        parametros: [],
        cargandoPlantillas: false,
        enviando: false,
        resultado: { ok: null, mensaje: '' },

        async abrirModal() {
            this.abierto = true;
            this.resultado = { ok: null, mensaje: '' };
            if (this.plantillas.length === 0) await this.cargarPlantillas();
        },

        cerrar() {
            this.abierto = false;
            this.plantillaId = '';
            this.plantillaSeleccionada = null;
            this.parametros = [];
            this.resultado = { ok: null, mensaje: '' };
        },

        async cargarPlantillas() {
            this.cargandoPlantillas = true;
            try {
                const resp = await fetch('{{ route('admin.whatsapp.api.plantillas') }}', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                this.plantillas = data.plantillas || [];
            } catch(e) {
                console.error('Error cargando plantillas WhatsApp:', e);
            } finally {
                this.cargandoPlantillas = false;
            }
        },

        cargarPlantilla() {
            this.plantillaSeleccionada = this.plantillas.find(p => p.id == this.plantillaId) || null;
            const cant = this.plantillaSeleccionada?.cant_variables || 0;
            this.parametros = Array(cant).fill('');
        },

        nombreMes() {
            const meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            return (meses[this.mes] || '—') + ' ' + this.anio;
        },

        async enviar() {
            if (!this.plantillaId || this.enviando) return;
            this.enviando = true;
            this.resultado = { ok: null, mensaje: '' };

            const url = this.tipoEnvio === 'empresa'
                ? '{{ route('admin.whatsapp.masivo.empresa') }}'
                : '{{ route('admin.whatsapp.masivo.individual') }}';

            const body = {
                plantilla_id: this.plantillaId,
                mes: this.mes,
                anio: this.anio,
                parametros: this.parametros,
            };
            if (this.tipoEnvio === 'empresa') body.empresa_ids = this.empresaIds;

            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                const data = await resp.json();
                this.resultado = { ok: data.ok, mensaje: data.ok ? data.mensaje : (data.error || 'Error al enviar') };
                if (data.ok) setTimeout(() => this.cerrar(), 3000);
            } catch(e) {
                this.resultado = { ok: false, mensaje: 'Error de conexión.' };
            } finally {
                this.enviando = false;
            }
        },
    };
}
</script>
@endpush
@endonce
