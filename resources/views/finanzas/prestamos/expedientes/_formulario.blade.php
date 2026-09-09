{{--
    Campos del préstamo formal. Lo comparten el alta (dentro del formulario de
    "Nuevo préstamo", cuando se activa el interruptor) y la edición del
    expediente, para que las dos pantallas pidan exactamente lo mismo.

    Espera: $accion, $metodo, $prestamistas y, al editar, $exp.
--}}
@php
    $exp = $exp ?? null;
    $v = fn ($campo, $defecto = '') => old($campo, $exp->{$campo} ?? $defecto);
    $porDefecto = $prestamistas->firstWhere('por_defecto', true) ?? $prestamistas->first();
@endphp

<form action="{{ $accion }}" method="POST"
      x-data="{
          codeudor: {{ old('tiene_codeudor', $exp->tiene_codeudor ?? false) ? 'true' : 'false' }},
          prenda: {{ old('tiene_prenda', $exp->tiene_prenda ?? false) ? 'true' : 'false' }},
          monto: {{ (float) $v('monto', 0) }},
          tasa: {{ (float) $v('tasa_interes_mensual', 2.1) }},
          enBlanco: {{ old('tasa_en_blanco', $exp->tasa_en_blanco ?? false) ? 'true' : 'false' }},
          get tope() { return this.monto * 2 },
          get interes() { return Math.round(this.monto * this.tasa / 100) },
          pesos(n) { return '$' + (n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 }) }
      }">
    @csrf
    @if($metodo !== 'POST') @method($metodo) @endif

    @if(isset($errors) && $errors->any())
        <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.75rem 1rem; border-radius:9px; margin-bottom:1rem; font-size:0.8rem;">
            <strong>Revisa estos campos:</strong>
            <ul style="margin:0.4rem 0 0 1rem;">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Prestamista --}}
    <h3 class="fin-sub-titulo">1. ¿A nombre de quién sale el préstamo?</h3>
    @if($prestamistas->isEmpty())
        <div style="background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:0.75rem 1rem; border-radius:9px; font-size:0.8rem;">
            Todavía no hay ningún prestamista registrado. Créalo en
            <a href="{{ route('finanzas.expedientes.index') }}" style="font-weight:700; color:#b45309;">Préstamos formales</a>
            antes de abrir el expediente: sus datos encabezan el contrato y el pagaré.
        </div>
    @else
        <div style="display:flex; gap:1rem;">
            <div class="form-group-bx" style="flex:2;">
                <label class="form-label-bx">Prestamista (mutuante)</label>
                <select name="prestamista_id" class="form-select-bx" required>
                    @foreach($prestamistas as $p)
                        <option value="{{ $p->id }}" @selected((int) $v('prestamista_id', $porDefecto?->id) === $p->id)>
                            {{ $p->nombre }}{{ $p->cedula ? ' — C.C. '.$p->cedula : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Ciudad del contrato</label>
                <input type="text" name="ciudad" value="{{ $v('ciudad', 'Cali') }}" class="form-input-bx" required>
            </div>
        </div>
    @endif

    {{-- Deudor --}}
    <h3 class="fin-sub-titulo">2. Datos del deudor</h3>
    <div style="display:flex; gap:1rem;">
        <div class="form-group-bx" style="flex:2;">
            <label class="form-label-bx">Nombre completo *</label>
            <input type="text" name="deudor_nombre" value="{{ $v('deudor_nombre') }}" class="form-input-bx" required>
        </div>
        <div class="form-group-bx" style="flex:1;">
            <label class="form-label-bx">Cédula *</label>
            <input type="text" name="deudor_cedula" value="{{ $v('deudor_cedula') }}" class="form-input-bx" required>
        </div>
        <div class="form-group-bx" style="flex:1;">
            <label class="form-label-bx">Expedida en</label>
            <input type="text" name="deudor_expedida_en" value="{{ $v('deudor_expedida_en') }}" class="form-input-bx">
        </div>
    </div>
    <div style="display:flex; gap:1rem; margin-top:0.75rem;">
        <div class="form-group-bx" style="flex:2;">
            <label class="form-label-bx">Dirección</label>
            <input type="text" name="deudor_direccion" value="{{ $v('deudor_direccion') }}" class="form-input-bx">
        </div>
        <div class="form-group-bx" style="flex:1;">
            <label class="form-label-bx">Ciudad</label>
            <input type="text" name="deudor_ciudad" value="{{ $v('deudor_ciudad', 'Cali') }}" class="form-input-bx">
        </div>
    </div>
    <div style="display:flex; gap:1rem; margin-top:0.75rem;">
        <div class="form-group-bx" style="flex:1;">
            <label class="form-label-bx">Teléfono (WhatsApp)</label>
            <input type="text" name="deudor_telefono" value="{{ $v('deudor_telefono') }}" class="form-input-bx">
        </div>
        <div class="form-group-bx" style="flex:1;">
            <label class="form-label-bx">Correo</label>
            <input type="email" name="deudor_correo" value="{{ $v('deudor_correo') }}" class="form-input-bx">
        </div>
        <div class="form-group-bx" style="flex:1;">
            <label class="form-label-bx">Ocupación</label>
            <input type="text" name="deudor_ocupacion" value="{{ $v('deudor_ocupacion') }}" class="form-input-bx">
        </div>
    </div>

    {{-- Codeudor --}}
    <h3 class="fin-sub-titulo">3. Codeudor solidario</h3>
    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.82rem; font-weight:600; color:#475569;">
        <input type="hidden" name="tiene_codeudor" value="0">
        <input type="checkbox" name="tiene_codeudor" value="1" x-model="codeudor" style="width:16px; height:16px; cursor:pointer;">
        Este préstamo lleva codeudor solidario
    </label>

    <div x-show="codeudor" x-cloak style="margin-top:0.75rem; padding:0.9rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
        <div style="display:flex; gap:1rem;">
            <div class="form-group-bx" style="flex:2;">
                <label class="form-label-bx">Nombre completo</label>
                <input type="text" name="codeudor_nombre" value="{{ $v('codeudor_nombre') }}" class="form-input-bx">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Cédula</label>
                <input type="text" name="codeudor_cedula" value="{{ $v('codeudor_cedula') }}" class="form-input-bx">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Expedida en</label>
                <input type="text" name="codeudor_expedida_en" value="{{ $v('codeudor_expedida_en') }}" class="form-input-bx">
            </div>
        </div>
        <div style="display:flex; gap:1rem; margin-top:0.75rem;">
            <div class="form-group-bx" style="flex:2;">
                <label class="form-label-bx">Dirección</label>
                <input type="text" name="codeudor_direccion" value="{{ $v('codeudor_direccion') }}" class="form-input-bx">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Ciudad</label>
                <input type="text" name="codeudor_ciudad" value="{{ $v('codeudor_ciudad', 'Cali') }}" class="form-input-bx">
            </div>
        </div>
        <div style="display:flex; gap:1rem; margin-top:0.75rem;">
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Teléfono</label>
                <input type="text" name="codeudor_telefono" value="{{ $v('codeudor_telefono') }}" class="form-input-bx">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Correo</label>
                <input type="email" name="codeudor_correo" value="{{ $v('codeudor_correo') }}" class="form-input-bx">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Ocupación</label>
                <input type="text" name="codeudor_ocupacion" value="{{ $v('codeudor_ocupacion') }}" class="form-input-bx">
            </div>
        </div>
    </div>

    {{-- Garantía --}}
    <h3 class="fin-sub-titulo">4. Garantía prendaria</h3>
    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.82rem; font-weight:600; color:#475569;">
        <input type="hidden" name="tiene_prenda" value="0">
        <input type="checkbox" name="tiene_prenda" value="1" x-model="prenda" style="width:16px; height:16px; cursor:pointer;">
        Respaldar con prenda sobre un vehículo o moto
    </label>

    <div x-show="prenda" x-cloak style="margin-top:0.75rem; padding:0.9rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
        <div style="display:flex; gap:1rem;">
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Placa</label>
                <input type="text" name="prenda_placa" value="{{ $v('prenda_placa') }}" class="form-input-bx" style="text-transform:uppercase;">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Clase</label>
                <input type="text" name="prenda_clase" value="{{ $v('prenda_clase') }}" placeholder="Motocicleta, automóvil..." class="form-input-bx">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Marca</label>
                <input type="text" name="prenda_marca" value="{{ $v('prenda_marca') }}" class="form-input-bx">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Línea</label>
                <input type="text" name="prenda_linea" value="{{ $v('prenda_linea') }}" class="form-input-bx">
            </div>
        </div>
        <div style="display:flex; gap:1rem; margin-top:0.75rem;">
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Modelo (año)</label>
                <input type="text" name="prenda_modelo" value="{{ $v('prenda_modelo') }}" class="form-input-bx">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Color</label>
                <input type="text" name="prenda_color" value="{{ $v('prenda_color') }}" class="form-input-bx">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">No. de motor</label>
                <input type="text" name="prenda_motor" value="{{ $v('prenda_motor') }}" class="form-input-bx">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">No. de chasis / VIN</label>
                <input type="text" name="prenda_chasis" value="{{ $v('prenda_chasis') }}" class="form-input-bx">
            </div>
        </div>
        <div style="display:flex; gap:1rem; margin-top:0.75rem;">
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Licencia de tránsito</label>
                <input type="text" name="prenda_matricula" value="{{ $v('prenda_matricula') }}" class="form-input-bx">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Avalúo declarado</label>
                <input type="number" name="prenda_avaluo" value="{{ $v('prenda_avaluo') }}" class="form-input-bx" min="0">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Propietario (si no es el deudor)</label>
                <input type="text" name="prenda_propietario" value="{{ $v('prenda_propietario') }}" class="form-input-bx">
            </div>
            <div class="form-group-bx" style="flex:1;">
                <label class="form-label-bx">Cédula del propietario</label>
                <input type="text" name="prenda_propietario_cedula" value="{{ $v('prenda_propietario_cedula') }}" class="form-input-bx">
            </div>
        </div>
        <p style="font-size:0.72rem; color:#92400e; background:#fffbeb; border:1px solid #fde68a; padding:0.5rem 0.7rem; border-radius:8px; margin:0.75rem 0 0 0;">
            La prenda solo es oponible a terceros si se inscribe en el Registro de Garantías Mobiliarias (Confecámaras). El contrato queda listo, pero el registro se hace aparte.
        </p>
    </div>

    {{-- Condiciones --}}
    <h3 class="fin-sub-titulo">5. Condiciones del préstamo</h3>
    <div style="display:flex; gap:1rem;">
        <div class="form-group-bx" style="flex:1;">
            <label class="form-label-bx">Monto a prestar *</label>
            <input type="number" name="monto" x-model.number="monto" value="{{ $v('monto') }}" class="form-input-bx" required min="1" autocomplete="off">
        </div>
        <div class="form-group-bx" style="flex:1;">
            <label class="form-label-bx">Tasa mensual (%) *</label>
            <input type="number" step="0.001" name="tasa_interes_mensual" x-model.number="tasa" value="{{ $v('tasa_interes_mensual', '2.1') }}" class="form-input-bx" required min="0">
        </div>
        <div class="form-group-bx" style="flex:1;">
            <label class="form-label-bx">Plazo del capital (meses) *</label>
            <input type="number" name="plazo_meses" value="{{ $v('plazo_meses', 12) }}" class="form-input-bx" required min="1" max="120">
        </div>
    </div>
    <div style="display:flex; gap:1rem; margin-top:0.75rem;">
        <div class="form-group-bx" style="flex:1;">
            <label class="form-label-bx">Fecha prevista de desembolso *</label>
            <input type="date" name="fecha_desembolso" value="{{ $v('fecha_desembolso', now()->toDateString()) }}" class="form-input-bx" required>
        </div>
        <div class="form-group-bx" style="flex:1;">
            <label class="form-label-bx">Días límite de pago (mora)</label>
            <input type="number" name="dias_mora_alerta" value="{{ $v('dias_mora_alerta', 30) }}" class="form-input-bx" required min="1">
        </div>
    </div>

    <label style="display:flex; align-items:center; gap:0.5rem; margin-top:0.9rem; cursor:pointer; font-size:0.82rem; font-weight:600; color:#475569;">
        <input type="hidden" name="tasa_en_blanco" value="0">
        <input type="checkbox" name="tasa_en_blanco" value="1" x-model="enBlanco" style="width:16px; height:16px; cursor:pointer;">
        Imprimir los documentos con la tasa en blanco, para escribirla a mano
    </label>
    <p x-show="enBlanco" x-cloak style="margin:0.4rem 0 0 0; font-size:0.74rem; color:#92400e; background:#fffbeb; border:1px solid #fde68a; padding:0.5rem 0.7rem; border-radius:8px;">
        El porcentaje sale en blanco en los cuatro documentos: contrato, pagaré, carta de instrucciones y recibo.
        Llénalos a mano <strong>antes de firmar</strong> y que las partes rubriquen esa página:
        un contrato firmado con el interés en blanco es fácil de discutir después.
        El sistema sigue liquidando con la tasa que escribiste arriba.
    </p>

    <div style="margin-top:0.9rem; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:0.75rem 0.9rem; font-size:0.78rem; color:#166534;">
        Interés mensual sobre el saldo inicial: <strong x-text="pesos(interes)"></strong> ·
        Tope máximo del pagaré: <strong x-text="pesos(tope)"></strong> (el doble del capital)
    </div>

    <div style="display:flex; gap:1rem; margin-top:0.9rem;">
        <div class="form-group-bx" style="flex:1;">
            <label class="form-label-bx">Descripción / destino</label>
            <input type="text" name="descripcion" value="{{ $v('descripcion') }}" placeholder="Ej: capital de trabajo" class="form-input-bx">
        </div>
    </div>
    <div class="form-group-bx" style="margin-top:0.75rem;">
        <label class="form-label-bx">Observaciones</label>
        <textarea name="observaciones" class="form-input-bx" style="height:70px; resize:none;">{{ $v('observaciones') }}</textarea>
    </div>

    <div class="form-foot-bx" style="margin-top:1.5rem; display:flex; justify-content:flex-end; gap:0.75rem; border-top:1px solid #e2e8f0; padding-top:1.25rem;">
        <a href="{{ route('finanzas.expedientes.index') }}" class="btn-cancelar-bx">Cancelar</a>
        <button type="submit" class="btn-guardar-bx" @disabled($prestamistas->isEmpty())>
            {{ $exp ? '💾 Guardar cambios' : '📄 Crear expediente y generar documentos' }}
        </button>
    </div>
</form>

<style>
    .fin-sub-titulo {
        margin: 1.6rem 0 0.75rem 0;
        font-size: 0.9rem;
        font-weight: 800;
        color: #0f172a;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 0.4rem;
    }
    .fin-sub-titulo:first-of-type { margin-top: 0.25rem; }
</style>
