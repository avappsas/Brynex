<div x-show="openGastoRapido"
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

        <form action="{{ route('finanzas.gastos.store') }}" method="POST">
            @csrf
            <div class="modal-body" style="display:flex; flex-direction:column; gap:1rem;">
                
                {{-- Fecha --}}
                <div class="form-group-bx">
                    <label class="form-label-bx">Fecha del Gasto</label>
                    <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                </div>

                {{-- Monto --}}
                <div class="form-group-bx">
                    <label class="form-label-bx">Monto ($ COP)</label>
                    <input type="number" name="monto" placeholder="Ej: 50000" class="form-input-bx" required min="1">
                </div>

                {{-- Categoría --}}
                <div class="form-group-bx">
                    <label class="form-label-bx">Categoría</label>
                    <select name="categoria_id" class="form-select-bx" required>
                        @foreach($categorias ?? [] as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->icono }} {{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Tipo de Movimiento --}}
                <div class="form-group-bx" x-data="{ tipo: 'gasto' }">
                    <label class="form-label-bx">Tipo de Movimiento</label>
                    <select name="tipo_movimiento" x-model="tipo" class="form-select-bx" required>
                        <option value="gasto">Gasto Habitual</option>
                        <option value="prestamo">Desembolso Préstamo</option>
                        <option value="inversion">Inversión (Cripto/USDT)</option>
                    </select>
                </div>

                {{-- Descripción --}}
                <div class="form-group-bx">
                    <label class="form-label-bx">Descripción / Observación</label>
                    <input type="text" name="descripcion" placeholder="Ej: almuerzo con cliente, gasolina carro" class="form-input-bx">
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
                <button type="submit" class="btn-accion" style="background:#22c55e;">💾 Guardar Gasto</button>
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
</style>
