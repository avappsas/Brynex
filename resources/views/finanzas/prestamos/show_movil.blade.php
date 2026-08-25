<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Préstamo · {{ $prestamo->nombre_deudor }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --bg:     #090e17;
            --card:   #151e2e;
            --borde:  rgba(255,255,255,0.07);
            --verde:  #10b981; --verde-bg: rgba(16,185,129,0.12);
            --rojo:   #f43f5e; --rojo-bg:  rgba(244,63,94,0.12);
            --azul:   #3b82f6; --azul-bg:  rgba(59,130,246,0.12);
            --naranja:#f59e0b; --naranja-bg:rgba(245,158,11,0.12);
            --morado: #a78bfa;
            --t1: #f8fafc; --t2: #94a3b8; --t3: #64748b;
        }
        * { box-sizing:border-box; margin:0; padding:0; font-family:'Inter',sans-serif; -webkit-tap-highlight-color:transparent; }
        body { background:var(--bg); color:var(--t1); font-size:0.9rem; line-height:1.45; padding-bottom:80px; overflow-x:hidden; }

        /* Header */
        .mob-hdr { position:sticky; top:0; z-index:500; background:rgba(9,14,23,0.92); backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px); border-bottom:1px solid var(--borde); padding:0.75rem 1rem; display:flex; align-items:center; gap:0.75rem; }
        .hdr-back { width:38px; height:38px; border-radius:10px; background:rgba(255,255,255,0.06); border:1px solid var(--borde); display:flex; align-items:center; justify-content:center; color:var(--t2); font-size:1rem; text-decoration:none; flex-shrink:0; }
        .hdr-back:active { background:rgba(255,255,255,0.12); }
        .hdr-title { flex:1; min-width:0; }
        .hdr-title h1 { font-size:0.95rem; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .hdr-title p  { font-size:0.68rem; color:var(--t3); margin-top:0.05rem; }
        .hdr-edit { background:var(--azul-bg); border:1px solid rgba(59,130,246,0.25); color:var(--azul); font-size:0.72rem; font-weight:700; padding:0.4rem 0.75rem; border-radius:8px; text-decoration:none; flex-shrink:0; }

        /* Container */
        .wrap { padding:1rem; max-width:520px; margin:0 auto; }

        /* Hero */
        .hero { background:linear-gradient(135deg,#1a2744 0%,#0f172a 100%); border:1px solid var(--borde); border-radius:20px; padding:1.5rem; text-align:center; position:relative; overflow:hidden; margin-bottom:1rem; box-shadow:0 10px 30px rgba(0,0,0,0.3); }
        .hero::before { content:''; position:absolute; top:-50%;left:-50%; width:200%;height:200%; background:radial-gradient(circle,rgba(59,130,246,0.07) 0%,transparent 60%); pointer-events:none; }
        .hero .h-label  { font-size:0.7rem; color:var(--t2); text-transform:uppercase; letter-spacing:1px; font-weight:600; }
        .hero .h-amount { font-size:2.2rem; font-weight:800; letter-spacing:-1.5px; margin:0.4rem 0; }
        .hero .h-name   { font-size:0.88rem; color:var(--t2); font-weight:500; }
        .mora-badge { display:inline-flex; align-items:center; gap:0.3rem; padding:0.25rem 0.75rem; border-radius:99px; font-size:0.72rem; font-weight:700; margin-top:0.6rem; }

        /* KPIs */
        .kpi-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.6rem; margin-bottom:1rem; }
        .kpi { background:var(--card); border:1px solid var(--borde); border-radius:14px; padding:0.85rem 1rem; display:flex; flex-direction:column; gap:0.2rem; }
        .kpi-l { font-size:0.62rem; color:var(--t3); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; }
        .kpi-v { font-size:1rem; font-weight:700; }
        .kpi-v.danger  { color:var(--rojo); }
        .kpi-v.warning { color:var(--naranja); }
        .kpi-v.success { color:var(--verde); }

        /* Info card */
        .info-card { background:var(--card); border:1px solid var(--borde); border-radius:16px; padding:1rem 1.1rem; margin-bottom:1rem; }
        .info-card h4 { font-size:0.72rem; font-weight:700; color:var(--t3); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.75rem; }
        .ir { display:flex; justify-content:space-between; align-items:center; padding:0.4rem 0; border-bottom:1px solid rgba(255,255,255,0.04); font-size:0.8rem; }
        .ir:last-child { border-bottom:none; }
        .ir .lbl { color:var(--t3); font-weight:500; }
        .ir .val { color:var(--t1); font-weight:600; text-align:right; }

        /* Notas */
        .notas { background:rgba(167,139,250,0.06); border:1px solid rgba(167,139,250,0.2); border-left:3px solid var(--morado); border-radius:12px; padding:0.85rem 1rem; margin-bottom:1rem; }
        .notas strong { font-size:0.7rem; color:var(--morado); text-transform:uppercase; letter-spacing:0.05em; display:block; margin-bottom:0.35rem; }
        .notas p { font-size:0.8rem; color:var(--t2); line-height:1.45; }

        /* Acciones */
        .acc-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.6rem; margin-bottom:1rem; }
        .btn-acc { border:none; border-radius:14px; padding:0.9rem 0.75rem; font-size:0.78rem; font-weight:700; cursor:pointer; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:0.35rem; transition:transform 0.1s,opacity 0.1s; text-decoration:none; color:#fff; line-height:1.25; text-align:center; width:100%; }
        .btn-acc:active { transform:scale(0.96); opacity:0.88; }
        .btn-acc i { font-size:1.3rem; }
        .btn-acc.green  { background:linear-gradient(135deg,#10b981,#059669); box-shadow:0 4px 14px rgba(16,185,129,0.3); }
        .btn-acc.blue   { background:linear-gradient(135deg,#3b82f6,#1d4ed8); box-shadow:0 4px 14px rgba(59,130,246,0.3); }
        .btn-acc.orange { background:linear-gradient(135deg,#f97316,#ea580c); box-shadow:0 4px 14px rgba(249,115,22,0.3); }
        .btn-acc.wa     { background:linear-gradient(135deg,#25d366,#128c7e); box-shadow:0 4px 14px rgba(37,211,102,0.3); }
        .btn-acc.ghost  { background:rgba(255,255,255,0.05); border:1px solid var(--borde); color:var(--t2); box-shadow:none; }
        .btn-acc.ghost:active { background:rgba(255,255,255,0.1); }
        .full-col { grid-column:1/-1; flex-direction:row; gap:0.5rem; }

        /* Section title */
        .sec-title { font-size:0.72rem; font-weight:700; color:var(--t3); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.6rem; }

        /* Historial */
        .mov-list { display:flex; flex-direction:column; gap:0.5rem; }
        .mov-item { background:var(--card); border:1px solid var(--borde); border-radius:12px; padding:0.75rem 0.9rem; display:flex; align-items:center; gap:0.75rem; cursor:pointer; transition:background 0.12s; }
        .mov-item:active { background:rgba(255,255,255,0.04); }
        .mov-dot  { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
        .mov-info { flex:1; min-width:0; }
        .mov-tipo { font-size:0.72rem; font-weight:700; }
        .mov-obs  { font-size:0.68rem; color:var(--t3); margin-top:0.1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mov-right { text-align:right; flex-shrink:0; }
        .mov-monto { font-size:0.88rem; font-weight:700; }
        .mov-date  { font-size:0.62rem; color:var(--t3); margin-top:0.1rem; }
        .mov-saldo { font-size:0.62rem; color:var(--t3); margin-top:0.05rem; }
        .c-green  { color:var(--verde); }
        .c-red    { color:var(--rojo); }
        .c-gray   { color:var(--t2); }

        /* Badge tipo */
        .btype { display:inline-block; font-size:0.6rem; font-weight:700; padding:0.1rem 0.4rem; border-radius:5px; text-transform:uppercase; }
        .btype.desembolso      { background:rgba(100,116,139,0.18); color:#94a3b8; }
        .btype.interes_mensual { background:var(--rojo-bg);  color:var(--rojo); }
        .btype.interes_proporcional { background:var(--rojo-bg);  color:var(--rojo); }
        .btype.capitalizacion  { background:var(--rojo-bg);  color:var(--rojo); }
        .btype.abono_interes   { background:var(--verde-bg); color:var(--verde); }
        .btype.abono_capital   { background:var(--verde-bg); color:var(--verde); border:1px dashed rgba(16,185,129,0.4); }
        .btype.pago_total      { background:var(--verde-bg); color:var(--verde); border:1px solid rgba(16,185,129,0.5); }

        /* Bottom Sheet */
        .bs-overlay { position:fixed; inset:0; z-index:9998; background:rgba(0,0,0,0.65); backdrop-filter:blur(4px); display:flex; align-items:flex-end; justify-content:center; }
        .bs-box { background:#111827; width:100%; max-width:520px; border-top-left-radius:24px; border-top-right-radius:24px; border:1px solid var(--borde); border-bottom:none; box-shadow:0 -10px 40px rgba(0,0,0,0.4); max-height:90vh; display:flex; flex-direction:column; padding-bottom:env(safe-area-inset-bottom,1rem); }
        .bs-handle { width:40px; height:4px; background:rgba(255,255,255,0.15); border-radius:99px; margin:0.75rem auto 0.25rem; flex-shrink:0; }
        .bs-head { padding:0.4rem 1.25rem 0.85rem; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.06); flex-shrink:0; }
        .bs-head h3 { font-size:0.95rem; font-weight:700; }
        .bs-close { background:none; border:none; font-size:1.5rem; color:var(--t3); cursor:pointer; line-height:1; }
        .bs-body  { padding:1.25rem; overflow-y:auto; flex:1; }

        /* Forms dark */
        .fg { display:flex; flex-direction:column; gap:0.3rem; margin-bottom:1rem; }
        .fg label { font-size:0.75rem; font-weight:600; color:var(--t2); }
        .fg input, .fg select { background:rgba(255,255,255,0.04); border:1px solid var(--borde); color:var(--t1); padding:0.6rem 0.9rem; height:46px; border-radius:12px; font-size:0.85rem; outline:none; width:100%; -webkit-appearance:none; appearance:none; font-family:'Inter',sans-serif; }
        .fg input:focus { border-color:var(--azul); background:rgba(255,255,255,0.06); }
        .fg input[type="file"] { height:auto; padding:0.55rem; }
        .fg small { font-size:0.68rem; color:var(--t3); line-height:1.35; margin-top:0.1rem; }

        .bs-actions { padding:0.85rem 1.25rem; border-top:1px solid rgba(255,255,255,0.06); display:flex; gap:0.5rem; justify-content:flex-end; flex-shrink:0; }
        .btn-cancel { background:rgba(255,255,255,0.06); border:1px solid var(--borde); color:var(--t2); padding:0.6rem 1.1rem; border-radius:10px; font-size:0.78rem; font-weight:600; cursor:pointer; }
        .btn-ok { border:none; padding:0.6rem 1.35rem; border-radius:10px; font-size:0.8rem; font-weight:700; color:#fff; cursor:pointer; }
        .btn-ok.green  { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-ok.blue   { background:linear-gradient(135deg,#3b82f6,#1d4ed8); }
        .btn-ok.orange { background:linear-gradient(135deg,#f97316,#ea580c); }
        .btn-danger { background:rgba(244,63,94,0.08); border:1px solid rgba(244,63,94,0.25); color:var(--rojo); padding:0.6rem 1.1rem; border-radius:10px; font-size:0.78rem; font-weight:600; cursor:pointer; }

        .img-wrap { position:relative; display:inline-block; margin-top:0.65rem; }
        .img-wrap img { max-height:120px; border-radius:8px; border:1px solid var(--borde); }
        .img-x { position:absolute; top:-6px; right:-6px; background:var(--rojo); color:#fff; border:none; border-radius:50%; width:22px; height:22px; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }

        /* Flash */
        .flash-ok  { background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); color:#34d399; border-radius:12px; padding:0.75rem 1rem; font-size:0.8rem; font-weight:600; display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem; }
        .flash-err { background:rgba(244,63,94,0.12); border:1px solid rgba(244,63,94,0.3); color:#fb7185; border-radius:12px; padding:0.75rem 1rem; font-size:0.8rem; font-weight:600; display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem; }
    </style>
</head>

@php
    // Semáforo sobre la fecha de corte real (la misma que anuncia el recordatorio
    // de WhatsApp), no sobre `dias_mora`, que cuenta desde el último abono.
    $vencido      = $prestamo->esta_vencido;
    $diasVencidos = $prestamo->dias_vencidos;
    $diasCorte    = $prestamo->dias_para_corte;
    $fCorte       = $prestamo->fecha_corte->format('d/m/Y');

    if ($vencido && $diasVencidos > 5) { $cMora='#f43f5e'; $bgMora='rgba(244,63,94,0.15)';  $lMora='🔴 Vencido · ' . $diasVencidos . 'd'; }
    elseif ($vencido)                  { $cMora='#f59e0b'; $bgMora='rgba(245,158,11,0.15)'; $lMora='🟡 Vencido · ' . $diasVencidos . 'd'; }
    elseif ($diasCorte <= 5)           { $cMora='#f59e0b'; $bgMora='rgba(245,158,11,0.15)'; $lMora='🟡 Corte en ' . $diasCorte . 'd · ' . $fCorte; }
    else                               { $cMora='#22c55e'; $bgMora='rgba(34,197,94,0.15)';  $lMora='🟢 Al día · corte ' . $fCorte; }
    $paid = $prestamo->estado === 'pagado';
@endphp

<body x-data="{
    openAbono:false, openLiquidar:false, openAnexar:false, openMov:false, openNoTelefono:false,
    mov:{id:null,fecha:'',monto:0,obs:'',soporte:''},
    pvAbono:null, pvAnexar:null,
    fileAbono(e){ const f=e.target.files[0]; this.pvAbono = f&&f.type.startsWith('image/') ? URL.createObjectURL(f) : null; },
    clearAbono(){ this.$refs.fAbono.value=''; this.pvAbono=null; },
    fileAnexar(e){ const f=e.target.files[0]; this.pvAnexar = f&&f.type.startsWith('image/') ? URL.createObjectURL(f) : null; },
    clearAnexar(){ this.$refs.fAnexar.value=''; this.pvAnexar=null; },
    initPaste() {
        window.addEventListener('paste', (e) => {
            if (!this.openAbono && !this.openAnexar) return;
            const items = (e.clipboardData || e.originalEvent.clipboardData).items;
            for (let index in items) {
                const item = items[index];
                if (item.kind === 'file') {
                    const blob = item.getAsFile();
                    if (blob.type.startsWith('image/')) {
                        if (this.openAbono) {
                            const fileInput = this.$refs.fAbono;
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(blob);
                            fileInput.files = dataTransfer.files;
                            this.pvAbono = URL.createObjectURL(blob);
                        } else if (this.openAnexar) {
                            const fileInput = this.$refs.fAnexar;
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(blob);
                            fileInput.files = dataTransfer.files;
                            this.pvAnexar = URL.createObjectURL(blob);
                        }
                    }
                }
            }
        });
    }
}" x-init="initPaste()">

    <!-- HEADER -->
    <header class="mob-hdr">
        <a href="{{ route('finanzas.dashboard', ['tab'=>'deudas']) }}" class="hdr-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="hdr-title">
            <h1>{{ $prestamo->nombre_deudor }}</h1>
            <p>{{ $prestamo->descripcion ?: 'Préstamo personal' }}</p>
        </div>
        <a href="{{ route('finanzas.prestamos.edit', $prestamo->id) }}" class="hdr-edit">✏️ Editar</a>
    </header>

    <div class="wrap">

        <!-- Flash -->
        @if(session('success'))
        <div x-data="{s:true}" x-show="s" x-init="setTimeout(()=>s=false,4500)" class="flash-ok">✅ {{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div x-data="{s:true}" x-show="s" x-init="setTimeout(()=>s=false,5000)" class="flash-err">❌ {{ session('error') }}</div>
        @endif

        <!-- HERO -->
        <div class="hero">
            <div class="h-label">Saldo Total a Cobrar</div>
            <div class="h-amount" style="color:{{ $paid ? '#10b981' : '#f8fafc' }}">
                ${{ number_format($prestamo->saldo_actual, 0, ',', '.') }}
                <span style="font-size:0.9rem;font-weight:500;opacity:.6"> COP</span>
            </div>
            <div class="h-name">👤 {{ $prestamo->nombre_deudor }}</div>
            <div class="mora-badge" style="background:{{ $bgMora }};color:{{ $cMora }};border:1px solid {{ $cMora }}33">
                {{ $lMora }}
            </div>
            @if($paid)
            <div style="margin-top:.6rem">
                <span style="background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.35);color:#10b981;font-size:.75rem;font-weight:700;padding:.25rem .85rem;border-radius:99px">✅ PAGADO</span>
            </div>
            @endif
        </div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi">
                <span class="kpi-l">Capital Original</span>
                <span class="kpi-v">${{ number_format($prestamo->monto_original, 0, ',', '.') }}</span>
            </div>
            <div class="kpi">
                <span class="kpi-l">Intereses Acum.</span>
                <span class="kpi-v warning">${{ number_format($prestamo->intereses_acumulados, 0, ',', '.') }}</span>
            </div>
            <div class="kpi">
                <span class="kpi-l">Tasa Mensual</span>
                <span class="kpi-v">{{ $prestamo->tasa_interes_mensual }}%</span>
            </div>
            <div class="kpi">
                <span class="kpi-l">Días en mora</span>
                <span class="kpi-v {{ $dias>=30?'danger':($dias>=25?'warning':'success') }}">{{ $dias }}d</span>
            </div>
            <div class="kpi" style="grid-column: span 2;">
                <span class="kpi-l">Valor Interés Actual</span>
                <span class="kpi-v" style="color: var(--azul);">${{ number_format($prestamo->saldo_actual * ($prestamo->tasa_interes_mensual / 100), 0, ',', '.') }}</span>
            </div>
            @if($prestamo->ultimo_mensaje_cobro)
            <div class="kpi" style="grid-column: span 2; background: rgba(16, 185, 129, 0.06); border-color: rgba(16, 185, 129, 0.25);">
                <span class="kpi-l" style="color: var(--verde); font-weight: 700;">🟢 Último Cobro WhatsApp</span>
                <span class="kpi-v" style="font-size: 0.78rem; font-weight: 500; color: var(--t2); line-height: 1.4; margin-top: 0.25rem; display: block; font-style: italic;">
                    "{{ mb_substr($prestamo->ultimo_mensaje_cobro->contenido, 0, 60) }}{{ strlen($prestamo->ultimo_mensaje_cobro->contenido) > 60 ? '...' : '' }}"
                </span>
                <small style="color: var(--t3); display: block; margin-top: 0.3rem; font-size: 0.62rem;">
                    Enviado: {{ $prestamo->ultimo_mensaje_cobro->created_at->format('d/m/Y H:i') }}
                </small>
            </div>
            @endif
        </div>

        <!-- DATOS DEUDOR -->
        <div class="info-card">
            <h4>📋 Datos del Deudor</h4>
            <div class="ir"><span class="lbl">Cédula</span><span class="val">{{ $prestamo->cedula_deudor ?: '—' }}</span></div>
            <div class="ir">
                <span class="lbl">Celular</span>
                <span class="val">
                    @if($prestamo->telefono_deudor)
                        <a href="tel:{{ $prestamo->telefono_deudor }}" style="color:var(--azul);text-decoration:none">{{ $prestamo->telefono_deudor }}</a>
                    @else —
                    @endif
                </span>
            </div>
            <div class="ir"><span class="lbl">Desembolso</span><span class="val">{{ \Carbon\Carbon::parse($prestamo->fecha_desembolso)->format('d/m/Y') }}</span></div>
            <div class="ir">
                <span class="lbl">Último Corte</span>
                <span class="val">{{ $prestamo->ultimo_corte ? \Carbon\Carbon::parse($prestamo->ultimo_corte)->format('d/m/Y') : 'Ninguno' }}</span>
            </div>
            <div class="ir">
                <span class="lbl">Próximo Corte</span>
                <span class="val">{{ $fCorte }} ({{ $diasCorte }}d)</span>
            </div>
            <div class="ir">
                <span class="lbl">Vencido</span>
                <span class="val">{{ $vencido ? '$' . number_format($prestamo->intereses_acumulados, 0, ',', '.') . ' (' . $diasVencidos . 'd)' : 'Al día' }}</span>
            </div>
            @if($prestamo->soporte_path)
            <div class="ir">
                <span class="lbl">Soporte</span>
                <a href="{{ route('finanzas.prestamos.descargar-soporte', $prestamo->id) }}" target="_blank"
                   style="background:var(--azul-bg);border:1px solid rgba(59,130,246,.25);color:var(--azul);font-size:.72rem;font-weight:600;padding:.2rem .55rem;border-radius:6px;text-decoration:none">📄 Ver</a>
            </div>
            @endif
        </div>

        <!-- NOTAS -->
        @if($prestamo->observaciones)
        <div class="notas">
            <strong>📝 Anotaciones</strong>
            <p>{{ $prestamo->observaciones }}</p>
        </div>
        @endif

        <!-- ACCIONES -->
        @if(!$paid)
        <p class="sec-title">⚡ Acciones</p>
        <div class="acc-grid">
            <button @click="openAbono=true" class="btn-acc green">
                <i class="fas fa-dollar-sign"></i>Registrar Abono
            </button>
            <button @click="openLiquidar=true" class="btn-acc blue">
                <i class="fas fa-calculator"></i>Liquidar Intereses
            </button>
            <button @click="openAnexar=true" class="btn-acc orange">
                <i class="fas fa-plus-circle"></i>Anexar Capital
            </button>
            @if($prestamo->telefono_deudor)
            <form action="{{ route('finanzas.prestamos.whatsapp', $prestamo->id) }}" method="POST" style="display:contents">
                @csrf
                <button type="submit" class="btn-acc wa">
                    <i class="fab fa-whatsapp"></i>{{ $vencido ? 'Cobrar WA' : 'Recordar WA' }}
                </button>
            </form>
            @else
            <button @click="openNoTelefono = true" class="btn-acc wa" style="opacity: 0.7;">
                <i class="fab fa-whatsapp"></i>{{ $vencido ? 'Cobrar WA' : 'Recordar WA' }}
            </button>
            @endif
            <form action="{{ route('finanzas.prestamos.toggle-alertas', $prestamo->id) }}" method="POST" style="display:contents">
                @csrf
                <button type="submit" class="btn-acc ghost full-col">
                    <i class="fas fa-bell{{ $prestamo->alertas_activas?'-slash':'' }}"></i>
                    {{ $prestamo->alertas_activas ? '🔕 Desactivar Recordatorios' : '🔔 Activar Recordatorios' }}
                </button>
            </form>
        </div>
        @else
        <form action="{{ route('finanzas.prestamos.toggle-alertas', $prestamo->id) }}" method="POST" style="margin-bottom:1rem">
            @csrf
            <button type="submit" class="btn-acc ghost full-col" style="width:100%">
                <i class="fas fa-bell"></i>
                {{ $prestamo->alertas_activas ? 'Desactivar Recordatorios' : 'Activar Recordatorios' }}
            </button>
        </form>
        @endif

        <!-- HISTORIAL -->
        <p class="sec-title" style="margin-top:.5rem">📜 Historial ({{ $prestamo->movimientos->count() }} movimientos)</p>
        <div class="mov-list">
            @forelse($prestamo->movimientos as $m)
            @php
                $isAbono = in_array($m->tipo, ['abono_interes','abono_capital','pago_total']);
                $isCargo = in_array($m->tipo, ['interes_mensual','interes_proporcional','capitalizacion','desembolso']);
                $cColor  = $isAbono ? 'c-green' : ($isCargo ? 'c-red' : 'c-gray');
                $dotC    = $isAbono ? '#10b981' : ($isCargo ? '#f43f5e' : '#94a3b8');
                $sig     = $isAbono ? '+' : '-';
                $label   = match($m->tipo) {
                    'desembolso'      => 'Capital Inicial',
                    'interes_mensual' => 'Liq. Interés',
                    'interes_proporcional' => 'Interés x Días',
                    'capitalizacion'  => 'Capitalización',
                    'abono_interes'   => 'Abono Interés',
                    'abono_capital'   => 'Abono Capital',
                    'pago_total'      => 'Pago Total',
                    default           => $m->tipo
                };
            @endphp
            <div class="mov-item"
                 @click="mov={id:{{ $m->id }},fecha:'{{ $m->fecha }}',monto:{{ $m->monto }},obs:'{{ addslashes($m->observacion ?? '') }}',soporte:'{{ $m->soporte_path ?? '' }}'}; openMov=true">
                <div class="mov-dot" style="background:{{ $dotC }}"></div>
                <div class="mov-info">
                    <div class="mov-tipo"><span class="btype {{ $m->tipo }}">{{ $label }}</span></div>
                    <div class="mov-obs">
                        {{ $m->observacion ?: '—' }}
                        @if($m->soporte_path)<span style="color:var(--azul);font-size:.62rem"> · 📎</span>@endif
                    </div>
                </div>
                <div class="mov-right">
                    <div class="mov-monto {{ $cColor }}">{{ $sig }}${{ number_format($m->monto, 0, ',', '.') }}</div>
                    <div class="mov-date">{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</div>
                    <div class="mov-saldo">Saldo: ${{ number_format($m->saldo_despues, 0, ',', '.') }}</div>
                </div>
            </div>
            @empty
            <div style="text-align:center;padding:2rem;color:var(--t3);font-size:.8rem;background:var(--card);border-radius:14px;border:1px solid var(--borde)">
                No hay movimientos registrados aún.
            </div>
            @endforelse
        </div>

    </div><!-- /wrap -->

    <!-- ===== BOTTOM SHEET: Abono ===== -->
    <div x-show="openAbono" class="bs-overlay" @click.self="openAbono=false" x-cloak>
        <div class="bs-box">
            <div class="bs-handle"></div>
            <div class="bs-head">
                <h3>💵 Registrar Abono / Pago</h3>
                <button @click="openAbono=false" class="bs-close">&times;</button>
            </div>
            <form action="{{ route('finanzas.prestamos.pago', $prestamo->id) }}" method="POST" enctype="multipart/form-data" class="bs-body">
                @csrf
                <div class="fg"><label>Fecha de Recepción</label><input type="date" name="fecha" value="{{ now()->toDateString() }}" required></div>
                <div class="fg">
                    <label>Monto Recibido ($ COP)</label>
                    <input type="number" name="monto" x-ref="montoAbono" placeholder="Ej: 200000" required min="1" autocomplete="off">
                    <small>Cubre primero los intereses liquidados, luego el interés por los días corridos del capital abonado, y el resto baja capital.</small>
                </div>
                @if(!empty($cierre))
                <div style="border:1px solid #bbf7d0; background:#f0fdf4; border-radius:10px; padding:.6rem .7rem; margin-bottom:.8rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:.5rem;">
                        <div>
                            <span style="font-size:.62rem; color:#166534; font-weight:700; text-transform:uppercase;">Paz y salvo hoy</span>
                            <div style="font-size:1.05rem; font-weight:800; color:#14532d;">${{ number_format($cierre['total'], 0, ',', '.') }}</div>
                        </div>
                        <button type="button" @click="$refs.montoAbono.value = {{ (int) round($cierre['total']) }}"
                                style="border:none; background:#16a34a; color:#fff; font-size:.66rem; font-weight:700; padding:.4rem .65rem; border-radius:8px; white-space:nowrap;">
                            Usar valor
                        </button>
                    </div>
                    <div style="margin-top:.4rem; font-size:.62rem; color:#3f6212; line-height:1.5;">
                        Capital ${{ number_format($cierre['capital'], 0, ',', '.') }}
                        @if($cierre['intereses_pendientes'] > 0)
                            · Int. liquidados ${{ number_format($cierre['intereses_pendientes'], 0, ',', '.') }}
                        @endif
                        @if($cierre['interes_fraccion'] > 0)
                            · {{ $cierre['dias_fraccion'] }} días ${{ number_format($cierre['interes_fraccion'], 0, ',', '.') }}
                        @endif
                    </div>
                </div>
                @endif
                @if(isset($cuentas) && $cuentas->isNotEmpty())
                <div class="fg"><label>¿A qué cuenta entró el dinero?</label>
                    <select name="cuenta_id" required>
                        @foreach($cuentas as $cta)
                            <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="fg"><label>Observaciones (opcional)</label><input type="text" name="observacion" placeholder="Ej: Transferencia Bancolombia"></div>
                <div class="fg">
                    <label>📸 Soporte (opcional)</label>
                    <input type="file" name="soporte" x-ref="fAbono" @change="fileAbono($event)" accept="image/*,application/pdf">
                    <template x-if="pvAbono">
                        <div class="img-wrap"><img :src="pvAbono"><button type="button" @click="clearAbono()" class="img-x">&times;</button></div>
                    </template>
                </div>
                <div class="bs-actions">
                    <button type="button" @click="openAbono=false" class="btn-cancel">Cancelar</button>
                    <button type="submit" class="btn-ok green">Registrar Pago</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== BOTTOM SHEET: Liquidar ===== -->
    <div x-show="openLiquidar" class="bs-overlay" @click.self="openLiquidar=false" x-cloak
         x-data="{
            fechaDesde: '{{ $prestamo->ultimo_corte ?: $prestamo->fecha_desembolso }}',
            fechaHasta: '{{ now()->toDateString() }}',
            diaCobro: {{ (int) \Carbon\Carbon::parse($prestamo->fecha_desembolso)->day }},
            meses: [],
            /* Mismo cálculo que siguienteCorte() en PrestamoLiquidacionService: el corte cae el
               mismo día de cada mes y, si ese día no existe, en el último día del mes. */
            siguienteCorte(desde) {
                let diasMes = new Date(desde.getFullYear(), desde.getMonth() + 1, 0).getDate();
                let dia = desde.getDate();
                let mesBase = new Date(desde.getFullYear(), desde.getMonth(), 1);
                if (dia === diasMes && this.diaCobro > dia) {
                    dia = this.diaCobro;
                } else if (dia <= 3 && this.diaCobro >= 29) {
                    mesBase = new Date(desde.getFullYear(), desde.getMonth() - 1, 1);
                    dia = this.diaCobro;
                }
                let sig = new Date(mesBase.getFullYear(), mesBase.getMonth() + 1, 1);
                let diasSig = new Date(sig.getFullYear(), sig.getMonth() + 1, 0).getDate();
                sig.setDate(Math.min(dia, diasSig));
                return sig;
            },
            calcularMeses() {
                if (!this.fechaDesde || !this.fechaHasta) {
                    this.meses = [];
                    return;
                }
                let start = new Date(this.fechaDesde + 'T00:00:00');
                let end = new Date(this.fechaHasta + 'T00:00:00');

                if (start >= end) {
                    this.meses = [];
                    return;
                }

                let result = [];
                let current = new Date(start);

                while (true) {
                    let next = this.siguienteCorte(current);
                    if (next > end) {
                        break;
                    }
                    
                    let label = next.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });
                    label = label.charAt(0).toUpperCase() + label.slice(1);
                    
                    let yyyy = next.getFullYear();
                    let mm = String(next.getMonth() + 1).padStart(2, '0');
                    let dd = String(next.getDate()).padStart(2, '0');
                    let fechaStr = `${yyyy}-${mm}-${dd}`;
                    
                    result.push({
                        fecha: fechaStr,
                        label: label,
                        seleccionado: true
                    });
                    current = next;
                }
                
                let diffMs = end - current;
                let diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
                if (diffDays > 0) {
                    let yyyy = end.getFullYear();
                    let mm = String(end.getMonth() + 1).padStart(2, '0');
                    let dd = String(end.getDate()).padStart(2, '0');
                    let fechaStr = `${yyyy}-${mm}-${dd}`;
                    result.push({
                        fecha: fechaStr,
                        label: `Fracc. de ${diffDays} días (hasta ${end.toLocaleDateString('es-ES')})`,
                        seleccionado: true,
                        esFraccion: true
                    });
                }
                
                this.meses = result;
            }
         }"
         x-init="$watch('openLiquidar', val => { if(val) { calcularMeses(); } }); $watch('fechaDesde', () => calcularMeses()); $watch('fechaHasta', () => calcularMeses());"
    >
        <div class="bs-box">
            <div class="bs-handle"></div>
            <div class="bs-head">
                <h3>⚙️ Liquidar Intereses</h3>
                <button @click="openLiquidar=false" class="bs-close">&times;</button>
            </div>
            <form action="{{ route('finanzas.prestamos.liquidar', $prestamo->id) }}" method="POST" class="bs-body">
                @csrf
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; margin-bottom:0.75rem;">
                    <div class="fg">
                        <label style="font-size:0.75rem;">Fecha Desde</label>
                        <input type="date" name="fecha_desde" x-model="fechaDesde" required style="font-size:0.85rem; padding:0.4rem;">
                    </div>
                    <div class="fg">
                        <label style="font-size:0.75rem;">Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" x-model="fechaHasta" required style="font-size:0.85rem; padding:0.4rem;">
                    </div>
                </div>
                
                <div class="fg" x-show="meses.length > 0" style="margin-top:0.5rem;">
                    <label style="font-size:0.75rem; margin-bottom:0.3rem;">Periodos a Liquidar:</label>
                    <div style="max-height: 160px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0.25rem 0.5rem; background: #f8fafc;">
                        <template x-for="(mes, idx) in meses" :key="idx">
                            <div style="display:flex; align-items:center; justify-content:space-between; padding:0.35rem 0.5rem; border-bottom: 1px solid #f1f5f9;">
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <input type="checkbox" :id="'chk_mob_' + idx" x-model="mes.seleccionado" style="width:16px; height:16px;">
                                    <label :for="'chk_mob_' + idx" style="font-size:0.75rem; color:#334155;" x-text="mes.label"></label>
                                </div>
                                <input type="hidden" name="meses_excluidos[]" :value="mes.fecha" :disabled="mes.seleccionado">
                            </div>
                        </template>
                    </div>
                </div>
                
                <div x-show="meses.length === 0" style="padding:0.75rem; text-align:center; background:#fee2e2; color:#991b1b; border-radius:8px; font-size:0.75rem; font-weight:600; margin-bottom:0.75rem;">
                    ⚠️ Rango inválido o menor a 1 día de diferencia.
                </div>
                
                <div class="bs-actions" style="margin-top:0.75rem;">
                    <button type="button" @click="openLiquidar=false" class="btn-cancel">Cancelar</button>
                    <button type="submit" class="btn-ok blue" :disabled="meses.length === 0">Ejecutar Liquidación</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== BOTTOM SHEET: Anexar Capital ===== -->
    <div x-show="openAnexar" class="bs-overlay" @click.self="openAnexar=false" x-cloak>
        <div class="bs-box">
            <div class="bs-handle"></div>
            <div class="bs-head">
                <h3>➕ Anexar Capital</h3>
                <button @click="openAnexar=false" class="bs-close">&times;</button>
            </div>
            <form action="{{ route('finanzas.prestamos.anexar', $prestamo->id) }}" method="POST" enctype="multipart/form-data" class="bs-body">
                @csrf
                <div class="fg"><label>Fecha Desembolso Adicional</label><input type="date" name="fecha" value="{{ now()->toDateString() }}" required></div>
                <div class="fg">
                    <label>Monto Adicional ($ COP)</label>
                    <input type="number" name="monto" placeholder="Ej: 500000" required min="1" autocomplete="off">
                    <small>Este valor se suma al capital y al saldo actual del préstamo.</small>
                </div>
                @if(isset($cuentas) && $cuentas->isNotEmpty())
                <div class="fg"><label>¿De qué cuenta salió el dinero?</label>
                    <select name="cuenta_id" required>
                        @foreach($cuentas as $cta)
                            <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="fg"><label>Observaciones (opcional)</label><input type="text" name="observacion" placeholder="Ej: Desembolso adicional"></div>
                <div class="fg">
                    <label>📸 Soporte (opcional)</label>
                    <input type="file" name="soporte" x-ref="fAnexar" @change="fileAnexar($event)" accept="image/*,application/pdf">
                    <template x-if="pvAnexar">
                        <div class="img-wrap"><img :src="pvAnexar"><button type="button" @click="clearAnexar()" class="img-x">&times;</button></div>
                    </template>
                </div>
                <div class="bs-actions">
                    <button type="button" @click="openAnexar=false" class="btn-cancel">Cancelar</button>
                    <button type="submit" class="btn-ok orange">Anexar Valor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== BOTTOM SHEET: Editar Movimiento ===== -->
    <div x-show="openMov" class="bs-overlay" @click.self="openMov=false" x-cloak>
        <div class="bs-box">
            <div class="bs-handle"></div>
            <div class="bs-head">
                <h3>✏️ Editar Movimiento</h3>
                <button @click="openMov=false" class="bs-close">&times;</button>
            </div>
            <form :action="'{{ route('finanzas.prestamos.movimiento.update', '') }}/' + mov.id" method="POST" enctype="multipart/form-data" class="bs-body">
                @csrf
                <div class="fg"><label>Fecha del Movimiento</label><input type="date" name="fecha" x-model="mov.fecha" required></div>
                <div class="fg">
                    <label>Monto ($ COP)</label>
                    <input type="number" name="monto" x-model="mov.monto" required min="0" autocomplete="off">
                    <small>⚠️ Modificar el monto recalcula todos los saldos posteriores.</small>
                </div>
                <div class="fg"><label>Observaciones</label><input type="text" name="observacion" x-model="mov.obs" placeholder="Detalle del movimiento"></div>
                <div class="fg">
                    <label>Archivo Soporte</label>
                    <input type="file" name="soporte" accept="image/*,application/pdf">
                    <template x-if="mov.soporte">
                        <div style="margin-top:.4rem;display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,.04);border:1px solid var(--borde);padding:.4rem .65rem;border-radius:8px;font-size:.72rem">
                            <span style="color:var(--t2)">📄 Soporte cargado</span>
                            <label style="display:flex;align-items:center;gap:.3rem;color:var(--rojo);cursor:pointer;font-weight:600">
                                <input type="checkbox" name="eliminar_soporte" value="1" style="width:14px;height:14px"> Eliminar
                            </label>
                        </div>
                    </template>
                </div>
                <div class="bs-actions" style="justify-content:space-between">
                    <button type="button" class="btn-danger"
                            @click="if(confirm('¿Eliminar este movimiento? Recalcula todos los saldos.')) {
                                        $refs.delForm.action = '{{ route('finanzas.prestamos.movimiento.destroy', '') }}/' + mov.id;
                                        $refs.delForm.submit();
                                    }">
                        🗑️ Eliminar
                    </button>
                    <div style="display:flex;gap:.5rem">
                        <button type="button" @click="openMov=false" class="btn-cancel">Cancelar</button>
                        <button type="submit" class="btn-ok blue">Guardar</button>
                    </div>
                </div>
            </form>
            <form x-ref="delForm" method="POST" style="display:none">@csrf @method('DELETE')</form>
        </div>
    </div>

    <!-- ===== BOTTOM SHEET: No Celular ===== -->
    <div x-show="openNoTelefono" class="bs-overlay" @click.self="openNoTelefono=false" x-cloak>
        <div class="bs-box">
            <div class="bs-handle"></div>
            <div class="bs-head">
                <h3>⚠️ Celular no Registrado</h3>
                <button @click="openNoTelefono=false" class="bs-close">&times;</button>
            </div>
            <div class="bs-body" style="text-align: center; padding: 1.5rem 1rem;">
                <span style="font-size: 2.5rem; display: block; margin-bottom: 0.5rem;">📱❌</span>
                <p style="font-size: 0.85rem; color: var(--t2); line-height: 1.45; margin-bottom: 1.25rem;">
                    Este deudor no tiene un número de celular registrado. Debes ingresar su número en la ficha para poder realizar cobros por WhatsApp.
                </p>
                <div style="display: flex; gap: 0.5rem; justify-content: center; width: 100%;">
                    <button type="button" @click="openNoTelefono=false" class="btn-cancel" style="flex: 1;">Cerrar</button>
                    <a href="{{ route('finanzas.prestamos.edit', $prestamo->id) }}" class="btn-ok" style="background: var(--rojo); text-decoration: none; text-align: center; line-height: 1.25; flex: 1; display: inline-flex; align-items: center; justify-content: center;">
                        ✏️ Editar Ficha
                    </a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
