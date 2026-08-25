{{--
    Modales de la ficha de cuenta corriente. Todos cuelgan del x-data `ccCliente()`
    definido al final de este archivo, para no repetir el editor de ítems.
--}}

{{-- ─────────────────────────────── Nuevo trabajo ─────────────────────────────── --}}
<div x-show="openTrabajo" class="modal-overlay-bx" @click.self="openTrabajo = false" x-cloak>
    <div class="modal-box-bx" style="max-width:720px;">
        <form action="{{ route('finanzas.cuenta-corriente.trabajos.store', $cliente->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-head-bx">
                <h3>➕ Registrar Trabajo · {{ $cliente->nombre }}</h3>
                <button type="button" class="modal-close-bx" @click="openTrabajo = false">✕</button>
            </div>

            <div class="modal-body-bx">
                <div class="form-group-bx">
                    <label class="form-label-bx">¿Qué trabajo se hizo?</label>
                    <input type="text" name="descripcion" class="form-input-bx" placeholder="Ej: Instalación de cámaras" required maxlength="255">
                </div>

                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Fecha del trabajo</label>
                        <input type="date" name="fecha" class="form-input-bx" value="{{ $hoy }}" required>
                        <small class="cc-hint">Desde aquí corre el mes de gracia.</small>
                    </div>
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Tasa mensual (%)</label>
                        <input type="number" step="0.001" min="0" max="100" name="tasa_interes_mensual"
                               class="form-input-bx" value="{{ $cliente->tasa_interes_mensual }}" required>
                    </div>
                </div>

                <label class="cc-check" style="margin-top:0.75rem;">
                    <input type="checkbox" name="sin_interes" value="1">
                    <span>Este trabajo nunca genera intereses</span>
                </label>

                {{-- Desglose --}}
                <div style="margin-top:1.25rem;" x-data="ccItems(@js($itemsVacio))">
                    <label class="form-label-bx">Desglose del trabajo</label>
                    <small class="cc-hint" style="margin-bottom:0.5rem;">Una línea por concepto: cámaras, DVR, mano de obra…</small>

                    <template x-for="(item, i) in items" :key="i">
                        <div class="cc-item-fila">
                            <div class="cc-col-desc">
                                <input type="text" :name="`items[${i}][descripcion]`" x-model="item.descripcion"
                                       placeholder="Ej: Cámara Hikvision 1080p" maxlength="150" required>
                            </div>
                            <div class="cc-col-num">
                                <input type="number" step="0.01" min="0.01" :name="`items[${i}][cantidad]`"
                                       x-model.number="item.cantidad" placeholder="Cant." required>
                            </div>
                            <div class="cc-col-val">
                                <input type="number" step="1" min="0" :name="`items[${i}][valor_unitario]`"
                                       x-model.number="item.valor_unitario" placeholder="V. unitario" required>
                            </div>
                            <div class="cc-col-sub" x-text="money(sub(item))"></div>
                            <button type="button" class="cc-item-quitar" @click="quitar(i)"
                                    x-show="items.length > 1" title="Quitar línea">✕</button>
                        </div>
                    </template>

                    <button type="button" class="btn-fin" style="margin-top:0.35rem;" @click="agregar()">＋ Agregar línea</button>

                    <div class="cc-item-total">
                        <span>Total del trabajo</span>
                        <span x-text="money(total)"></span>
                    </div>
                </div>

                {{-- Costo real --}}
                <div style="display:flex; gap:1rem; margin-top:1.25rem;">
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">¿Cuánto te costaron los materiales? (opcional)</label>
                        <input type="number" step="1" min="0" name="costo_materiales" class="form-input-bx" placeholder="Ej: 900000">
                        <small class="cc-hint">Se registra como gasto para ver la utilidad real.</small>
                    </div>
                    @if($cuentas->isNotEmpty())
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">¿De qué cuenta salió ese costo?</label>
                        <select name="cuenta_costo_id" class="form-select-bx">
                            @foreach($cuentas as $cta)
                                <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>

                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Observaciones (opcional)</label>
                    <textarea name="observaciones" class="form-input-bx" rows="2"></textarea>
                </div>

                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Soporte (cotización, remisión…)</label>
                    <input type="file" name="soporte" class="form-input-bx" accept=".jpg,.jpeg,.png,.pdf">
                </div>
            </div>

            <div class="modal-foot-bx">
                <button type="button" class="btn-fin" @click="openTrabajo = false">Cancelar</button>
                <button type="submit" class="btn-fin success" style="background:#7e22ce;">Registrar trabajo</button>
            </div>
        </form>
    </div>
