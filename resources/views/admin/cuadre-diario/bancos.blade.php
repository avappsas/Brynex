@extends('layouts.app')
@section('modulo', 'Saldos Bancarios')

@php
$fmt = fn($v) => '$'.number_format(abs($v ?? 0), 0, ',', '.');
$meses = collect(range(0,11))->map(fn($i) => now()->startOfMonth()->subMonths($i)->format('Y-m'));
// Filtrar bancos SIN movimientos en el mes
$saldosConMov = $saldos->filter(fn($sb) => $sb['movimientos']->isNotEmpty());
@endphp

@section('contenido')
<style>
.bk-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1rem}
table.tbl{width:100%;border-collapse:collapse;font-size:.78rem}
.tbl th{background:#0f172a;color:#94a3b8;font-size:.62rem;text-transform:uppercase;padding:.45rem .55rem;
        position:sticky;top:0;white-space:nowrap;text-align:center}
.tbl td{padding:.38rem .55rem;border-bottom:1px solid #f1f5f9;vertical-align:middle;text-align:center}
.tbl tr:hover td{background:#f8fafc}
.badge{padding:.1rem .4rem;border-radius:12px;font-size:.65rem;font-weight:700;white-space:nowrap;display:inline-block}
.btn-sm{padding:.18rem .5rem;font-size:.71rem;border-radius:6px;border:none;cursor:pointer;font-weight:600}
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center}
.modal-bg.open{display:flex}
.modal-box{background:#fff;border-radius:14px;width:min(580px,96vw);max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.35)}
.modal-box.lg{width:min(900px,98vw);max-height:94vh}
.modal-head{background:#1e3a5f;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center}
.modal-body{padding:1rem;overflow-y:auto;flex:1}
.btn-close{background:rgba(255,255,255,.18);color:#fff;border:none;border-radius:5px;width:28px;height:28px;cursor:pointer;font-weight:800;font-size:1rem}
.btn-filtro.active{background:#1e3a5f !important;color:#fff !important;border-color:#1e3a5f !important}
/* Toast Notification */
.toast-notif{position:fixed;bottom:20px;right:20px;background:#0f172a;color:#fff;padding:.75rem 1.25rem;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,.2);z-index:99999;font-size:.82rem;font-weight:600;display:flex;align-items:center;gap:.5rem;transform:translateY(150%);transition:transform .3s cubic-bezier(0.16,1,0.3,1)}
.toast-notif.show{transform:translateY(0)}
.zoomable-img{max-width:100%;max-height:100%;object-fit:contain;cursor:zoom-in;transition:width .15s ease,height .15s ease}
.zoomable-img.zoomed{max-width:none !important;max-height:none !important;width:180% !important;height:auto !important;cursor:zoom-out}
.img-zoom-wrapper{height:360px;width:100%;overflow:auto;display:flex;align-items:center;justify-content:center;border-radius:6px;background:#fff;position:relative;user-select:none}
.img-zoom-wrapper.zoomed-mode{display:block !important;cursor:grab}
</style>

{{-- HEADER --}}
<div class="bk-header">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.6rem">
        <div>
            <a href="{{ route('admin.cuadre-diario.index') }}" style="color:#94a3b8;font-size:.78rem;text-decoration:none">← Cuadre Diario</a>
            <div style="font-size:1.1rem;font-weight:800;margin-top:.2rem">🏦 Saldos Bancarios</div>
        </div>
        <form method="GET" style="display:flex;align-items:center;gap:.5rem">
            <label style="font-size:.78rem;color:#94a3b8">Mes:</label>
            <select name="mes" onchange="this.form.submit()"
                    style="border-radius:7px;padding:.3rem .6rem;font-size:.82rem;border:1px solid #334155;background:#0f172a;color:#fff">
                @foreach($meses as $m)
                <option value="{{ $m }}" @selected($m === $mes)>
                    {{ \Carbon\Carbon::createFromFormat('Y-m', $m)->locale('es')->isoFormat('MMMM YYYY') }}
                </option>
                @endforeach
            </select>
        </form>
    </div>
</div>



@if($saldosConMov->isEmpty())
<div style="background:#fff;border-radius:12px;border:2px dashed #e2e8f0;padding:2rem;text-align:center;color:#94a3b8">
    Sin movimientos bancarios en este mes
</div>
@endif

@foreach($saldosConMov as $sb)
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:.9rem">
    {{-- Header banco --}}
    <div style="padding:.7rem 1rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.4rem">
        <div>
            <span style="font-size:.93rem;font-weight:800">🏦 {{ $sb['banco']->banco }}
                @php $nSinB = trim(str_ireplace($sb['banco']->banco, '', $sb['banco']->nombre ?? '')); @endphp
                @if($nSinB)<span style="font-weight:600"> {{ $nSinB }}</span>@endif
            </span>
            @if($sb['banco']->numero_cuenta)
            <span style="font-size:.72rem;color:#64748b;margin-left:.5rem">— {{ $sb['banco']->numero_cuenta }}</span>
            @endif
        </div>
        <div style="text-align:right">
            <div id="saldo-banco-{{ $sb['banco']->id }}" style="font-size:1.3rem;font-weight:800;color:{{ $sb['saldo'] >= 0 ? '#1d4ed8' : '#dc2626' }}">
                {{ $fmt($sb['saldo']) }}
            </div>
            <div style="font-size:.68rem;color:#94a3b8">Saldo total histórico</div>
        </div>
    </div>

    {{-- Filtros de estado interactivos --}}
    <div style="padding:.5rem 1rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <span style="font-size:.68rem;font-weight:700;color:#64748b;text-transform:uppercase">Filtrar:</span>
        <button type="button" class="btn-filtro active" onclick="filtrarTabla({{ $sb['banco']->id }}, 'todos', this)" 
                style="padding:.2rem .6rem;font-size:.7rem;border-radius:20px;border:1px solid #cbd5e1;background:#fff;color:#475569;cursor:pointer;font-weight:600">
            Todos
        </button>
        <button type="button" class="btn-filtro" onclick="filtrarTabla({{ $sb['banco']->id }}, 'pendiente', this)"
                style="padding:.2rem .6rem;font-size:.7rem;border-radius:20px;border:1px solid #cbd5e1;background:#fff;color:#475569;cursor:pointer;font-weight:600">
            🕐 Pendientes
        </button>
        <button type="button" class="btn-filtro" onclick="filtrarTabla({{ $sb['banco']->id }}, 'no_aparece', this)"
                style="padding:.2rem .6rem;font-size:.7rem;border-radius:20px;border:1px solid #cbd5e1;background:#fff;color:#475569;cursor:pointer;font-weight:600">
            ❌ No confirmados
        </button>
        <button type="button" class="btn-filtro" onclick="filtrarTabla({{ $sb['banco']->id }}, 'verificado', this)"
                style="padding:.2rem .6rem;font-size:.7rem;border-radius:20px;border:1px solid #cbd5e1;background:#fff;color:#475569;cursor:pointer;font-weight:600">
            ✅ Verificados
        </button>
    </div>

    <div style="overflow-x:auto">
    <table class="tbl">
        <thead><tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Fact.</th>
            <th style="text-align:left;padding-left:.8rem">Cliente / Empresa</th>
            <th style="text-align:left;padding-left:.8rem">Descripción</th>
            <th>Registró</th>
            <th style="text-align:right;padding-right:.8rem">Valor</th>
            <th>Estado</th>
            <th>Img.</th>
        </tr></thead>
        <tbody>
        @foreach($sb['movimientos'] as $mov)
        @php
            $estadoFila = $mov->es_salida ? 'verificado' : ($mov->confirmado ? 'verificado' : ($mov->no_aparece ? 'no_aparece' : 'pendiente'));
            $rowId = $mov->es_salida ? 'gasto-row-' . $mov->id : 'consignacion-row-' . $mov->cs_id;
        @endphp
        <tr id="{{ $rowId }}" data-estado="{{ $estadoFila }}">
            {{-- Fecha --}}
            <td style="font-size:.73rem;white-space:nowrap;color:#64748b">
                {{ sqldate($mov->fecha)->format('d/m/Y') }}
            </td>

            {{-- Tipo --}}
            <td>
                @if(!$mov->es_salida)
                @php
                    $tipoLabel = match($mov->tipo ?? 'cliente') {
                        'cliente'            => ['label' => 'Pago SS',    'color' => '#1d4ed8', 'bg' => '#dbeafe', 'icon' => '📥'],
                        'anticipo'           => ['label' => 'Anticipo',   'color' => '#92400e', 'bg' => '#fef3c7', 'icon' => '💰'],
                        'traslado_efectivo'  => ['label' => 'Ef→Banco',   'color' => '#1d4ed8', 'bg' => '#dbeafe', 'icon' => '📥'],
                        'banco_recibido'     => ['label' => 'T. entrada', 'color' => '#1d4ed8', 'bg' => '#dbeafe', 'icon' => '📥'],
                        default              => ['label' => ucfirst($mov->tipo ?? ''), 'color' => '#1d4ed8', 'bg' => '#dbeafe', 'icon' => '📥'],
                    };
                @endphp
                <span class="badge" style="background:{{ $tipoLabel['bg'] }};color:{{ $tipoLabel['color'] }}">
                    {{ $tipoLabel['icon'] }} {{ $tipoLabel['label'] }}
                </span>
                @else
                <span class="badge" style="background:#fee2e2;color:#dc2626">
                    📤 {{ str_contains($mov->tipo ?? '','banco') ? 'Transferencia' : ucfirst(str_replace('_',' ',$mov->tipo ?? '')) }}
                </span>
                @endif
            </td>

            {{-- Factura / Anticipo --}}
            <td>
                @if(($mov->tipo ?? '') === 'anticipo' && ($mov->anticipo_id ?? null))
                    <div style="display:flex;flex-direction:column;align-items:center;gap:.15rem">
                        <a href="#" onclick="abrirReciboAnticipo({{ $mov->anticipo_id }}); return false;"
                           title="Ver Recibo de Anticipo"
                           style="font-weight:700;font-size:.78rem;color:#b45309;text-decoration:none;background:#fef3c7;border-radius:4px;padding:1px 5px;border:1px solid #fde68a">
                            💰 #{{ $mov->anticipo_id }}
                        </a>
                        @if($mov->anticipo_factura_id ?? null)
                            <div style="display:flex;align-items:center;gap:.1rem;font-size:.62rem;color:#64748b">
                                <span>Aplicado →</span>
                                <a href="#" onclick="abrirRecibo({{ $mov->anticipo_factura_id }}); return false;"
                                   style="font-weight:700;color:#2563eb;text-decoration:none">
                                    📋 #{{ $mov->anticipo_factura_num }}
                                </a>
                            </div>
                        @else
                            <span style="font-size:.6rem;color:#047857;font-weight:700;background:#dcfce7;border-radius:3px;padding:0 4px;border:1px solid #bbf7d0">Disponible</span>
                        @endif
                    </div>
                @elseif($mov->num_factura)
                    <a href="#" onclick="abrirRecibo({{ $mov->factura_id }}); return false;"
                       style="font-weight:700;font-size:.78rem;color:#2563eb;text-decoration:none">
                        📋 #{{ $mov->num_factura }}
                    </a>
                @else
                    <span style="color:#cbd5e1;font-size:.72rem">—</span>
                @endif
            </td>

            {{-- Cliente / Empresa --}}
            <td style="font-size:.77rem;max-width:175px;text-align:left;padding-left:.8rem">
                @if($mov->es_salida ?? false)
                    <span style="color:#64748b">{{ $mov->pagador ?? '—' }}</span>
                @elseif($mov->pagador ?? null)
                    @if($mov->es_empresa ?? false)
                        <span title="Empresa" style="font-size:.72rem">🏢</span>
                        <span style="font-weight:700;color:#1e40af">{{ $mov->pagador }}</span>
                    @else
                        <span title="Cliente" style="font-size:.72rem">👤</span>
                        <span style="font-weight:600;color:#1e293b">{{ $mov->pagador }}</span>
                    @endif
                @else
                    <span style="color:#cbd5e1">—</span>
                @endif
            </td>

            {{-- Descripción — izquierda --}}
            <td class="celda-descripcion" style="text-align:left;font-size:.73rem;color:#64748b;max-width:180px;padding-left:.8rem">
                {{ $mov->descripcion ? \Str::limit($mov->descripcion, 55) : '—' }}
            </td>

            {{-- Registró --}}
            <td style="font-size:.73rem;color:#64748b;white-space:nowrap">
                {{ $mov->usuario ?? '—' }}
            </td>

            {{-- Valor — alineado a la derecha con número visible --}}
            <td style="text-align:right;padding-right:.8rem;font-family:monospace;font-weight:700;font-size:.85rem;
                       color:{{ $mov->es_salida ? '#dc2626' : '#15803d' }};white-space:nowrap">
                {{ $mov->es_salida ? '-' : '+' }}{{ $fmt($mov->valor) }}
            </td>

            {{-- Estado (clic abre modal — solo admin/superadmin pueden cambiar) --}}
            <td>
                @if(!$mov->es_salida)
                @php
                    $bgEstado = '#fef3c7'; $colEstado = '#b45309'; $txtEstado = '🕐 Pendiente';
                    if ($mov->confirmado) { $bgEstado = '#dcfce7'; $colEstado = '#15803d'; $txtEstado = '✅ Verificado'; }
                    elseif ($mov->no_aparece) { $bgEstado = '#fee2e2'; $colEstado = '#dc2626'; $txtEstado = '❌ No aparece'; }
                @endphp
                @if(auth()->user()->hasRole(['admin','superadmin']))
                <button type="button"
                        onclick="abrirModalEstado({{ $mov->cs_id }}, {{ $mov->confirmado ? 'true' : 'false' }}, {{ $mov->no_aparece ? 'true' : 'false' }}, {{ $mov->imagen_path ? "'".asset('storage/' . $mov->imagen_path)."'" : 'null' }})"
                        class="btn-sm btn-estado-clic"
                        style="background:{{ $bgEstado }};color:{{ $colEstado }}">
                    {{ $txtEstado }}
                </button>
                @else
                <span class="badge" style="background:{{ $bgEstado }};color:{{ $colEstado }}">
                    {{ $txtEstado }}
                </span>
                @endif
                @else
                <span style="font-size:.72rem;color:#94a3b8">—</span>
                @endif
            </td>

            {{-- Imagen --}}
            <td style="white-space:nowrap">
                @if($mov->imagen_path ?? null)
                <button type="button" class="btn-sm" style="background:#dbeafe;color:#1d4ed8"
                        onclick="verImagen('{{ asset('storage/' . $mov->imagen_path) }}', {{ $mov->id }}, {{ $mov->es_gasto ? 'true' : 'false' }})">
                    🖼 Ver
                </button>
                @else
                <button type="button" class="btn-sm" style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0"
                        onclick="abrirSubirImagen({{ $mov->id }}, {{ $mov->es_gasto ? 'true' : 'false' }}, false)">
                    📎 Adjuntar
                </button>
                @endif
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
@endforeach

{{-- ═══ MODAL: Estado consignación ═══ --}}
<div id="modal-estado" class="modal-bg" onclick="if(event.target===this)cerrarModal('modal-estado')">
    <div class="modal-box" id="modal-estado-box" style="width:min(820px,96vw);transition:width .22s cubic-bezier(0.16,1,0.3,1)">
        <div class="modal-head">
            <span style="color:#fff;font-weight:700;font-size:.9rem">🏦 Estado de consignación</span>
            <button onclick="cerrarModal('modal-estado')" class="btn-close">×</button>
        </div>
        <div class="modal-body" style="display:flex;gap:1.2rem;padding:1.2rem;flex-wrap:wrap">
            <!-- Columna Izquierda: Visor del comprobante -->
            <div id="modal-estado-comp-container" style="flex:1.4;border:1px solid #e2e8f0;border-radius:10px;padding:.5rem;text-align:center;background:#f8fafc;display:none;min-width:280px">
                <div style="font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:.4rem;text-transform:uppercase">Comprobante Adjunto (Clic para Zoom):</div>
                <div id="modal-estado-img-wrapper" class="img-zoom-wrapper">
                    <img id="modal-estado-img" src="" onclick="toggleZoomImg(this)" class="zoomable-img" style="display:none" alt="Comprobante">
                    <iframe id="modal-estado-pdf" src="" style="width:100%;height:100%;border:none;display:none"></iframe>
                </div>
            </div>

            <!-- Columna Derecha: Opciones y estado -->
            <div id="modal-estado-opciones" style="flex:1;display:flex;flex-direction:column;justify-content:center;gap:1rem;min-width:240px">
                <div>
                    <p style="font-size:.88rem;color:#1e293b;margin:0 0 .5rem 0;font-weight:700">¿Cambiar el estado de esta consignación?</p>
                    <div id="estado-actual" style="text-align:center;font-size:.85rem;font-weight:600;padding:.6rem;border-radius:8px"></div>
                </div>
                
                <div style="display:flex;flex-direction:column;gap:.5rem">
                    <button type="button" onclick="cambiarEstadoConsignacion('verificar')" 
                            style="width:100%;padding:.7rem;background:#16a34a;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.85rem">
                        ✅ Marcar Verificado
                    </button>
                    <button type="button" onclick="cambiarEstadoConsignacion('pendiente')" 
                            style="width:100%;padding:.7rem;background:#f59e0b;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.85rem">
                        🕐 Marcar Pendiente
                    </button>
                    <button type="button" onclick="cambiarEstadoConsignacion('no-aparece')" 
                            style="width:100%;padding:.7rem;background:#dc2626;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.85rem">
                        ❌ No aparece en banco
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ MODAL: Ver imagen ═══ --}}
<div id="modal-img" class="modal-bg" onclick="if(event.target===this)cerrarModal('modal-img')">
    <div class="modal-box">
        <div class="modal-head">
            <span style="color:#fff;font-weight:700;font-size:.9rem">🖼 Comprobante</span>
            <div style="display:flex;gap:.5rem;align-items:center">
                @if(auth()->user()->hasRole(['admin','superadmin']))
                <button id="btn-reemplazar-modal-img" type="button" class="btn-sm" style="background:#f59e0b;color:#fff;font-weight:700;display:none">
                    🔄 Reemplazar
                </button>
                @endif
                <button onclick="cerrarModal('modal-img')" class="btn-close">×</button>
            </div>
        </div>
        <div class="modal-body img-zoom-wrapper" id="modal-img-wrapper" style="height:70vh">
            <img id="img-preview" src="" onclick="toggleZoomImg(this)" class="zoomable-img" style="display:none" alt="comprobante">
            <iframe id="pdf-preview" src="" style="display:none;width:100%;height:100%;border:none"></iframe>
        </div>
    </div>
</div>

{{-- ═══ MODAL: Subir / Reemplazar imagen ═══ --}}
<div id="modal-subir" class="modal-bg" onclick="if(event.target===this)cerrarModal('modal-subir')">
    <div class="modal-box" style="width:min(440px,96vw)">
        <div class="modal-head" id="modal-subir-head">
            <span id="modal-subir-titulo" style="color:#fff;font-weight:700;font-size:.9rem">📎 Adjuntar comprobante</span>
            <button onclick="cerrarModal('modal-subir')" class="btn-close">×</button>
        </div>
        <div class="modal-body">
            <form id="form-subir" method="POST" enctype="multipart/form-data" onsubmit="return subirComprobanteSubmit()">
                @csrf

                {{-- Badge reemplazar --}}
                <div id="badge-reemplazar" style="display:none;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;
                     padding:.4rem .75rem;font-size:.75rem;font-weight:700;color:#92400e;margin-bottom:.7rem">
                    ⚠️ Reemplazando el comprobante actual
                </div>

                {{-- Drop zone --}}
                <div id="drop-zone-subir"
                     onclick="document.getElementById('file-input-subir').click()"
                     style="border:2px dashed #3b82f6;border-radius:11px;padding:1.1rem;text-align:center;
                            cursor:pointer;background:#eff6ff;transition:background .15s;margin-bottom:.7rem;position:relative">
                    <div style="font-size:1.5rem">📁</div>
                    <div id="drop-label-subir" style="font-size:.8rem;color:#2563eb;font-weight:700;margin-top:.3rem">
                        Clic, arrastra o pega (Ctrl+V)
                    </div>
                    <div style="font-size:.67rem;color:#93c5fd;margin-top:.2rem">JPG, PNG o PDF · máx 5 MB</div>
                    <input type="file" id="file-input-subir" name="imagen" accept="image/*,.pdf"
                           style="display:none" onchange="onFileSubir(this.files[0])">
                </div>

                {{-- Preview --}}
                <div id="preview-subir" style="display:none;margin-bottom:.7rem;position:relative">
                    <img id="img-subir" src="" alt="preview"
                         style="max-width:100%;max-height:180px;border-radius:9px;border:1px solid #e2e8f0;object-fit:contain;display:block">
                    <div id="pdf-subir" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;
                         border-radius:9px;padding:.6rem;font-size:.78rem;color:#374151;font-weight:600">
                        📄 <span id="pdf-name-subir"></span>
                    </div>
                    <button type="button" onclick="clearFileSubir()"
                            style="position:absolute;top:4px;right:4px;background:#dc2626;color:#fff;
                                   border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;
                                   font-size:.75rem;font-weight:800;line-height:1">×</button>
                </div>

                <div id="error-subir" style="display:none;background:#fee2e2;border:1px solid #fca5a5;
                     border-radius:8px;padding:.4rem .7rem;font-size:.75rem;color:#dc2626;margin-bottom:.6rem"></div>

                <button type="submit" id="btn-subir-comp"
                        style="width:100%;padding:.6rem;background:#1d4ed8;color:#fff;border:none;
                               border-radius:9px;font-weight:800;cursor:pointer;font-size:.85rem">
                    📤 Subir comprobante
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ═══ MODAL: Recibo factura ═══ --}}
<div id="modal-recibo" class="modal-bg" onclick="if(event.target===this)cerrarModal('modal-recibo')">
    <div class="modal-box lg">
        <div class="modal-head">
            <span style="color:#fff;font-weight:700;font-size:.9rem">📋 Recibo de Factura</span>
            <div style="display:flex;gap:.4rem;align-items:center">
                <a id="btn-abrir-recibo" href="#" target="_blank"
                   style="background:rgba(255,255,255,.18);color:#fff;text-decoration:none;border-radius:5px;padding:.3rem .7rem;font-size:.78rem;font-weight:600">
                    🔗 Abrir
                </a>
                <button onclick="cerrarModal('modal-recibo')" class="btn-close">×</button>
            </div>
        </div>
        <div style="padding:0;flex:1;overflow:hidden">
            <iframe id="iframe-recibo" src="" style="width:100%;height:82vh;border:none"></iframe>
        </div>
    </div>
</div>

<script>
const baseUrl = '{{ url('') }}';
let _subirFile = null;
let _subirPasteHandler = null;

function cerrarModal(id) {
    document.getElementById(id).classList.remove('open');
    if (id === 'modal-img') {
        document.getElementById('img-preview').src = '';
        document.getElementById('pdf-preview').src = '';
        resetZoomImg('img-preview', 'modal-img-wrapper');
    }
    if (id === 'modal-recibo') document.getElementById('iframe-recibo').src = '';
    if (id === 'modal-subir') {
        clearFileSubir();
        if (_subirPasteHandler) { document.removeEventListener('paste', _subirPasteHandler); _subirPasteHandler = null; }
    }
    if (id === 'modal-estado') {
        resetZoomImg('modal-estado-img', 'modal-estado-img-wrapper');
    }
}

let _currentCsId = null;

function abrirModalEstado(csId, esVerificado, noAparece, imagenUrl) {
    resetZoomImg('modal-estado-img', 'modal-estado-img-wrapper');
    _currentCsId = csId;
    
    // Configurar estado actual en el modal
    const div = document.getElementById('estado-actual');
    if (esVerificado) {
        div.textContent = '✅ Estado actual: Verificado';
        div.style.background = '#dcfce7'; div.style.color = '#15803d';
    } else if (noAparece) {
        div.textContent = '❌ Estado actual: No aparece en banco';
        div.style.background = '#fee2e2'; div.style.color = '#dc2626';
    } else {
        div.textContent = '🕐 Estado actual: Pendiente de verificar';
        div.style.background = '#fef3c7'; div.style.color = '#b45309';
    }
    
    // Configurar visor de imagen/PDF y tamaño del modal
    const box = document.getElementById('modal-estado-box');
    const container = document.getElementById('modal-estado-comp-container');
    const img = document.getElementById('modal-estado-img');
    const pdf = document.getElementById('modal-estado-pdf');
    
    if (imagenUrl) {
        if (box) box.style.width = 'min(820px, 96vw)';
        container.style.display = 'block';
        const isPdf = imagenUrl.toLowerCase().endsWith('.pdf');
        if (isPdf) {
            img.style.display = 'none';
            pdf.style.display = 'block';
            pdf.src = imagenUrl;
        } else {
            pdf.style.display = 'none';
            img.style.display = 'block';
            img.src = imagenUrl;
        }
    } else {
        if (box) box.style.width = 'min(380px, 96vw)';
        container.style.display = 'none';
        img.src = '';
        pdf.src = '';
    }
    
    document.getElementById('modal-estado').classList.add('open');
}

function verImagen(url, id, esGasto) {
    resetZoomImg('img-preview', 'modal-img-wrapper');
    const isPdf = url.toLowerCase().endsWith('.pdf');
    const img = document.getElementById('img-preview');
    const pdf = document.getElementById('pdf-preview');
    img.style.display = isPdf ? 'none' : 'block';
    pdf.style.display = isPdf ? 'block' : 'none';
    if (isPdf) { pdf.src = url; } else { img.src = url; }
    
    // Configurar botón reemplazar si se proveen ID y esGasto
    const btnReemplazar = document.getElementById('btn-reemplazar-modal-img');
    if (btnReemplazar) {
        if (id !== undefined && esGasto !== undefined) {
            btnReemplazar.style.display = 'inline-block';
            btnReemplazar.onclick = () => {
                cerrarModal('modal-img');
                abrirSubirImagen(id, esGasto, true);
            };
        } else {
            btnReemplazar.style.display = 'none';
        }
    }
    
    document.getElementById('modal-img').classList.add('open');
}

function abrirSubirImagen(id, esGasto, esReemplazar = false) {
    const url = esGasto
        ? `${baseUrl}/cuadre-diario/gasto/${id}/imagen`
        : `${baseUrl}/cuadre-diario/consignacion/${id}/imagen`;
    document.getElementById('form-subir').action = url;

    // Badge y título según si es reemplazar
    const badge = document.getElementById('badge-reemplazar');
    const titulo = document.getElementById('modal-subir-titulo');
    const head   = document.getElementById('modal-subir-head');
    if (esReemplazar) {
        badge.style.display = 'block';
        titulo.textContent  = '🔄 Reemplazar comprobante';
        head.style.background = '#92400e';
    } else {
        badge.style.display = 'none';
        titulo.textContent  = '📎 Adjuntar comprobante';
        head.style.background = '';
    }

    clearFileSubir();
    document.getElementById('error-subir').style.display = 'none';

    // Drop zone drag & drop
    const dz = document.getElementById('drop-zone-subir');
    dz.ondragover  = (e) => { e.preventDefault(); dz.style.background = '#dbeafe'; };
    dz.ondragleave = ()  => { dz.style.background = '#eff6ff'; };
    dz.ondrop      = (e) => { e.preventDefault(); dz.style.background = '#eff6ff'; const f = e.dataTransfer?.files?.[0]; if (f) onFileSubir(f); };

    // Ctrl+V paste
    if (_subirPasteHandler) document.removeEventListener('paste', _subirPasteHandler);
    _subirPasteHandler = (e) => {
        const item = [...(e.clipboardData?.items || [])].find(i => i.type.startsWith('image/'));
        if (item) onFileSubir(item.getAsFile());
    };
    document.addEventListener('paste', _subirPasteHandler);

    document.getElementById('modal-subir').classList.add('open');
}

function onFileSubir(file) {
    if (!file) return;
    _subirFile = file;
    const prev = document.getElementById('preview-subir');
    const img  = document.getElementById('img-subir');
    const pdf  = document.getElementById('pdf-subir');
    prev.style.display = 'block';
    if (file.type === 'application/pdf') {
        img.style.display = 'none'; pdf.style.display = 'block';
        document.getElementById('pdf-name-subir').textContent = file.name;
    } else {
        pdf.style.display = 'none'; img.style.display = 'block';
        img.src = URL.createObjectURL(file);
    }
    document.getElementById('drop-label-subir').textContent = '✅ ' + file.name;
}

function clearFileSubir() {
    _subirFile = null;
    const fi = document.getElementById('file-input-subir');
    if (fi) fi.value = '';
    document.getElementById('preview-subir').style.display = 'none';
    const img = document.getElementById('img-subir');
    if (img) img.src = '';
    document.getElementById('drop-label-subir').textContent = 'Clic, arrastra o pega (Ctrl+V)';
}

function subirComprobanteSubmit() {
    const err = document.getElementById('error-subir');
    // Si hay archivo pegado/arrastrado, inyectarlo en el input
    if (_subirFile) {
        const dt = new DataTransfer();
        dt.items.add(_subirFile);
        document.getElementById('file-input-subir').files = dt.files;
    }
    const inp = document.getElementById('file-input-subir');
    if (!inp.files || !inp.files[0]) {
        err.textContent = 'Selecciona, arrastra o pega una imagen primero.';
        err.style.display = 'block';
        return false;
    }
    const btn = document.getElementById('btn-subir-comp');
    btn.disabled = true;
    btn.textContent = 'Subiendo...';
    return true; // permite el submit normal del form
}

function abrirRecibo(facturaId) {
    const url = `${baseUrl}/admin/facturacion/recibo/${facturaId}?modal=1`;
    document.getElementById('iframe-recibo').src = url;
    document.getElementById('btn-abrir-recibo').href = url;
    document.getElementById('modal-recibo').classList.add('open');
}

// ── Lógica de Filtros Interactivos ──
function filtrarTabla(bancoId, estado, btn) {
    const contenedor = btn.parentElement;
    contenedor.querySelectorAll('.btn-filtro').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Buscar la tabla correspondiente
    const tabla = contenedor.nextElementSibling.querySelector('table.tbl');
    if (!tabla) return;
    const filas = tabla.querySelectorAll('tbody tr');
    filas.forEach(tr => {
        const estFila = tr.getAttribute('data-estado');
        if (estado === 'todos' || estFila === estado) {
            tr.style.display = '';
        } else {
            tr.style.display = 'none';
        }
    });
}

// ── Actualización de Estado vía Fetch (AJAX) ──
function cambiarEstadoConsignacion(accion) {
    if (!_currentCsId) return;

    let url = '';
    let method = 'POST';
    
    if (accion === 'verificar') {
        url = `${baseUrl}/cuadre-diario/consignacion/${_currentCsId}/confirmar`;
    } else if (accion === 'pendiente') {
        url = `${baseUrl}/cuadre-diario/consignacion/${_currentCsId}/confirmar/reversar`;
    } else if (accion === 'no-aparece') {
        url = `${baseUrl}/cuadre-diario/consignacion/${_currentCsId}/no-aparece`;
    }

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
    
    const headers = {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token
    };

    const body = new FormData();
    if (accion === 'pendiente') {
        body.append('_method', 'PATCH');
    }

    fetch(url, {
        method: 'POST',
        headers: headers,
        body: body
    })
    .then(res => {
        if (!res.ok) throw new Error('Error al actualizar el estado.');
        return res.json();
    })
    .then(data => {
        if (data.success) {
            const tr = document.getElementById(`consignacion-row-${_currentCsId}`);
            if (tr) {
                // 1. Actualizar el atributo data-estado
                let nuevoEst = 'pendiente';
                if (accion === 'verificar') nuevoEst = 'verificado';
                else if (accion === 'no-aparece') nuevoEst = 'no_aparece';
                tr.setAttribute('data-estado', nuevoEst);

                // 2. Actualizar el botón de Estado en la fila
                const btnEstado = tr.querySelector('.btn-estado-clic');
                if (btnEstado) {
                    if (accion === 'verificar') {
                        btnEstado.style.background = '#dcfce7';
                        btnEstado.style.color = '#15803d';
                        btnEstado.innerHTML = '✅ Verificado';
                        btnEstado.setAttribute('onclick', `abrirModalEstado(${data.consignacion.id}, true, false, ${data.consignacion.imagen_url ? "'"+data.consignacion.imagen_url+"'" : 'null'})`);
                    } else if (accion === 'no-aparece') {
                        btnEstado.style.background = '#fee2e2';
                        btnEstado.style.color = '#dc2626';
                        btnEstado.innerHTML = '❌ No aparece';
                        btnEstado.setAttribute('onclick', `abrirModalEstado(${data.consignacion.id}, false, true, ${data.consignacion.imagen_url ? "'"+data.consignacion.imagen_url+"'" : 'null'})`);
                    } else {
                        btnEstado.style.background = '#fef3c7';
                        btnEstado.style.color = '#b45309';
                        btnEstado.innerHTML = '🕐 Pendiente';
                        btnEstado.setAttribute('onclick', `abrirModalEstado(${data.consignacion.id}, false, false, ${data.consignacion.imagen_url ? "'"+data.consignacion.imagen_url+"'" : 'null'})`);
                    }
                }

                // 3. Actualizar la celda de descripción
                const tdDesc = tr.querySelector('.celda-descripcion');
                if (tdDesc) {
                    const descText = data.consignacion.descripcion || '—';
                    tdDesc.textContent = descText.length > 55 ? descText.substring(0, 52) + '...' : descText;
                    tdDesc.setAttribute('title', descText);
                }
            }

            // 4. Actualizar el saldo del banco
            if (data.nuevo_saldo !== undefined && data.banco_id) {
                const divSaldo = document.getElementById(`saldo-banco-${data.banco_id}`);
                if (divSaldo) {
                    const fmtVal = '$' + new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(Math.abs(data.nuevo_saldo));
                    divSaldo.textContent = fmtVal;
                    divSaldo.style.color = data.nuevo_saldo >= 0 ? '#1d4ed8' : '#dc2626';
                }
            }

            cerrarModal('modal-estado');
            mostrarToast(data.message);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Ocurrió un error al procesar el cambio de estado.');
    });
}

// ── Lógica de Zoom Interactivo en Imágenes ──
function toggleZoomImg(img) {
    const wrapper = img.parentElement;
    if (!img.classList.contains('zoomed')) {
        // Activar Zoom (Redimensionamiento real del DOM)
        img.classList.add('zoomed');
        if (wrapper) {
            wrapper.classList.add('zoomed-mode');
            // Centrar el scroll inicialmente
            setTimeout(() => {
                wrapper.scrollLeft = (img.offsetWidth - wrapper.offsetWidth) / 2;
                wrapper.scrollTop = (img.offsetHeight - wrapper.offsetHeight) / 2;
            }, 100);
        }
    } else {
        // Desactivar Zoom
        img.classList.remove('zoomed');
        if (wrapper) {
            wrapper.classList.remove('zoomed-mode');
            wrapper.style.cursor = '';
        }
    }
}

function resetZoomImg(imgId, wrapperId) {
    const img = document.getElementById(imgId);
    if (img) {
        img.classList.remove('zoomed');
    }
    const wrapper = document.getElementById(wrapperId);
    if (wrapper) {
        wrapper.classList.remove('zoomed-mode');
        wrapper.style.cursor = '';
        wrapper.scrollLeft = 0;
        wrapper.scrollTop = 0;
    }
}

// ── Arrastre de imagen con el mouse (Drag to Scroll) ──
function initDragScroll(wrapper) {
    let isDown = false;
    let startX, startY;
    let scrollLeft, scrollTop;

    wrapper.addEventListener('mousedown', (e) => {
        const img = wrapper.querySelector('img.zoomable-img');
        if (img && img.classList.contains('zoomed')) {
            isDown = true;
            wrapper.style.cursor = 'grabbing';
            startX = e.pageX - wrapper.offsetLeft;
            startY = e.pageY - wrapper.offsetTop;
            scrollLeft = wrapper.scrollLeft;
            scrollTop = wrapper.scrollTop;
            e.preventDefault();
        }
    });

    wrapper.addEventListener('mouseleave', () => {
        isDown = false;
        const img = wrapper.querySelector('img.zoomable-img');
        if (img && img.classList.contains('zoomed')) {
            wrapper.style.cursor = 'grab';
        }
    });

    wrapper.addEventListener('mouseup', () => {
        isDown = false;
        const img = wrapper.querySelector('img.zoomable-img');
        if (img && img.classList.contains('zoomed')) {
            wrapper.style.cursor = 'grab';
        }
    });

    wrapper.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - wrapper.offsetLeft;
        const y = e.pageY - wrapper.offsetTop;
        const walkX = (x - startX) * 1.5; // multiplicador de velocidad
        const walkY = (y - startY) * 1.5;
        wrapper.scrollLeft = scrollLeft - walkX;
        wrapper.scrollTop = scrollTop - walkY;
    });
}

// Inicializar Drag to Scroll al renderizar la vista
const wr1 = document.getElementById('modal-estado-img-wrapper');
const wr2 = document.getElementById('modal-img-wrapper');
if (wr1) initDragScroll(wr1);
if (wr2) initDragScroll(wr2);

// ── Mostrar Toast de Notificación ──
function mostrarToast(mensaje) {
    let toast = document.getElementById('app-toast-notif');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'app-toast-notif';
        toast.className = 'toast-notif';
        document.body.appendChild(toast);
    }
    toast.innerHTML = `<span>ℹ️</span> <span>${mensaje}</span>`;
    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3500);
}

// ── Abrir Recibo de Anticipo ──
function abrirReciboAnticipo(anticipoId) {
    const url = `${baseUrl}/admin/anticipos/${anticipoId}/recibo?modal=1`;
    document.getElementById('iframe-recibo').src = url;
    document.getElementById('btn-abrir-recibo').href = url;
    document.getElementById('modal-recibo').classList.add('open');
}
</script>
@endsection
