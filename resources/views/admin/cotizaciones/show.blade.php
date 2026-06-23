@extends('layouts.app')

@section('titulo', 'Detalle Prospecto')
@section('modulo', 'Cotizaciones')

@section('contenido')
<div style="max-width:1200px;margin:0 auto;" x-data="cotizadorProspecto()">

    @if(session('success'))
        <div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;padding:0.75rem 1rem;margin-bottom:1.25rem;font-size:0.85rem;">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('info'))
        <div style="background:#e0f2fe;border:1px solid #bae6fd;border-radius:8px;color:#0369a1;padding:0.75rem 1rem;margin-bottom:1.25rem;font-size:0.85rem;">
            ℹ️ {{ session('info') }}
        </div>
    @endif
    @if($errors->any())
        <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.6rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
            <strong>Corrige los errores:</strong>
            <ul style="margin:0.25rem 0 0 1rem;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Top Bar Actions --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
        <div>
            @php
                $estadoClass = match($prospecto->estado) {
                    'interesado' => 'badge-interesado',
                    'sin_respuesta' => 'badge-sin_respuesta',
                    'pendiente_resp' => 'badge-pendiente_resp',
                    'no_interesado' => 'badge-no_interesado',
                    'convertido' => 'badge-convertido',
                    default => 'badge-sin_respuesta'
                };
                $nombreEstado = $lookups['estados'][$prospecto->estado] ?? $prospecto->estado;
            @endphp
            <span class="badge-estado {{ $estadoClass }}">{{ $nombreEstado }}</span>
        </div>
        <div style="display:flex;gap:0.75rem;">
            <a href="{{ route('admin.cotizaciones.index') }}" 
               style="padding:0.5rem 1rem;background:#fff;border:1px solid #cbd5e1;color:#475569;border-radius:8px;text-decoration:none;font-size:0.8rem;font-weight:600;">
               ⬅️ Volver
            </a>
            @if($prospecto->estado !== 'convertido')
            <form action="{{ route('admin.cotizaciones.convertir', $prospecto->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('¿Convertir este prospecto a cliente real?');">
                @csrf
                <button type="submit" style="padding:0.5rem 1rem;background:#10b981;color:#fff;border:none;border-radius:8px;font-size:0.8rem;font-weight:600;cursor:pointer;">
                    ✨ Convertir a Cliente
                </button>
            </form>
            @endif
        </div>
    </div>

    <form action="{{ route('admin.cotizaciones.update', $prospecto->id) }}" method="POST" id="form-cotizacion" @submit.prevent="guardar">
        @csrf
        @method('PUT')
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
                                    <option value="{{ $key }}" {{ old('tipo_doc', $prospecto->tipo_doc) == $key ? 'selected' : '' }}>{{ $key }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="lbl-campo">Documento</label>
                            <input type="text" name="cedula" class="inp-campo" value="{{ old('cedula', $prospecto->cedula) }}">
                        </div>
                        <div>
                            <label class="lbl-campo">Nombre Completo <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="nombre_completo" class="inp-campo" value="{{ old('nombre_completo', $prospecto->nombre_completo) }}" required>
                        </div>
                        <div>
                            <label class="lbl-campo">Celular <span style="color:#ef4444;">*</span></label>
                            <div style="display:flex; align-items:center; gap:0.25rem;">
                                <input type="text" name="celular" x-model="celular" class="inp-campo" required>
                                <a :href="'https://wa.me/57' + celular.replace(/\D/g, '')" target="_blank" style="display:inline-flex; align-items:center; justify-content:center; background:#25d366; color:#fff; width:26px; height:24px; border-radius:5px; text-decoration:none;" title="Enviar WhatsApp" x-show="celular && celular.replace(/\D/g, '').length >= 10">
                                    <svg style="width:14px;height:14px;fill:#fff;" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.513 2.262 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.455L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436.002 9.858-4.417 9.86-9.86.002-2.638-1.025-5.118-2.894-6.99C16.57 1.883 14.09 .856 11.456.856 6.02.856 1.6 5.275 1.6 10.718c.002 1.777.472 3.5 1.363 5.023l-.95 3.473 3.565-.935zm11.233-6.52c-.3-.149-1.772-.875-2.046-.975-.276-.1-.476-.15-.676.15-.2.3-.775.975-.95 1.175-.175.2-.35.225-.65.075-.3-.15-1.265-.467-2.41-1.485-.89-.793-1.49-1.773-1.665-2.073-.175-.3-.018-.462.13-.61.135-.133.3-.35.45-.525.15-.175.2-.3.3-.5.1-.2.05-.375-.025-.525-.075-.15-.676-1.625-.926-2.225-.244-.589-.49-.51-.676-.52-.175-.01-.375-.01-.575-.01-.2 0-.525.075-.8 0-.376-.275-.8-1.125-1.125-1.125-.325 0-.6.15-.75.3-.15.15-.6.15-.6.15 0-.525.4-.775.625-.925 1.625-.175.2-.35.225-.65.075-.3-.15-1.772-.875-2.046-.975z"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1.2fr 1fr 1fr 1fr 1fr;gap:0.5rem;margin-bottom:0.5rem;">
                        <div>
                            <label class="lbl-campo">Correo Electrónico</label>
                            <input type="email" name="correo" class="inp-campo" value="{{ old('correo', $prospecto->correo) }}">
                        </div>
                        <div>
                            <label class="lbl-campo">Municipio / Ciudad</label>
                            <input type="text" name="municipio" class="inp-campo" value="{{ old('municipio', $prospecto->municipio) }}">
                        </div>
                        <div>
                            <label class="lbl-campo">Canal de Origen</label>
                            <select name="canal_origen" class="inp-campo">
                                <option value="">Seleccione...</option>
                                @foreach($lookups['canales'] as $key => $val)
                                    <option value="{{ $key }}" {{ old('canal_origen', $prospecto->canal_origen) == $key ? 'selected' : '' }}>{{ $val }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="lbl-campo">Referido / Amigo</label>
                            <input type="text" name="referido" class="inp-campo" value="{{ old('referido', $prospecto->referido) }}">
                        </div>
                        <div>
                            <label class="lbl-campo">Asesor Asignado</label>
                            <select name="asesor_id" class="inp-campo select2">
                                <option value="">Sin Asignar</option>
                                @foreach($lookups['asesores'] as $id => $nombre)
                                    <option value="{{ $id }}" {{ old('asesor_id', $prospecto->asesor_id) == $id ? 'selected' : '' }}>{{ $nombre }}</option>
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
                        </div>
                    </div>

                    {{-- Fila 2: Valores y Fechas --}}
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:0.5rem;margin-bottom:0.5rem;">
                        <div>
                            <label class="lbl-campo">Salario Base <span style="color:#ef4444;">*</span></label>
                            <input type="text" id="inp_salario" class="inp-campo campo-money" value="{{ old('salario_base', $prospecto->salario_base ?? $lookups['salarioMinimo']) }}" required>
                            <input type="hidden" name="salario_base" x-model="salario">
                        </div>
                        <div>
                            <label class="lbl-campo">F. Ingreso Estimada</label>
                            <input type="date" name="fecha_ingreso" x-model="fechaIngreso" @change="calcularDias(); recalcular()" class="inp-campo">
                        </div>
                        <div>
                            <label class="lbl-campo">Cobro Afiliación</label>
                            <input type="text" id="inp_costo" class="inp-campo campo-money" value="{{ round((float)old('costo_afiliacion', $prospecto->costo_afiliacion ?? $lookups['costo_afiliacion_default'])) }}">
                            <input type="hidden" name="costo_afiliacion" x-model="costoAfiliacion">
                        </div>
                        <div>
                            <label class="lbl-campo">Administración</label>
                            <input type="text" id="inp_admon" class="inp-campo campo-money" value="{{ round((float)old('administracion', $prospecto->administracion ?? 0)) }}">
                            <input type="hidden" name="administracion" x-model="administracion">
                        </div>
                    </div>
                </div>

                {{-- Acciones --}}
                <div style="margin-bottom:1rem;display:flex;justify-content:flex-end;gap:0.5rem;">
                    <button type="submit" 
                       style="padding:0.45rem 1.25rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;border-radius:6px;font-size:0.8rem;font-weight:600;box-shadow:0 3px 10px rgba(37,99,235,0.25);cursor:pointer;display:inline-flex;align-items:center;gap:0.3rem;">
                       💾 Actualizar Prospecto
                    </button>
                </div>            </div>

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

        {{-- Gestiones (Ancho Completo) --}}
        <div style="background:#fff;border-radius:10px;padding:1rem;box-shadow:0 1px 8px rgba(0,0,0,0.06);margin-top:0.75rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.5rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <div style="height:26px;width:26px;border-radius:6px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.85rem;">📞</div>
                    <h3 style="font-size:0.85rem;font-weight:700;color:#0f172a;margin:0;">Historial de Gestiones</h3>
                </div>
                <button type="button" onclick="document.getElementById('form-gestion').style.display='block'" 
                        style="padding:0.25rem 0.6rem;background:#f8fafc;border:1px solid #cbd5e1;color:#475569;border-radius:5px;font-size:0.7rem;font-weight:600;cursor:pointer;">
                    + Agregar Gestión
                </button>
            </div>

            {{-- Formulario nueva gestión (oculto por defecto) --}}
            <div id="form-gestion" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:0.75rem;margin-bottom:0.75rem;">
                <form action="{{ route('admin.cotizaciones.gestion', $prospecto->id) }}" method="POST">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.5rem;">
                        <div>
                            <label class="lbl-campo">Tipo de Contacto</label>
                            <select name="tipo_gestion" class="inp-campo" required>
                                <option value="Llamada">Llamada Telefónica</option>
                                <option value="WhatsApp">Mensaje WhatsApp</option>
                                <option value="Correo">Correo Electrónico</option>
                                <option value="Reunión">Reunión / Visita</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl-campo">Resultado de la Gestión</label>
                            <select name="resultado" class="inp-campo" required>
                                @foreach($lookups['estados'] as $key => $val)
                                    <option value="{{ $key }}" {{ $prospecto->estado == $key ? 'selected' : '' }}>{{ $val }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div style="margin-bottom:0.5rem;">
                        <label class="lbl-campo">Detalles de la conversación</label>
                        <textarea name="descripcion" class="inp-campo" rows="2" required placeholder="¿Qué dijo el cliente?" style="resize:vertical;"></textarea>
                    </div>
                    <div style="margin-bottom:0.5rem;">
                        <label class="lbl-campo">Agendar próxima llamada (Opcional)</label>
                        <input type="date" name="proxima_llamada" class="inp-campo">
                    </div>
                    <div style="text-align:right;">
                        <button type="button" onclick="document.getElementById('form-gestion').style.display='none'" style="padding:0.25rem 0.6rem;background:none;border:none;color:#64748b;font-size:0.75rem;cursor:pointer;margin-right:0.4rem;">Cancelar</button>
                        <button type="submit" style="padding:0.25rem 0.8rem;background:#3b82f6;color:#fff;border:none;border-radius:5px;font-size:0.75rem;font-weight:600;cursor:pointer;">Guardar Gestión</button>
                    </div>
                </form>
            </div>

            {{-- Lista de Gestiones --}}
            @if($prospecto->gestiones->isEmpty())
                <div style="text-align:center;padding:1.5rem 0;color:#94a3b8;font-size:0.8rem;">
                    No hay gestiones registradas para este prospecto.
                </div>
            @else
                <div x-data="{ verTodas: false }" style="display:flex;flex-direction:column;gap:0.4rem;">
                    @foreach($prospecto->gestiones as $g)
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:0.5rem;display:flex;justify-content:space-between;align-items:center;font-size:0.78rem;"
                         x-show="verTodas || {{ $loop->index }} < 3">
                        <div style="display:flex;align-items:center;gap:0.5rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:75%;">
                            @php
                                $icon = match($g->tipo_gestion) {
                                    'Llamada' => '📞',
                                    'WhatsApp' => '💬',
                                    'Correo' => '✉️',
                                    'Reunión' => '🤝',
                                    default => '📝'
                                };
                            @endphp
                            <span title="{{ $g->tipo_gestion }}">{{ $icon }}</span>
                            <span style="font-weight:600;color:#334155;white-space:nowrap;">{{ $g->usuario->nombre ?? 'Sistema' }}:</span>
                            <span style="color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $g->descripcion }}">{{ $g->descripcion }}</span>
                            @if($g->proxima_llamada)
                                <span style="font-size:0.65rem;color:#d97706;background:#fef3c7;padding:0.05rem 0.25rem;border-radius:3px;white-space:nowrap;" title="Próximo contacto: {{ $g->proxima_llamada->format('d M Y') }}">🗓️ {{ $g->proxima_llamada->format('d M') }}</span>
                            @endif
                        </div>
                        <div style="font-size:0.7rem;color:#94a3b8;margin-left:0.5rem;white-space:nowrap;">
                            {{ $g->created_at->format('d M h:i A') }}
                        </div>
                    </div>
                    @endforeach
                    
                    @if($prospecto->gestiones->count() > 3)
                        <div style="text-align:center;margin-top:0.25rem;">
                            <button type="button" @click="verTodas = !verTodas" 
                                    style="background:none;border:none;color:#3b82f6;font-size:0.72rem;font-weight:600;cursor:pointer;padding:0.15rem;"
                                    x-text="verTodas ? 'Ver menos gestiones ⬆️' : 'Ver más gestiones ({{ $prospecto->gestiones->count() - 3 }}) ⬇️'">
                            </button>
                        </div>
                    @endif
                </div>
            @endif
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
    const MODALIDAD_PLANES = @json($lookups['planesPermitidos'] ?? []);
    const TODOS_LOS_PLANES = @json($lookups['planes'] ?? []);
    const STORED_RESULTADO = @json($prospecto->resultado_cotizacion);
    const TODAS_MODALIDADES = @json($todasModalidades);
</script>

<style>
    .lbl-campo { display:block;font-size:0.65rem;font-weight:600;color:#475569;margin-bottom:0.05rem;text-transform:uppercase;letter-spacing:0.02em; }
    .inp-campo { width:100%;padding:0.25rem 0.4rem;border:1px solid #cbd5e1;border-radius:5px;font-size:0.78rem;color:#0f172a;transition:border 0.2s; background:#fff; }
    .inp-campo:focus { outline:none;border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,0.15); }
    select.inp-campo { appearance: none; background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E"); background-position: right 0.4rem center; background-repeat: no-repeat; background-size: 1.2em 1.2em; padding-right: 1.5rem; }
    
    .badge-estado { display:inline-block;padding:0.35rem 0.75rem;border-radius:20px;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.02em; }
    .badge-interesado { background:#e0f2fe;color:#0369a1; }
    .badge-sin_respuesta { background:#f1f5f9;color:#475569; }
    .badge-pendiente_resp { background:#fef3c7;color:#b45309; }
    .badge-no_interesado { background:#fee2e2;color:#b91c1c; }
    .badge-convertido { background:#dcfce7;color:#15803d; }
</style>
@endsection

@push('scripts')
<script>
    function cotizadorProspecto() {
        return {
            salario: {{ old('salario_base', $prospecto->salario_base ?? $lookups['salarioMinimo']) }},
            costoAfiliacion: {{ round((float)old('costo_afiliacion', $prospecto->costo_afiliacion ?? $lookups['costo_afiliacion_default'])) }},
            administracion: {{ round((float)old('administracion', $prospecto->administracion ?? $lookups['administracion_default'])) }},
            esIndependiente: '{{ old('es_independiente', $prospecto->es_independiente ? '1' : '0') }}',
            nivelArl: '{{ old('n_arl', $prospecto->n_arl ?? '1') }}',
            modalidadId: '',
            planId: '',
            fechaIngreso: '{{ old('fecha_ingreso', $prospecto->fecha_ingreso ? $prospecto->fecha_ingreso->format('Y-m-d') : '') }}',
            celular: '{{ old('celular', $prospecto->celular ?? '') }}',
            diasProporcionales: 30,
            cargando: false,
            yaInit: false,
            
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
                
                // Asegurar que las opciones se han renderizado antes de setear el modelo
                const initialModalidad = '{{ old('modalidad_id', $prospecto->modalidad_id ?? '') }}';
                const initialPlan = '{{ old('plan_id', $prospecto->plan_id ?? '') }}';
                
                this.$nextTick(() => {
                    if (initialModalidad !== '') this.modalidadId = initialModalidad;
                    this.$nextTick(() => {
                        if (initialPlan !== '') this.planId = initialPlan;
                    });
                });

                // Cargar datos cacheados
                if (STORED_RESULTADO) {
                    this.diasProporcionales = STORED_RESULTADO.dias_proporcionales || 30;
                    this.resultProp = STORED_RESULTADO.proporcional || {};
                    this.resultFull = STORED_RESULTADO.completo || {};
                    // Prevenir recálculo inicial para evitar doble carga
                    this.yaInit = true;
                } else {
                    this.$nextTick(() => {
                        this.$nextTick(() => {
                            if(this.modalidadId && this.planId) this.recalcular();
                        });
                    });
                }

                this.$watch('esIndependiente', (v) => {
                    if(!this.yaInit) return;
                    this.modalidadId = '';
                    this.planId = '';
                    this.resultProp = {};
                    this.resultFull = {};
                });

                setTimeout(() => this.yaInit = true, 500);
                
                const t = this;
                document.querySelectorAll('.campo-money').forEach(el => {
                    const strVal = el.value.split('.')[0].replace(/\D/g, '');
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
                if(!this.yaInit) return;
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
                if(!this.yaInit) return;
                this.recalcular();
            },

            async recalcular() {
                if(!this.yaInit || !this.modalidadId || !this.planId || !this.salario) return;
                
                this.cargando = true;

                try {
                    const req = {
                        tipo_modalidad_id: this.modalidadId,
                        plan_id: this.planId,
                        salario: this.salario,
                        ibc: this.salario,
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

                    if (this.diasProporcionales < 30) {
                        req.dias = this.diasProporcionales;
                        const rProp = await fetch('{{ route('admin.contratos.cotizar') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify(req)
                        });
                        this.resultProp = await rProp.json();
                    } else {
                        this.resultProp = {...this.resultFull};
                    }

                    // Reglas Especiales
                    if (this.modalidadId === '15') {
                        const totalGestion = parseInt(this.costoAfiliacion || 0) + parseInt(this.administracion || 0);
                        this.resultFull = { eps:0, pen:0, arl:0, caja:0, admon:parseInt(this.administracion||0), seguro:0, iva:0, total:totalGestion };
                        this.resultProp = { ...this.resultFull };
                    }
                    
                    if (this.modalidadId === '12') {
                        this.resultFull = { eps:0, pen:0, arl:0, caja:0, admon:0, seguro:0, iva:0, total:0 };
                    }

                } catch(e) {
                    console.error("Error cotizando:", e);
                } finally {
                    this.cargando = false;
                }
            },
            
            watchEsIndependiente() {
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