</div>

{{-- ─────────────────────────────── Editar trabajo ─────────────────────────────── --}}
<div x-show="openEditar" class="modal-overlay-bx" @click.self="openEditar = false" x-cloak>
    <div class="modal-box-bx" style="max-width:720px;">
        <form :action="`{{ url('finanzas/cuenta-corriente-trabajo') }}/${editar.id}`" method="POST">
            @csrf @method('PUT')
            <div class="modal-head-bx">
                <h3>✏️ Editar trabajo</h3>
                <button type="button" class="modal-close-bx" @click="openEditar = false">✕</button>
            </div>

            <div class="modal-body-bx">
                <div class="form-group-bx">
                    <label class="form-label-bx">¿Qué trabajo se hizo?</label>
                    <input type="text" name="descripcion" class="form-input-bx" x-model="editar.descripcion" required maxlength="255">
                </div>

                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Fecha del trabajo</label>
                        <input type="date" name="fecha" class="form-input-bx" x-model="editar.fecha" required>
                    </div>
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Tasa mensual (%)</label>
                        <input type="number" step="0.001" min="0" max="100" name="tasa_interes_mensual"
                               class="form-input-bx" x-model.number="editar.tasa" required>
                    </div>
                </div>

                <label class="cc-check" style="margin-top:0.75rem;">
                    <input type="checkbox" name="sin_interes" value="1" x-model="editar.sin_interes">
                    <span>Este trabajo nunca genera intereses</span>
                </label>

                <div style="margin-top:1.25rem;">
                    <label class="form-label-bx">Desglose del trabajo</label>

                    <template x-for="(item, i) in editar.items" :key="i">
                        <div class="cc-item-fila">
                            <div class="cc-col-desc">
                                <input type="text" :name="`items[${i}][descripcion]`" x-model="item.descripcion" maxlength="150" required>
                            </div>
                            <div class="cc-col-num">
                                <input type="number" step="0.01" min="0.01" :name="`items[${i}][cantidad]`" x-model.number="item.cantidad" required>
                            </div>
                            <div class="cc-col-val">
                                <input type="number" step="1" min="0" :name="`items[${i}][valor_unitario]`" x-model.number="item.valor_unitario" required>
                            </div>
                            <div class="cc-col-sub" x-text="money(sub(item))"></div>
                            <button type="button" class="cc-item-quitar" @click="editar.items.splice(i, 1)"
                                    x-show="editar.items.length > 1" title="Quitar línea">✕</button>
                        </div>
                    </template>

                    <button type="button" class="btn-fin" style="margin-top:0.35rem;"
                            @click="editar.items.push({ descripcion: '', cantidad: 1, valor_unitario: 0 })">＋ Agregar línea</button>

                    <div class="cc-item-total">
                        <span>Total del trabajo</span>
                        <span x-text="money(totalDe(editar.items))"></span>
                    </div>
                </div>

                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Observaciones</label>
                    <textarea name="observaciones" class="form-input-bx" rows="2" x-model="editar.observaciones"></textarea>
                </div>

                <p class="cc-hint" style="margin-top:0.75rem;">
                    Al guardar se recalculan los saldos del trabajo respetando los pagos ya registrados.
                </p>
            </div>

            <div class="modal-foot-bx">
                <button type="button" class="btn-fin" @click="openEditar = false">Cancelar</button>
                <button type="submit" class="btn-fin success">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

