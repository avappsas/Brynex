{{--
    Campos de la ficha de empresa, compartidos por crear y editar.

    Viven en un parcial y no duplicados en cada vista a propósito: son dos
    formularios de lo mismo, y cuando se les agrega un campo a uno y al otro no,
    la empresa creada entra incompleta y hay que editarla enseguida. Recibe
    `$empresa` —un modelo real al editar, uno vacío al crear— y `$departamentos`.
--}}
    @php
        $tipoDoc   = old('tipo_documento', $empresa->tipo_documento ?? 'NIT');
        $deptoAct  = old('departamento_id', $empresa->departamento_id);
        $muniAct   = old('municipio_id', $empresa->municipio_id);
    @endphp
    <div class="card" x-data="fichaEmpresa('{{ $tipoDoc }}')">
        <div class="card-title">🏢 Datos Básicos</div>
        <div class="form-row">
            <div>
                <label class="flb">Tipo de documento *</label>
                <select class="finp" name="tipo_documento" x-model="tipo" required>
                    @foreach(\App\Models\Empresa::TIPOS_DOC as $cod => $etiqueta)
                        <option value="{{ $cod }}" @selected($tipoDoc === $cod)>{{ $cod }} — {{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="flb">Número de documento</label>
                <div style="display:flex;gap:.4rem;">
                    <input class="finp" type="number" name="nit" x-model="documento"
                           value="{{ old('nit', $empresa->nit) }}" style="flex:1">
                    <button type="button" class="finp" style="width:auto;white-space:nowrap;cursor:pointer;background:#eff6ff;border-color:#93c5fd;color:#1d4ed8"
                            x-show="esPersona" @click="consultar()" :disabled="cargando"
                            x-text="cargando ? 'Consultando…' : '🔎 Traer nombre'"></button>
                </div>
                <div x-show="aviso" x-text="aviso" x-cloak
                     style="font-size:.72rem;margin-top:.25rem;" :style="avisoOk ? 'color:#059669' : 'color:#dc2626'"></div>
            </div>

            {{-- Persona natural: el nombre del documento y el del negocio son cosas distintas.
                 A la DIAN va el del documento; en Brynex se sigue viendo el del negocio. --}}
            <div class="form-full" x-show="esPersona" x-cloak>
                <label class="flb">Nombre según el documento</label>
                <input class="finp" type="text" name="nombre_legal" x-model="nombreLegal"
                       value="{{ old('nombre_legal', $empresa->nombre_legal) }}"
                       placeholder="Lo trae la consulta por cédula">
                <div style="font-size:.72rem;color:#94a3b8;margin-top:.2rem">
                    Es el que viaja a la factura electrónica. Debe coincidir con el RUT de esa cédula.
                </div>
            </div>

            <div class="form-full">
                <label class="flb" x-text="esPersona ? 'Nombre del negocio *' : 'Razón social *'">Nombre empresa *</label>
                <input class="finp" type="text" name="empresa" value="{{ old('empresa', $empresa->empresa) }}" required>
                <div style="font-size:.72rem;color:#94a3b8;margin-top:.2rem" x-show="esPersona" x-cloak>
                    Es como reconoces al cliente en Brynex: listados, cuenta de cobro, facturación.
                </div>
            </div>

            <div>
                <label class="flb">Correo</label>
                <input class="finp" type="email" name="correo" value="{{ old('correo', $empresa->correo) }}">
            </div>
            <div>
                <label class="flb">Teléfono</label>
                <input class="finp" type="text" name="telefono" value="{{ old('telefono', $empresa->telefono) }}">
            </div>
            <div>
                <label class="flb">Celular de la empresa</label>
                <input class="finp" type="text" name="celular" value="{{ old('celular', $empresa->celular) }}">
            </div>
            {{-- Interruptor de facturación electrónica. Apagado, la factura sale
                 a consumidor final: es para las empresas cuyo documento no es
                 suyo —un establecimiento a nombre del dueño, o una cédula mal
                 digitada— donde facturar a ese número sería emitirle a un
                 tercero. --}}
            <div class="form-full">
                <label class="flb">Facturación electrónica</label>
                <label style="display:flex;gap:.5rem;align-items:center;font-size:.85rem;color:#334155;padding:.45rem 0;">
                    <input type="checkbox" name="factura_electronica" value="1"
                           @checked(old('factura_electronica', $empresa->factura_electronica ?? true))>
                    <span>Facturar a nombre de esta empresa</span>
                </label>
                <div style="font-size:.72rem;color:#94a3b8">
                    Si lo apagas, sus facturas salen a <strong>consumidor final</strong>. Úsalo cuando
                    el documento no sea de la empresa: un negocio a nombre del dueño, o una cédula
                    que resultó ser de otra persona.
                </div>
            </div>

            <div>
                <label class="flb">IVA</label>
                <div class="iva-group">
                    <label>
                        <input type="radio" name="iva" value="SI" {{ strtoupper((string) old('iva', $empresa->iva ?? '')) === 'SI' ? 'checked' : '' }}>
                        <span>Sí</span>
                    </label>
                    <label>
                        <input type="radio" name="iva" value="NO" {{ strtoupper((string) old('iva', $empresa->iva ?? '')) !== 'SI' ? 'checked' : '' }}>
                        <span>No</span>
                    </label>
                </div>
            </div>

            <div class="form-full">
                <label class="flb">Dirección</label>
                <input class="finp" type="text" name="direccion" value="{{ old('direccion', $empresa->direccion) }}">
            </div>
            <div>
                <label class="flb">Departamento</label>
                <select class="finp" name="departamento_id" id="selDeptoEmp">
                    <option value="">— Sin departamento —</option>
                    @foreach($departamentos as $d)
                        <option value="{{ $d->id }}" @selected((int)$deptoAct === (int)$d->id)>{{ $d->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="flb">Ciudad</label>
                <select class="finp" name="municipio_id" id="selCiudadEmp" data-actual="{{ $muniAct }}">
                    <option value="">— Elige primero el departamento —</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Contacto encargado --}}
    <div class="card">
        <div class="card-title">📇 Encargado</div>
        <div style="font-size:.78rem;color:#64748b;margin-bottom:.7rem">
            Si la empresa tiene a alguien encargado de la seguridad social, las cuentas de cobro
            y las planillas se le mandan a su celular. Si lo dejas vacío, van al de la empresa.
        </div>
        <div class="form-row">
            <div>
                <label class="flb">Nombre del contacto</label>
                <input class="finp" type="text" name="contacto" value="{{ old('contacto', $empresa->contacto) }}">
            </div>
            <div>
                <label class="flb">Celular del contacto</label>
                <input class="finp" type="text" name="contacto_celular" value="{{ old('contacto_celular', $empresa->contacto_celular) }}">
            </div>
        </div>
    </div>

@push('scripts')
<script>
// Trae el nombre del registro oficial por documento. Es la misma consulta del
// modal de cliente nuevo: no exige autorización sobre el aportante, así que
// sirve para cualquier documento.
function fichaEmpresa(tipoInicial) {
    return {
        tipo: tipoInicial,
        documento: '{{ old('nit', $empresa->nit) }}',
        nombreLegal: @json(old('nombre_legal', $empresa->nombre_legal)),
        cargando: false,
        aviso: '',
        avisoOk: false,
        get esPersona() { return this.tipo && this.tipo !== 'NIT'; },
        consultar() {
            if (!this.documento) { this.aviso = 'Escribe primero el número.'; this.avisoOk = false; return; }
            this.cargando = true; this.aviso = '';
            fetch('{{ route('admin.clientes.buscar_cedula') }}?cedula=' + encodeURIComponent(this.documento) + '&tipo_doc=' + this.tipo)
                .then(r => r.json())
                .then(d => {
                    const o = (d && d.oficial) ? d.oficial : d;
                    const partes = [o?.primer_nombre, o?.segundo_nombre, o?.primer_apellido, o?.segundo_apellido]
                        .filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
                    if (partes) {
                        this.nombreLegal = partes;
                        this.aviso = '✓ ' + partes;
                        this.avisoOk = true;
                    } else {
                        this.aviso = 'Ese documento no figura en el registro oficial.';
                        this.avisoOk = false;
                    }
                })
                .catch(() => { this.aviso = 'No se pudo consultar el registro.'; this.avisoOk = false; })
                .finally(() => this.cargando = false);
        },
    };
}

// Ciudades dependientes del departamento, igual que en la ficha del cliente.
(function () {
    const depto  = document.getElementById('selDeptoEmp');
    const ciudad = document.getElementById('selCiudadEmp');
    if (!depto || !ciudad) return;

    function cargar(seleccionar) {
        const id = depto.value;
        ciudad.innerHTML = '<option value="">— Sin ciudad —</option>';
        if (!id) return;
        fetch('{{ url('admin/api/departamentos') }}/' + id + '/ciudades')
            .then(r => r.json())
            .then(list => {
                list.forEach(c => {
                    const o = document.createElement('option');
                    o.value = c.id; o.textContent = c.nombre;
                    if (String(seleccionar) === String(c.id)) o.selected = true;
                    ciudad.appendChild(o);
                });
            });
    }

    depto.addEventListener('change', () => cargar(null));
    if (depto.value) cargar(ciudad.dataset.actual);
})();
</script>
@endpush
