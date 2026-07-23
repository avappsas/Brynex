<div x-show="openEntradaRapida"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     x-cloak
     class="modal-overlay-bx"
     @click.self="openEntradaRapida = false">

    <div class="modal-box-bx" style="max-width: 500px;">
        <div class="modal-head-bx" style="background: linear-gradient(135deg, #10b981, #047857); color: #fff;">
            <h3 style="color:#fff;">➕ Registrar Entrada Rápida</h3>
            <button @click="openEntradaRapida = false" class="modal-close-bx" style="color:rgba(255,255,255,0.7);">&times;</button>
        </div>

        @php
            $catEsporadicaId = \App\Models\Finanzas\CategoriaGasto::where('user_id', auth()->id())
                ->where(function($q) {
                    $q->where('nombre', 'like', '%Ingreso%')
                      ->orWhere('nombre', 'like', '%esporadico%');
                })->first()?->id;
        @endphp

        <form action="{{ route('finanzas.gastos.store') }}" method="POST" onsubmit="return confirmarMonto(this, 'monto', 'Vas a registrar una entrada de')">
            @csrf

            {{-- Campos ocultos para forzar ingreso esporádico --}}
            <input type="hidden" name="tipo_movimiento" value="ingreso_esporadico">
            <input type="hidden" name="categoria_id" value="{{ $catEsporadicaId }}">

            <div class="modal-body-bx" style="display:flex; flex-direction:column; gap:1rem;">
                
                {{-- Fecha --}}
                <div class="form-group-bx">
                    <label class="form-label-bx">Fecha del Ingreso</label>
                    <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                </div>

                {{-- Monto --}}
                <div class="form-group-bx">
                    <label class="form-label-bx">Monto ($ COP)</label>
                    <input type="number" name="monto" placeholder="Ej: 380000" class="form-input-bx" required min="1" autocomplete="off">
                </div>

                {{-- Cuenta / Bolsillo --}}
                @if(isset($cuentas) && $cuentas->isNotEmpty())
                <div class="form-group-bx">
                    <label class="form-label-bx">¿A qué cuenta entró el dinero?</label>
                    <select name="cuenta_id" class="form-select-bx" required>
                        @foreach($cuentas as $cta)
                            <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Descripción --}}
                <div class="form-group-bx">
                    <label class="form-label-bx">Descripción / Detalle</label>
                    <input type="text" name="descripcion" placeholder="Ej: Pago de comisión, venta de artículo" class="form-input-bx" required>
                </div>

                <div style="font-size:0.75rem; color:#047857; font-weight:500; background:#f0fdf4; padding:0.75rem; border-radius:8px; border:1px solid #bbf7d0;">
                    💡 Este ingreso se registrará de forma rápida y se sumará al mes correspondiente bajo la fuente consolidada <strong>"OTRAS ENTRADAS"</strong>.
                </div>

            </div>

            <div class="modal-foot-bx">
                <button type="button" @click="openEntradaRapida = false" class="btn-glass-bx" style="border-color:#cbd5e1; color:#475569;">Cancelar</button>
                <button type="submit" class="btn-fin success" style="background:#10b981; border:none; color:#fff; padding:0.45rem 1rem; border-radius:8px; font-weight:600; cursor:pointer;">💾 Guardar Entrada</button>
            </div>
        </form>
    </div>
</div>