{{-- ───────────────────────────── Pago de un trabajo ───────────────────────────── --}}
<div x-show="openPago" class="modal-overlay-bx" @click.self="openPago = false" x-cloak>
    <div class="modal-box-bx">
        <form :action="`{{ url('finanzas/cuenta-corriente-trabajo') }}/${pago.id}/pago`" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-head-bx">
                <h3>💵 Registrar pago</h3>
                <button type="button" class="modal-close-bx" @click="openPago = false">✕</button>
            </div>

            <div class="modal-body-bx">
                <div class="cc-pago-resumen">
                    <span x-text="pago.descripcion"></span>
                    <strong>Saldo: <span x-text="money(pago.saldo)"></span></strong>
                </div>

                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Monto pagado</label>
                        <input type="number" step="1" min="1" name="monto" class="form-input-bx" x-model.number="pago.monto" required>
                        <button type="button" class="btn-fin" style="margin-top:0.35rem; font-size:0.7rem;"
                                @click="pago.monto = Math.round(pago.saldo)">Pagar todo el saldo</button>
                    </div>
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Fecha del pago</label>
                        <input type="date" name="fecha" class="form-input-bx" value="{{ $hoy }}" required>
                    </div>
                </div>

                @if($cuentas->isNotEmpty())
                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">¿A qué cuenta entró el dinero?</label>
                    <select name="cuenta_id" class="form-select-bx">
                        @foreach($cuentas as $cta)
                            <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Observación (opcional)</label>
                    <input type="text" name="observacion" class="form-input-bx" maxlength="255">
                </div>

                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Comprobante (opcional)</label>
                    <input type="file" name="soporte" class="form-input-bx" accept=".jpg,.jpeg,.png,.pdf">
                </div>
            </div>

            <div class="modal-foot-bx">
                <button type="button" class="btn-fin" @click="openPago = false">Cancelar</button>
                <button type="submit" class="btn-fin success">Registrar pago</button>
            </div>
        </form>
    </div>
</div>

{{-- ───────────────────────────────── Abono general ────────────────────────────── --}}
<div x-show="openAbono" class="modal-overlay-bx" @click.self="openAbono = false" x-cloak>
    <div class="modal-box-bx">
        <form action="{{ route('finanzas.cuenta-corriente.abono', $cliente->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-head-bx">
                <h3>💰 Abono general · {{ $cliente->nombre }}</h3>
                <button type="button" class="modal-close-bx" @click="openAbono = false">✕</button>
            </div>

            <div class="modal-body-bx">
                <p class="cc-hint" style="margin-bottom:0.75rem;">
                    El abono se reparte del trabajo más antiguo al más nuevo. Si sobra dinero después de dejar
                    todo en cero, se te avisa y <strong>no</strong> se registra como saldo a favor.
                </p>

                <div style="display:flex; gap:1rem;">
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Monto abonado</label>
                        <input type="number" step="1" min="1" name="monto" class="form-input-bx"
                               placeholder="{{ (int) $totales['saldo'] }}" required>
                        <small class="cc-hint">Saldo total: ${{ number_format($totales['saldo'], 0, ',', '.') }}</small>
                    </div>
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Fecha</label>
                        <input type="date" name="fecha" class="form-input-bx" value="{{ $hoy }}" required>
                    </div>
                </div>

                @if($cuentas->isNotEmpty())
                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">¿A qué cuenta entró el dinero?</label>
                    <select name="cuenta_id" class="form-select-bx">
                        @foreach($cuentas as $cta)
                            <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Observación (opcional)</label>
                    <input type="text" name="observacion" class="form-input-bx" maxlength="255">
                </div>

                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Comprobante (opcional)</label>
                    <input type="file" name="soporte" class="form-input-bx" accept=".jpg,.jpeg,.png,.pdf">
                </div>
            </div>

            <div class="modal-foot-bx">
                <button type="button" class="btn-fin" @click="openAbono = false">Cancelar</button>
                <button type="submit" class="btn-fin success">Aplicar abono</button>
            </div>
        </form>
    </div>
</div>

