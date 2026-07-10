@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Cobros Aliados Brynex')

@section('contenido')
<div class="finanzas-container" x-data="brynexAliadosGrid()">

    {{-- Breadcrumb & Period Selector --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <a href="{{ route('finanzas.entradas.index') }}">Entradas Mensuales</a>
            <span>›</span>
            <span>Aliados Brynex</span>
        </div>
        
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <button @click="openAddAliadoModal = true" class="btn-fin-link success" style="cursor: pointer; border: none;">➕ Agregar Aliado</button>
            
            <form method="GET" action="{{ route('finanzas.brynex-aliados.index') }}" class="period-selector-bx">
                <select name="anio" class="select-fin" onchange="this.form.submit()">
                    @foreach(range(2020, now()->year + 1) as $a)
                        <option value="{{ $a }}" @selected($anio == $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1 class="fin-title">🏢 Cobros de Aliados Brynex - {{ $anio }}</h1>
            <p class="fin-subtitle">Registra lo pagado por cada aliado mensualmente. Los totales se consolidan de forma automática en la fila "BRYNEX".</p>
        </div>
    </div>

    {{-- Cuadrícula Excel --}}
    <div class="card-tabla" style="overflow-x:auto;">
        <table class="tabla-excel">
            <thead>
                <tr>
                    <th class="fuente-col">Aliado / Mes</th>
                    @foreach(range(1,12) as $m)
                        <th class="mes-col">{{ ucfirst(\Carbon\Carbon::create()->month($m)->locale('es')->shortMonthName) }}</th>
                    @endforeach
                    <th class="total-col">Total Anual</th>
                </tr>
            </thead>
            <tbody>
                @forelse($aliados as $aliado)
                    @php $totalAliado = 0; @endphp
                    <tr class="fuente-row">
                        <td class="fuente-name-cell">
                            <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                                <strong style="font-size:0.75rem; text-overflow:ellipsis; overflow:hidden;">{{ $aliado->nombre }}</strong>
                                <button type="button" 
                                        @click="openEditModal({
                                            id: {{ $aliado->id }},
                                            nombre: '{{ addslashes($aliado->nombre) }}',
                                            nit: '{{ addslashes($aliado->nit) }}',
                                            fecha_inicio: '{{ $aliado->brynex_fecha_inicio ? $aliado->brynex_fecha_inicio->toDateString() : '' }}',
                                            fecha_fin: '{{ $aliado->brynex_fecha_fin ? $aliado->brynex_fecha_fin->toDateString() : '' }}'
                                        })" 
                                        style="background:none; border:none; color:#3b82f6; cursor:pointer; font-size:0.85rem; padding: 0.2rem;" 
                                        title="Editar vigencia y datos">
                                    ✏️
                                </button>
                            </div>
                        </td>
                        @foreach(range(1,12) as $mesNum)
                            @php
                                $mesPagos = isset($pagos[$aliado->id][$mesNum]) ? $pagos[$aliado->id][$mesNum] : collect();
                                $monto = $mesPagos->sum('monto');
                                $totalAliado += $monto;
                                $ultimoPago = $mesPagos->last();
                            @endphp
                            <td class="excel-cell" 
                                @click="abrirPago({
                                    id: {{ $aliado->id }},
                                    nombre: '{{ addslashes($aliado->nombre) }}'
                                }, {{ $mesNum }}, '{{ ucfirst(\Carbon\Carbon::create()->month($mesNum)->locale('es')->monthName) }}', 
                                {{ json_encode($mesPagos->map(function($p) {
                                    return [
                                        'id' => $p->id,
                                        'monto' => $p->monto,
                                        'estado' => $p->estado,
                                        'saldo_pendiente' => $p->saldo_pendiente,
                                        'observacion' => $p->observacion,
                                        'recibo' => $p->recibo ? [
                                            'id' => $p->recibo->id,
                                            'monto_total' => $p->recibo->monto_total,
                                            'fecha_pago' => $p->recibo->fecha_pago->toDateString(),
                                            'banco' => $p->recibo->banco,
                                            'observacion' => $p->recibo->observacion,
                                            'soporte_url' => $p->recibo->soporte_path ? route('finanzas.brynex-aliados.descargar-soporte', $p->recibo->id) : ''
                                        ] : null
                                    ];
                                })->toArray()) }}
                                )"
                            >
                                <div class="cell-val" style="display:flex; flex-direction:column; align-items:flex-end; justify-content:center; padding:0.25rem 0.5rem; min-height:35px; box-sizing:border-box;">
                                    <span style="font-weight:600; font-size:0.78rem; color: {{ $ultimoPago && $ultimoPago->estado === 'pendiente' ? '#d97706' : '#0f172a' }}">
                                        {{ $monto > 0 ? '$' . number_format($monto, 0, ',', '.') : '-' }}
                                    </span>
                                    @if($ultimoPago && $ultimoPago->estado === 'pendiente')
                                        <span style="font-size:0.58rem; color:#d97706; font-weight:700; line-height:1; margin-top:1px;">Abono</span>
                                    @endif
                                </div>
                            </td>
                        @endforeach
                        <td class="fuente-total-cell" style="text-align:right; font-weight:700; background:#f8fafc; color:#1e293b; font-size:0.8rem; padding: 0.6rem 0.5rem;">
                            ${{ number_format($totalAliado, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" style="text-align:center; padding:2rem; color:#64748b;">
                            No hay aliados activos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            
            {{-- Fila de Consolidado --}}
            @if($aliados->isNotEmpty())
                <tfoot>
                    <tr style="background:#f1f5f9; font-weight:bold; border-top:2px solid #cbd5e1;">
                        <td style="padding: 0.6rem 0.5rem;">CONSOLIDADO BRYNEX</td>
                        @php $totalGeneral = 0; @endphp
                        @foreach(range(1,12) as $mesNum)
                            @php
                                $totalMes = 0;
                                foreach($aliados as $aliado) {
                                    $mesPagos = isset($pagos[$aliado->id][$mesNum]) ? $pagos[$aliado->id][$mesNum] : collect();
                                    $totalMes += $mesPagos->sum('monto');
                                }
                                $totalGeneral += $totalMes;
                            @endphp
                            <td style="text-align:right; padding: 0.6rem 0.5rem; color:#0f172a;">
                                {{ $totalMes > 0 ? '$' . number_format($totalMes, 0, ',', '.') : '-' }}
                            </td>
                        @endforeach
                        <td style="text-align:right; padding: 0.6rem 0.5rem; color:#047857; font-weight:800;">
                            ${{ number_format($totalGeneral, 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    {{-- Modal Agregar Aliado --}}
    <div x-show="openAddAliadoModal" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="modal-overlay-bx" 
         @click.self="openAddAliadoModal = false">
        
        <div class="modal-box-bx" style="max-width:400px;">
            <div class="modal-head-bx" style="background: linear-gradient(135deg, #10b981, #047857); color: #fff; display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #cbd5e1;">
                <h3 style="color:#fff; margin:0;">➕ Agregar Nuevo Aliado</h3>
                <button @click="openAddAliadoModal = false" class="modal-close-bx" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:rgba(255,255,255,0.7);">&times;</button>
            </div>
            
            <form action="{{ route('finanzas.brynex-aliados.store-aliado') }}" method="POST">
                @csrf
                <div class="modal-body-bx" style="padding:1.25rem;">
                    <div style="margin-bottom:1rem;">
                        <label for="nombre" style="display:block; margin-bottom:0.5rem; font-size:0.8rem; font-weight:600; color:#334155;">Nombre del Aliado / Cliente:</label>
                        <input type="text" id="nombre" name="nombre" required placeholder="Ej. GiMave S.A.S" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem; outline:none; box-sizing:border-box;">
                    </div>
                </div>
                
                <div class="modal-foot-bx" style="display:flex; justify-content:end; gap:0.5rem; padding:1rem; border-top:1px solid #cbd5e1; background:#f8fafc;">
                    <button type="button" @click="openAddAliadoModal = false" class="btn-glass-bx" style="padding:0.45rem 1rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.78rem; font-weight:600; cursor:pointer; background:#fff; color:#475569;">Cancelar</button>
                    <button type="submit" class="btn-glass-bx" style="padding:0.45rem 1rem; border:none; border-radius:8px; font-size:0.78rem; font-weight:600; cursor:pointer; background:#10b981; color:#fff;">Agregar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Editar Aliado / Vigencia --}}
    <div x-show="openEditAliadoModal" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="modal-overlay-bx" 
         @click.self="openEditAliadoModal = false">
        
        <div class="modal-box-bx" style="max-width:420px;">
            <div class="modal-head-bx" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #cbd5e1;">
                <h3 style="color:#fff; margin:0;">✏️ Editar Aliado / Vigencia</h3>
                <button @click="openEditAliadoModal = false" class="modal-close-bx" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:rgba(255,255,255,0.7);">&times;</button>
            </div>
            
            <form action="{{ route('finanzas.brynex-aliados.update-aliado') }}" method="POST">
                @csrf
                <input type="hidden" name="id" x-model="editAliadoId">
                <div class="modal-body-bx" style="padding:1.25rem;">
                    <div style="margin-bottom:1rem;">
                        <label for="edit_nombre" style="display:block; margin-bottom:0.35rem; font-size:0.8rem; font-weight:600; color:#334155;">Nombre del Aliado:</label>
                        <input type="text" id="edit_nombre" name="nombre" required x-model="editAliadoNombre" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem; outline:none; box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:1rem;">
                        <label for="edit_nit" style="display:block; margin-bottom:0.35rem; font-size:0.8rem; font-weight:600; color:#334155;">NIT:</label>
                        <input type="text" id="edit_nit" name="nit" required x-model="editAliadoNit" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem; outline:none; box-sizing:border-box;">
                    </div>
                    <div style="display:flex; gap:0.5rem; margin-bottom:1rem;">
                        <div style="flex:1;">
                            <label for="edit_fecha_inicio" style="display:block; margin-bottom:0.35rem; font-size:0.8rem; font-weight:600; color:#334155;">Fecha Inicio:</label>
                            <input type="date" id="edit_fecha_inicio" name="brynex_fecha_inicio" x-model="editAliadoFechaInicio" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem; outline:none; box-sizing:border-box;">
                        </div>
                        <div style="flex:1;">
                            <label for="edit_fecha_fin" style="display:block; margin-bottom:0.35rem; font-size:0.8rem; font-weight:600; color:#334155;">Fecha Fin (Retiro):</label>
                            <input type="date" id="edit_fecha_fin" name="brynex_fecha_fin" x-model="editAliadoFechaFin" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem; outline:none; box-sizing:border-box;">
                        </div>
                    </div>
                    <small style="color: #64748b; font-size: 0.72rem; line-height: 1.3; display: block;">
                        💡 Al poner una <b>Fecha Fin</b> (ej. 2025-12-31), el aliado dejará de aparecer en la cuadrícula a partir de ese año (por ejemplo, en el 2026), pero mantendrá todo su historial anterior intacto.
                    </small>
                </div>
                
                <div class="modal-foot-bx" style="display:flex; justify-content:end; gap:0.5rem; padding:1rem; border-top:1px solid #cbd5e1; background:#f8fafc;">
                    <button type="button" @click="openEditAliadoModal = false" class="btn-glass-bx" style="padding:0.45rem 1rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.78rem; font-weight:600; cursor:pointer; background:#fff; color:#475569;">Cancelar</button>
                    <button type="submit" class="btn-glass-bx" style="padding:0.45rem 1rem; border:none; border-radius:8px; font-size:0.78rem; font-weight:600; cursor:pointer; background:#3b82f6; color:#fff;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Detalle / Registrar Pago --}}
    <div x-show="openPagoModal" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="modal-overlay-bx" 
         @click.self="openPagoModal = false">
        
        <div class="modal-box-bx" style="max-width:500px; max-height: 90vh; display: flex; flex-direction: column;">
            
            <!-- Header (Fixed) -->
            <div class="modal-head-bx" style="flex-shrink: 0; background: linear-gradient(135deg, #0f172a, #334155); color: #fff; display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #cbd5e1;">
                <div>
                    <h3 style="color:#fff; margin:0; font-size:1.1rem;" x-text="'💵 Pago - ' + pagoAliadoNombre"></h3>
                    <p style="margin: 2px 0 0 0; font-size: 0.75rem; color: #cbd5e1;" x-text="'Mes de servicio: ' + pagoMesNombre + ' ' + {{ $anio }}"></p>
                </div>
                <button @click="openPagoModal = false" class="modal-close-bx" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:rgba(255,255,255,0.7);">&times;</button>
            </div>
            
            <!-- Formulario oculto global para eliminar pagos/recibos sin anidar forms -->
            <form id="delete-recibo-form" action="" method="POST" style="display:none;">
                @csrf
                @method('DELETE')
            </form>

            <!-- Cuerpo Scrollable (Lista de pagos + Formulario de carga en el mismo flujo) -->
            <div style="flex-grow: 1; overflow-y: auto; padding: 1.25rem;">
                
                {{-- LISTADO DE PAGOS YA REGISTRADOS EN ESTE MES --}}
                <template x-if="pagosMes.length > 0">
                    <div style="margin-bottom: 1.25rem;">
                        <h4 style="margin: 0 0 0.5rem 0; font-size: 0.85rem; color: #475569; font-weight: 700;">Pagos Registrados:</h4>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <template x-for="(pago, pIdx) in pagosMes" :key="pago.id">
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem;">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                                        <span style="font-size:0.72rem; color:#64748b; font-weight:600;">Monto Distribuido:</span>
                                        <span style="font-size:0.75rem; color:#10b981; font-weight:800;" x-text="formatMoney(pago.monto)"></span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;" x-show="pago.recibo">
                                        <span style="font-size:0.72rem; color:#64748b; font-weight:600;">Banco / Fecha:</span>
                                        <span style="font-size:0.72rem; color:#0f172a; font-weight:600;" x-text="pago.recibo ? pago.recibo.banco + ' (' + pago.recibo.fecha_pago + ')' : ''"></span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                                        <span style="font-size:0.72rem; color:#64748b; font-weight:600;">Estado:</span>
                                        <span style="font-size:0.7rem; font-weight:700; padding: 0.1rem 0.35rem; border-radius: 4px;" 
                                              :style="pago.estado === 'pendiente' ? 'background:rgba(217,119,6,0.1); color:#d97706;' : 'background:rgba(16,185,129,0.1); color:#10b981;'" 
                                              x-text="pago.estado === 'pendiente' ? 'Abono parcial' : 'Pago completo'"></span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;" x-show="pago.estado === 'pendiente'">
                                        <span style="font-size:0.72rem; color:#ef4444; font-weight:600;">Saldo Pendiente:</span>
                                        <span style="font-size:0.72rem; color:#ef4444; font-weight:700;" x-text="formatMoney(pago.saldo_pendiente)"></span>
                                    </div>
                                    <div style="margin-top: 0.25rem; font-size: 0.72rem; color: #475569;" x-show="pago.observacion">
                                        <span style="font-weight:600; color:#64748b;">Obs:</span> <span x-text="pago.observacion"></span>
                                    </div>
                                    
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.5rem; border-top:1px solid #e2e8f0; padding-top:0.5rem;">
                                        <div>
                                            <template x-if="pago.recibo && pago.recibo.soporte_url">
                                                <a :href="pago.recibo.soporte_url" target="_blank" style="font-size:0.7rem; font-weight:600; text-decoration:none; color:#1d4ed8;">
                                                    📄 Ver Soporte
                                                </a>
                                            </template>
                                        </div>
                                        <button type="button" @click="eliminarRecibo(pago.recibo ? pago.recibo.id : '')" style="background:none; border:none; color:#ef4444; font-size:0.7rem; font-weight:600; cursor:pointer; padding:0;">
                                            🗑️ Eliminar Pago
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="pagosMes.length > 0 && pagosMes[pagosMes.length - 1].estado === 'pendiente'">
                    <div style="border-top: 1px solid #cbd5e1; padding-top: 0.75rem; margin-bottom: 1rem;">
                        <h4 style="margin: 0; font-size: 0.85rem; color: #d97706; font-weight: 700;">📝 Registrar Pago de Saldo Restante / Nuevo Abono:</h4>
                    </div>
                </template>

                {{-- CASO B: REGISTRAR UN PAGO NUEVO (CON SOPORTE Y SELECCIÓN DE MESES) --}}
                <form id="registrar-recibo-form" 
                      x-show="pagosMes.length === 0 || (pagosMes.length > 0 && pagosMes[pagosMes.length - 1].estado === 'pendiente')" 
                      action="{{ route('finanzas.brynex-aliados.registrar-recibo') }}" 
                      method="POST" 
                      enctype="multipart/form-data"
                      style="margin: 0;">
                    @csrf
                    <input type="hidden" name="aliado_id" x-model="pagoAliadoId">
                    <input type="hidden" name="anio" value="{{ $anio }}">
                    
                    <div style="display:flex; gap:0.5rem; margin-bottom:1rem;">
                        <div style="flex:1;">
                            <label style="display:block; margin-bottom:0.35rem; font-size:0.8rem; font-weight:600; color:#334155;">Fecha de Pago:</label>
                            <input type="date" name="fecha_pago" required value="{{ now()->toDateString() }}" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem; outline:none; box-sizing:border-box;">
                        </div>
                        <div style="flex:1;">
                            <label style="display:block; margin-bottom:0.35rem; font-size:0.8rem; font-weight:600; color:#334155;">Banco / Cuenta Destino:</label>
                            <select name="banco" required style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem; outline:none; background:#fff; box-sizing:border-box;">
                                <option value="Bancolombia">Bancolombia</option>
                                <option value="Nequi">Nequi</option>
                                <option value="Daviplata">Daviplata</option>
                                <option value="Davivienda">Davivienda</option>
                                <option value="Banco de Bogotá">Banco de Bogotá</option>
                                <option value="Caja Menor">Caja Menor</option>
                                <option value="Efectivo">Efectivo</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom:1rem;" 
                         @paste.window="handlePaste($event)"
                         @dragover.prevent="dragOver = true"
                         @dragleave.prevent="dragOver = false"
                         @drop.prevent="handleDrop($event)">
                        <label style="display:block; margin-bottom:0.35rem; font-size:0.8rem; font-weight:600; color:#334155;">Soporte de Pago (PDF o Imagen):</label>
                        
                        <div :style="dragOver ? 'border-color: #3b82f6; background: rgba(59, 130, 246, 0.05);' : 'border-color: #cbd5e1;'"
                             style="border: 2px dashed; border-radius: 10px; padding: 1rem; text-align: center; cursor: pointer; position: relative; transition: all 0.2s;"
                             @click="$refs.soporteInput.click()">
                            
                            <input type="file" 
                                   name="soporte" 
                                   x-ref="soporteInput"
                                   accept="image/*,application/pdf" 
                                   style="display: none;" 
                                   @change="handleFileSelected($event)">
                            
                            <div x-show="!soportePreviewUrl" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.25rem;">
                                <span style="font-size: 1.5rem;">📁</span>
                                <span style="font-size: 0.78rem; font-weight: 600; color: #475569;">Arrastra un archivo, búscalo o pega un pantallazo (Ctrl+V)</span>
                                <span style="font-size: 0.65rem; color: #94a3b8;">Formatos permitidos: JPG, PNG, PDF (Máx. 10MB)</span>
                            </div>

                            <div x-show="soportePreviewUrl" style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem;" @click.stop>
                                <template x-if="soporteEsImagen">
                                    <img :src="soportePreviewUrl" style="max-height: 120px; border-radius: 6px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                </template>
                                <template x-if="!soporteEsImagen">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; background: #f1f5f9; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #cbd5e1;">
                                        <span style="font-size: 1.2rem;">📄</span>
                                        <span style="font-size: 0.75rem; font-weight: 600; color: #334155;" x-text="soporteFileName"></span>
                                    </div>
                                </template>
                                <button type="button" @click="quitarSoporte()" style="background: #ef4444; color: #fff; border: none; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.7rem; font-weight: 600; cursor: pointer; transition: background 0.1s;">
                                    Quitar soporte
                                </button>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom:1rem;">
                        <label style="display:block; margin-bottom:0.35rem; font-size:0.8rem; font-weight:600; color:#334155;">Observación:</label>
                        <textarea name="observacion" rows="2" placeholder="Ej. Pago correspondiente a varias mensualidades o saldo restante..." style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.8rem; outline:none; box-sizing:border-box; resize:none;"></textarea>
                    </div>

                    {{-- Formulario Dinámico de Meses --}}
                    <div style="margin-bottom:1rem; border-top:1px solid #cbd5e1; padding-top:1rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                            <label style="display:block; font-size:0.8rem; font-weight:700; color:#334155;">Detalle de Meses a Pagar:</label>
                            <button type="button" @click="agregarFilaAbono()" style="background:none; border:1px dashed #3b82f6; border-radius:6px; color:#2563eb; font-size:0.75rem; padding:0.25rem 0.5rem; cursor:pointer; font-weight:600;">
                                ➕ Agregar Mes
                            </button>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            <template x-for="(item, index) in itemsAbono" :key="index">
                                <div style="display:flex; gap:0.35rem; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; padding:0.5rem; border-radius:8px;">
                                    {{-- Mes --}}
                                    <div style="width: 80px;">
                                        <select :name="'pago_items['+index+'][mes]'" x-model="item.mes" style="width:100%; padding:0.35rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.75rem; background:#fff;">
                                            @foreach(range(1,12) as $m)
                                                <option value="{{ $m }}">{{ ucfirst(\Carbon\Carbon::create()->month($m)->locale('es')->shortMonthName) }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- Monto --}}
                                    <div style="flex:1;">
                                        <input type="text" :name="'pago_items['+index+'][monto]'" required placeholder="$ Abono" :value="item.monto" @input="event => { event.target.value = formatMoney(event.target.value); item.monto = event.target.value; calcularTotalAbono(); }" style="width:100%; padding:0.35rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.75rem; text-align:right; box-sizing:border-box;">
                                    </div>

                                    {{-- Estado --}}
                                    <div style="width: 100px;">
                                        <select :name="'pago_items['+index+'][estado]'" x-model="item.estado" style="width:100%; padding:0.35rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.75rem; background:#fff;">
                                            <option value="completo">Completo</option>
                                            <option value="pendiente">Abono (Pendiente)</option>
                                        </select>
                                    </div>

                                    {{-- Saldo Pendiente --}}
                                    <div style="width: 90px;" x-show="item.estado === 'pendiente'">
                                        <input type="text" :name="'pago_items['+index+'][saldo_pendiente]'" :required="item.estado === 'pendiente'" placeholder="$ Deuda" :value="item.saldo_pendiente" @input="event => { event.target.value = formatMoney(event.target.value); item.saldo_pendiente = event.target.value; }" style="width:100%; padding:0.35rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.75rem; text-align:right; box-sizing:border-box; color:#ef4444;">
                                    </div>

                                    {{-- Eliminar --}}
                                    <div>
                                        <button type="button" @click="eliminarFilaAbono(index)" style="background:none; border:none; color:#ef4444; font-size:1.2rem; line-height:1; cursor:pointer; padding:0 0.2rem;" title="Eliminar fila">&times;</button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div style="margin-top:0.75rem; border-top:1px solid #e2e8f0; padding-top:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.78rem; font-weight:700; color:#475569;">Total Abono (Suma de Meses):</span>
                            <span style="font-size:0.95rem; font-weight:800; color:#10b981;" x-text="formatMoney(montoTotalAbono)"></span>
                        </div>
                    </div>
                </form>

            </div>

            <!-- Footer fijo inferior (Visible siempre) -->
            <div x-show="pagosMes.length === 0 || (pagosMes.length > 0 && pagosMes[pagosMes.length - 1].estado === 'pendiente')" 
                 class="modal-foot-bx" 
                 style="flex-shrink: 0; display:flex; justify-content:end; gap:0.5rem; padding:1rem; border-top:1px solid #cbd5e1; background:#f8fafc;">
                <button type="button" @click="openPagoModal = false" class="btn-glass-bx" style="padding:0.45rem 1rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.78rem; font-weight:600; cursor:pointer; background:#fff; color:#475569;">Cancelar</button>
                <button type="submit" form="registrar-recibo-form" class="btn-glass-bx" style="padding:0.45rem 1rem; border:none; border-radius:8px; font-size:0.78rem; font-weight:600; cursor:pointer; background:#10b981; color:#fff;">Registrar Pago</button>
            </div>

            <div x-show="pagosMes.length > 0 && pagosMes[pagosMes.length - 1].estado === 'completo'" 
                 class="modal-foot-bx" 
                 style="flex-shrink: 0; display:flex; justify-content:end; padding:1rem; border-top:1px solid #cbd5e1; background:#f8fafc;">
                <button type="button" @click="openPagoModal = false" class="btn-glass-bx" style="padding:0.45rem 1rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.78rem; font-weight:600; cursor:pointer; background:#fff; color:#475569;">Cerrar</button>
            </div>
        </div>
    </div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1400px; margin: 0 auto; padding: 0.5rem; }
.btn-fin-link { text-decoration: none; padding: 0.4rem 0.85rem; border-radius: 8px; font-size: 0.78rem; font-weight: 600; text-align: center; }
.btn-fin-link.primary { background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3); color: var(--azul-btn); }
.btn-fin-link.success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #166534; }

/* Tabla Excel */
.card-tabla { background: #fff; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.04); margin-top: 1rem; }
.tabla-excel { width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left; table-layout: fixed; }
.tabla-excel th, .tabla-excel td { border: 1px solid #e2e8f0; padding: 0.6rem 0.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tabla-excel th { background: #f8fafc; font-weight: 700; color: #475569; text-align: center; }
.tabla-excel th.fuente-col { width: 150px; text-align: left; }
.tabla-excel th.mes-col { width: 80px; }
.tabla-excel th.total-col { width: 110px; background: #f1f5f9; color: #1e293b; }

.fuente-name-cell { display: flex; flex-direction: column; gap: 0.2rem; }
.excel-cell { text-align: right; cursor: pointer; transition: background 0.1s; position: relative; padding: 0 !important; }
.excel-cell:hover { background: #f8fafc; }
.excel-cell.editing { background: #fff; box-shadow: inset 0 0 0 2px #3b82f6; z-index: 10; }
.excel-cell.calculated-cell { background: #f8fafc; cursor: default; }

.cell-val { padding: 0.6rem 0.5rem; color: #0f172a; font-weight: 500; }
.cell-input { width: 100%; height: 100%; border: none; padding: 0.6rem 0.5rem; text-align: right; font-size: 0.8rem; font-weight: 600; outline: none; background: transparent; }

.fin-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.breadcrumb-bx { display: flex; gap: 0.4rem; font-size: 0.75rem; color: #64748b; align-items: center; }
.breadcrumb-bx a { text-decoration: none; color: #475569; font-weight: 500; }
.breadcrumb-bx a:hover { color: #1e293b; }

.period-selector-bx { display: flex; align-items: center; }
.select-fin { background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.35rem 1.5rem 0.35rem 0.75rem; font-size: 0.78rem; font-weight: 600; color: #334155; outline: none; cursor: pointer; }

.fin-header-section { margin-bottom: 1.25rem; }
.fin-title { font-size: 1.3rem; font-weight: 700; color: #0f172a; margin: 0 0 0.25rem 0; }
.fin-subtitle { font-size: 0.78rem; color: #64748b; margin: 0; }

[x-cloak] { display: none !important; }

/* Clases de Modales Centrados */
.modal-overlay-bx {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 9999;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    box-sizing: border-box;
}

.modal-box-bx {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    width: 100%;
    overflow: hidden;
    white-space: normal;
    margin: auto;
    display: flex;
    flex-direction: column;
}
</style>
@endpush

@push('scripts')
<script>
function brynexAliadosGrid() {
    return {
        editingCell: { aliado_id: null, mes: null },
        openAddAliadoModal: false,
        openEditAliadoModal: false,
        openPagoModal: false,
        editAliadoId: null,
        editAliadoNombre: '',
        editAliadoNit: '',
        editAliadoFechaInicio: '',
        editAliadoFechaFin: '',
        
        pagoAliadoId: null,
        pagoAliadoNombre: '',
        pagoMes: null,
        pagoMesNombre: '',
        pagosMes: [],
        
        dragOver: false,
        soportePreviewUrl: '',
        soporteEsImagen: false,
        soporteFileName: '',
        
        itemsAbono: [],
        montoTotalAbono: 0,
        
        openEditModal(aliado) {
            this.editAliadoId = aliado.id;
            this.editAliadoNombre = aliado.nombre;
            this.editAliadoNit = aliado.nit;
            this.editAliadoFechaInicio = aliado.fecha_inicio;
            this.editAliadoFechaFin = aliado.fecha_fin;
            this.openEditAliadoModal = true;
        },

        abrirPago(aliado, mesNum, mesNombre, pagosList) {
            this.pagoAliadoId = aliado.id;
            this.pagoAliadoNombre = aliado.nombre;
            this.pagoMes = mesNum;
            this.pagoMesNombre = mesNombre;
            this.pagosMes = pagosList || [];
            this.quitarSoporte();

            if (this.pagosMes.length > 0) {
                const ultimoPago = this.pagosMes[this.pagosMes.length - 1];
                if (ultimoPago.estado === 'pendiente' && ultimoPago.saldo_pendiente > 0) {
                    this.itemsAbono = [{
                        mes: mesNum,
                        monto: this.formatMoney(ultimoPago.saldo_pendiente),
                        estado: 'completo',
                        saldo_pendiente: ''
                    }];
                } else {
                    this.itemsAbono = [];
                }
            } else {
                this.itemsAbono = [{
                    mes: mesNum,
                    monto: '',
                    estado: 'completo',
                    saldo_pendiente: ''
                }];
            }
            this.calcularTotalAbono();
            this.openPagoModal = true;
        },

        handleFileSelected(event) {
            const files = event.target.files;
            if (files && files.length > 0) {
                this.procesarArchivoSoporte(files[0]);
            }
        },

        handlePaste(event) {
            if (!this.openPagoModal) return;
            const isFormVisible = this.pagosMes.length === 0 || (this.pagosMes.length > 0 && this.pagosMes[this.pagosMes.length - 1].estado === 'pendiente');
            if (!isFormVisible) return;

            const clipboardItems = event.clipboardData.items;
            for (let i = 0; i < clipboardItems.length; i++) {
                if (clipboardItems[i].type.indexOf('image') !== -1) {
                    const file = clipboardItems[i].getAsFile();
                    this.procesarArchivoSoporte(file);
                    
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    if (this.$refs.soporteInput) {
                        this.$refs.soporteInput.files = dataTransfer.files;
                    }
                    break;
                }
            }
        },

        handleDrop(event) {
            this.dragOver = false;
            const files = event.dataTransfer.files;
            if (files && files.length > 0) {
                this.procesarArchivoSoporte(files[0]);
                
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(files[0]);
                if (this.$refs.soporteInput) {
                    this.$refs.soporteInput.files = dataTransfer.files;
                }
            }
        },

        procesarArchivoSoporte(file) {
            if (!file) return;
            this.soporteFileName = file.name || 'soporte_pegado.png';
            this.soporteEsImagen = file.type.startsWith('image/');
            
            if (this.soportePreviewUrl) {
                URL.revokeObjectURL(this.soportePreviewUrl);
            }
            this.soportePreviewUrl = URL.createObjectURL(file);
        },

        quitarSoporte() {
            if (this.soportePreviewUrl) {
                URL.revokeObjectURL(this.soportePreviewUrl);
            }
            this.soportePreviewUrl = '';
            this.soporteEsImagen = false;
            this.soporteFileName = '';
            if (this.$refs.soporteInput) {
                this.$refs.soporteInput.value = '';
            }
        },

        eliminarRecibo(reciboId) {
            if (!reciboId) return;
            if (confirm('¿Estás seguro de eliminar este pago? Se borrarán todos los pagos distribuidos con su respectivo recibo.')) {
                const form = document.getElementById('delete-recibo-form');
                form.action = "{{ route('finanzas.brynex-aliados.delete-recibo', '') }}/" + reciboId;
                form.submit();
            }
        },

        agregarFilaAbono() {
            let ultimoMes = 1;
            if (this.itemsAbono.length > 0) {
                ultimoMes = parseInt(this.itemsAbono[this.itemsAbono.length - 1].mes) % 12 + 1;
            }
            this.itemsAbono.push({
                mes: ultimoMes,
                monto: '',
                estado: 'completo',
                saldo_pendiente: ''
            });
            this.calcularTotalAbono();
        },

        eliminarFilaAbono(index) {
            this.itemsAbono.splice(index, 1);
            this.calcularTotalAbono();
        },

        calcularTotalAbono() {
            let total = 0;
            this.itemsAbono.forEach(item => {
                let clean = item.monto.toString().replace(/\D/g, '');
                if (clean) {
                    total += parseInt(clean);
                }
            });
            this.montoTotalAbono = total;
        },
        
        isEditing(aliadoId, mes) {
            return this.editingCell.aliado_id === aliadoId && this.editingCell.mes === mes;
        },
        
        editCell(aliadoId, mes, tdEl) {
            this.editingCell = { aliado_id: aliadoId, mes: mes };
            this.$nextTick(() => {
                const input = this.$refs[`input_${aliadoId}_${mes}`];
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        },
        
        cancelEdit() {
            this.editingCell = { aliado_id: null, mes: null };
        },
        
        formatMoney(value) {
            let num = value.toString().replace(/\D/g, '');
            if (num === '') return '';
            return '$' + parseInt(num).toLocaleString('es-CO', { maximumFractionDigits: 0 });
        },

        async saveCell(aliadoId, mes, val) {
            const cleanVal = val.toString().replace(/\D/g, '');
            const monto = parseFloat(cleanVal) || 0;
            this.cancelEdit();

            try {
                const response = await fetch("{{ route('finanzas.brynex-aliados.save-cell') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        aliado_id: aliadoId,
                        anio: {{ $anio }},
                        mes: mes,
                        monto: monto
                    })
                });

                if (response.ok) {
                    window.location.reload();
                } else {
                    alert("Error al guardar el monto. Verifica la conexión.");
                }
            } catch (error) {
                console.error(error);
                alert("Error de red al intentar guardar.");
            }
        }
    }
}
</script>
@endpush
