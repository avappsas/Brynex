<div x-data="{
    categoriaOpen: false,
    categoriaSearch: '',
    categoriaIdSelected: '',
    categoriaIconSelected: '',
    categorias: {!! json_encode($categorias->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'icono' => $c->icono, 'color' => $c->color]) ?? []) !!},
    soportePreview: null,
    soporteName: '',
    cargando: false,
    tipo: 'gasto',
    montoLimpio: '',
    montoFormateado: '',
    formatearMonto() {
        let valor = this.montoFormateado.replace(/\D/g, '');
        this.montoLimpio = valor;
        if (valor) {
            this.montoFormateado = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(valor);
        } else {
            this.montoFormateado = '';
        }
    },
    
    get filtradas() {
        if (!this.categoriaSearch) return this.categorias;
        return this.categorias.filter(c => c.nombre.toLowerCase().includes(this.categoriaSearch.toLowerCase()));
    },
    select(cat) {
        this.categoriaIdSelected = cat.id;
        this.categoriaIconSelected = cat.icono;
        this.categoriaSearch = cat.nombre;
        this.categoriaOpen = false;
    },
    clearCategoria() {
        this.categoriaIdSelected = '';
        this.categoriaIconSelected = '';
        this.categoriaSearch = '';
    },
    async pegarSoporte() {
        try {
            const clipboardItems = await navigator.clipboard.read();
            for (const item of clipboardItems) {
                for (const type of item.types) {
                    if (type.startsWith('image/')) {
                        const blob = await item.getType(type);
                        const file = new File([blob], 'soporte_pegado_' + Date.now() + '.png', { type: type });
                        
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        this.$refs.soporteInput.files = dt.files;
                        
                        this.soporteName = file.name;
                        this.soportePreview = URL.createObjectURL(blob);
                        return;
                    }
                }
            }
            alert('No se encontró ninguna imagen en el portapapeles. Copia una imagen primero.');
        } catch (err) {
            console.error(err);
            alert('No se pudo acceder al portapapeles. Intenta subir el archivo seleccionándolo.');
        }
    },
    handleFileChange(e) {
        const file = e.target.files[0];
        if (file) {
            this.soporteName = file.name;
            this.soportePreview = URL.createObjectURL(file);
        }
    },
    limpiarSoporte() {
        this.$refs.soporteInput.value = '';
        this.soporteName = '';
        this.soportePreview = null;
    },
    handlePaste(e) {
        if (!openGastoRapido) return;
        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        for (const item of items) {
            if (item.kind === 'file' && item.type.startsWith('image/')) {
                const blob = item.getAsFile();
                const file = new File([blob], 'soporte_pegado_' + Date.now() + '.png', { type: item.type });
                
                const dt = new DataTransfer();
                dt.items.add(file);
                this.$refs.soporteInput.files = dt.files;
                
                this.soporteName = file.name;
                this.soportePreview = URL.createObjectURL(blob);
            }
        }
    }
}"
@paste.window="handlePaste($event)"
x-show="openGastoRapido"
x-transition:enter="transition ease-out duration-200"
x-transition:enter-start="opacity-0"
x-transition:enter-end="opacity-100"
x-transition:leave="transition ease-in duration-150"
x-transition:leave-start="opacity-100"
x-transition:leave-end="opacity-0"
x-cloak
class="modal-overlay"
@click.self="openGastoRapido = false">

    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-head" style="background: linear-gradient(135deg, var(--azul-oscuro), var(--azul-medio)); color: #fff;">
            <h3 style="color:#fff;">➕ Registrar Gasto Rápido</h3>
            <button @click="openGastoRapido = false" class="modal-close" style="color:rgba(255,255,255,0.7);">&times;</button>
        </div>

        <form action="{{ route('finanzas.gastos.store') }}" method="POST" enctype="multipart/form-data" @submit="cargando = true">
            @csrf
            <div class="modal-body" style="display:flex; flex-direction:column; gap:1rem;">
                
                {{-- Fecha y Monto en la misma fila --}}
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    {{-- Fecha --}}
                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha del Gasto</label>
                        <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" style="font-size: 1.15rem; font-weight: 700; color: #1e293b;" required>
                    </div>

                    {{-- Monto --}}
                    <div class="form-group-bx">
                        <label class="form-label-bx">Monto ($ COP)</label>
                        <input type="text" 
                               x-model="montoFormateado" 
                               @input="formatearMonto()" 
                               placeholder="Ej: 50.000" 
                               class="form-input-bx" 
                               style="font-size: 1.15rem; font-weight: 700; color: #1e293b;"
                               required>
                        <input type="hidden" name="monto" :value="montoLimpio">
                    </div>
                </div>

                {{-- Categoría y Tipo de Movimiento en la misma fila --}}
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    {{-- Categoría (Combobox buscable y editable) --}}
                    <div class="form-group-bx" style="position: relative;">
                        <label class="form-label-bx">Categoría</label>
                        <div class="combobox-container-bx" @click.away="categoriaOpen = false" style="width: 100%;">
                            <div class="combobox-input-wrapper-bx" style="position: relative; display: flex; align-items: center; width: 100%;">
                                <span x-show="categoriaIconSelected" class="combobox-icon-bx" x-text="categoriaIconSelected" style="position: absolute; left: 0.75rem; font-size: 0.95rem; z-index: 5;"></span>
                                <input type="text" 
                                       x-model="categoriaSearch" 
                                       @focus="categoriaOpen = true"
                                       @input="categoriaOpen = true; categoriaIdSelected = ''; categoriaIconSelected = ''"
                                       placeholder="Seleccione o escriba..."
                                       class="form-input-bx" 
                                       :style="categoriaIconSelected ? 'padding-left: 2.25rem; width: 100%; flex: 1;' : 'padding-left: 0.75rem; width: 100%; flex: 1;'"
                                       autocomplete="off">
                                <button type="button" x-show="categoriaSearch" @click="clearCategoria()" class="combobox-clear-btn-bx" style="position: absolute; right: 0.75rem; background: none; border: none; font-size: 1.1rem; color: #94a3b8; cursor: pointer;">&times;</button>
                            </div>

                            {{-- Inputs ocultos --}}
                            <input type="hidden" name="categoria_id" :value="categoriaIdSelected">
                            <input type="hidden" name="nueva_categoria" :value="!categoriaIdSelected && categoriaSearch ? categoriaSearch : ''">

                            {{-- Lista desplegable --}}
                            <div x-show="categoriaOpen" 
                                 x-cloak 
                                 class="combobox-dropdown-bx">
                                <template x-for="cat in filtradas" :key="cat.id">
                                    <div @click="select(cat)" class="combobox-item-bx">
                                        <span x-text="cat.icono" style="margin-right: 0.5rem;"></span>
                                        <span x-text="cat.nombre" style="font-weight: 500;"></span>
                                    </div>
                                </template>
                                <div x-show="categoriaSearch && filtradas.length === 0" 
                                     @click="categoriaOpen = false"
                                     class="combobox-item-bx" 
                                     style="color: var(--azul-btn); font-weight: 600;">
                                    <span>➕ Crear: "</span><span x-text="categoriaSearch"></span><span>"</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Tipo de Movimiento --}}
                    <div class="form-group-bx">
                        <label class="form-label-bx">Tipo de Movimiento</label>
                        <select name="tipo_movimiento" x-model="tipo" class="form-select-bx" required style="height: 100%;">
                            <option value="gasto">Gasto Habitual</option>
                            <option value="prestamo">Desembolso Préstamo</option>
                            <option value="inversion">Inversión (Cripto/USDT)</option>
                        </select>
                    </div>
                </div>

                {{-- Cuenta / Bolsillo --}}
                @if(isset($cuentas) && $cuentas->isNotEmpty())
                <div class="form-group-bx">
                    <label class="form-label-bx">¿De qué cuenta salió el dinero?</label>
                    <select name="cuenta_id" class="form-select-bx" required>
                        @foreach($cuentas as $cta)
                            <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Descripción --}}
                <div class="form-group-bx">
                    <label class="form-label-bx">Descripción / Observación</label>
                    <input type="text" name="descripcion" placeholder="Ej: almuerzo con cliente, gasolina carro" class="form-input-bx">
                </div>

                {{-- Soporte de Pago --}}
                <div class="form-group-bx">
                    <label class="form-label-bx">Soporte de Pago (Opcional)</label>
                    <input type="file" name="soporte" x-ref="soporteInput" accept="image/*" style="display: none;" @change="handleFileChange($event)">
                    
                    <div style="display: flex; gap: 0.5rem; margin-top: 0.25rem;">
                        <button type="button" @click="pegarSoporte()" class="btn-glass" style="display: flex; align-items: center; gap: 0.35rem; color: #1e293b; background: #e2e8f0; border-color: #cbd5e1; padding: 0.4rem 0.75rem; font-size: 0.75rem;">
                            📋 Pegar Soporte
                        </button>
                        <button type="button" @click="$refs.soporteInput.click()" class="btn-glass" style="display: flex; align-items: center; gap: 0.35rem; color: #1e293b; background: #e2e8f0; border-color: #cbd5e1; padding: 0.4rem 0.75rem; font-size: 0.75rem;">
                            📸 Tomar Foto / Subir
                        </button>
                    </div>

                    {{-- Previsualización de Soporte --}}
                    <div x-show="soportePreview" x-cloak style="margin-top: 0.75rem; position: relative; display: inline-block;">
                        <img :src="soportePreview" style="max-height: 120px; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">
                        <button type="button" @click="limpiarSoporte()" class="btn-danger" style="position: absolute; top: -5px; right: -5px; width: 22px; height: 22px; border-radius: 50%; padding: 0; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; border: none; cursor: pointer; background: #ef4444; color: white;">
                            &times;
                        </button>
                        <div style="font-size: 0.65rem; color: #64748b; margin-top: 0.25rem; font-weight: 500;" x-text="soporteName"></div>
                    </div>
                </div>

                {{-- Patrimonio Checkbox --}}
                <div x-data="{ esPatrimonio: false }">
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.5rem;">
                        <input type="checkbox" name="es_patrimonio" value="1" x-model="esPatrimonio" id="es_patrimonio_check" style="cursor:pointer; width:16px; height:16px;">
                        <label for="es_patrimonio_check" style="font-size:0.8rem; color:#475569; cursor:pointer; font-weight:500;">
                            ¿Este gasto genera o se asocia a un bien de Patrimonio?
                        </label>
                    </div>

                    {{-- Selector de Patrimonio --}}
                    <div x-show="esPatrimonio" x-cloak style="margin-top:0.75rem; padding-left:1.5rem; border-left:2px solid #a855f7;">
                        <label class="form-label-bx" style="color:#7e22ce;">Asociar al Bien Patrimonial</label>
                        <select name="patrimonio_id" class="form-select-bx">
                            <option value="">-- Seleccionar Bien --</option>
                            @foreach($patrimonios ?? [] as $pat)
                                <option value="{{ $pat->id }}">{{ $pat->nombre }}</option>
                            @endforeach
                        </select>
                        <small style="color:#64748b; font-size:0.7rem; display:block; margin-top:0.25rem;">
                            El valor de este gasto se acumulará en el historial de gastos del bien.
                        </small>
                    </div>
                </div>

            </div>

            <div class="modal-foot">
                <button type="button" @click="openGastoRapido = false" class="btn-glass" style="border-color:#cbd5e1; color:#475569;">Cancelar</button>
                <button type="submit" :disabled="cargando" class="btn-accion-premium" style="background: linear-gradient(135deg, #10b981, #059669); color: white; display: flex; align-items: center; gap: 0.5rem; justify-content: center;">
                    <span x-show="!cargando">💾 Guardar Gasto</span>
                    <span x-show="cargando" x-cloak style="display: flex; align-items: center; gap: 0.35rem;">
                        <i class="fas fa-spinner fa-spin"></i> Guardando...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.form-group-bx { display: flex; flex-direction: column; gap: 0.25rem; }
.form-label-bx { font-size: 0.78rem; font-weight: 600; color: #334155; }
.form-input-bx { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.82rem; outline: none; }
.form-input-bx:focus { border-color: var(--acento); box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }
.form-select-bx { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.82rem; outline: none; background: #fff; cursor: pointer; }
.form-select-bx:focus { border-color: var(--acento); }

/* Combobox */
.combobox-container-bx { position: relative; width: 100%; }
.combobox-dropdown-bx {
    position: absolute;
    z-index: 1000;
    width: 100%;
    max-height: 200px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    margin-top: 0.25rem;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.combobox-item-bx {
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    font-size: 0.82rem;
    color: #334155;
    transition: background 0.1s;
}
.combobox-item-bx:hover {
    background: #f1f5f9;
}

/* Botón Premium */
.btn-accion-premium {
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.1s, opacity 0.15s;
    box-shadow: 0 4px 6px rgba(16, 185, 129, 0.15);
}
.btn-accion-premium:hover:not(:disabled) {
    transform: translateY(-1px);
    opacity: 0.95;
}
.btn-accion-premium:active:not(:disabled) {
    transform: translateY(0);
}
.btn-accion-premium:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
