@extends('layouts.app')

@section('titulo', 'Nueva Cotización')
@section('modulo', 'Cotizaciones')

@section('contenido')
<div style="max-width:1200px;margin:0 auto;" x-data="cotizadorProspecto()">

    @if($errors->any())
        <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.6rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
            <strong>Corrige los errores:</strong>
            <ul style="margin:0.25rem 0 0 1rem;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('admin.cotizaciones.store') }}" method="POST" id="form-cotizacion" @submit.prevent="guardar">
        @csrf
        <input type="hidden" name="resultado_cotizacion" id="resultado_cotizacion">

        <div style="display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start;">

            {{-- COLUMNA IZQUIERDA: Formulario --}}
            <div>
                {{-- Bloque 1: Datos Básicos --}}
                <div style="background:#fff;border-radius:10px;padding:1rem;box-shadow:0 1px 8px rgba(0,0,0,0.06);margin-bottom:0.75rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.5rem;">
                        <div style="height:28px;width:28px;border-radius:6px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.9rem;">👤</div>
                        <div>
                            <h2 style="font-size:0.9rem;font-weight:700;color:#0f172a;margin:0;">1. Datos del Prospecto</h2>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:80px 140px 1fr 150px;gap:0.5rem;margin-bottom:0.5rem;">
                        <div>
                            <label class="lbl-campo">Tipo Doc.</label>
                            <select name="tipo_doc" class="inp-campo">
                                <option value="">Sel...</option>
                                @foreach($lookups['tipos_doc'] as $key => $val)
                                    <option value="{{ $key }}" {{ old('tipo_doc', 'CC') == $key ? 'selected' : '' }}>{{ $key }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="lbl-campo">Documento</label>
                            <input type="text" name="cedula" class="inp-campo" value="{{ old('cedula') }}" placeholder="Ej: 10123456">
                        </div>
                        <div>
                            <label class="lbl-campo">Nombre Completo <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="nombre_completo" class="inp-campo" value="{{ old('nombre_completo') }}" placeholder="Ej: Juan Perez Garcia" required>
                        </div>
                        <div>
                            <label class="lbl-campo">Celular <span style="color:#ef4444;">*</span></label>
                            <div style="display:flex; align-items:center; gap:0.25rem;">
                                <input type="text" name="celular" x-model="celular" class="inp-campo" placeholder="Ej: 3001234567" required>
                                <a :href="'https://wa.me/57' + celular.replace(/\D/g, '')" target="_blank" style="display:inline-flex; align-items:center; justify-content:center; background:#25d366; color:#fff; width:26px; height:24px; border-radius:5px; text-decoration:none;" title="Enviar WhatsApp" x-show="celular && celular.replace(/\D/g, '').length >= 10">
                                    <svg style="width:14px;height:14px;fill:#fff;" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.513 2.262 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.455L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436.002 9.858-4.417 9.86-9.86.002-2.638-1.025-5.118-2.894-6.99C16.57 1.883 14.09 .856 11.456.856 6.02.856 1.6 5.275 1.6 10.718c.002 1.777.472 3.5 1.363 5.023l-.95 3.473 3.565-.935zm11.233-6.52c-.3-.149-1.772-.875-2.046-.975-.276-.1-.476-.15-.676.15-.2.3-.775.975-.95 1.175-.175.2-.35.225-.65.075-.3-.15-1.265-.467-2.41-1.485-.89-.793-1.49-1.773-1.665-2.073-.175-.3-.018-.462.13-.61.135-.133.3-.35.45-.525.15-.175.2-.3.3-.5.1-.2.05-.375-.025-.525-.075-.15-.676-1.625-.926-2.225-.244-.589-.49-.51-.676-.52-.175-.01-.375-.01-.575-.01-.2 0-.525.075-.8 0-.376-.275-.8-1.125-1.125-1.125-.325 0-.6.15-.75.3-.15.15-.6.15-.6.15 0-.525.4-.775.625-.925 1.625-.175.2-.35.225-.65.075-.3-.15-1.772-.875-2.046-.975z"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1.2fr 1fr 1fr 1fr 1fr;gap:0.5rem;margin-bottom:0.5rem;">
                        <div>
                            <label class="lbl-campo">Correo Electrónico</label>
                            <input type="email" name="correo" class="inp-campo" value="{{ old('correo') }}">
                        </div>
                        <div>
                            <label class="lbl-campo">Municipio / Ciudad</label>
                            <input type="text" name="municipio" class="inp-campo" value="{{ old('municipio') }}" placeholder="¿De dónde escribe?">
                        </div>
                        <div>
                            <label class="lbl-campo">Canal de Origen</label>
                            <select name="canal_origen" class="inp-campo">
                                <option value="">Seleccione...</option>
                                @foreach($lookups['canales'] as $key => $val)
                                    <option value="{{ $key }}" {{ old('canal_origen') == $key ? 'selected' : '' }}>{{ $val }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="lbl-campo">Referido / Amigo</label>
                            <input type="text" name="referido" class="inp-campo" value="{{ old('referido') }}">
                        </div>
                        <div>
                            <label class="lbl-campo">Asesor Asignado</label>
                            <select name="asesor_id" class="inp-campo select2">
                                <option value="">Sin Asignar</option>
                                @foreach($lookups['asesores'] as $id => $nombre)
                                    <option value="{{ $id }}" {{ old('asesor_id') == $id ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Bloque 2: Datos de Cotización --}}
                <div style="background:#fff;border-radius:10px;padding:1rem;box-shadow:0 1px 8px rgba(0,0,0,0.06);margin-bottom:0.75rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.5rem;">
                        <div style="height:28px;width:28px;border-radius:6px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.9rem;">🧮</div>
                        <div>
                            <h2 style="font-size:0.9rem;font-weight:700;color:#0f172a;margin:0;">2. Cotizador de Plan</h2>
                        </div>
                    </div>

                    {{-- Fila 1: Configuración de Plan --}}
                    <div style="display:grid;grid-template-columns:1.5fr 0.7fr 1.4fr 1.4fr;gap:0.5rem;margin-bottom:0.5rem;">
                        <div>
                            <label class="lbl-campo">Perfil del prospecto</label>
                            <select name="es_independiente" x-model="esIndependiente" @change="watchEsIndependiente(); recalcular()" class="inp-campo">
                                <option value="1">Trabajador Independiente</option>
                                <option value="0">Empresa / Razón Social</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl-campo">Nivel de Riesgo (ARL)</label>
                            <select name="n_arl" x-model="nivelArl" @change="recalcular" class="inp-campo">
                                <option value="1">Riesgo I (Bajo)</option>
                                <option value="2">Riesgo II</option>
                                <option value="3">Riesgo III</option>
                                <option value="4">Riesgo IV</option>
                                <option value="5">Riesgo V (Alto)</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl-campo">Modalidad <span style="color:#ef4444;">*</span></label>
                            <select name="modalidad_id" x-model="modalidadId" @change="onModalidadChange" class="inp-campo" required>
                                <option value="">-- Seleccione --</option>
                                <template x-for="mod in modalidadesFiltradas" :key="mod.id">
                                    <option :value="String(mod.id)" :data-independiente="mod.independiente" x-text="mod.nombre"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="lbl-campo">Plan <span style="color:#ef4444;">*</span></label>
                            <select name="plan_id" x-model="planId" id="sel_plan" @change="onPlanChange" class="inp-campo" required>
                                <option value="">-- Seleccione Plan --</option>
                                <template x-for="p in planesFiltrados" :key="p.id">
                                    <option :value="String(p.id)" x-text="p.nombre"></option>
                                </template>
                            </select>
                            <div x-show="!modalidadId" style="font-size:0.65rem;color:#94a3b8;margin-top:0.15rem;">Seleccione una modalidad primero.</div>
                        </div>
                    </div>

                    {{-- Fila 2: Valores y Fechas --}}
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:0.5rem;margin-bottom:0.5rem;">
                        <div>
                            <label class="lbl-campo">Salario Base <span style="color:#ef4444;">*</span></label>
                            <input type="text" id="inp_salario" class="inp-campo campo-money" value="{{ $lookups['salarioMinimo'] }}" required>
                            <input type="hidden" name="salario_base" x-model="salario">
                        </div>
                        <div>
                            <label class="lbl-campo">F. Ingreso Estimada</label>
                            <input type="date" name="fecha_ingreso" x-model="fechaIngreso" @change="calcularDias(); recalcular()" class="inp-campo">
                        </div>
                        <div>
                            <label class="lbl-campo">Cobro Afiliación</label>
                            <input type="text" id="inp_costo" class="inp-campo campo-money" value="{{ round((float)old('costo_afiliacion', $lookups['costo_afiliacion_default'])) }}">
                            <input type="hidden" name="costo_afiliacion" x-model="costoAfiliacion">
                        </div>
                        <div>
                            <label class="lbl-campo">Administración</label>
                            <input type="text" id="inp_admon" class="inp-campo campo-money" value="{{ round((float)old('administracion', $lookups['administracion_default'])) }}">
                            <input type="hidden" name="administracion" x-model="administracion">
                        </div>
                    </div>
                </div>

                {{-- Acciones --}}
                <div style="margin-top:1rem;display:flex;justify-content:flex-end;gap:0.5rem;">
                    <a href="{{ route('admin.cotizaciones.index') }}" 
                       style="padding:0.45rem 1.1rem;border:1px solid #cbd5e1;background:#fff;color:#475569;border-radius:6px;text-decoration:none;font-size:0.8rem;font-weight:600;transition:background 0.15s;">
                       Cancelar
                    </a>
                    <button type="submit" 
                       style="padding:0.45rem 1.25rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;border-radius:6px;font-size:0.8rem;font-weight:600;box-shadow:0 3px 10px rgba(37,99,235,0.25);cursor:pointer;display:inline-flex;align-items:center;gap:0.3rem;">
                       💾 Guardar Cotización
                    </button>
                </div>
            </div>

            {{-- COLUMNA DERECHA: Resultados del Cotizador --}}
            <div>
                <div style="background:#1e293b;border-radius:10px;color:#f8fafc;padding:1rem;position:sticky;top:20px;box-shadow:0 10px 25px -5px rgba(0,0,0,0.3);">
                    
                    <h3 style="font-size:0.95rem;font-weight:700;margin:0 0 0.75rem 0;color:#f8fafc;border-bottom:1px solid #334155;padding-bottom:0.5rem;">
                        📊 Resumen de Cotización
                    </h3>

                    <div x-show="cargando" style="text-align:center;padding:1.5rem 0;color:#94a3b8;font-size:0.8rem;">
                        <span style="font-size:1.25rem;display:block;margin-bottom:0.4rem;">⏳</span>
                        Calculando...
                    </div>

                    <div x-show="!planId" style="text-align:center;padding:1.5rem 0;color:#64748b;font-size:0.8rem;">
                        Seleccione modalidad y plan para ver el cálculo.
                    </div>

                    <div x-show="planId && !cargando" style="display:none;">
                        
                        <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:0.4rem;font-size:0.65rem;font-weight:600;color:#94a3b8;margin-bottom:0.4rem;border-bottom:1px solid #334155;padding-bottom:0.25rem;">
                            <div>Concepto</div>
                            <div style="text-align:right;" x-show="diasProporcionales < 30">Proporcional<br><span style="font-size:0.55rem;color:#cbd5e1;" x-text="`(${diasProporcionales} días)`"></span></div>
                            <div style="text-align:right;">Mes Completo</div>
                        </div>

                        {{-- EPS --}}
                        <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:0.4rem;font-size:0.75rem;padding:0.25rem 0;border-bottom:1px solid #334155;">
                            <div>Salud (EPS) <span x-show="pctEps>0" x-text="`(${pctEps}%)`" style="color:#64748b;font-size:0.6rem;"></span></div>
                            <div style="text-align:right;color:#cbd5e1;" x-show="diasProporcionales < 30" x-text="fmt(resultProp.eps)"></div>
                            <div style="text-align:right;" x-text="fmt(resultFull.eps)"></div>
                        </div>
                        
                        {{-- Pensión --}}
                        <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:0.4rem;font-size:0.75rem;padding:0.25rem 0;border-bottom:1px solid #334155;">
                            <div>Pensión (AFP) <span x-show="pctPen>0" x-text="`(${pctPen}%)`" style="color:#64748b;font-size:0.6rem;"></span></div>
                            <div style="text-align:right;color:#cbd5e1;" x-show="diasProporcionales < 30" x-text="fmt(resultProp.pen)"></div>
                            <div style="text-align:right;" x-text="fmt(resultFull.pen)"></div>
                        </div>

                        {{-- ARL --}}
                        <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:0.4rem;font-size:0.75rem;padding:0.25rem 0;border-bottom:1px solid #334155;">
                            <div>Riesgos (ARL) <span x-show="pctArl>0" x-text="`(${pctArl}%)`" style="color:#64748b;font-size:0.6rem;"></span></div>
                            <div style="text-align:right;color:#cbd5e1;" x-show="diasProporcionales < 30" x-text="fmt(resultProp.arl)"></div>
                            <div style="text-align:right;" x-text="fmt(resultFull.arl)"></div>
                        </div>

                        {{-- Caja --}}
                        <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:0.4rem;font-size:0.75rem;padding:0.25rem 0;border-bottom:1px solid #334155;">
                            <div>Caja Comp. <span x-show="pctCaja>0" x-text="`(${pctCaja}%)`" style="color:#64748b;font-size:0.6rem;"></span></div>
                            <div style="text-align:right;color:#cbd5e1;" x-show="diasProporcionales < 30" x-text="fmt(resultProp.caja)"></div>
                            <div style="text-align:right;" x-text="fmt(resultFull.caja)"></div>
                        </div>

                        {{-- Honorarios Fijos --}}
                        <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:0.4rem;font-size:0.75rem;padding:0.25rem 0;border-bottom:1px solid #334155;color:#93c5fd;">
                            <div>Administración</div>
                            <div style="text-align:right;color:#60a5fa;" x-show="diasProporcionales < 30" x-text="fmt(resultFull.admon)"></div>
                            <div style="text-align:right;" x-text="fmt(resultFull.admon)"></div>
                        </div>
                        <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:0.4rem;font-size:0.75rem;padding:0.25rem 0;border-bottom:1px solid #334155;color:#93c5fd;" x-show="resultFull.seguro > 0">
                            <div>Seguro</div>
                            <div style="text-align:right;color:#60a5fa;" x-show="diasProporcionales < 30" x-text="fmt(resultFull.seguro)"></div>
                            <div style="text-align:right;" x-text="fmt(resultFull.seguro)"></div>
                        </div>
                        
                        {{-- Totales --}}
                        <div style="margin-top:1rem;background:#0f172a;border-radius:8px;padding:0.75rem;">
                            
                            {{-- Afiliación Única --}}
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;padding-bottom:0.5rem;border-bottom:1px solid #334155;">
                                <div style="font-size:0.75rem;color:#94a3b8;">Cobro de Afiliación (único)</div>
                                <div style="font-size:1rem;font-weight:700;color:#fcd34d;" x-text="fmt(costoAfiliacion)"></div>
                            </div>

                            {{-- Proporcional (solo si aplica) --}}
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;padding-bottom:0.5rem;border-bottom:1px solid #334155;" x-show="diasProporcionales < 30">
                                <div>
                                    <div style="font-size:0.8rem;color:#f8fafc;font-weight:600;">Primer mes proporcional</div>
                                    <div style="font-size:0.6rem;color:#94a3b8;">Por los <span x-text="diasProporcionales"></span> días del mes actual</div>
                                </div>
                                <div style="font-size:1.1rem;font-weight:700;color:#60a5fa;" x-text="fmt(resultProp.total)"></div>
                            </div>

                            {{-- Mensual Completo --}}
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div>
                                    <div style="font-size:0.85rem;color:#f8fafc;font-weight:700;">Mensual Completo</div>
                                    <div style="font-size:0.6rem;color:#94a3b8;">Meses posteriores (30 días)</div>
                                </div>
                                <div style="font-size:1.25rem;font-weight:800;color:#10b981;" x-text="fmt(resultFull.total)"></div>
                            </div>

                        </div>
                        
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

@php
    $todasModalidades = collect($lookups['modalidades'])->map(function($m) use ($lookups) {
        return [
            'id' => $m->id,
            'nombre' => $m->observacion ?: $m->tipo_modalidad,
            'independiente' => in_array($m->id, $lookups['modalidadesIndependientes']) ? '1' : '0'
        ];
    })->values()->all();
@endphp
<script>
    // Variables globales para el filtrado dinámico de planes
    const MODALIDAD_PLANES = @json($lookups['planesPermitidos'] ?? []);
    const TODOS_LOS_PLANES = @json($lookups['planes'] ?? []);
    const TODAS_MODALIDADES = @json($todasModalidades);
</script>

<style>
    .lbl-campo { display:block;font-size:0.65rem;font-weight:600;color:#475569;margin-bottom:0.05rem;text-transform:uppercase;letter-spacing:0.02em; }
    .inp-campo { width:100%;padding:0.25rem 0.4rem;border:1px solid #cbd5e1;border-radius:5px;font-size:0.78rem;color:#0f172a;transition:border 0.2s; background:#fff; }
    .inp-campo:focus { outline:none;border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,0.15); }
    select.inp-campo { appearance: none; background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E"); background-position: right 0.4rem center; background-repeat: no-repeat; background-size: 1.2em 1.2em; padding-right: 1.5rem; }
</style>
@endsection

@push('scripts')
<script>
    // Alpine Component
    function cotizadorProspecto() {
        return {
            salario: {{ round((float)old('salario_base', $lookups['salarioMinimo'])) }},
            costoAfiliacion: {{ round((float)old('costo_afiliacion', $lookups['costo_afiliacion_default'])) }},
            administracion: {{ round((float)old('administracion', $lookups['administracion_default'])) }},
            esIndependiente: '{{ old('es_independiente', '0') }}',
            nivelArl: '{{ old('n_arl', '1') }}',
            modalidadId: '',
            planId: '',
            fechaIngreso: '{{ old('fecha_ingreso', date('Y-m-d')) }}',
            celular: '{{ old('celular', '') }}',
            diasProporcionales: 30,
            cargando: false,
            
            // Resultados
            pctEps: 0, pctPen: 0, pctArl: 0, pctCaja: 0,
            resultProp: { eps:0, pen:0, arl:0, caja:0, admon:0, seguro:0, iva:0, total:0 },
            resultFull: { eps:0, pen:0, arl:0, caja:0, admon:0, seguro:0, iva:0, total:0 },

            get modalidadesFiltradas() {
                return TODAS_MODALIDADES.filter(m => {
                    if (this.esIndependiente === '1') {
                        return m.independiente === '1';
                    }
                    return m.independiente === '0';
                });
            },

            get planesFiltrados() {
                if (!this.modalidadId) return [];
                const permitidos = (MODALIDAD_PLANES[this.modalidadId] || []).map(String);
                return TODOS_LOS_PLANES.filter(p => permitidos.includes(String(p.id)));
            },

            init() {
                this.calcularDias();
                
                const initialModalidad = '{{ old('modalidad_id', '') }}';
                const initialPlan = '{{ old('plan_id', '') }}';
                
                this.$nextTick(() => {
                    if (initialModalidad !== '') this.modalidadId = initialModalidad;
                    this.$nextTick(() => {
                        if (initialPlan !== '') this.planId = initialPlan;
                    });
                });

                this.$watch('esIndependiente', (v) => {
                    this.modalidadId = '';
                    this.planId = '';
                    this.resultProp = {};
                    this.resultFull = {};
                });
                
                // Money masks
                const t = this;
                document.querySelectorAll('.campo-money').forEach(el => {
                    const strVal = el.value.split('.')[0].replace(/\D/g, ''); // Ignorar decimales si los hay
                    el.value = t.fmt(strVal).replace('$', '');
                    el.addEventListener('focus', () => { el.value = el.value.replace(/\./g, ''); });
                    el.addEventListener('blur', (e) => { 
                        const v = parseInt(el.value.replace(/\D/g, '') || 0);
                        el.value = t.fmt(v).replace('$', '');
                        if(el.id === 'inp_salario') {
                            t.salario = v;
                            t.recalcular();
                        } else if(el.id === 'inp_costo') {
                            t.costoAfiliacion = v;
                        } else if(el.id === 'inp_admon') {
                            t.administracion = v;
                            t.recalcular();
                        }
                    });
                });
            },

            fmt(v) {
                return '$' + Math.round(v||0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            },

            calcularDias() {
                if (this.modalidadId === '12') {
                    // Ingreso Retiro: forzar fecha 26 del mes
                    let dDate = this.fechaIngreso ? new Date(this.fechaIngreso + 'T00:00:00') : new Date();
                    dDate.setDate(26);
                    this.fechaIngreso = dDate.toISOString().split('T')[0];
                }

                if (!this.fechaIngreso) {
                    this.diasProporcionales = 30;
                    return;
                }

                if (this.modalidadId === '-1') {
                    // Estudiante K: siempre 30 días, no proporcional
                    this.diasProporcionales = 30;
                    return;
                }

                const d = new Date(this.fechaIngreso + 'T00:00:00');
                const dia = d.getDate();
                this.diasProporcionales = Math.max(1, 30 - dia + 1);
            },

            onModalidadChange(e) {
                // Si el planId anterior no está en los permitidos, resetear
                const permitidos = (MODALIDAD_PLANES[this.modalidadId] || []).map(String);
                if(this.planId && !permitidos.includes(String(this.planId))) {
                    this.planId = '';
                    this.resultProp = {};
                    this.resultFull = {};
                }
                this.calcularDias();
                this.recalcular();
            },

            onPlanChange() {
                // Fetch tarifas default para actualizar admon/seguro (opcional, como en contratos)
                // Aquí simplificado: delegamos todo al endpoint `admin.contratos.cotizar`
                this.recalcular();
            },

            async recalcular() {
                if(!this.modalidadId || !this.planId || !this.salario) return;
                
                this.cargando = true;

                // 1. Cotizar mes completo (30 días)
                try {
                    const req = {
                        tipo_modalidad_id: this.modalidadId,
                        plan_id: this.planId,
                        salario: this.salario,
                        ibc: this.salario, // Cotizador backend calcula IBC real internamente
                        n_arl: this.nivelArl,
                        administracion: this.administracion,
                        dias: 30
                    };

                    const rFull = await fetch('{{ route('admin.contratos.cotizar') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify(req)
                    });
                    this.resultFull = await rFull.json();

                    this.pctEps = this.resultFull.pctEps || 0;
                    this.pctPen = this.resultFull.pctPen || 0;
                    this.pctArl = this.resultFull.pctArl || 0;
                    this.pctCaja = this.resultFull.pctCaja || 0;

                    // 2. Cotizar proporcional si dias < 30
                    if (this.diasProporcionales < 30) {
                        req.dias = this.diasProporcionales;
                        const rProp = await fetch('{{ route('admin.contratos.cotizar') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify(req)
                        });
                        this.resultProp = await rProp.json();
                    } else {
                        // Si dias == 30, proporcional es igual a Full (se oculta en UI)
                        this.resultProp = {...this.resultFull};
                    }

                    // Reglas Especiales
                    if (this.modalidadId === '15') {
                        // Gestión ARL: no trae planilla, total mensual = afiliación + admin (si lo sumamos), pero el req dice "seria afiliacion siempre".
                        // Igualamos el total al costoAfiliacion + administracion, y limpiamos desglose
                        const totalGestion = parseInt(this.costoAfiliacion || 0) + parseInt(this.administracion || 0);
                        this.resultFull = { eps:0, pen:0, arl:0, caja:0, admon:parseInt(this.administracion||0), seguro:0, iva:0, total:totalGestion };
                        this.resultProp = { ...this.resultFull };
                    }
                    
                    if (this.modalidadId === '12') {
                        // Ingreso Retiro: No existe el "Mes Completo", forzamos resultFull a 0 para ocultarlo y solo valdrá el proporcional de 5 días
                        this.resultFull = { eps:0, pen:0, arl:0, caja:0, admon:0, seguro:0, iva:0, total:0 };
                    }

                } catch(e) {
                    console.error("Error cotizando:", e);
                } finally {
                    this.cargando = false;
                }
            },
            
            watchEsIndependiente() {
                // Si el perfil cambia, revisar si la modalidad actual sigue siendo permitida
                if (this.modalidadId) {
                    const permitida = this.modalidadesFiltradas.find(m => m.id == this.modalidadId);
                    if (!permitida) {
                        this.modalidadId = '';
                        this.planId = '';
                        this.resultProp = {};
                        this.resultFull = {};
                    }
                }
            },

            guardar() {
                // Serializar el resultado en el input hidden
                const json = JSON.stringify({
                    dias_proporcionales: this.diasProporcionales,
                    proporcional: this.resultProp,
                    completo: this.resultFull,
                    costo_afiliacion: this.costoAfiliacion,
                    administracion: this.administracion
                });
                document.getElementById('resultado_cotizacion').value = json;
                document.getElementById('form-cotizacion').submit();
            }
        };
    }
</script>
@endpush
