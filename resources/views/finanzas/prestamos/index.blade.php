@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Préstamos Otorgados')

@section('contenido')
<div class="finanzas-container">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <span>Préstamos</span>
        </div>
        
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <a href="{{ route('finanzas.prestamos.cuenta-corriente') }}" class="btn-fin-link success">💼 Cuenta Corriente (Servicios)</a>
            <a href="{{ route('finanzas.prestamos.create') }}" class="btn-fin success" style="background:#f59e0b; text-decoration:none; display:inline-block; line-height:22px; text-align:center;">
                ➕ Nuevo Préstamo
            </a>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>🤝 Préstamos a Terceros</h1>
            <p>Monitorea capital, intereses acumulados, y días transcurridos de vencimiento con semáforo de alerta en mora.</p>
        </div>
        
        {{-- Selector Filtro de Estado --}}
        <div class="period-selector-bx">
            <a href="{{ route('finanzas.prestamos.index', ['estado' => 'activo']) }}" class="btn-state-filtro {{ $estado === 'activo' ? 'activo' : '' }}">
                ⏳ Activos / Mora
            </a>
            <a href="{{ route('finanzas.prestamos.index', ['estado' => 'pagado']) }}" class="btn-state-filtro {{ $estado === 'pagado' ? 'activo' : '' }}">
                ✅ Pagados
            </a>
        </div>
    </div>

    {{-- Grid de Préstamos --}}
    <div class="prestamos-grid">
        @forelse($prestamos as $p)
            @php
                $diasMora = $p->dias_mora;
                // Definir semáforo de mora
                $claseMora = 'ok'; // Sin mora
                $colorMora = '#22c55e';
                if ($diasMora > 0) {
                    if ($diasMora < $p->dias_mora_alerta) {
                        $claseMora = 'warning';
                        $colorMora = '#f59e0b';
                    } else {
                        $claseMora = 'danger';
                        $colorMora = '#ef4444';
                    }
                }
            @endphp
            <div class="prestamo-card" style="border-top: 4px solid {{ $colorMora }}">
                <div class="pc-header">
                    <div>
                        <h3>👤 {{ $p->nombre_deudor }}</h3>
                        <small>Ref: {{ $p->descripcion ?: 'Préstamo' }}</small>
                    </div>
                    @if($p->estado === 'pagado')
                        <span class="badge-ok-bx">Pagado</span>
                    @elseif($diasMora > 0)
                        <span class="badge-err-bx" style="background:rgba(239,68,68,0.1); border-color:#fca5a5; color:#b91c1c;">Mora: {{ $diasMora }} días</span>
                    @else
                        <span class="badge-ok-bx" style="background:rgba(34,197,94,0.1); color:#166534;">Al día</span>
                    @endif
                </div>

                <div class="pc-body">
                    <div class="pc-item">
                        <span class="pci-label">Desembolso</span>
                        <span class="pci-val">${{ number_format($p->monto_original, 0, ',', '.') }}</span>
                    </div>
                    <div class="pc-item">
                        <span class="pci-label">Tasa Interés</span>
                        <span class="pci-val">{{ $p->tasa_interes_mensual }}% mensual</span>
                    </div>
                    <div class="pc-item" style="border-top:1px dashed #e2e8f0; padding-top:0.4rem; margin-top:0.4rem;">
                        <span class="pci-label" style="font-weight:700; color:#334155;">Saldo Actual (Capital + Int.)</span>
                        <span class="pci-val" style="font-weight:800; color:#0f172a; font-size:1.05rem;">
                            ${{ number_format($p->saldo_actual, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <div class="pc-footer">
                    <a href="{{ route('finanzas.prestamos.show', $p->id) }}" class="btn-fin-card primary">
                        👁️ Ver Ficha
                    </a>
                    @if($p->estado !== 'pagado')
                        <form action="{{ route('finanzas.prestamos.whatsapp', $p->id) }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn-fin-card success" {{ !$p->telefono_deudor ? 'disabled' : '' }}>
                                🟢 Cobrar WhatsApp
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div style="grid-column:1/-1; text-align:center; padding:3rem; background:#fff; border-radius:14px; border:1px solid #e2e8f0; color:#64748b;">
                No hay préstamos registrados en este estado.
            </div>
        @endforelse
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }
.btn-fin-link { text-decoration: none; padding: 0.4rem 0.85rem; border-radius: 8px; font-size: 0.78rem; font-weight: 600; text-align: center; }
.btn-fin-link.success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #166534; }

/* Filtro de Estado */
.btn-state-filtro { display: inline-block; padding: 0.4rem 0.8rem; text-decoration: none; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #475569; font-size: 0.78rem; font-weight: 600; transition: all 0.15s; }
.btn-state-filtro:hover { border-color: #94a3b8; }
.btn-state-filtro.activo { background: var(--azul-btn); color: #fff; border-color: var(--azul-btn); }

/* Grid de Tarjetas */
.prestamos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: 1.25rem; margin-top: 1.5rem; }
.prestamo-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.25rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04); display: flex; flex-direction: column; justify-content: space-between; height: 100%; transition: transform 0.2s; }
.prestamo-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.06); }

.pc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; }
.pc-header h3 { font-size: 0.95rem; font-weight: 700; color: #0f172a; }
.pc-header small { font-size: 0.7rem; color: #64748b; display: block; margin-top: 0.1rem; }

.pc-body { display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 1rem; }
.pc-item { display: flex; justify-content: space-between; font-size: 0.78rem; }
.pci-label { color: #64748b; }
.pci-val { color: #334155; font-weight: 600; }

.pc-footer { display: flex; gap: 0.5rem; border-top: 1px solid #f1f5f9; padding-top: 0.75rem; margin-top: auto; }
.btn-fin-card { flex: 1; padding: 0.4rem; border: none; border-radius: 7px; font-size: 0.75rem; font-weight: 600; text-align: center; text-decoration: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.15s; }
.btn-fin-card.primary { background: rgba(59,130,246,0.1); color: var(--azul-btn); }
.btn-fin-card.primary:hover { background: rgba(59,130,246,0.18); }
.btn-fin-card.success { background: rgba(34,197,94,0.1); color: #166534; }
.btn-fin-card.success:hover { background: rgba(34,197,94,0.18); }
.btn-fin-card.success:disabled { opacity: 0.4; cursor: not-allowed; }

.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-err-bx { border: 1px solid; border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
</style>
@endpush
