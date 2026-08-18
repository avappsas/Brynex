{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- PARTIAL: MODAL CREAR / EDITAR INCAPACIDAD                   --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="modalCrear">
<div class="modal" style="max-width:920px">
    <div class="modal-header">
        <div><h3 id="modalCrearTitle">➕ Nueva Incapacidad</h3></div>
        <button class="btn-close-modal" onclick="cerrarModal('modalCrear')">✕</button>
    </div>
    <form id="formCrear" data-store-url="{{ route('admin.incapacidades.store') }}">
    @csrf
    <input type="hidden" name="_method" id="formMethod" value="POST">
    <input type="hidden" name="_id" id="formId">
    <input type="hidden" name="incapacidad_padre_id" id="padreId">
    <input type="hidden" name="razon_social_id" id="razonSocialHidden">
    <div class="modal-body">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.1rem">

            {{-- ── COLUMNA IZQUIERDA ───────────────────────────────────── --}}
            <div>
                <div class="section-title">👤 Afiliado</div>

                {{-- Cédula + Nombre en la misma fila --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;position:relative">
                    <div class="form-group">
                        <label>Cédula *</label>
                        <input type="text" name="cedula_usuario" id="cedulaInput" required
                               placeholder="Buscar cédula..." oninput="buscarCliente(this.value)" autocomplete="off">
                        <div id="clienteSugerencias"
                             style="display:none;background:#fff;border:1px solid #e2e8f0;border-radius:8px;
                                    position:absolute;top:100%;left:0;z-index:300;width:320px;
                                    box-shadow:0 8px 24px rgba(0,0,0,.14)"></div>
                    </div>
                    <div class="form-group">
                        <label>Nombre del Cliente</label>
                        <input type="text" id="nombreCliente" readonly style="background:#f8fafc;color:#374151">
                    </div>
                </div>

                {{-- Contrato (2/3) + Fecha Recibido (1/3) en misma fila --}}
                <div style="display:grid;grid-template-columns:3fr 2fr;gap:.6rem">
                    <div class="form-group">
                        <label>Contrato</label>
                        <select name="contrato_id" id="contratoSelect" onchange="contratoSeleccionado(this)">
                            <option value="">Sin contrato</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Fecha Recibido *</label>
                        <input type="date" name="fecha_recibido" required value="{{ date('Y-m-d') }}">
                    </div>
                </div>



                {{-- Info box contrato vigente (RS + salario) --}}
                <div id="contratoInfoBox"
                     style="display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;
                            padding:.6rem .9rem;font-size:.78rem;color:#065f46;margin-bottom:.85rem">
                    <div style="font-weight:700;margin-bottom:.2rem">✅ Contrato vigente</div>
                    <div id="contratoInfoText" style="color:#047857"></div>
                </div>
                <div id="contratoInfoBoxInactivo"
                     style="display:none;background:#fef3c7;border:1px solid #fde68a;border-radius:10px;
                            padding:.6rem .9rem;font-size:.78rem;color:#92400e;margin-bottom:.85rem">
                    <div style="font-weight:700;margin-bottom:.2rem">⚠️ Contrato inactivo/retirado</div>
                    <div id="contratoInfoTextInactivo" style="color:#78350f"></div>
                </div>

                {{-- Encargado + Quien Remite (select) --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
                    <div class="form-group">
                        <label>Encargado *</label>
                        <select name="quien_recibe_id" id="quienRecibeSelect" required data-auth-id="{{ Auth::id() }}">
                            <option value="">Seleccionar...</option>
                            @foreach($trabajadores as $t)
                            <option value="{{ $t->id }}" {{ $t->id == Auth::id() ? 'selected' : '' }}>{{ $t->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quien Remite</label>
                        <select name="quien_remite" id="quienRemiteSelect">
                            <option value="">Seleccionar...</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Diagnóstico (CIE-10 o descripción)</label>
                    <input type="text" name="diagnostico">
                </div>
                <div class="form-group">
                    <label>Observación</label>
                    <textarea name="observacion" style="min-height:55px"></textarea>
                </div>
            </div>

            {{-- ── COLUMNA DERECHA ─────────────────────────────────────── --}}
            <div>
                <div class="section-title">🏥 Incapacidad</div>

                <div class="form-group">
                    <label>Tipo de Incapacidad *</label>
                    <select name="tipo_incapacidad" id="tipoIncapacidadSelect" required
                            onchange="tipoIncapacidadCambiado(this.value)">
                        <option value="">Seleccionar...</option>
                        @foreach(\App\Models\Incapacidad::TIPOS_INCAPACIDAD as $k=>$v)
                        <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Días + Fecha Inicio + Fecha Fin en una fila --}}
                <div style="display:grid;grid-template-columns:70px 1fr 1fr;gap:.6rem">
                    <div class="form-group">
                        <label>Días *</label>
                        <input type="number" name="dias_incapacidad" min="1" required oninput="calcularFechaFin()">
                    </div>
                    <div class="form-group">
                        <label>Fecha Inicio *</label>
                        <input type="date" name="fecha_inicio" id="fechaInicioInput" required oninput="calcularFechaFin()">
                    </div>
                    <div class="form-group">
                        <label>Fecha Terminación</label>
                        <input type="date" name="fecha_terminacion" id="fechaFinInput">
                    </div>
                </div>

                {{-- Aviso de posible prórroga: aparece solo si la persona ya tiene
                     una incapacidad cuyo período toca al que se está registrando.
                     Quien digita tiene el soporte a la vista y es quien decide. --}}
                <div id="avisoProrroga" style="display:none;margin-bottom:.85rem"></div>

                {{-- Tipo Entidad + Entidad en una fila --}}
                <div class="section-title" style="margin-top:.7rem">🏦 Entidad Responsable</div>
                
                {{-- Inputs informativos de Razón Social y NIT --}}
                <div style="display:grid;grid-template-columns:3fr 2fr;gap:.6rem;margin-bottom:.65rem">
                    <div class="form-group">
                        <label>Razón Social</label>
                        <input type="text" id="razonSocialInput" readonly style="background:#f8fafc;color:#475569;font-weight:500" placeholder="Sin contrato seleccionado">
                    </div>
                    <div class="form-group">
                        <label>NIT Razón Social</label>
                        <input type="text" id="razonSocialNitInput" readonly style="background:#f8fafc;color:#475569;font-weight:500" placeholder="Sin registrar">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
                    <div class="form-group">
                        <label>Tipo Entidad *</label>
                        <select name="tipo_entidad" id="tipoEntidadSelect" required
                                onchange="actualizarListaEntidades(this.value)">
                            <option value="">Seleccionar...</option>
                            @foreach(\App\Models\Incapacidad::TIPOS_ENTIDAD as $k=>$v)
                            <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Entidad Responsable</label>
                        <select name="entidad_responsable_id" id="entidadSelect">
                            <option value="">Seleccionar tipo...</option>
                        </select>
                    </div>
                </div>

                <div class="section-title" style="margin-top:.7rem">📋 Información Adicional</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
                    <div class="form-group">
                        <label>Número Radicado</label>
                        <input type="text" name="numero_radicado" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label>Fecha Radicado</label>
                        <input type="date" name="fecha_radicado">
                    </div>
                </div>
            </div>

        </div>{{-- /grid --}}
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalCrear')">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarIncapacidad()">💾 Guardar y Subir Documentos</button>
    </div>
    </form>
</div>
</div>
