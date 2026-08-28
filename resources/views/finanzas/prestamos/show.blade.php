@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Ficha del Préstamo')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="{ openAbono: false, openAnexar: false, openEditarMov: false, openEditarPago: false, openCastigar: false, openReactivar: false, openNoTelefono: false, movEditar: { id: null, fecha: '', monto: 0, observacion: '', soporte_path: '' }, pagoEditar: { id: null, fecha: '', monto: 0, observacion: '', soporte_path: '', interes: 0, abInt: 0, abCap: 0 } }">

    @component('finanzas.partials._header_banner', [
        'titulo' => '👤 Ficha: ' . $prestamo->nombre_deudor,
        'subtitulo' => 'Monitoreo completo de amortización, cobros e intereses acumulados.',
        'breadcrumb' => [
            'Finanzas Personales' => route('finanzas.dashboard'),
            'Préstamos' => route('finanzas.prestamos.index'),
            $prestamo->nombre_deudor => null
        ]
    ])
        @slot('opciones')
            <a href="{{ route('finanzas.prestamos.index') }}" class="btn-fin-link success" style="background:#f1f5f9; border:1px solid #cbd5e1; color:#475569;">⬅️ Volver a Préstamos</a>
            <a href="{{ route('finanzas.prestamos.edit', $prestamo->id) }}" class="btn-fin-link primary">✏️ Editar Ficha</a>
        @endslot
    @endcomponent

    {{-- Banner de Préstamo Inactivo --}}
    @if($prestamo->estado === 'castigado')
        <div style="background:linear-gradient(135deg, rgba(124,45,18,0.08), rgba(194,65,12,0.06)); border:1px solid rgba(194,65,12,0.25); border-left: 4px solid #ea580c; border-radius:12px; padding:1rem 1.25rem; margin-bottom:1rem; display:flex; align-items:flex-start; gap:0.75rem;">
            <span style="font-size:1.4rem; line-height:1;">⛔</span>
            <div>
                <strong style="font-size:0.88rem; color:#7c2d12; display:block;">Préstamo Inactivado / Castigado</strong>
                <p style="font-size:0.78rem; color:#9a3412; margin:0.2rem 0 0;">Este préstamo fue marcado como inactivo. Los intereses están congelados (tasa 0%) y no generará alertas de cobro automáticas. El saldo pendiente queda registrado para efectos contables.</p>
            </div>
        </div>
    @endif

    {{-- Grid de Detalles Técnicos --}}
    <div class="prestamo-ficha-grid">
        
        {{-- Bloque de Resumen de Cifras --}}
        <div class="ficha-datos-card">
            <h3>📊 Resumen de Saldos</h3>
            <div class="fdc-grid">
                <div class="fdc-item" {!! !$prestamo->ultimo_mensaje_cobro ? 'style="grid-column: span 2;"' : '' !!}>
                    <span class="fdc-label">Saldo Total a Cobrar</span>
                    <span class="fdc-val destacado">${{ number_format($prestamo->saldo_actual, 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Capital Original</span>
                    <span class="fdc-val">${{ number_format($prestamo->monto_original, 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Tasa de Interés</span>
                    <span class="fdc-val">{{ $prestamo->tasa_interes_mensual }}% Mensual</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Valor Interés Actual</span>
                    <span class="fdc-val" style="color: #2563eb;">${{ number_format($prestamo->saldo_actual * ($prestamo->tasa_interes_mensual / 100), 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Intereses Acumulados</span>
                    <span class="fdc-val text-warning">${{ number_format($prestamo->intereses_acumulados, 0, ',', '.') }} COP</span>
                </div>
                @if($prestamo->ultimo_mensaje_cobro)
                    <div class="fdc-item" style="background: rgba(34, 197, 94, 0.03); border-color: rgba(34, 197, 94, 0.2);">
                        <span class="fdc-label" style="color: #166534; font-weight: 700;">🟢 Último Cobro WhatsApp</span>
                        <span class="fdc-val" style="font-size: 0.75rem; font-weight: 500; color: #334155; line-height: 1.4; margin-top: 0.2rem; display: block; font-style: italic;" title="{{ $prestamo->ultimo_mensaje_cobro->contenido }}">
                            "{{ mb_substr($prestamo->ultimo_mensaje_cobro->contenido, 0, 55) }}{{ strlen($prestamo->ultimo_mensaje_cobro->contenido) > 55 ? '...' : '' }}"
                        </span>
                        <small style="color: #64748b; font-size: 0.62rem; display: block; margin-top: 0.25rem;">
                            Enviado: {{ $prestamo->ultimo_mensaje_cobro->created_at->format('d/m/Y H:i') }}
                        </small>
                    </div>
                @endif
            </div>
            
            <div class="sep-light"></div>
            
            <h3>📋 Datos Generales</h3>
            <div class="fdc-general-list">
                <div class="fdcg-row">
                    <span>Cédula:</span> <strong>{{ $prestamo->cedula_deudor ?: 'No registrada' }}</strong>
                </div>
                <div class="fdcg-row">
                    <span>Celular:</span> <strong>{{ $prestamo->telefono_deudor ?: 'No registrado' }}</strong>
                </div>
                <div class="fdcg-row">
                    <span>Fecha Desembolso:</span> <strong>{{ Carbon\Carbon::parse($prestamo->fecha_desembolso)->format('d/m/Y') }}</strong>
                </div>
                <div class="fdcg-row">
                    <span>Última Liquidación:</span> <strong>{{ $prestamo->ultimo_corte ? Carbon\Carbon::parse($prestamo->ultimo_corte)->format('d/m/Y') : 'Ninguna' }}</strong>
                </div>
                <div class="fdcg-row">
                    <span>Próximo Corte:</span>
                    <strong>{{ $prestamo->fecha_corte->format('d/m/Y') }}
                        <small style="font-weight:500; color:#6b7280;">({{ $prestamo->dias_para_corte }}d)</small>
                    </strong>
                </div>
                <div class="fdcg-row">
                    <span>Vencido:</span>
                    <strong>
                        @if($prestamo->esta_vencido)
                            ${{ number_format($prestamo->intereses_acumulados, 0, ',', '.') }}
                            <small style="font-weight:500; color:#b91c1c;">({{ $prestamo->dias_vencidos }}d)</small>
                        @else
                            Al día
                        @endif
                    </strong>
                </div>
                @if($prestamo->soporte_path)
                    <div class="fdcg-row" style="margin-top:0.75rem;">
                        <span>Archivo Soporte:</span>
                        <a href="{{ route('finanzas.prestamos.descargar-soporte', $prestamo->id) }}" target="_blank" class="badge-info" style="text-decoration:none;">
                            📄 Ver Soporte
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Panel de Acciones --}}
        <div class="ficha-acciones-card">
            <h3>⚡ Acciones Disponibles</h3>
            <div class="fac-list">
                
                @if($prestamo->estado !== 'pagado' && $prestamo->estado !== 'castigado')
                    {{-- Registrar Pago --}}
                    <button @click="openAbono = true" class="btn-fac-action green">
                        💵 Registrar Abono / Pago
                    </button>

                    {{-- Anexar Valor Préstamo --}}
                    <button @click="openAnexar = true" class="btn-fac-action orange">
                        ➕ Anexar Valor Préstamo
                    </button>

                    {{-- Cobrar por WhatsApp --}}
                    @if($prestamo->telefono_deudor)
                        <form action="{{ route('finanzas.prestamos.whatsapp', $prestamo->id) }}" method="POST" style="display:block;">
                            @csrf
                            <button type="submit" class="btn-fac-action success" style="width:100%;">
                                {{ $prestamo->esta_vencido ? '🔴 Cobrar por WhatsApp' : '🟢 Recordar por WhatsApp' }}
                            </button>
                        </form>
                    @else
                        <button @click="openNoTelefono = true" class="btn-fac-action success" style="width:100%; opacity: 0.7;">
                            {{ $prestamo->esta_vencido ? '🔴 Cobrar por WhatsApp' : '🟢 Recordar por WhatsApp' }}
                        </button>
                    @endif

                    {{-- Inactivar / Castigar Préstamo --}}
                    <div style="border-top:1px dashed #e2e8f0; padding-top:0.6rem; margin-top:0.25rem;">
                        <button @click="openCastigar = true" class="btn-fac-action" style="width:100%; background:rgba(239,68,68,0.07); color:#b91c1c; border:1px solid rgba(239,68,68,0.2); font-weight:700;">
                            ⛔ Inactivar Préstamo
                        </button>
                    </div>
                @endif

                @if($prestamo->estado === 'castigado')
                    {{-- Reactivar Préstamo --}}
                    <div style="background:rgba(245,158,11,0.06); border:1px solid rgba(245,158,11,0.2); border-radius:10px; padding:0.85rem; text-align:center;">
                        <p style="font-size:0.75rem; color:#92400e; margin:0 0 0.6rem; font-weight:500;">Este préstamo está inactivo. ¿Hubo un acuerdo de pago?</p>
                        <button @click="openReactivar = true" class="btn-fac-action" style="background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; border:none;">
                            🔄 Reactivar Préstamo
                        </button>
                    </div>
                @endif

                {{-- Activar/Desactivar Alertas --}}
                @if($prestamo->estado !== 'castigado')
                <form action="{{ route('finanzas.prestamos.toggle-alertas', $prestamo->id) }}" method="POST" style="display:block;">
                    @csrf
                    <button type="submit" class="btn-fac-action ghost" style="width:100%;">
                        🔔 {{ $prestamo->alertas_activas ? 'Desactivar Recordatorios' : 'Activar Recordatorios' }}
                    </button>
                </form>
                @endif

            </div>

            @if($prestamo->observaciones)
                <div class="fac-notes-bx">
                    <strong>Anotaciones:</strong>
                    <p style="white-space:pre-line;">{{ $prestamo->observaciones }}</p>
                </div>
            @endif
        </div>

    </div>

    {{-- Tabla Historial de Movimientos --}}
    <div class="card-tabla-bx" style="margin-top:1.5rem;">
        <div style="padding:1rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:0.9rem; font-weight:700; color:#334155;">📜 Historial de Movimientos</h3>
        </div>
        <div class="tabla-scroll-wrapper">
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th style="width: 10%">Fecha</th>
                    <th style="width: 14%">Movimiento</th>
                    <th style="text-align:right; width: 13%;">Cargo / Interés</th>
                    <th style="text-align:right; width: 13%;">Abono Interés</th>
                    <th style="text-align:right; width: 13%;">Abono Capital</th>
                    <th style="text-align:right; width: 13%;">Saldo</th>
                    <th style="width: 24%">Detalle</th>
                </tr>
            </thead>
            <tbody>
                @forelse($historial as $fila)
                    <tr>
                        <td>{{ Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') }}</td>
                        <td>
                            @if($fila['clase'] === 'pago')
                                <span class="mov-tipo-tag abono_interes">💵 Pago</span>
                                @if($fila['reancla'])
                                    <span title="El pago cubrió todo el interés corrido: el próximo corte es un mes después de esta fecha" style="font-size:0.62rem; color:#2563eb; font-weight:700; display:block; margin-top:0.2rem;">↻ ciclo reiniciado</span>
                                @endif
                            @else
                                <span class="mov-tipo-tag {{ $fila['tipo'] }}">
                                    {{ match($fila['tipo']) {
                                        'desembolso' => 'Desembolso',
                                        'interes_mensual' => 'Corte de Interés',
                                        'capitalizacion' => 'Capitalización',
                                        default => $fila['tipo'],
                                    } }}
                                </span>
                            @endif
                        </td>
                        <td style="text-align:right; font-weight:700; color:#ef4444;">
                            @if($fila['cargo'] > 0)
                                ${{ number_format($fila['cargo'], 0, ',', '.') }}
                                @if($fila['dias'])
                                    <small style="display:block; font-weight:500; color:#94a3b8; font-size:0.62rem;">{{ $fila['dias'] }} días</small>
                                @endif
                            @else
                                <span style="color:#cbd5e1; font-weight:400;">—</span>
                            @endif
                        </td>
                        <td style="text-align:right; font-weight:700; color:#16a34a;">
                            @if($fila['abono_interes'] > 0)
                                ${{ number_format($fila['abono_interes'], 0, ',', '.') }}
                            @else
                                <span style="color:#cbd5e1; font-weight:400;">—</span>
                            @endif
                        </td>
                        <td style="text-align:right; font-weight:700; color:#15803d;">
                            @if($fila['abono_capital'] > 0)
                                ${{ number_format($fila['abono_capital'], 0, ',', '.') }}
                            @else
                                <span style="color:#cbd5e1; font-weight:400;">—</span>
                            @endif
                        </td>
                        <td style="text-align:right; font-weight:700; color:#0f172a;">${{ number_format($fila['saldo_despues'], 0, ',', '.') }}</td>
                        <td style="color:#475569; font-size:0.75rem;">
                            {{ $fila['observacion'] ?: '-' }}
                            <div style="margin-top:0.25rem; display:flex; gap:0.3rem; flex-wrap:wrap; align-items:center;">
                                @if($fila['soporte_path'])
                                    @php $movSoporte = collect($fila['movimientos'])->firstWhere('soporte_path', $fila['soporte_path']); @endphp
                                    @if($movSoporte)
                                        <a href="{{ route('finanzas.prestamos.movimiento.descargar-soporte', $movSoporte->id) }}" target="_blank" class="badge-info" style="font-size:0.65rem; padding: 0.05rem 0.25rem;">📄 Soporte</a>
                                    @endif
                                @endif
                                @if($fila['clase'] === 'pago')
                                    @php
                                        $grupoMovs = collect($fila['movimientos']);
                                        $principal = $grupoMovs->firstWhere('tipo', 'abono_capital') ?? $grupoMovs->firstWhere('tipo', 'abono_interes') ?? $grupoMovs->first();
                                    @endphp
                                    <button @click="pagoEditar = { id: {{ $principal->id }}, fecha: '{{ $fila['fecha'] }}', monto: {{ $fila['abono_interes'] + $fila['abono_capital'] }}, observacion: '{{ addslashes($fila['observacion'] ?? '') }}', soporte_path: '{{ $fila['soporte_path'] }}', interes: {{ $fila['cargo'] }}, abInt: {{ $fila['abono_interes'] }}, abCap: {{ $fila['abono_capital'] }} }; openEditarPago = true"
                                            class="badge-info"
                                            style="border:none; cursor:pointer; padding: 0.15rem 0.35rem; font-size:0.62rem; background: rgba(59,130,246,0.08); color: var(--azul-btn);">
                                        ✏️ Editar
                                    </button>
                                @else
                                    @php $mov = $fila['movimientos'][0]; @endphp
                                    <button @click="movEditar = { id: {{ $mov->id }}, fecha: '{{ $mov->fecha }}', monto: {{ $mov->monto }}, observacion: '{{ addslashes($mov->observacion ?? '') }}', soporte_path: '{{ $mov->soporte_path }}' }; openEditarMov = true"
                                            class="badge-info"
                                            style="border:none; cursor:pointer; padding: 0.15rem 0.35rem; font-size:0.62rem; background: rgba(59,130,246,0.08); color: var(--azul-btn);">
                                        ✏️ Editar
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center; padding:2rem; color:#64748b;">
                            No hay movimientos registrados en el préstamo.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>


    {{-- Modal Registrar Pago --}}
    <div x-show="openAbono" class="modal-overlay-bx" @click.self="openAbono = false" x-cloak x-data="{ 
        imagePreview: null, 
        handleFile(e) { 
            const file = e.target.files[0]; 
            if(file && file.type.startsWith('image/')) { 
                this.imagePreview = URL.createObjectURL(file); 
            } else {
                this.imagePreview = null;
            }
        }, 
        initPaste() { 
            window.addEventListener('paste', (e) => { 
                if (!openAbono) return; 
                const items = (e.clipboardData || e.originalEvent.clipboardData).items; 
                for (let index in items) { 
                    const item = items[index]; 
                    if (item.kind === 'file') { 
                        const blob = item.getAsFile(); 
                        if (blob.type.startsWith('image/')) {
                            const fileInput = this.$refs.soporteInputAbono; 
                            const dataTransfer = new DataTransfer(); 
                            dataTransfer.items.add(blob); 
                            fileInput.files = dataTransfer.files; 
                            this.imagePreview = URL.createObjectURL(blob); 
                        }
                    } 
                } 
            }); 
        } 
    }" x-init="initPaste()">
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #15803d, #166534);">
                <h3>💵 Registrar Abono / Pago</h3>
                <button @click="openAbono = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.prestamos.pago', $prestamo->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha de Recepción</label>
                        <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                    </div>

                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto Recibido ($ COP)</label>
                        <input type="number" name="monto" x-ref="montoAbono" placeholder="Ej: 200000" class="form-input-bx" required min="1" autocomplete="off">
                        <small style="color:#64748b; font-size:0.7rem; display:block; margin-top:0.25rem;">
                            El pago cubre primero todo el interés corrido a la fecha y el resto baja capital.
                            Si alcanza a cubrir el interés completo, el ciclo se reinicia: el próximo corte
                            queda un mes después de este pago.
                        </small>
                    </div>

                    @if(!empty($cierre))
                    <div style="margin-top:1rem; border:1px solid #bbf7d0; background:#f0fdf4; border-radius:10px; padding:0.7rem 0.85rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem;">
                            <div>
                                <span style="font-size:0.68rem; color:#166534; font-weight:700; text-transform:uppercase; letter-spacing:0.02em;">Para quedar a paz y salvo hoy</span>
                                <div style="font-size:1.15rem; font-weight:800; color:#14532d; line-height:1.3;">
                                    ${{ number_format($cierre['total'], 0, ',', '.') }}
                                </div>
                            </div>
                            <button type="button" @click="$refs.montoAbono.value = {{ (int) round($cierre['total']) }}"
                                    style="border:none; background:#16a34a; color:#fff; font-size:0.7rem; font-weight:700; padding:0.4rem 0.7rem; border-radius:8px; cursor:pointer; white-space:nowrap;">
                                Usar este valor
                            </button>
                        </div>
                        <div style="margin-top:0.5rem; font-size:0.68rem; color:#3f6212; line-height:1.6;">
                            Capital ${{ number_format($cierre['capital'], 0, ',', '.') }}
                            @if($cierre['intereses_pendientes'] > 0)
                                · Intereses liquidados ${{ number_format($cierre['intereses_pendientes'], 0, ',', '.') }}
                            @endif
                            @if($cierre['interes_fraccion'] > 0)
                                · {{ $cierre['dias_fraccion'] }} días corridos ${{ number_format($cierre['interes_fraccion'], 0, ',', '.') }}
                            @endif
                        </div>
                    </div>
                    @endif


                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Archivo Soporte (Opcional - Adjuntar o Pegar captura)</label>
                        <input type="file" name="soporte" x-ref="soporteInputAbono" @change="handleFile" class="form-input-bx" style="padding:0.35rem 0.5rem;" accept="image/*,application/pdf">
                        
                        <template x-if="imagePreview">
                            <div style="margin-top:0.75rem; border:1px solid #cbd5e1; padding:0.5rem; border-radius:8px; background:#f8fafc; text-align:center; position:relative;">
                                <span style="font-size:0.72rem; color:#475569; display:block; margin-bottom:0.4rem; font-weight:600;">📸 Vista Previa del Soporte:</span>
                                <img :src="imagePreview" style="max-height:160px; max-width:100%; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,0.08);">
                                <button type="button" @click="imagePreview = null; $refs.soporteInputAbono.value = ''" style="position:absolute; top:4px; right:4px; border:none; background:#ef4444; color:#fff; border-radius:50%; width:20px; height:20px; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
                            </div>
                        </template>
                    </div>

                    @if(isset($cuentas) && $cuentas->isNotEmpty())
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">¿A qué cuenta entró el dinero?</label>
                        <select name="cuenta_id" class="form-select-bx" required>
                            @foreach($cuentas as $cta)
                                <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observaciones (Opcional)</label>
                        <input type="text" name="observacion" placeholder="Ej: Abono en efectivo, transferencia Bancolombia" class="form-input-bx">
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openAbono = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#16a34a;">Registrar Pago</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Anexar Valor Préstamo --}}
    <div x-show="openAnexar" class="modal-overlay-bx" @click.self="openAnexar = false" x-cloak x-data="{ 
        imagePreview: null, 
        handleFile(e) { 
            const file = e.target.files[0]; 
            if(file && file.type.startsWith('image/')) { 
                this.imagePreview = URL.createObjectURL(file); 
            } else {
                this.imagePreview = null;
            }
        }, 
        initPaste() { 
            window.addEventListener('paste', (e) => { 
                if (!openAnexar) return; 
                const items = (e.clipboardData || e.originalEvent.clipboardData).items; 
                for (let index in items) { 
                    const item = items[index]; 
                    if (item.kind === 'file') { 
                        const blob = item.getAsFile(); 
                        if (blob.type.startsWith('image/')) {
                            const fileInput = this.$refs.soporteInputAnexar; 
                            const dataTransfer = new DataTransfer(); 
                            dataTransfer.items.add(blob); 
                            fileInput.files = dataTransfer.files; 
                            this.imagePreview = URL.createObjectURL(blob); 
                        }
                    } 
                } 
            }); 
        } 
    }" x-init="initPaste()">
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #f97316, #ea580c);">
                <h3>➕ Anexar Valor (Capital)</h3>
                <button @click="openAnexar = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.prestamos.anexar', $prestamo->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha del Desembolso Adicional</label>
                        <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                    </div>

                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto Adicional ($ COP)</label>
                        <input type="number" name="monto" placeholder="Ej: 500000" class="form-input-bx" required min="1" autocomplete="off">
                        <small style="color:#64748b; font-size:0.7rem; display:block; margin-top:0.25rem;">
                            Este valor se sumará al capital original y al saldo actual del préstamo.
                        </small>
                    </div>
                    
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Archivo Soporte (Opcional - Adjuntar o Pegar captura)</label>
                        <input type="file" name="soporte" x-ref="soporteInputAnexar" @change="handleFile" class="form-input-bx" style="padding:0.35rem 0.5rem;" accept="image/*,application/pdf">
                        
                        <template x-if="imagePreview">
                            <div style="margin-top:0.75rem; border:1px solid #cbd5e1; padding:0.5rem; border-radius:8px; background:#f8fafc; text-align:center; position:relative;">
                                <span style="font-size:0.72rem; color:#475569; display:block; margin-bottom:0.4rem; font-weight:600;">📸 Vista Previa del Soporte:</span>
                                <img :src="imagePreview" style="max-height:160px; max-width:100%; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,0.08);">
                                <button type="button" @click="imagePreview = null; $refs.soporteInputAnexar.value = ''" style="position:absolute; top:4px; right:4px; border:none; background:#ef4444; color:#fff; border-radius:50%; width:20px; height:20px; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
                            </div>
                        </template>
                    </div>

                    @if(isset($cuentas) && $cuentas->isNotEmpty())
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">¿De qué cuenta salió el dinero?</label>
                        <select name="cuenta_id" class="form-select-bx" required>
                            @foreach($cuentas as $cta)
                                <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observaciones (Opcional)</label>
                        <input type="text" name="observacion" placeholder="Ej: Desembolso adicional transferencia" class="form-input-bx">
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openAnexar = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#ea580c;">Anexar Valor</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Editar Movimiento --}}
    <div x-show="openEditarMov" class="modal-overlay-bx" @click.self="openEditarMov = false" x-cloak>
        <div class="modal-box-bx" style="max-width: 480px;">
            <div class="modal-head-bx" style="background: linear-gradient(135deg, #1e3a8a, #1d4ed8);">
                <h3>✏️ Editar Movimiento</h3>
                <button @click="openEditarMov = false" class="modal-close-bx">&times;</button>
            </div>
            
            <form :action="'{{ route('finanzas.prestamos.movimiento.update', '') }}/' + movEditar.id" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha del Movimiento</label>
                        <input type="date" name="fecha" x-model="movEditar.fecha" class="form-input-bx" required>
                    </div>

                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto ($ COP)</label>
                        <input type="number" name="monto" x-model="movEditar.monto" class="form-input-bx" required min="0" autocomplete="off">
                        <small style="color:#64748b; font-size:0.7rem; display:block; margin-top:0.25rem;">
                            ⚠️ Modificar el monto recalculará automáticamente todos los saldos posteriores.
                        </small>
                    </div>
                    
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observaciones</label>
                        <input type="text" name="observacion" x-model="movEditar.observacion" placeholder="Detalle del movimiento" class="form-input-bx">
                    </div>
                    
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Archivo Soporte</label>
                        <input type="file" name="soporte" class="form-input-bx" accept="image/*,application/pdf">
                        
                        <template x-if="movEditar.soporte_path">
                            <div style="margin-top:0.5rem; display:flex; align-items:center; justify-content:space-between; background:#f8fafc; padding:0.4rem; border-radius:6px; border:1px solid #e2e8f0;">
                                <span style="font-size:0.72rem; color:#475569;">Tiene soporte cargado</span>
                                <label style="font-size:0.72rem; color:#ef4444; display:flex; align-items:center; gap:0.25rem; cursor:pointer;">
                                    <input type="checkbox" name="eliminar_soporte" value="1"> Eliminar
                                </label>
                            </div>
                        </template>
                    </div>
                </div>
                
                <div class="modal-foot-bx" style="display:flex; justify-content:space-between; align-items:center; width:100%; box-sizing:border-box;">
                    <button type="button" 
                            @click="if(confirm('¿Estás seguro de eliminar este movimiento? Esto recalculará todos los saldos posteriores.')) { 
                                        $refs.deleteForm.action = '{{ route('finanzas.prestamos.movimiento.destroy', '') }}/' + movEditar.id; 
                                        $refs.deleteForm.submit(); 
                                    }" 
                            class="btn-glass-bx" 
                            style="color:#ef4444; border-color:#fca5a5; background:rgba(239,68,68,0.04);">
                        🗑️ Eliminar
                    </button>
                    
                    <div style="display:flex; gap:0.5rem;">
                        <button type="button" @click="openEditarMov = false" class="btn-glass-bx">Cancelar</button>
                        <button type="submit" class="btn-fin success">Guardar Cambios</button>
                    </div>
                </div>
            </form>
            
            <form x-ref="deleteForm" method="POST" style="display:none;">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    {{-- Modal Editar Pago (edita el pago completo; el motor recalcula el reparto) --}}
    <div x-show="openEditarPago" class="modal-overlay-bx" @click.self="openEditarPago = false" x-cloak>
        <div class="modal-box-bx" style="max-width: 480px;">
            <div class="modal-head-bx" style="background: linear-gradient(135deg, #166534, #15803d);">
                <h3>✏️ Editar Pago</h3>
                <button @click="openEditarPago = false" class="modal-close-bx">&times;</button>
            </div>

            <form :action="'{{ route('finanzas.prestamos.pago.update', '') }}/' + pagoEditar.id" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body-bx">
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:0.6rem 0.8rem; margin-bottom:1rem; font-size:0.72rem; color:#166534; line-height:1.6;">
                        Reparto actual:
                        <template x-if="pagoEditar.interes > 0"><span> interés del ciclo $<span x-text="Number(pagoEditar.interes).toLocaleString('es-CO')"></span> ·</span></template>
                        <span> a interés $<span x-text="Number(pagoEditar.abInt).toLocaleString('es-CO')"></span></span>
                        <span> · a capital $<span x-text="Number(pagoEditar.abCap).toLocaleString('es-CO')"></span></span>
                    </div>

                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha del Pago</label>
                        <input type="date" name="fecha" x-model="pagoEditar.fecha" class="form-input-bx" required>
                    </div>

                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto Recibido ($ COP)</label>
                        <input type="number" name="monto" x-model="pagoEditar.monto" class="form-input-bx" required min="1" step="any" autocomplete="off">
                        <small style="color:#64748b; font-size:0.7rem; display:block; margin-top:0.25rem;">
                            Al cambiar la fecha o el monto, el sistema re-aplica la historia del préstamo:
                            el reparto entre interés y capital y las fechas de corte se recalculan solos
                            desde este pago en adelante.
                        </small>
                    </div>

                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observaciones</label>
                        <input type="text" name="observacion" x-model="pagoEditar.observacion" placeholder="Detalle del pago" class="form-input-bx">
                    </div>

                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Archivo Soporte</label>
                        <input type="file" name="soporte" class="form-input-bx" accept="image/*,application/pdf">

                        <template x-if="pagoEditar.soporte_path">
                            <div style="margin-top:0.5rem; display:flex; align-items:center; justify-content:space-between; background:#f8fafc; padding:0.4rem; border-radius:6px; border:1px solid #e2e8f0;">
                                <span style="font-size:0.72rem; color:#475569;">Tiene soporte cargado</span>
                                <label style="font-size:0.72rem; color:#ef4444; display:flex; align-items:center; gap:0.25rem; cursor:pointer;">
                                    <input type="checkbox" name="eliminar_soporte" value="1"> Eliminar
                                </label>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="modal-foot-bx" style="display:flex; justify-content:space-between; align-items:center; width:100%; box-sizing:border-box;">
                    <button type="button"
                            @click="if(confirm('¿Eliminar este pago completo? Se borran sus partes y el préstamo se recalcula como si el pago no hubiera existido.')) {
                                        $refs.deletePagoForm.action = '{{ route('finanzas.prestamos.pago.destroy', '') }}/' + pagoEditar.id;
                                        $refs.deletePagoForm.submit();
                                    }"
                            class="btn-glass-bx"
                            style="color:#ef4444; border-color:#fca5a5; background:rgba(239,68,68,0.04);">
                        🗑️ Eliminar Pago
                    </button>

                    <div style="display:flex; gap:0.5rem;">
                        <button type="button" @click="openEditarPago = false" class="btn-glass-bx">Cancelar</button>
                        <button type="submit" class="btn-fin success">Guardar Cambios</button>
                    </div>
                </div>
            </form>

            <form x-ref="deletePagoForm" method="POST" style="display:none;">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    {{-- Modal Inactivar / Castigar Préstamo --}}
    <div x-show="openCastigar" class="modal-overlay-bx" @click.self="openCastigar = false" x-cloak>
        <div class="modal-box-bx" style="max-width:460px;">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #7c2d12, #b91c1c);">
                <h3>⛔ Inactivar Préstamo</h3>
                <button @click="openCastigar = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.prestamos.castigar', $prestamo->id) }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <div style="background:rgba(239,68,68,0.06); border:1px solid rgba(239,68,68,0.2); border-radius:10px; padding:0.85rem; margin-bottom:1rem;">
                        <p style="font-size:0.8rem; color:#7c2d12; margin:0; line-height:1.5;">
                            ⚠️ <strong>Esto hará lo siguiente:</strong><br>
                            • Cambia el estado a <strong>Inactivo/Castigado</strong><br>
                            • Congela la tasa de interés en <strong>0%</strong> (sin nuevos cargos)<br>
                            • Desactiva alertas automáticas de cobro<br>
                            • El saldo deudor queda registrado para efectos contables<br>
                            • Puedes <strong>reactivarlo</strong> si la persona llega a un acuerdo
                        </p>
                    </div>
                    <div class="form-group-bx">
                        <label class="form-label-bx">Motivo de la Inactivación <span style="color:#ef4444;">*</span></label>
                        <textarea name="motivo" rows="3" placeholder="Ej: El deudor manifiesta no tener capacidad de pago. Se acuerda mantener el saldo pendiente sin intereses hasta nuevo aviso." class="form-input-bx" style="height:auto; padding:0.65rem;" required></textarea>
                        <small style="color:#64748b; font-size:0.7rem;">Este motivo quedará registrado en las observaciones del préstamo con la fecha de hoy.</small>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openCastigar = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin" style="background:linear-gradient(135deg,#b91c1c,#7f1d1d); color:#fff;">⛔ Confirmar Inactivación</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Reactivar Préstamo --}}
    <div x-show="openReactivar" class="modal-overlay-bx" @click.self="openReactivar = false" x-cloak>
        <div class="modal-box-bx" style="max-width:440px;">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #d97706, #b45309);">
                <h3>🔄 Reactivar Préstamo</h3>
                <button @click="openReactivar = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.prestamos.reactivar', $prestamo->id) }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <p style="font-size:0.8rem; color:#64748b; margin:0 0 1rem;">Al reactivar, el préstamo volverá a aparecer en la lista de cobros activos. Define la nueva tasa de interés para continuar el seguimiento.</p>
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nueva Tasa de Interés Mensual (%)</label>
                        <input type="number" step="0.001" min="0" max="100" name="tasa_interes_mensual" value="{{ $prestamo->tasa_interes_mensual }}" placeholder="Ej: 3" class="form-input-bx" required>
                        <small style="color:#64748b; font-size:0.7rem;">Puedes dejarlo en 0% si el acuerdo no genera intereses.</small>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openReactivar = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin" style="background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff;">🔄 Confirmar Reactivación</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal No Celular --}}
    <div x-show="openNoTelefono" class="modal-overlay-bx" @click.self="openNoTelefono = false" x-cloak>
        <div class="modal-box-bx" style="max-width:400px;">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #ef4444, #dc2626);">
                <h3>⚠️ Celular no Registrado</h3>
                <button @click="openNoTelefono = false" class="modal-close-bx">&times;</button>
            </div>
            <div class="modal-body-bx" style="text-align: center; padding: 1.5rem 1.25rem;">
                <span style="font-size: 3rem; display: block; margin-bottom: 0.75rem;">📱❌</span>
                <p style="font-size: 0.88rem; color: #334155; line-height: 1.5; margin-bottom: 1rem;">
                    Este deudor no tiene un número de celular registrado en su ficha. Para poder enviarle recordatorios de cobro por WhatsApp, debes ingresar su número.
                </p>
            </div>
            <div class="modal-foot-bx" style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" @click="openNoTelefono = false" class="btn-glass-bx">Cerrar</button>
                <a href="{{ route('finanzas.prestamos.edit', $prestamo->id) }}" class="btn-fin success" style="background: #ef4444; border-color: #ef4444; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                    ✏️ Editar Ficha
                </a>
            </div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

/* Top Bar & Breadcrumb */

/* Header Section */

/* Ficha Grid */
.prestamo-ficha-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 1.25rem; margin-top: 1rem; }
@media (max-width: 768px) {
    .prestamo-ficha-grid { grid-template-columns: 1fr; }
    .fin-top-bar { flex-wrap: wrap; gap: 0.5rem; }
    .fin-top-bar .breadcrumb-bx { font-size: 0.72rem; max-width: calc(100% - 130px); overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    .header-text h1 { font-size: 1.1rem; }
    /* Tabla scrolleable en móvil */
    .card-tabla-bx { overflow: hidden; }
    .card-tabla-bx .tabla-scroll-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .tabla-brynex-bx { min-width: 620px; }
    /* Modales en pantalla completa móvil */
    .modal-overlay-bx { padding: 0; align-items: flex-end; }
    .modal-box-bx { border-bottom-left-radius: 0; border-bottom-right-radius: 0; max-height: 90vh; overflow-y: auto; }
    .fdc-grid { grid-template-columns: 1fr 1fr; }
}

.ficha-datos-card, .ficha-acciones-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 1.25rem; box-shadow: 0 4px 14px rgba(0,0,0,0.03); }
.ficha-datos-card h3, .ficha-acciones-card h3 { font-size: 0.9rem; font-weight: 700; color: #334155; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; }

/* KPIs internos de la ficha */
.fdc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }
.fdc-item { display: flex; flex-direction: column; background: #f8fafc; padding: 0.75rem 1rem; border-radius: 12px; border: 1px solid #f1f5f9; }
.fdc-label { font-size: 0.65rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }
.fdc-val { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-top: 0.2rem; }
.fdc-val.destacado { color: #b91c1c; font-size: 1.2rem; }

.fdc-general-list { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem; }
.fdcg-row { display: flex; justify-content: space-between; font-size: 0.8rem; padding: 0.35rem 0; border-bottom: 1px solid #f8fafc; }
.fdcg-row span { color: #64748b; font-weight: 500; }
.fdcg-row strong { color: #1e293b; font-weight: 600; }

.sep-light { height: 1px; background: #e2e8f0; margin: 1.25rem 0; }

/* Botones Acciones */
.fac-list { display: flex; flex-direction: column; gap: 0.6rem; }
.btn-fac-action { padding: 0.65rem 1rem; border: none; border-radius: 10px; font-size: 0.82rem; font-weight: 600; cursor: pointer; text-align: center; display: flex; align-items: center; justify-content: center; transition: all 0.15s; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
.btn-fac-action.green { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
.btn-fac-action.green:hover { background: linear-gradient(135deg, #059669, #047857); transform: translateY(-1px); box-shadow: 0 4px 10px rgba(16,185,129,0.2); }
.btn-fac-action.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; }
.btn-fac-action.blue:hover { background: linear-gradient(135deg, #2563eb, #1e3a8a); transform: translateY(-1px); box-shadow: 0 4px 10px rgba(59,130,246,0.2); }
.btn-fac-action.orange { background: linear-gradient(135deg, #f97316, #ea580c); color: #fff; }
.btn-fac-action.orange:hover { background: linear-gradient(135deg, #ea580c, #c2410c); transform: translateY(-1px); box-shadow: 0 4px 10px rgba(249,115,22,0.2); }
.btn-fac-action.success { background: rgba(34,197,94,0.08); color: #166534; border: 1px solid rgba(34,197,94,0.18); }
.btn-fac-action.success:hover { background: rgba(34,197,94,0.15); transform: translateY(-1px); }
.btn-fac-action.success:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-fac-action.ghost { background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; }
.btn-fac-action.ghost:hover { background: #f1f5f9; border-color: #94a3b8; }

.fac-notes-bx { margin-top: 1.25rem; padding: 0.85rem; background: #f8fafc; border-left: 3px solid #cbd5e1; border-radius: 8px; border: 1px solid #e2e8f0; border-left-width: 4px; }
.fac-notes-bx strong { font-size: 0.72rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em; }
.fac-notes-bx p { font-size: 0.8rem; color: #334155; margin-top: 0.3rem; line-height: 1.45; }

/* Tabla */

.mov-tipo-tag { display: inline-block; font-size: 0.62rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 6px; text-transform: uppercase; }
.mov-tipo-tag.desembolso { background: #f1f5f9; color: #475569; }
.mov-tipo-tag.interes_mensual { background: #fee2e2; color: #991b1b; }
.mov-tipo-tag.interes_proporcional { background: #ffedd5; color: #9a3412; }
.mov-tipo-tag.abono_interes { background: #d1fae5; color: #065f46; }
.mov-tipo-tag.abono_capital { background: #d1fae5; color: #065f46; border: 1px dashed #059669; }
.mov-tipo-tag.pago_total { background: #d1fae5; color: #065f46; font-weight: 800; border: 1px solid #059669; }

/* Modales */




.badge-info { background: rgba(59,130,246,0.08); color: #2563eb; border: 1px solid rgba(59,130,246,0.22); border-radius: 6px; padding: 0.2rem 0.5rem; font-size: 0.72rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem; }
.badge-info:hover { background: rgba(59,130,246,0.15); }
</style>
@endpush