{{-- ───────────────────────────────── Editar cliente ───────────────────────────── --}}
<div x-show="openCliente" class="modal-overlay-bx" @click.self="openCliente = false" x-cloak>
    <div class="modal-box-bx">
        <form action="{{ route('finanzas.cuenta-corriente.clientes.update', $cliente->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="modal-head-bx">
                <h3>✏️ Editar cliente</h3>
                <button type="button" class="modal-close-bx" @click="openCliente = false">✕</button>
            </div>

            <div class="modal-body-bx">
                <div class="form-group-bx">
                    <label class="form-label-bx">Nombre</label>
                    <input type="text" name="nombre" class="form-input-bx" value="{{ $cliente->nombre }}" required maxlength="100">
                </div>

                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Cédula / NIT</label>
                        <input type="text" name="cedula" class="form-input-bx" value="{{ $cliente->cedula }}" maxlength="20">
                    </div>
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Celular (WhatsApp)</label>
                        <input type="text" name="telefono" class="form-input-bx" value="{{ $cliente->telefono }}" maxlength="20">
                    </div>
                </div>

                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Tasa mensual por defecto (%)</label>
                        <input type="number" step="0.001" min="0" max="100" name="tasa_interes_mensual"
                               class="form-input-bx" value="{{ $cliente->tasa_interes_mensual }}" required>
                        <small class="cc-hint">Solo afecta los trabajos nuevos.</small>
                    </div>
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Días límite de pago</label>
                        <input type="number" min="1" name="dias_mora_alerta" class="form-input-bx" value="{{ $cliente->dias_mora_alerta }}" required>
                    </div>
                </div>

                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Notas</label>
                    <textarea name="notas" class="form-input-bx" rows="2">{{ $cliente->notas }}</textarea>
                </div>

                <label class="cc-check" style="margin-top:1rem;">
                    <input type="checkbox" name="alertas_activas" value="1" @checked($cliente->alertas_activas)>
                    <span>Enviar recordatorios por WhatsApp</span>
                </label>

                <label class="cc-check" style="margin-top:0.5rem;">
                    <input type="checkbox" name="activo" value="1" @checked($cliente->activo)>
                    <span>Cliente activo</span>
                </label>
            </div>

            <div class="modal-foot-bx">
                <button type="button" class="btn-fin" @click="openCliente = false">Cancelar</button>
                <button type="submit" class="btn-fin success">Guardar</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Estado compartido de la ficha: modales, pago dirigido y edición de trabajo.
    function ccCliente() {
        return {
            openTrabajo: false,
            openAbono: false,
            openCliente: false,
            openPago: false,
            openEditar: false,
            pago: { id: null, descripcion: '', saldo: 0, monto: 0 },
            editar: { id: null, descripcion: '', fecha: '', tasa: 0, sin_interes: false, observaciones: '', items: [] },

            abrirPago(id, descripcion, saldo) {
                this.pago = { id, descripcion, saldo, monto: Math.round(saldo) };
                this.openPago = true;
            },

            abrirEditar(id, descripcion, fecha, tasa, sinInteres, observaciones, items) {
                this.editar = {
                    id,
                    descripcion,
                    fecha,
                    tasa,
                    sin_interes: sinInteres,
                    observaciones: observaciones || '',
                    // Copia propia: editar el modal no debe tocar la tarjeta de abajo.
                    items: JSON.parse(JSON.stringify(items)),
                };
                this.openEditar = true;
            },

            sub(item) {
                return (parseFloat(item.cantidad) || 0) * (parseFloat(item.valor_unitario) || 0);
            },

            totalDe(items) {
                return (items || []).reduce((acc, item) => acc + this.sub(item), 0);
            },

            money(valor) {
                return '$' + Math.round(valor || 0).toLocaleString('es-CO');
            },
        };
    }

    // Editor de ítems del formulario de creación (aislado del de edición).
    function ccItems(iniciales) {
        return {
            items: JSON.parse(JSON.stringify(iniciales)),

            agregar() {
                this.items.push({ descripcion: '', cantidad: 1, valor_unitario: 0 });
            },

            quitar(i) {
                if (this.items.length > 1) {
                    this.items.splice(i, 1);
                }
            },

            sub(item) {
                return (parseFloat(item.cantidad) || 0) * (parseFloat(item.valor_unitario) || 0);
            },

            get total() {
                return this.items.reduce((acc, item) => acc + this.sub(item), 0);
            },

            money(valor) {
                return '$' + Math.round(valor || 0).toLocaleString('es-CO');
            },
        };
    }
</script>
@endpush
