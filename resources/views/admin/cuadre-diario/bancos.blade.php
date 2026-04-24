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

@if(session('success'))
<div style="background:#dcfce7;border-radius:8px;padding:.55rem 1rem;margin-bottom:.7rem;font-size:.82rem;color:#15803d;font-weight:600">
    {{ session('success') }}
</div>
@endif

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
            <div style="font-size:1.3rem;font-weight:800;color:{{ $sb['saldo'] >= 0 ? '#1d4ed8' : '#dc2626' }}">
                {{ $fmt($sb['saldo']) }}
            </div>
            <div style="font-size:.68rem;color:#94a3b8">Saldo total histórico</div>
        </div>
    </div>

    <div style="overflow-x:auto">
    <table class="tbl">
        <thead><tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Fact.</th>
            <th>Pagador / Destino</th>
            <th style="text-align:left;padding-left:.8rem">Descripción</th>
            <th>Registró</th>
            <th style="text-align:right;padding-right:.8rem">Valor</th>
            <th>Estado</th>
            <th>Img.</th>
        </tr></thead>
        <tbody>
        @foreach($sb['movimientos'] as $mov)
        <tr>
            {{-- Fecha --}}
            <td style="font-size:.73rem;white-space:nowrap;color:#64748b">
                {{ sqldate($mov->fecha)->format('d/m/Y') }}
            </td>

            {{-- Tipo --}}
            <td>
                @if(!$mov->es_salida)
                <span class="badge" style="background:#dbeafe;color:#1d4ed8">
                    📥 {{ match($mov->tipo) {
                        'cliente'           => 'Pago SS',
                        'traslado_efectivo' => 'Ef→Banco',
                        'banco_recibido'    => 'T. entrada',
                        default             => ucfirst($mov->tipo),
                    } }}
                </span>
                @else
                <span class="badge" style="background:#fee2e2;color:#dc2626">
                    📤 {{ str_contains($mov->tipo ?? '','banco') ? 'Transferencia' : ucfirst(str_replace('_',' ',$mov->tipo ?? '')) }}
                </span>
                @endif
            </td>

            {{-- Factura --}}
            <td>
                @if($mov->num_factura)
                <a href="#" onclick="abrirRecibo({{ $mov->factura_id }}); return false;"
                   style="font-weight:700;font-size:.78rem;color:#2563eb;text-decoration:none">
                    📋 #{{ $mov->num_factura }}
                </a>
                @else
                <span style="color:#cbd5e1;font-size:.72rem">—</span>
                @endif
            </td>

            {{-- Pagador / Destino — centrado --}}
            <td style="font-size:.77rem;max-width:160px">
                {{ $mov->pagador ?? '—' }}
            </td>

            {{-- Descripción — izquierda --}}
            <td style="text-align:left;font-size:.73rem;color:#64748b;max-width:180px;padding-left:.8rem">
                {{ $mov->descripcion ? \Str::limit($mov->descripcion, 55) : '—' }}
            </td>

            {{-- Registró --}}
            <td style="font-size:.73rem;color:#64748b;white-space:nowrap">
                {{ $mov->usuario?->nombre ?? '—' }}
            </td>

            {{-- Valor — alineado a la derecha con número visible --}}
            <td style="text-align:right;padding-right:.8rem;font-family:monospace;font-weight:700;font-size:.85rem;
                       color:{{ $mov->es_salida ? '#dc2626' : '#15803d' }};white-space:nowrap">
                {{ $mov->es_salida ? '-' : '+' }}{{ $fmt($mov->valor) }}
            </td>

            {{-- Estado (clic abre modal) --}}
            <td>
                @if(!$mov->es_salida)
                <button type="button"
                        onclick="abrirModalEstado({{ $mov->cs_id }}, {{ $mov->confirmado ? 'true' : 'false' }})"
                        class="btn-sm"
                        style="background:{{ $mov->confirmado ? '#dcfce7' : '#fef3c7' }};color:{{ $mov->confirmado ? '#15803d' : '#b45309' }}">
                    {{ $mov->confirmado ? '✅ Verificado' : '🕐 Pendiente' }}
                </button>
                @else
                <span style="font-size:.72rem;color:#94a3b8">—</span>
                @endif
            </td>

            {{-- Imagen --}}
            <td>
                @if($mov->imagen_path ?? null)
                <button type="button" class="btn-sm" style="background:#dbeafe;color:#1d4ed8"
                        onclick="verImagen('{{ asset('storage/' . $mov->imagen_path) }}')">
                    🖼 Ver
                </button>
                @else
                <button type="button" class="btn-sm" style="background:#f1f5f9;color:#64748b"
                        onclick="abrirSubirImagen({{ $mov->id }}, {{ $mov->es_gasto ? 'true' : 'false' }})">
                    📎
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
    <div class="modal-box" style="width:min(380px,96vw)">
        <div class="modal-head">
            <span style="color:#fff;font-weight:700;font-size:.9rem">🏦 Estado de consignación</span>
            <button onclick="cerrarModal('modal-estado')" class="btn-close">×</button>
        </div>
        <div class="modal-body" style="display:flex;flex-direction:column;gap:.8rem">
            <p style="font-size:.83rem;color:#374151;margin:0">¿Cambiar el estado de esta consignación?</p>
            <div id="estado-actual" style="text-align:center;font-size:.85rem;font-weight:600;padding:.5rem;border-radius:8px"></div>
            <div style="display:flex;gap:.6rem">
                <form id="form-verificar" method="POST" style="flex:1">
                    @csrf
                    <button type="submit" style="width:100%;padding:.6rem;background:#16a34a;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.85rem">
                        ✅ Marcar Verificado
                    </button>
                </form>
                <form id="form-pendiente" method="POST" style="flex:1">
                    @csrf
                    @method('PATCH')
                    <button type="submit" style="width:100%;padding:.6rem;background:#f59e0b;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.85rem">
                        🕐 Marcar Pendiente
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ═══ MODAL: Ver imagen ═══ --}}
<div id="modal-img" class="modal-bg" onclick="if(event.target===this)cerrarModal('modal-img')">
    <div class="modal-box">
        <div class="modal-head">
            <span style="color:#fff;font-weight:700;font-size:.9rem">🖼 Comprobante</span>
            <button onclick="cerrarModal('modal-img')" class="btn-close">×</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:.5rem">
            <img id="img-preview" src="" style="max-width:100%;max-height:70vh;border-radius:8px;display:none" alt="comprobante">
            <iframe id="pdf-preview" src="" style="display:none;width:100%;height:70vh;border:none"></iframe>
        </div>
    </div>
