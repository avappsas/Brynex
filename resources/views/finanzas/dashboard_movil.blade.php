<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>BryNex - Finanzas Personales</title>
    
    <!-- Google Fonts & Font Awesome -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        :root {
            --bg-principal: #090e17;
            --bg-tarjeta: #151e2e;
            --bg-tarjeta-glass: rgba(21, 30, 46, 0.7);
            --borde-tarjeta: rgba(255, 255, 255, 0.07);
            
            --verde-neon: #10b981;
            --verde-neon-bg: rgba(16, 185, 129, 0.12);
            --rojo-coral: #f43f5e;
            --rojo-coral-bg: rgba(244, 63, 94, 0.12);
            
            --azul-vivo: #3b82f6;
            --azul-vivo-bg: rgba(59, 130, 246, 0.12);
            --naranja: #f59e0b;
            --naranja-bg: rgba(245, 158, 11, 0.12);
            
            --texto-principal: #f8fafc;
            --texto-secundario: #94a3b8;
            --texto-mutado: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--bg-principal);
            color: var(--texto-principal);
            font-size: 0.9rem;
            line-height: 1.4;
            padding-bottom: 80px; /* Espacio para el Bottom Nav */
            overflow-x: hidden;
        }

        /* Utilidades */
        .glass-card {
            background: var(--bg-tarjeta-glass);
            border: 1px solid var(--borde-tarjeta);
            border-radius: 16px;
            backdrop-filter: blur(12px);
            padding: 1.25rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            margin-bottom: 1rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .badge.success { background: var(--verde-neon-bg); color: var(--verde-neon); border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge.danger { background: var(--rojo-coral-bg); color: var(--rojo-coral); border: 1px solid rgba(244, 63, 94, 0.2); }
        .badge.warning { background: var(--naranja-bg); color: var(--naranja); border: 1px solid rgba(245, 158, 11, 0.2); }
        .badge.info { background: var(--azul-vivo-bg); color: var(--azul-vivo); border: 1px solid rgba(59, 130, 246, 0.2); }

        /* Contenedor Principal */
        .app-container {
            padding: 1rem;
            max-width: 500px;
            margin: 0 auto;
        }

        /* Cabecera Superior */
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            padding-top: 0.5rem;
        }
        .app-title h1 {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #60a5fa, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .app-title p {
            font-size: 0.72rem;
            color: var(--texto-secundario);
            font-weight: 500;
        }
        
        .period-selector {
            display: flex;
            gap: 0.35rem;
            background: rgba(255,255,255,0.05);
            padding: 0.25rem;
            border-radius: 10px;
            border: 1px solid var(--borde-tarjeta);
        }
        .period-selector select {
            background: transparent;
            color: var(--texto-principal);
            border: none;
            font-size: 0.75rem;
            font-weight: 700;
            outline: none;
            padding: 0.15rem 0.3rem;
            cursor: pointer;
        }

        /* Widget Cripto */
        .cripto-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--borde-tarjeta);
            padding: 0.6rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: 0.78rem;
        }
        .cripto-bar-left { display: flex; align-items: center; gap: 0.4rem; font-weight: 600; color: var(--texto-secundario); }
        .cripto-bar-right { font-weight: 700; color: var(--verde-neon); }

        /* KPIs */
        .balance-card {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid var(--borde-tarjeta);
            border-radius: 20px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.25rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .balance-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(59,130,246,0.08) 0%, transparent 60%);
            pointer-events: none;
        }
        .balance-card h2 { font-size: 0.78rem; color: var(--texto-secundario); text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .balance-amount { font-size: 1.85rem; font-weight: 800; margin: 0.4rem 0; letter-spacing: -1px; }
        .balance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 1rem;
        }
        .balance-subcol h3 { font-size: 0.7rem; color: var(--texto-secundario); font-weight: 500; margin-bottom: 0.15rem; }
        .balance-subcol p { font-size: 1rem; font-weight: 700; }

        /* Accesos Directos Gigantes */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .btn-action-giant {
            border: none;
            border-radius: 16px;
            padding: 1rem;
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            transition: transform 0.1s;
        }
        .btn-action-giant:active { transform: scale(0.97); }
        .btn-action-giant.entrada { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-action-giant.gasto { background: linear-gradient(135deg, #f43f5e, #be123c); }
        .btn-action-giant span { font-size: 0.7rem; font-weight: 500; opacity: 0.9; }

        /* Lista Compacta de Transacciones / Deudores */
        .list-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        .list-title-row h3 { font-size: 0.88rem; font-weight: 700; color: var(--texto-secundario); text-transform: uppercase; letter-spacing: 0.5px; }
        .list-title-row button { background: none; border: none; color: var(--azul-vivo); font-size: 0.78rem; font-weight: 600; cursor: pointer; }

        .list-container {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }
        .list-item-tactile {
            background: var(--bg-tarjeta);
            border: 1px solid var(--borde-tarjeta);
            border-radius: 14px;
            padding: 0.75rem 0.9rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .lit-left { display: flex; align-items: center; gap: 0.75rem; overflow: hidden; }
        .lit-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .lit-body { overflow: hidden; }
        .lit-name { font-weight: 600; font-size: 0.82rem; color: var(--texto-principal); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lit-desc { font-size: 0.72rem; color: var(--texto-secundario); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.1rem; }
        
        .lit-right { text-align: right; flex-shrink: 0; }
        .lit-amount { font-weight: 700; font-size: 0.88rem; }
        .lit-amount.in { color: var(--verde-neon); }
        .lit-amount.out { color: var(--rojo-coral); }
        .lit-date { font-size: 0.65rem; color: var(--texto-mutado); margin-top: 0.15rem; }

        /* Barra de Navegación Inferior (Bottom Nav) */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 68px;
            background: rgba(9, 14, 23, 0.92);
            backdrop-filter: blur(15px);
            border-top: 1px solid var(--borde-tarjeta);
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            z-index: 5000;
            padding-bottom: env(safe-area-inset-bottom);
        }
        .nav-tab {
            background: none;
            border: none;
            color: var(--texto-mutado);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            cursor: pointer;
            transition: color 0.15s;
        }
        .nav-tab i { font-size: 1.2rem; }
        .nav-tab span { font-size: 0.65rem; font-weight: 600; }
        .nav-tab.active { color: var(--azul-vivo); }

        /* Bottom Sheets (Paneles Deslizantes de Formulario) */
        .bottom-sheet-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        .bottom-sheet-box {
            background: #111827;
            width: 100%;
            max-width: 500px;
            border-top-left-radius: 24px;
            border-top-right-radius: 24px;
            border: 1px solid var(--borde-tarjeta);
            border-bottom: none;
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.4);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            padding-bottom: env(safe-area-inset-bottom);
        }
        .bs-handle {
            width: 40px;
            height: 4px;
            background: rgba(255,255,255,0.15);
            border-radius: 99px;
            margin: 0.75rem auto 0.25rem;
            flex-shrink: 0;
        }
        .bs-header {
            padding: 0.5rem 1.25rem 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }
        .bs-header h3 { font-size: 0.95rem; font-weight: 700; color: var(--texto-principal); }
        .bs-close { background: none; border: none; font-size: 1.4rem; color: var(--texto-secundario); cursor: pointer; line-height: 1; }
        
        .bs-body {
            padding: 1.25rem;
            overflow-y: auto;
            flex: 1;
        }
        .bs-foot {
            padding: 0.75rem 1.25rem 1.25rem;
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            background: rgba(255,255,255,0.01);
            flex-shrink: 0;
        }

        /* Formularios en Modo Oscuro */
        .form-group-bx { display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 1rem; }
        .form-label-bx { font-size: 0.75rem; font-weight: 600; color: var(--texto-secundario); }
        
        .form-input-bx, .form-select-bx {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--borde-tarjeta);
            color: var(--texto-principal);
            padding: 0 0.9rem;
            height: 46px;
            border-radius: 12px;
            font-size: 0.85rem;
            outline: none;
            width: 100%;
            box-sizing: border-box;
            -webkit-appearance: none;
            appearance: none;
        }
        .form-select-bx {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            padding-right: 2.25rem;
        }
        /* Alinear fecha en iOS Safari */
        input[type="date"].form-input-bx {
            display: flex;
            align-items: center;
        }
        .form-input-bx:focus, .form-select-bx:focus {
            border-color: var(--azul-vivo);
            background: rgba(255, 255, 255, 0.06);
        }

        .btn-glass-bx {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--borde-tarjeta);
            color: var(--texto-principal);
            padding: 0.6rem 1.1rem;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-accion-premium {
            border: none;
            padding: 0.6rem 1.25rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        /* Buscador Filtros Historial */
        .search-filters-bar {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .filter-tabs {
            display: flex;
            gap: 0.35rem;
            overflow-x: auto;
            padding-bottom: 0.25rem;
            scrollbar-width: none;
        }
        .filter-tabs::-webkit-scrollbar { display: none; }
        .filter-btn {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--borde-tarjeta);
            color: var(--texto-secundario);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            cursor: pointer;
            white-space: nowrap;
        }
        .filter-btn.active {
            background: var(--azul-vivo-bg);
            color: var(--azul-vivo);
            border-color: var(--azul-vivo);
        }

        /* Estilos del Combobox en Móvil */
        .combobox-container-bx { position: relative; width: 100%; }
        .combobox-dropdown-bx {
            position: absolute;
            z-index: 6000;
            width: 100%;
            max-height: 150px;
            overflow-y: auto;
            background: #1f2937;
            border: 1px solid var(--borde-tarjeta);
            border-radius: 10px;
            margin-top: 0.25rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        .combobox-item-bx {
            padding: 0.55rem 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: 0.8rem;
            color: var(--texto-principal);
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        .combobox-item-bx:hover { background: rgba(255,255,255,0.05); }

        .link-grid-movil {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
        }
        .link-item-tactile {
            background: var(--bg-tarjeta);
            border: 1px solid var(--borde-tarjeta);
            border-radius: 12px;
            padding: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--texto-principal);
        }
        .link-item-tactile span { font-size: 1.15rem; }
        .link-item-tactile div h4 { font-size: 0.75rem; font-weight: 700; }
        .link-item-tactile div p { font-size: 0.6rem; color: var(--texto-secundario); }

        /* Badge Soporte */
        .badge-soporte-link {
            display: inline-flex;
            align-items: center;
            background: rgba(59, 130, 246, 0.12);
            color: var(--azul-vivo);
            font-size: 0.65rem;
            font-weight: 600;
            text-decoration: none;
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            border: 1px solid rgba(59, 130, 246, 0.25);
        }
    </style>
</head>
<body x-data="{ 
    activeTab: '{{ request()->input('tab', 'inicio') }}',
    openGasto: false,
    openEntrada: false,
    openConsolidado: false,
    openEditarGasto: false,
    selectedGasto: {},
    abrirEditar(item) {
        let gastoClon = Object.assign({}, item);
        gastoClon.fecha = item.raw_fecha;
        this.selectedGasto = gastoClon;
        this.openEditarGasto = true;
    }
}">

    <div class="app-container">

        <!-- Cabecera Superior -->
        <header class="app-header">
            <div class="app-title">
                <h1>BryNex Finanzas</h1>
                <p>Mi Contabilidad Privada</p>
            </div>
            
            <form method="GET" action="{{ route('finanzas.dashboard') }}" class="period-selector">
                <select name="mes" onchange="this.form.submit()">
                    @foreach(range(1,12) as $m)
                        <option value="{{ $m }}" @selected($mes == $m)>
                            {{ ucfirst(\Carbon\Carbon::create()->month($m)->locale('es')->monthName) }}
                        </option>
                    @endforeach
                </select>
                <select name="anio" onchange="this.form.submit()">
                    @foreach(range(2020, now()->year + 1) as $a)
                        <option value="{{ $a }}" @selected($anio == $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </form>
        </header>

        <!-- Widget Cotización Cripto (Fijo en la parte superior del Dashboard) -->
        <div class="cripto-bar">
            <div class="cripto-bar-left">
                <span>🪙</span> USDT (Tether):
            </div>
            <div class="cripto-bar-right">
                ${{ number_format($criptoPrecio['precio_cop'], 0, ',', '.') }} COP
            </div>
        </div>

        <!-- SECCIÓN 1: INICIO (Dashboard Principal) -->
        <div x-show="activeTab === 'inicio'">
            
            <!-- Tarjeta Consolidada de Balance -->
            <div class="balance-card">
                <h2>Balance del Mes</h2>
                <div class="balance-amount" style="color: {{ $resumen['balance'] >= 0 ? 'var(--verde-neon)' : 'var(--rojo-coral)' }};">
                    ${{ number_format($resumen['balance'], 0, ',', '.') }}
                </div>
                
                <div class="balance-grid">
                    <div class="balance-subcol" style="border-right: 1px solid rgba(255,255,255,0.08);">
                        <h3>📥 Entradas</h3>
                        <p style="color: var(--verde-neon);">${{ number_format($resumen['entradas'], 0, ',', '.') }}</p>
                    </div>
                    <div class="balance-subcol">
                        <h3>📤 Gastos</h3>
                        <p style="color: var(--rojo-coral);">${{ number_format($resumen['gastos_habituales'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <!-- Accesos Directos Gigantes -->
            <div class="quick-actions-grid">
                <button @click="openEntrada = true" class="btn-action-giant entrada">
                    <i class="fa-solid fa-circle-down"></i>
                    Entrada Rápida
                    <span>Registrar Ingreso</span>
                </button>
                <button @click="openGasto = true" class="btn-action-giant gasto">
                    <i class="fa-solid fa-circle-up"></i>
                    Gasto Rápido
                    <span>Registrar Salida</span>
                </button>
            </div>

            <!-- Alertas Críticas (Mora y Recurrentes Pendientes) -->
            @if(count($prestamosMora) > 0 || count($gastosFaltantes) > 0)
                <div class="list-title-row">
                    <h3>Alertas Pendientes</h3>
                </div>

                @if(count($prestamosMora) > 0)
                    <div class="glass-card" style="border-left: 4px solid var(--rojo-coral);">
                        <h4 style="font-size: 0.8rem; font-weight: 700; color: var(--rojo-coral); display: flex; align-items: center; gap: 0.35rem;">
                            ⚠️ Deudores en Mora Vencidos
                        </h4>
                        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.6rem;">
                            @foreach($prestamosMora as $p)
                                <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(244,63,94,0.05); padding:0.4rem 0.6rem; border-radius:8px; font-size:0.75rem; border: 1px solid rgba(244, 63, 94, 0.1);">
                                    <div>
                                        <strong>{{ $p->nombre_deudor }}</strong> 
                                        <span style="color:var(--texto-secundario);">(${{ number_format($p->saldo_actual, 0, ',', '.') }})</span>
                                        <span class="badge danger" style="font-size:0.58rem; margin-left:3px; padding:0.1rem 0.3rem;">{{ $p->dias_mora }}d</span>
                                    </div>
                                    <form action="{{ route('finanzas.prestamos.whatsapp', $p->id) }}" method="POST" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="badge success" style="border:none; cursor:pointer; font-size:0.65rem;">🟢 Cobrar</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(count($gastosFaltantes) > 0)
                    <div class="glass-card" style="border-left: 4px solid var(--naranja);">
                        <h4 style="font-size: 0.8rem; font-weight: 700; color: var(--naranja); display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.5rem;">
                            💡 Gastos Mensuales Pendientes
                        </h4>
                        <div style="display:flex; flex-wrap:wrap; gap:0.35rem;">
                            @foreach($gastosFaltantes as $gf)
                                <span class="badge warning" style="font-size:0.68rem;">
                                    {{ $gf->icono }} {{ $gf->nombre }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif

            <!-- Botón del Consolidado -->
            <button @click="openConsolidado = true" class="glass-card" style="width: 100%; border: none; text-align: left; display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                <div>
                    <h4 style="font-size: 0.82rem; font-weight: 700; color: var(--texto-principal);">🌐 Saldos Consolidado Global</h4>
                    <p style="font-size: 0.68rem; color: var(--texto-secundario); margin-top: 0.15rem;">Cuentas bancarias, efectivo y ahorros consolidado.</p>
                </div>
                <span style="color: var(--azul-vivo); font-weight: 700;">Ver →</span>
            </button>

        </div>

        <!-- SECCIÓN 2: HISTORIAL DE TRANSACCIONES -->
        <div x-show="activeTab === 'historial'" x-data="{
            filtroTipo: 'todos',
            buscarTexto: '',
            transacciones: {{ json_encode($transacciones->map(fn($t) => [
                'id' => $t->id,
                'fecha' => Carbon\Carbon::parse($t->fecha)->format('d/m/Y'),
                'raw_fecha' => $t->fecha,
                'categoria' => $t->categoria->nombre ?? 'Sin Categoria',
                'categoria_id' => $t->categoria_id,
                'icono' => $t->categoria->icono ?? '📂',
                'color' => $t->categoria->color ?? '#64748b',
                'descripcion' => $t->descripcion ?: '',
                'tipo_movimiento' => $t->tipo_movimiento,
                'monto' => $t->monto,
                'soporte_path' => $t->soporte_path,
                'es_patrimonio' => $t->es_patrimonio,
                'patrimonio_id' => $t->patrimonio_id
            ])) }},
            get filtradas() {
                return this.transacciones.filter(t => {
                    // Filtro de tipo
                    if (this.filtroTipo === 'ingresos' && t.tipo_movimiento !== 'ingreso_esporadico') return false;
                    if (this.filtroTipo === 'egresos' && t.tipo_movimiento === 'ingreso_esporadico') return false;
                    // Buscador por texto
                    if (this.buscarTexto) {
                        const txt = this.buscarTexto.toLowerCase();
                        return t.categoria.toLowerCase().includes(txt) || t.descripcion.toLowerCase().includes(txt);
                    }
                    return true;
                });
            }
        }">
            <div class="list-title-row">
                <h3>Transacciones del Mes</h3>
            </div>

            <!-- Buscador y Filtros -->
            <div class="search-filters-bar">
                <input type="text" x-model="buscarTexto" placeholder="🔍 Buscar por descripción o categoría..." class="form-input-bx" style="font-size: 0.8rem; padding: 0.55rem 0.75rem;">
                
                <div class="filter-tabs">
                    <button @click="filtroTipo = 'todos'" class="filter-btn" :class="filtroTipo === 'todos' ? 'active' : ''">📂 Todos</button>
                    <button @click="filtroTipo = 'ingresos'" class="filter-btn" :class="filtroTipo === 'ingresos' ? 'active' : ''">📥 Entradas</button>
                    <button @click="filtroTipo = 'egresos'" class="filter-btn" :class="filtroTipo === 'egresos' ? 'active' : ''">📤 Gastos</button>
                </div>
            </div>

            <!-- Lista de Movimientos -->
            <div class="list-container">
                <template x-for="item in filtradas" :key="item.id">
                    <div class="list-item-tactile" @click="abrirEditar(item)" style="cursor: pointer;">
                        <div class="lit-left">
                            <div class="lit-icon" :style="'background: ' + item.color + '22; color: ' + item.color">
                                <span x-text="item.icono"></span>
                            </div>
                            <div class="lit-body">
                                <div class="lit-name" x-text="item.categoria"></div>
                                <div class="lit-desc">
                                    <span x-text="item.descripcion || '-'"></span>
                                    <template x-if="item.soporte_path">
                                        <a :href="'/finanzas/gastos/' + item.id + '/soporte'" @click.stop target="_blank" class="badge-soporte-link" style="margin-left: 3px;">
                                            📎 Soporte
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="lit-right">
                            <div class="lit-amount" :class="item.tipo_movimiento === 'ingreso_esporadico' ? 'in' : 'out'" 
                                 x-text="(item.tipo_movimiento === 'ingreso_esporadico' ? '+' : '-') + ' $' + new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(item.monto)"></div>
                            <div class="lit-date" x-text="item.fecha"></div>
                        </div>
                    </div>
                </template>
                <div x-show="filtradas.length === 0" x-cloak style="text-align:center; padding:2rem; color:var(--texto-secundario); font-size:0.8rem;">
                    No se encontraron movimientos registrados.
                </div>
            </div>
        </div>

        <!-- SECCIÓN 3: PRÉSTAMOS / DEUDAS (MORA) -->
        <div x-show="activeTab === 'deudas'">
            <div class="list-title-row">
                <h3>Préstamos Activos</h3>
            </div>

            <div class="list-container">
                @php
                    $prestamos = \App\Models\Finanzas\Prestamo::where('user_id', auth()->id())->activos()->get();
                @endphp
                @forelse($prestamos as $pres)
                    <div class="list-item-tactile">
                        <div class="lit-left">
                            <div class="lit-icon" style="background: var(--naranja-bg); color: var(--naranja)">
                                🤝
                            </div>
                            <div class="lit-body">
                                <div class="lit-name">{{ $pres->nombre_deudor }}</div>
                                <div class="lit-desc">Interés: {{ $pres->tasa_interes }}% | Historial</div>
                            </div>
                        </div>
                        <div class="lit-right">
                            <div class="lit-amount" style="color: var(--naranja);">${{ number_format($pres->saldo_actual, 0, ',', '.') }}</div>
                            <div class="lit-date">Fecha: {{ Carbon\Carbon::parse($pres->fecha_prestamo)->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div style="text-align:center; padding:2rem; color:var(--texto-secundario); font-size:0.8rem;">
                        No tienes préstamos registrados activos.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- SECCIÓN 4: MÓDULOS (Accesos Adicionales) -->
        <div x-show="activeTab === 'modulos'">
            <div class="list-title-row">
                <h3>Módulos de Finanzas</h3>
            </div>
            
            <div class="link-grid-movil">
                <a href="{{ route('finanzas.entradas.index') }}" class="link-item-tactile">
                    <span style="background:var(--verde-neon-bg); padding:0.4rem; border-radius:8px;">📥</span>
                    <div>
                        <h4>Entradas</h4>
                        <p>Fuentes fijas</p>
                    </div>
                </a>
                <a href="{{ route('finanzas.gastos.index') }}" class="link-item-tactile">
                    <span style="background:var(--azul-vivo-bg); padding:0.4rem; border-radius:8px;">💸</span>
                    <div>
                        <h4>Gastos</h4>
                        <p>Diario PC</p>
                    </div>
                </a>
                <a href="{{ route('finanzas.prestamos.index') }}" class="link-item-tactile">
                    <span style="background:var(--naranja-bg); padding:0.4rem; border-radius:8px;">🤝</span>
                    <div>
                        <h4>Préstamos</h4>
                        <p>Deudores</p>
                    </div>
                </a>
                <a href="{{ route('finanzas.inversiones.index') }}" class="link-item-tactile">
                    <span style="background:rgba(124,58,237,0.12); padding:0.4rem; border-radius:8px;">🪙</span>
                    <div>
                        <h4>Inversiones</h4>
                        <p>Cripto/USDT</p>
                    </div>
                </a>
                <a href="{{ route('finanzas.patrimonio.index') }}" class="link-item-tactile">
                    <span style="background:rgba(236,72,153,0.12); padding:0.4rem; border-radius:8px;">🏠</span>
                    <div>
                        <h4>Patrimonio</h4>
                        <p>Bienes raíces</p>
                    </div>
                </a>
                <a href="{{ route('finanzas.categorias.index') }}" class="link-item-tactile">
                    <span style="background:rgba(100,116,139,0.12); padding:0.4rem; border-radius:8px;">⚙️</span>
                    <div>
                        <h4>Categorías</h4>
                        <p>Ajustes de iconos</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Pestañas de Navegación Inferior (Bottom Nav) -->
        <nav class="bottom-nav">
            <button @click="activeTab = 'inicio'" class="nav-tab" :class="activeTab === 'inicio' ? 'active' : ''">
                <i class="fa-solid fa-house"></i>
                <span>Inicio</span>
            </button>
            <button @click="activeTab = 'historial'" class="nav-tab" :class="activeTab === 'historial' ? 'active' : ''">
                <i class="fa-solid fa-receipt"></i>
                <span>Historial</span>
            </button>
            <button @click="activeTab = 'deudas'" class="nav-tab" :class="activeTab === 'deudas' ? 'active' : ''">
                <i class="fa-solid fa-handshake"></i>
                <span>Préstamos</span>
            </button>
            <button @click="activeTab = 'modulos'" class="nav-tab" :class="activeTab === 'modulos' ? 'active' : ''">
                <i class="fa-solid fa-folder-open"></i>
                <span>Módulos</span>
            </button>
        </nav>

        <!-- BOTTOM SHEET: REGISTRAR GASTO RÁPIDO -->
        <div x-show="openGasto" x-cloak class="bottom-sheet-overlay" @click.self="openGasto = false">
            <div class="bottom-sheet-box" 
                 x-show="openGasto"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 x-data="{
                     categoriaOpen: false,
                     categoriaSearch: '',
                     categoriaIdSelected: '',
                     categoriaIconSelected: '',
                     categorias: {{ json_encode($categorias->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'icono' => $c->icono, 'color' => $c->color]) ?? []) }},
                     soportePreview: null,
                     soporteName: '',
                     cargando: false,
                     tipo: 'gasto',
                     montoLimpio: '',
                     montoFormateado: '',
                     formatearMonto() {
                         let valor = this.montoFormateado.replace(/\D/g, '');
                         this.montoLimpio = valor;
                         if (valor) {
                             this.montoFormateado = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(valor);
                         } else {
                             this.montoFormateado = '';
                         }
                     },
                     get filtradas() {
                         if (!this.categoriaSearch) return this.categorias;
                         return this.categorias.filter(c => c.nombre.toLowerCase().includes(this.categoriaSearch.toLowerCase()));
                     },
                     select(cat) {
                         this.categoriaIdSelected = cat.id;
                         this.categoriaIconSelected = cat.icono;
                         this.categoriaSearch = cat.nombre;
                         this.categoriaOpen = false;
                      },
                      clearCategoria() {
                          this.categoriaIdSelected = '';
                          this.categoriaIconSelected = '';
                          this.categoriaSearch = '';
                      },
                      async pegarSoporte() {
                          try {
                              const clipboardItems = await navigator.clipboard.read();
                              for (const item of clipboardItems) {
                                  for (const type of item.types) {
                                      if (type.startsWith('image/')) {
                                          const blob = await item.getType(type);
                                          const file = new File([blob], 'soporte_movil_' + Date.now() + '.png', { type: type });
                                          const dt = new DataTransfer();
                                          dt.items.add(file);
                                          this.$refs.soporteInputMovil.files = dt.files;
                                          this.soporteName = file.name;
                                          this.soportePreview = URL.createObjectURL(blob);
                                          return;
                                      }
                                  }
                              }
                              alert('No se encontró ninguna imagen en el portapapeles. Copia una imagen primero.');
                          } catch (err) {
                              alert('No se pudo acceder al portapapeles. Sube el archivo seleccionándolo.');
                          }
                      },
                      handleFileChange(e) {
                          const file = e.target.files[0];
                          if (file) {
                              this.soporteName = file.name;
                              this.soportePreview = URL.createObjectURL(file);
                          }
                      },
                      limpiarSoporte() {
                          this.$refs.soporteInputMovil.value = '';
                          this.soporteName = '';
                          this.soportePreview = null;
                      },
                      handlePaste(e) {
                          if (!openGasto) return;
                          const items = (e.clipboardData || e.originalEvent.clipboardData).items;
                          for (const item of items) {
                              if (item.kind === 'file' && item.type.startsWith('image/')) {
                                  const blob = item.getAsFile();
                                  const file = new File([blob], 'soporte_movil_' + Date.now() + '.png', { type: item.type });
                                  const dt = new DataTransfer();
                                  dt.items.add(file);
                                  this.$refs.soporteInputMovil.files = dt.files;
                                  this.soporteName = file.name;
                                  this.soportePreview = URL.createObjectURL(blob);
                              }
                          }
                      }
                 }"
                 @paste.window="handlePaste($event)"
            >
                <div class="bs-handle"></div>
                <div class="bs-header">
                    <h3 style="display: flex; align-items: center; gap: 0.35rem; color: var(--rojo-coral);">
                        📤 Registrar Gasto Rápido
                    </h3>
                    <button @click="openGasto = false" class="bs-close">&times;</button>
                </div>
                
                <form action="{{ route('finanzas.gastos.store') }}" method="POST" enctype="multipart/form-data" @submit="cargando = true">
                    @csrf
                    <div class="bs-body">
                        
                        {{-- Fecha y Monto en la misma fila --}}
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group-bx">
                                <label class="form-label-bx">Fecha</label>
                                <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" style="font-size: 1.15rem; font-weight: 700; color: #f8fafc;" required>
                            </div>
                            <div class="form-group-bx">
                                <label class="form-label-bx">Monto ($ COP)</label>
                                <input type="text" 
                                       x-model="montoFormateado" 
                                       @input="formatearMonto()" 
                                       placeholder="Ej: 50.000" 
                                       class="form-input-bx" 
                                       style="font-size: 1.15rem; font-weight: 700; color: #f8fafc;"
                                       required>
                                <input type="hidden" name="monto" :value="montoLimpio">
                            </div>
                        </div>

                        {{-- Tipo de Movimiento y Categoría en la misma fila --}}
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; position: relative;">
                            {{-- Tipo de Movimiento --}}
                            <div class="form-group-bx">
                                <label class="form-label-bx">Movimiento</label>
                                <select name="tipo_movimiento" x-model="tipo" class="form-select-bx" required>
                                    <option value="gasto">Gasto Habitual</option>
                                    <option value="prestamo">Desembolso Préstamo</option>
                                    <option value="inversion">Inversión (Cripto/USDT)</option>
                                </select>
                            </div>

                            {{-- Categoría (Combobox) --}}
                            <div class="form-group-bx" style="position: relative;">
                                <label class="form-label-bx">Categoría</label>
                                <div class="combobox-container-bx" @click.away="categoriaOpen = false" style="width: 100%;">
                                    <div class="combobox-input-wrapper-bx" style="position: relative; width: 100%;">
                                        <span x-show="categoriaIconSelected" class="combobox-icon-bx" x-text="categoriaIconSelected" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.95rem; z-index: 5;"></span>
                                        <input type="text" 
                                               x-model="categoriaSearch" 
                                               @focus="categoriaOpen = true"
                                               @input="categoriaOpen = true; categoriaIdSelected = ''; categoriaIconSelected = ''"
                                               placeholder="Seleccione..."
                                               class="form-input-bx" 
                                               style="width: 100% !important; display: block; box-sizing: border-box;"
                                               :style="categoriaIconSelected ? 'padding-left: 2.25rem; padding-right: 2.25rem;' : 'padding-left: 0.75rem; padding-right: 2.25rem;'"
                                               autocomplete="off">
                                        <button type="button" x-show="categoriaSearch" @click="clearCategoria()" class="combobox-clear-btn-bx" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 1.1rem; color: #94a3b8; cursor: pointer; z-index: 5; padding: 0.2rem;">&times;</button>
                                    </div>

                                    <input type="hidden" name="categoria_id" :value="categoriaIdSelected">
                                    <input type="hidden" name="nueva_categoria" :value="!categoriaIdSelected && categoriaSearch ? categoriaSearch : ''">

                                    <div x-show="categoriaOpen" x-cloak class="combobox-dropdown-bx">
                                        <template x-for="cat in filtradas" :key="cat.id">
                                            <div @click="select(cat)" class="combobox-item-bx">
                                                <span x-text="cat.icono" style="margin-right: 0.5rem;"></span>
                                                <span x-text="cat.nombre" style="font-weight: 500;"></span>
                                            </div>
                                        </template>
                                        <div x-show="categoriaSearch && filtradas.length === 0" 
                                             @click="categoriaOpen = false"
                                             class="combobox-item-bx" 
                                             style="color: var(--azul-vivo); font-weight: 600;">
                                            <span>➕ Crear: "</span><span x-text="categoriaSearch"></span><span>"</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Descripción --}}
                        <div class="form-group-bx">
                            <label class="form-label-bx">Descripción / Observación</label>
                            <input type="text" name="descripcion" placeholder="Ej: almuerzo con cliente, gasolina" class="form-input-bx">
                        </div>

                        {{-- Soporte de Pago --}}
                        <div class="form-group-bx">
                            <label class="form-label-bx">Soporte de Pago (Opcional)</label>
                            <input type="file" name="soporte" x-ref="soporteInputMovil" accept="image/*" style="display: none;" @change="handleFileChange($event)">
                            
                            <div style="display: flex; gap: 0.5rem; margin-top: 0.25rem;">
                                <button type="button" @click="pegarSoporte()" class="btn-glass-bx" style="display: flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.85rem; font-size: 0.72rem; background: rgba(255,255,255,0.06); flex: 1; justify-content: center;">
                                    📋 Pegar Imagen
                                </button>
                                <button type="button" @click="$refs.soporteInputMovil.click()" class="btn-glass-bx" style="display: flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.85rem; font-size: 0.72rem; background: rgba(255,255,255,0.06); flex: 1; justify-content: center;">
                                    📸 Cámara / Subir
                                </button>
                            </div>

                            <div x-show="soportePreview" x-cloak style="margin-top: 0.75rem; position: relative; display: inline-block;">
                                <img :src="soportePreview" style="max-height: 100px; border-radius: 8px; border: 1px solid var(--borde-tarjeta);">
                                <button type="button" @click="limpiarSoporte()" class="btn-icon-bx" style="position: absolute; top: -5px; right: -5px; background: var(--rojo-coral); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; border: none; font-size: 0.7rem;">
                                    &times;
                                </button>
                                <div style="font-size: 0.6rem; color: var(--texto-secundario); margin-top: 0.25rem;" x-text="soporteName"></div>
                            </div>
                        </div>

                        {{-- Patrimonio --}}
                        <div x-data="{ esPatrimonio: false }">
                            <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.5rem;">
                                <input type="checkbox" name="es_patrimonio" value="1" x-model="esPatrimonio" id="es_patrimonio_check_movil" style="cursor:pointer; width:16px; height:16px;">
                                <label for="es_patrimonio_check_movil" style="font-size:0.78rem; color:var(--texto-secundario); cursor:pointer; font-weight:500;">
                                    ¿Asociar a un bien de Patrimonio?
                                </label>
                            </div>

                            <div x-show="esPatrimonio" x-cloak style="margin-top:0.75rem; padding-left:1rem; border-left:2px solid #a855f7;">
                                <label class="form-label-bx" style="color:#a855f7;">Seleccionar Bien</label>
                                <select name="patrimonio_id" class="form-select-bx">
                                    <option value="">-- Seleccionar Bien --</option>
                                    @foreach($patrimonios ?? [] as $pat)
                                        <option value="{{ $pat->id }}">{{ $pat->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>
                    
                    <div class="bs-foot">
                        <button type="button" @click="openGasto = false" class="btn-glass-bx">Cancelar</button>
                        <button type="submit" :disabled="cargando" class="btn-accion-premium" style="background: linear-gradient(135deg, #f43f5e, #be123c); display: flex; align-items: center; gap: 0.35rem;">
                            <span x-show="!cargando">💾 Guardar Gasto</span>
                            <span x-show="cargando" x-cloak><i class="fas fa-spinner fa-spin"></i> Guardando...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- BOTTOM SHEET: REGISTRAR ENTRADA RÁPIDA -->
        <div x-show="openEntrada" x-cloak class="bottom-sheet-overlay" @click.self="openEntrada = false">
            <div class="bottom-sheet-box" 
                 x-show="openEntrada"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 x-data="{
                     soportePreview: null,
                     soporteName: '',
                     cargando: false,
                     montoLimpio: '',
                     montoFormateado: '',
                     formatearMonto() {
                         let valor = this.montoFormateado.replace(/\D/g, '');
                         this.montoLimpio = valor;
                         if (valor) {
                             this.montoFormateado = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(valor);
                         } else {
                             this.montoFormateado = '';
                         }
                     },
                     async pegarSoporte() {
                          try {
                              const clipboardItems = await navigator.clipboard.read();
                              for (const item of clipboardItems) {
                                  for (const type of item.types) {
                                      if (type.startsWith('image/')) {
                                          const blob = await item.getType(type);
                                          const file = new File([blob], 'soporte_entrada_' + Date.now() + '.png', { type: type });
                                          const dt = new DataTransfer();
                                          dt.items.add(file);
                                          this.$refs.soporteInputEntradaMovil.files = dt.files;
                                          this.soporteName = file.name;
                                          this.soportePreview = URL.createObjectURL(blob);
                                          return;
                                      }
                                  }
                              }
                              alert('No se encontró ninguna imagen en el portapapeles. Copia una imagen primero.');
                          } catch (err) {
                              alert('No se pudo acceder al portapapeles. Sube el archivo seleccionándolo.');
                          }
                      },
                      handleFileChange(e) {
                          const file = e.target.files[0];
                          if (file) {
                              this.soporteName = file.name;
                              this.soportePreview = URL.createObjectURL(file);
                          }
                      },
                      limpiarSoporte() {
                          this.$refs.soporteInputEntradaMovil.value = '';
                          this.soporteName = '';
                          this.soportePreview = null;
                      },
                      handlePaste(e) {
                          if (!openEntrada) return;
                          const items = (e.clipboardData || e.originalEvent.clipboardData).items;
                          for (const item of items) {
                              if (item.kind === 'file' && item.type.startsWith('image/')) {
                                  const blob = item.getAsFile();
                                  const file = new File([blob], 'soporte_entrada_' + Date.now() + '.png', { type: item.type });
                                  const dt = new DataTransfer();
                                  dt.items.add(file);
                                  this.$refs.soporteInputEntradaMovil.files = dt.files;
                                  this.soporteName = file.name;
                                  this.soportePreview = URL.createObjectURL(blob);
                              }
                          }
                      }
                 }"
                 @paste.window="handlePaste($event)"
            >
                <div class="bs-handle"></div>
                <div class="bs-header">
                    <h3 style="display: flex; align-items: center; gap: 0.35rem; color: var(--verde-neon);">
                        📥 Registrar Entrada Rápida
                    </h3>
                    <button @click="openEntrada = false" class="bs-close">&times;</button>
                </div>

                @php
                    $catEsporadicaId = \App\Models\Finanzas\CategoriaGasto::where('user_id', auth()->id())
                        ->where(function($q) {
                            $q->where('nombre', 'like', '%Ingreso%')
                              ->orWhere('nombre', 'like', '%esporadico%');
                        })->first()?->id;
                @endphp

                <form action="{{ route('finanzas.gastos.store') }}" method="POST" enctype="multipart/form-data" @submit="cargando = true">
                    @csrf
                    
                    {{-- Campos ocultos obligatorios --}}
                    <input type="hidden" name="tipo_movimiento" value="ingreso_esporadico">
                    <input type="hidden" name="categoria_id" value="{{ $catEsporadicaId }}">

                    <div class="bs-body">
                        
                        {{-- Fecha y Monto en la misma fila --}}
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group-bx">
                                <label class="form-label-bx">Fecha del Ingreso</label>
                                <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" style="font-size: 1.15rem; font-weight: 700; color: #f8fafc;" required>
                            </div>
                            <div class="form-group-bx">
                                <label class="form-label-bx">Monto ($ COP)</label>
                                <input type="text" 
                                       x-model="montoFormateado" 
                                       @input="formatearMonto()" 
                                       placeholder="Ej: 380.000" 
                                       class="form-input-bx" 
                                       style="font-size: 1.15rem; font-weight: 700; color: #f8fafc;"
                                       required>
                                <input type="hidden" name="monto" :value="montoLimpio">
                            </div>
                        </div>

                        {{-- Descripción --}}
                        <div class="form-group-bx">
                            <label class="form-label-bx">Descripción / Detalle</label>
                            <input type="text" name="descripcion" placeholder="Ej: Pago de nómina, venta" class="form-input-bx" required>
                        </div>

                        {{-- Soporte de Pago --}}
                        <div class="form-group-bx">
                            <label class="form-label-bx">Soporte (Opcional)</label>
                            <input type="file" name="soporte" x-ref="soporteInputEntradaMovil" accept="image/*" style="display: none;" @change="handleFileChange($event)">
                            
                            <div style="display: flex; gap: 0.5rem; margin-top: 0.25rem;">
                                <button type="button" @click="pegarSoporte()" class="btn-glass-bx" style="display: flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.85rem; font-size: 0.72rem; background: rgba(255,255,255,0.06); flex: 1; justify-content: center;">
                                    📋 Pegar Imagen
                                </button>
                                <button type="button" @click="$refs.soporteInputEntradaMovil.click()" class="btn-glass-bx" style="display: flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.85rem; font-size: 0.72rem; background: rgba(255,255,255,0.06); flex: 1; justify-content: center;">
                                    📸 Cámara / Subir
                                </button>
                            </div>

                            <div x-show="soportePreview" x-cloak style="margin-top: 0.75rem; position: relative; display: inline-block;">
                                <img :src="soportePreview" style="max-height: 100px; border-radius: 8px; border: 1px solid var(--borde-tarjeta);">
                                <button type="button" @click="limpiarSoporte()" class="btn-icon-bx" style="position: absolute; top: -5px; right: -5px; background: var(--rojo-coral); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; border: none; font-size: 0.7rem;">
                                    &times;
                                </button>
                                <div style="font-size: 0.65rem; color: var(--texto-secundario); margin-top: 0.25rem;" x-text="soporteName"></div>
                            </div>
                        </div>

                        <div style="font-size: 0.75rem; color: var(--verde-neon); font-weight: 500; background: var(--verde-neon-bg); padding: 0.75rem; border-radius: 10px; border: 1px solid rgba(16,185,129,0.15); margin-top: 1rem;">
                            💡 Se sumará al mes correspondiente bajo la fuente consolidada "OTRAS ENTRADAS".
                        </div>

                    </div>
                    
                    <div class="bs-foot">
                        <button type="button" @click="openEntrada = false" class="btn-glass-bx">Cancelar</button>
                        <button type="submit" :disabled="cargando" class="btn-accion-premium" style="background: linear-gradient(135deg, #10b981, #059669); display: flex; align-items: center; gap: 0.35rem;">
                            <span x-show="!cargando">💾 Guardar Entrada</span>
                            <span x-show="cargando" x-cloak><i class="fas fa-spinner fa-spin"></i> Guardando...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- BOTTOM SHEET: CONSOLIDADO GLOBAL -->
        <div x-show="openConsolidado" x-cloak class="bottom-sheet-overlay" @click.self="openConsolidado = false">
            <div class="bottom-sheet-box" 
                 x-show="openConsolidado"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
            >
                <div class="bs-handle"></div>
                <div class="bs-header">
                    <h3 style="display: flex; align-items: center; gap: 0.35rem; color: var(--azul-vivo);">
                        🌐 Consolidado Global de Saldos
                    </h3>
                    <button @click="openConsolidado = false" class="bs-close">&times;</button>
                </div>
                
                <div class="bs-body" style="padding-bottom: 2rem;">
                    <div style="display:flex; flex-direction:column; gap:0.75rem;">
                        
                        {{-- Liquidez Personal --}}
                        <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.03); border:1px solid var(--borde-tarjeta); padding:0.75rem 1rem; border-radius:12px;">
                            <span style="font-weight:600; font-size:0.82rem; color:var(--texto-secundario);">💵 Liquidez Personal</span>
                            <span style="font-weight:700; font-size:0.9rem; color:var(--texto-principal);">${{ number_format($consolidado['liquidez_personal'] ?? 0, 0, ',', '.') }} COP</span>
                        </div>

                        {{-- Préstamos por Cobrar (Cartera) --}}
                        <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.03); border:1px solid var(--borde-tarjeta); padding:0.75rem 1rem; border-radius:12px;">
                            <span style="font-weight:600; font-size:0.82rem; color:var(--texto-secundario);">🤝 Préstamos Cartera</span>
                            <span style="font-weight:700; font-size:0.9rem; color:var(--texto-principal);">${{ number_format($consolidado['prestado_cartera'] ?? 0, 0, ',', '.') }} COP</span>
                        </div>

                        {{-- Inversiones Cripto --}}
                        <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.03); border:1px solid var(--borde-tarjeta); padding:0.75rem 1rem; border-radius:12px;">
                            <span style="font-weight:600; font-size:0.82rem; color:var(--texto-secundario);">🪙 Inversiones Cripto</span>
                            <span style="font-weight:700; font-size:0.9rem; color:var(--texto-principal);">${{ number_format($consolidado['inversiones_cripto'] ?? 0, 0, ',', '.') }} COP</span>
                        </div>

                        {{-- Patrimonio --}}
                        <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.03); border:1px solid var(--borde-tarjeta); padding:0.75rem 1rem; border-radius:12px;">
                            <span style="font-weight:600; font-size:0.82rem; color:var(--texto-secundario);">🏠 Patrimonio Activo</span>
                            <span style="font-weight:700; font-size:0.9rem; color:var(--texto-principal);">${{ number_format($consolidado['patrimonio_total'] ?? 0, 0, ',', '.') }} COP</span>
                        </div>

                        {{-- Proyectos --}}
                        @if(($consolidado['total_saldo_proyectos'] ?? 0) > 0)
                            <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.03); border:1px solid var(--borde-tarjeta); padding:0.75rem 1rem; border-radius:12px;">
                                <span style="font-weight:600; font-size:0.82rem; color:var(--texto-secundario);">📁 Saldo Proyectos</span>
                                <span style="font-weight:700; font-size:0.9rem; color:var(--texto-principal);">${{ number_format($consolidado['total_saldo_proyectos'], 0, ',', '.') }} COP</span>
                            </div>
                        @endif
                        
                        {{-- Balance / Liquidez Global --}}
                        <div style="display:flex; justify-content:space-between; align-items:center; background:var(--azul-vivo-bg); border:1px solid var(--azul-vivo); padding:1rem; border-radius:14px; margin-top:0.5rem;">
                            <span style="font-weight:700; font-size:0.88rem; color:#fff;">Liquidez Global</span>
                            <span style="font-weight:800; font-size:1.05rem; color:#fff;">${{ number_format($consolidado['liquidez_global'] ?? 0, 0, ',', '.') }} COP</span>
                        </div>

                    </div>
                </div>
                
                <div class="bs-foot">
                    <button type="button" @click="openConsolidado = false" class="btn-glass-bx" style="width: 100%;">Cerrar</button>
                </div>
            </div>
        </div>

        <!-- BOTTOM SHEET: EDITAR TRANSACCIÓN -->
        <div x-show="openEditarGasto" x-cloak class="bottom-sheet-overlay" @click.self="openEditarGasto = false">
            <div class="bottom-sheet-box" 
                 x-show="openEditarGasto"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 x-data="{
                     categoriaOpen: false,
                     categoriaSearch: '',
                     categoriaIdSelected: '',
                     categoriaIconSelected: '',
                     categorias: {{ json_encode($categorias->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'icono' => $c->icono, 'color' => $c->color]) ?? []) }},
                     soportePreview: null,
                     soporteName: '',
                     cargando: false,
                     tipo: 'gasto',
                     montoLimpio: '',
                     montoFormateado: '',
                     gastoSoporteActual: null,
                     eliminarSoporteAnterior: false,
                     formatearMonto() {
                         let valor = this.montoFormateado.replace(/\D/g, '');
                         this.montoLimpio = valor;
                         if (valor) {
                             this.montoFormateado = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(valor);
                         } else {
                             this.montoFormateado = '';
                         }
                     },
                     get filtradas() {
                         if (!this.categoriaSearch) return this.categorias;
                         return this.categorias.filter(c => c.nombre.toLowerCase().includes(this.categoriaSearch.toLowerCase()));
                     },
                     select(cat) {
                         this.categoriaIdSelected = cat.id;
                         this.categoriaIconSelected = cat.icono;
                         this.categoriaSearch = cat.nombre;
                         this.categoriaOpen = false;
                      },
                      clearCategoria() {
                          this.categoriaIdSelected = '';
                          this.categoriaIconSelected = '';
                          this.categoriaSearch = '';
                      },
                      async pegarSoporte() {
                          try {
                              const clipboardItems = await navigator.clipboard.read();
                              for (const item of clipboardItems) {
                                  for (const type of item.types) {
                                      if (type.startsWith('image/')) {
                                          const blob = await item.getType(type);
                                          const file = new File([blob], 'soporte_movil_' + Date.now() + '.png', { type: type });
                                          const dt = new DataTransfer();
                                          dt.items.add(file);
                                          this.$refs.soporteInputMovilEdit.files = dt.files;
                                          this.soporteName = file.name;
                                          this.soportePreview = URL.createObjectURL(blob);
                                          return;
                                      }
                                  }
                              }
                              alert('No se encontró ninguna imagen en el portapapeles. Copia una imagen primero.');
                          } catch (err) {
                              alert('No se pudo acceder al portapapeles. Sube el archivo seleccionándolo.');
                          }
                      },
                      handleFileChange(e) {
                          const file = e.target.files[0];
                          if (file) {
                              this.soporteName = file.name;
                              this.soportePreview = URL.createObjectURL(file);
                          }
                      },
                      limpiarSoporte() {
                          this.$refs.soporteInputMovilEdit.value = '';
                          this.soporteName = '';
                          this.soportePreview = null;
                      },
                      init() {
                          this.$watch('selectedGasto', (value) => {
                              if (value && value.id) {
                                  this.categoriaIdSelected = value.categoria_id || '';
                                  this.eliminarSoporteAnterior = false;
                                  this.soportePreview = null;
                                  this.soporteName = '';
                                  
                                  let found = this.categorias.find(c => c.id == this.categoriaIdSelected);
                                  if (found) {
                                      this.categoriaIconSelected = found.icono;
                                      this.categoriaSearch = found.nombre;
                                  } else {
                                      this.categoriaIconSelected = '';
                                      this.categoriaSearch = value.categoria || '';
                                  }
                                  
                                  this.gastoSoporteActual = value.soporte_path || null;
                                  this.tipo = value.tipo_movimiento || 'gasto';
                                  
                                  this.montoLimpio = value.monto ? Math.round(value.monto).toString() : '';
                                  this.montoFormateado = this.montoLimpio ? new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(this.montoLimpio) : '';
                              }
                          });
                      },
                      handlePaste(e) {
                          if (!openEditarGasto) return;
                          const items = (e.clipboardData || e.originalEvent.clipboardData).items;
                          for (const item of items) {
                              if (item.kind === 'file' && item.type.startsWith('image/')) {
                                  const blob = item.getAsFile();
                                  const file = new File([blob], 'soporte_movil_' + Date.now() + '.png', { type: item.type });
                                  const dt = new DataTransfer();
                                  dt.items.add(file);
                                  this.$refs.soporteInputMovilEdit.files = dt.files;
                                  this.soporteName = file.name;
                                  this.soportePreview = URL.createObjectURL(blob);
                              }
                          }
                      }
                 }"
                 @paste.window="handlePaste($event)"
            >
                <div class="bs-handle"></div>
                <div class="bs-header">
                    <h3 style="display: flex; align-items: center; gap: 0.35rem; color: var(--azul-vivo);">
                        ✏️ Editar Transacción
                    </h3>
                    <button @click="openEditarGasto = false" class="bs-close">&times;</button>
                </div>
                
                <form :action="'/finanzas/gastos/' + selectedGasto.id" method="POST" enctype="multipart/form-data" @submit="cargando = true">
                    @csrf
                    @method('PUT')
                    <div class="bs-body">
                        
                        {{-- Fecha y Monto en la misma fila --}}
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group-bx">
                                <label class="form-label-bx">Fecha</label>
                                <input type="date" name="fecha" x-model="selectedGasto.fecha" class="form-input-bx" style="font-size: 1.15rem; font-weight: 700; color: #f8fafc;" required>
                            </div>
                            <div class="form-group-bx">
                                <label class="form-label-bx">Monto ($ COP)</label>
                                <input type="text" 
                                       x-model="montoFormateado" 
                                       @input="formatearMonto()" 
                                       placeholder="Ej: 50.000" 
                                       class="form-input-bx" 
                                       style="font-size: 1.15rem; font-weight: 700; color: #f8fafc;"
                                       required>
                                <input type="hidden" name="monto" :value="montoLimpio">
                            </div>
                        </div>

                        {{-- Tipo de Movimiento y Categoría en la misma fila --}}
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; position: relative;">
                            {{-- Tipo de Movimiento --}}
                            <div class="form-group-bx">
                                <label class="form-label-bx">Movimiento</label>
                                <select name="tipo_movimiento" x-model="tipo" class="form-select-bx" required>
                                    <option value="gasto">Gasto Habitual</option>
                                    <option value="ingreso_esporadico">Entrada</option>
                                    <option value="prestamo">Desembolso Préstamo</option>
                                    <option value="inversion">Inversión (Cripto/USDT)</option>
                                </select>
                            </div>

                            {{-- Categoría (Combobox) --}}
                            <div class="form-group-bx" style="position: relative;">
                                <label class="form-label-bx">Categoría</label>
                                <div class="combobox-container-bx" @click.away="categoriaOpen = false" style="width: 100%;">
                                    <div class="combobox-input-wrapper-bx" style="position: relative; width: 100%;">
                                        <span x-show="categoriaIconSelected" class="combobox-icon-bx" x-text="categoriaIconSelected" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.95rem; z-index: 5;"></span>
                                        <input type="text" 
                                               x-model="categoriaSearch" 
                                               @focus="categoriaOpen = true"
                                               @input="categoriaOpen = true; categoriaIdSelected = ''; categoriaIconSelected = ''"
                                               placeholder="Seleccione..."
                                               class="form-input-bx" 
                                               style="width: 100% !important; display: block; box-sizing: border-box;"
                                               :style="categoriaIconSelected ? 'padding-left: 2.25rem; padding-right: 2.25rem;' : 'padding-left: 0.75rem; padding-right: 2.25rem;'"
                                               autocomplete="off">
                                        <button type="button" x-show="categoriaSearch" @click="clearCategoria()" class="combobox-clear-btn-bx" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 1.1rem; color: #94a3b8; cursor: pointer; z-index: 5; padding: 0.2rem;">&times;</button>
                                    </div>

                                    <input type="hidden" name="categoria_id" :value="categoriaIdSelected">
                                    <input type="hidden" name="nueva_categoria" :value="!categoriaIdSelected && categoriaSearch ? categoriaSearch : ''">

                                    <div x-show="categoriaOpen" x-cloak class="combobox-dropdown-bx">
                                        <template x-for="cat in filtradas" :key="cat.id">
                                            <div @click="select(cat)" class="combobox-item-bx">
                                                <span x-text="cat.icono" style="margin-right: 0.5rem;"></span>
                                                <span x-text="cat.nombre" style="font-weight: 500;"></span>
                                            </div>
                                        </template>
                                        <div x-show="categoriaSearch && filtradas.length === 0" 
                                             @click="categoriaOpen = false"
                                             class="combobox-item-bx" 
                                             style="color: var(--azul-vivo); font-weight: 600;">
                                            <span>➕ Crear: "</span><span x-text="categoriaSearch"></span><span>"</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Descripción --}}
                        <div class="form-group-bx">
                            <label class="form-label-bx">Descripción / Observación</label>
                            <input type="text" name="descripcion" x-model="selectedGasto.descripcion" class="form-input-bx">
                        </div>

                        {{-- Soporte de Pago --}}
                        <div class="form-group-bx">
                            <label class="form-label-bx">Soporte de Pago</label>
                            
                            {{-- Soporte Anterior Existente --}}
                            <div x-show="gastoSoporteActual && !eliminarSoporteAnterior" x-cloak style="display:flex; justify-content:space-between; align-items:center; background:rgba(59,130,246,0.06); padding:0.6rem; border-radius:10px; border:1px solid rgba(59,130,246,0.15); margin-bottom:0.75rem;">
                                <a :href="'/finanzas/gastos/' + selectedGasto.id + '/soporte'" target="_blank" style="color:var(--azul-vivo); font-size:0.8rem; font-weight:600; text-decoration:none; display:flex; align-items:center; gap:0.35rem;">
                                    📎 Descargar soporte actual
                                </a>
                                <button type="button" @click="eliminarSoporteAnterior = true" style="background:rgba(244,63,94,0.12); color:var(--rojo-coral); border:none; padding:0.25rem 0.5rem; border-radius:6px; font-size:0.65rem; font-weight:700; cursor:pointer;">
                                    Eliminar
                                </button>
                            </div>
                            <input type="hidden" name="eliminar_soporte" :value="eliminarSoporteAnterior ? 1 : 0">

                            <input type="file" name="soporte" x-ref="soporteInputMovilEdit" accept="image/*" style="display: none;" @change="handleFileChange($event)">
                            
                            <div style="display: flex; gap: 0.5rem; margin-top: 0.25rem;">
                                <button type="button" @click="pegarSoporte()" class="btn-glass-bx" style="display: flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.85rem; font-size: 0.72rem; background: rgba(255,255,255,0.06); flex: 1; justify-content: center;">
                                    📋 Pegar Imagen
                                </button>
                                <button type="button" @click="$refs.soporteInputMovilEdit.click()" class="btn-glass-bx" style="display: flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.85rem; font-size: 0.72rem; background: rgba(255,255,255,0.06); flex: 1; justify-content: center;">
                                    📸 Cámara / Subir
                                </button>
                            </div>

                            <div x-show="soportePreview" x-cloak style="margin-top: 0.75rem; position: relative; display: inline-block;">
                                <img :src="soportePreview" style="max-height: 100px; border-radius: 8px; border: 1px solid var(--borde-tarjeta);">
                                <button type="button" @click="limpiarSoporte()" class="btn-icon-bx" style="position: absolute; top: -5px; right: -5px; background: var(--rojo-coral); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; border: none; font-size: 0.7rem;">
                                    &times;
                                </button>
                                <div style="font-size: 0.6rem; color: var(--texto-secundario); margin-top: 0.25rem;" x-text="soporteName"></div>
                            </div>
                        </div>

                        {{-- Patrimonio --}}
                        <div x-data="{ esPatrimonio: false }" x-init="$watch('selectedGasto', val => esPatrimonio = val.es_patrimonio ? true : false)">
                            <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.5rem;">
                                <input type="checkbox" name="es_patrimonio" value="1" x-model="esPatrimonio" id="es_patrimonio_check_movil_edit" style="cursor:pointer; width:16px; height:16px;">
                                <label for="es_patrimonio_check_movil_edit" style="font-size:0.78rem; color:var(--texto-secundario); cursor:pointer; font-weight:500;">
                                    ¿Asociar a un bien de Patrimonio?
                                </label>
                            </div>

                            <div x-show="esPatrimonio" x-cloak style="margin-top:0.75rem; padding-left:1rem; border-left:2px solid #a855f7;">
                                <label class="form-label-bx" style="color:#a855f7;">Seleccionar Bien</label>
                                <select name="patrimonio_id" class="form-select-bx" x-model="selectedGasto.patrimonio_id">
                                    <option value="">-- Seleccionar Bien --</option>
                                    @foreach($patrimonios ?? [] as $pat)
                                        <option value="{{ $pat->id }}">{{ $pat->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>
                    
                    <div class="bs-foot" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div>
                            <button type="button" @click="if(confirm('¿Seguro que deseas eliminar esta transacción?')) { $refs.formEliminarGastoMovil.submit(); }" class="btn-accion-premium" style="background: rgba(244,63,94,0.12); color: var(--rojo-coral); border: 1px solid rgba(244,63,94,0.25); display: flex; align-items: center; gap: 0.3rem; padding: 0.6rem 1rem;">
                                🗑&nbsp;Borrar
                            </button>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" @click="openEditarGasto = false" class="btn-glass-bx">Cancelar</button>
                            <button type="submit" :disabled="cargando" class="btn-accion-premium" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); display: flex; align-items: center; gap: 0.35rem;">
                                <span x-show="!cargando">💾 Guardar</span>
                                <span x-show="cargando" x-cloak><i class="fas fa-spinner fa-spin"></i> Guardando...</span>
                            </button>
                        </div>
                    </div>
                </form>
                
                {{-- Formulario oculto de borrado --}}
                <form :action="'/finanzas/gastos/' + selectedGasto.id" method="POST" x-ref="formEliminarGastoMovil" style="display:none;">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </div>

    </div>

</body>
</html>