</div>

{{-- ═══ MODAL: Subir imagen ═══ --}}
<div id="modal-subir" class="modal-bg" onclick="if(event.target===this)cerrarModal('modal-subir')">
    <div class="modal-box" style="width:min(400px,96vw)">
        <div class="modal-head">
            <span style="color:#fff;font-weight:700;font-size:.9rem">📎 Adjuntar comprobante</span>
            <button onclick="cerrarModal('modal-subir')" class="btn-close">×</button>
        </div>
        <div class="modal-body">
            <form id="form-subir" method="POST" enctype="multipart/form-data">
                @csrf
                <p style="font-size:.79rem;color:#64748b;margin:0 0 .7rem">JPG, PNG o PDF. Máx 5MB.</p>
                <input type="file" name="imagen" accept="image/*,.pdf"
                       style="width:100%;border:2px dashed #cbd5e1;border-radius:8px;padding:1rem;font-size:.82rem;margin-bottom:.8rem;box-sizing:border-box">
                <button type="submit" style="width:100%;padding:.55rem;background:#1d4ed8;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.85rem">
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

function cerrarModal(id) {
    document.getElementById(id).classList.remove('open');
    if (id === 'modal-img') {
        document.getElementById('img-preview').src = '';
        document.getElementById('pdf-preview').src = '';
    }
    if (id === 'modal-recibo') document.getElementById('iframe-recibo').src = '';
}

function abrirModalEstado(csId, esVerificado) {
    const base = `${baseUrl}/cuadre-diario/consignacion/${csId}/confirmar`;
    document.getElementById('form-verificar').action = base;
    document.getElementById('form-pendiente').action = `${base}/reversar`;

    const div = document.getElementById('estado-actual');
    if (esVerificado) {
        div.textContent = '✅ Estado actual: Verificado';
        div.style.background = '#dcfce7';
        div.style.color = '#15803d';
    } else {
        div.textContent = '🕐 Estado actual: Pendiente de verificar';
        div.style.background = '#fef3c7';
        div.style.color = '#b45309';
    }
    document.getElementById('modal-estado').classList.add('open');
}

function verImagen(url) {
    const isPdf = url.toLowerCase().endsWith('.pdf');
    const img = document.getElementById('img-preview');
    const pdf = document.getElementById('pdf-preview');
    img.style.display = isPdf ? 'none' : 'block';
    pdf.style.display = isPdf ? 'block' : 'none';
    if (isPdf) { pdf.src = url; } else { img.src = url; }
    document.getElementById('modal-img').classList.add('open');
}

function abrirSubirImagen(id, esGasto) {
    // Si es consignación usa ruta de consignación, si es gasto usa ruta de gasto
    const url = esGasto
        ? `${baseUrl}/cuadre-diario/gasto/${id}/imagen`
        : `${baseUrl}/cuadre-diario/consignacion/${id}/imagen`;
    document.getElementById('form-subir').action = url;
    document.getElementById('modal-subir').classList.add('open');
}

function abrirRecibo(facturaId) {
    const url = `${baseUrl}/admin/facturacion/recibo/${facturaId}`;
    document.getElementById('iframe-recibo').src = url;
    document.getElementById('btn-abrir-recibo').href = url;
    document.getElementById('modal-recibo').classList.add('open');
}
</script>
@endsection
