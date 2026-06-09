@extends('layouts.app')
@section('modulo', 'Reporte Consolidado Mensual')
@section('contenido')

<div style="max-width:1200px;margin:0 auto; font-family: 'Outfit', 'Inter', sans-serif;">

    {{-- Botón volver y título --}}
    <div style="margin-bottom:2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.informes.hub') }}" class="btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Volver al Centro de Informes
            </a>
            <h1 style="font-size:1.65rem;font-weight:800;color:#0f172a;margin-top:0.5rem; letter-spacing: -0.02em;">Reporte de Ingresos y Retiros</h1>
            <p style="color:#64748b;font-size:0.88rem;margin-top:0.15rem;">Análisis histórico de nuevas administraciones y afiliaciones por mes · Sin repetir clientes de meses anteriores</p>
        </div>
        <div style="background: rgba(13, 148, 136, 0.08); border: 1px solid rgba(13, 148, 136, 0.2); border-radius: 12px; padding: 0.6rem 1rem; text-align: right;">
            <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #0d9488; display: block; letter-spacing: 0.05em;">Mes en Curso</span>
            <span style="font-size: 1rem; font-weight: 800; color: #0f766e;">{{ $kpisActual['label'] }}</span>
        </div>
    </div>

    {{-- Grid de KPIs principales (Mes Actual) --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.25rem;margin-bottom:2.5rem;">

        <!-- Tarjeta Admon Vigentes (Base de Cobro) -->
        <div class="card-kpi" style="padding:1.5rem; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
            <div style="position: absolute; right: -10px; top: -10px; font-size: 5.5rem; opacity: 0.04; pointer-events: none; font-weight: 900;">📋</div>
            <div>
                <span style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Admon Vigentes</span>
                <div style="font-size:2.4rem;font-weight:900;color:#0d9488;margin-top:0.25rem;line-height:1.1;">
                    {{ number_format($kpisActual['admon_vigentes'], 0, ',', '.') }}
                </div>
                <div style="font-size:0.72rem;color:#94a3b8;margin-top:0.2rem;">Contratos activos en el mes</div>
            </div>
            <div style="margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem; border-top: 1px solid #f1f5f9; padding-top: 0.75rem;">
                @if($kpisActual['variacion'] > 0)
                    <span class="trend-badge trend-up">▲ +{{ $kpisActual['variacion'] }}</span>
                @elseif($kpisActual['variacion'] < 0)
                    <span class="trend-badge trend-down">▼ {{ $kpisActual['variacion'] }}</span>
                @else
                    <span class="trend-badge trend-neutral">■ 0</span>
                @endif
                <span style="font-size:0.75rem;color:#94a3b8;">variación neta vs anterior</span>
            </div>
        </div>

        <!-- Tarjeta Afiliaciones del Mes (Con sub-info Facturadas) -->
        <div class="card-kpi" style="padding:1.5rem; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
            <div style="position: absolute; right: -10px; top: -10px; font-size: 5.5rem; opacity: 0.04; pointer-events: none; font-weight: 900;">👥</div>
            <div>
                <span style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Afiliaciones del Mes</span>
                <div style="font-size:2.4rem;font-weight:900;color:#6366f1;margin-top:0.25rem;line-height:1.1;">
                    {{ number_format($kpisActual['afil_por_fecha'], 0, ',', '.') }}
                </div>
                <div style="font-size:0.72rem;color:#94a3b8;margin-top:0.2rem;">Nuevas afiliaciones por fecha de ingreso</div>
            </div>
            <div style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 0.75rem;">
                <span style="font-size:0.75rem;color:#94a3b8;">Afiliaciones Facturadas:</span>
                <strong style="font-size:0.8rem;color:#4f46e5;">{{ number_format($kpisActual['afil_facturadas'], 0, ',', '.') }}</strong>
            </div>
        </div>

        <!-- Tarjeta Retiros del Mes (Con sub-info Reales/Informativas) -->
        <div class="card-kpi" style="padding:1.5rem; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
            <div style="position: absolute; right: -10px; top: -10px; font-size: 5.5rem; opacity: 0.04; pointer-events: none; font-weight: 900;">🚪</div>
            <div>
                <span style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Retiros del Mes</span>
                <div style="font-size:2.4rem;font-weight:900;color:#ef4444;margin-top:0.25rem;line-height:1.1;">
                    {{ number_format($kpisActual['total_retiros'], 0, ',', '.') }}
                </div>
                <div style="font-size:0.72rem;color:#94a3b8;margin-top:0.2rem;">Total contratos retirados este período</div>
            </div>
            <div style="margin-top: 1rem; display: flex; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 0.75rem;">
                <div style="font-size:0.75rem;color:#475569;">
                    Reales: <strong style="color:#b45309;">{{ $kpisActual['retiros_reales'] }}</strong>
                </div>
                <div style="font-size:0.75rem;color:#475569;">
                    Informativos: <strong style="color:#78350f;">{{ $kpisActual['retiros_inform'] }}</strong>
                </div>
            </div>
        </div>

    </div>

    {{-- Nota explicativa del cambio de lógica --}}
    <div style="background: linear-gradient(135deg, rgba(13,148,136,0.06), rgba(99,102,241,0.06)); border: 1px solid rgba(13,148,136,0.2); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 2rem; display: flex; gap: 0.75rem; align-items: flex-start;">
        <span style="font-size: 1.2rem; flex-shrink: 0;">💡</span>
        <div>
            <p style="margin:0; font-size: 0.82rem; color: #0f766e; font-weight: 700;">¿Cómo se interpretan los datos del mes?</p>
            <p style="margin:0.25rem 0 0; font-size: 0.78rem; color: #475569; line-height: 1.5;">
                <strong>Admon Vigentes</strong>: Contratos activos durante el mes (excluyendo ingresos del mismo mes). 
                <strong>Afiliaciones del Mes</strong>: Contratos nuevos creados en el mes con fecha de ingreso del mes.
                <strong>Total Activos</strong>: Suma consolidada de Admon Vigentes y Afiliaciones del Mes.
                <strong>Balance Neto</strong>: Contratos totales del mes menos los retiros del período. Representa el saldo final del mes.
            </p>
        </div>
    </div>

    {{-- Sección Gráfico --}}
    <div style="margin-bottom:2.5rem;">
        <div class="chart-container">
            <h3 style="font-size:1rem;font-weight:700;color:#1e293b;margin-bottom:1rem; display: flex; align-items: center; gap: 0.5rem;">
                <span style="display: inline-block; width: 8px; height: 8px; background-color: #0d9488; border-radius: 50%;"></span>
                Tendencia de los Últimos 6 Meses
            </h3>
            <div style="position: relative; height: 320px; width: 100%;">
                <canvas id="consolidadoChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Tabla de Datos Consolidados Unificada --}}
    <div style="background: #fff; border-radius: 16px; border: 1px solid #f1f5f9; padding: 1.5rem; box-shadow: 0 4px 30px rgba(0, 0, 0, 0.02); margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size:1rem;font-weight:700;color:#1e293b;margin:0;">📋 Resumen Consolidado Mensual</h3>
            <span style="font-size: 0.68rem; color: #0d9488; background: rgba(13,148,136,0.08); border: 1px solid rgba(13,148,136,0.2); border-radius: 6px; padding: 0.25rem 0.5rem; font-weight: 700;">Datos por período mensual</span>
        </div>

        <div style="overflow-x: auto;">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th>Mes / Año</th>
                        <th style="text-align: right;">Admon (Vigentes)</th>
                        <th style="text-align: right;">Afil. Fecha Ingreso</th>
                        <th style="text-align: right;">Afil. Facturadas</th>
                        <th style="text-align: right;">Retiros Reales</th>
                        <th style="text-align: right;">Retiros Informativos</th>
                        <th style="text-align: right; color: #ef4444; font-weight: 700;">Total Retiros</th>
                        <th style="text-align: right; background-color: rgba(13, 148, 136, 0.02); color: #0d9488;">Total Activos</th>
                        <th style="text-align: right; background-color: rgba(99, 102, 241, 0.03); color: #4f46e5; font-weight: 700;">Balance Neto</th>
                        <th style="text-align: center;">Variación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mesesFinal as $mes)
                    <tr>
                        <td style="font-weight: 600;">{{ $mes['label'] }}</td>
                        <td style="text-align: right; font-weight: 700; color: #0d9488;">
                            {{ number_format($mes['admon_vigentes'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right; font-weight: 600; color: #6366f1;">
                            {{ number_format($mes['afil_por_fecha'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right; color: #1d4ed8;">
                            {{ number_format($mes['afil_facturadas'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right; color: #d97706;">
                            {{ number_format($mes['retiros_reales'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right; color: #78350f;">
                            {{ number_format($mes['retiros_inform'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right; font-weight: 600; color: #ef4444;">
                            {{ number_format($mes['total_retiros'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right; font-weight: 700; color: #0d9488; background-color: rgba(13, 148, 136, 0.02);">
                            {{ number_format($mes['total_activos'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right; font-weight: 800; color: #4f46e5; background-color: rgba(99, 102, 241, 0.03); font-size: 0.95rem;">
                            {{ number_format($mes['neto_periodo'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: center;">
                            @if($mes['variacion'] > 0)
                                <span class="trend-badge trend-up">▲ +{{ $mes['variacion'] }}</span>
                            @elseif($mes['variacion'] < 0)
                                <span class="trend-badge trend-down">▼ {{ $mes['variacion'] }}</span>
                            @else
                                <span class="trend-badge trend-neutral">■ 0</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p style="font-size: 0.72rem; color: #94a3b8; margin-top: 1rem; line-height: 1.5;">
            * <strong>Admon (Vigentes)</strong>: Contratos activos durante el mes, excluyendo ingresos nuevos del mismo mes. 
            * <strong>Afil. Fecha Ingreso</strong>: Nuevos contratos ingresados en el mes. 
            * <strong>Afil. Facturadas</strong>: Facturas de afiliación liquidadas con número de factura mayor a 0.
            * <strong>Total Retiros</strong>: Suma de retiros reales e informativos del período.
            * <strong>Total Activos</strong>: Suma de Admon (Vigentes) y Afil. Fecha Ingreso.
            * <strong>Balance Neto</strong>: Total Activos - Total Retiros. Muestra el saldo neto de contratos del mes.
            * <strong>Variación</strong>: Diferencia del Balance Neto de este mes comparado con el mes anterior.
        </p>
    </div>

</div>

<style>
    .card-kpi {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.4);
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.03);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .card-kpi:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 45px rgba(13, 148, 136, 0.08);
        border-color: rgba(13, 148, 136, 0.25);
    }
    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        transition: all 0.2s;
    }
    .btn-back:hover {
        color: #0d9488;
        transform: translateX(-2px);
    }
    .trend-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.7rem;
        font-weight: 800;
        padding: 0.2rem 0.5rem;
        border-radius: 9999px;
        line-height: 1;
    }
    .trend-up   { background-color: #d1fae5; color: #065f46; }
    .trend-down { background-color: #fee2e2; color: #991b1b; }
    .trend-neutral { background-color: #f3f4f6; color: #475569; }
    .table-premium {
        width: 100%;
        border-collapse: collapse;
        border-radius: 12px;
        overflow: hidden;
    }
    .table-premium th {
        background-color: #f8fafc;
        color: #64748b;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 0.75rem 1rem;
        text-align: left;
        border-bottom: 2px solid #e2e8f0;
    }
    .table-premium td {
        padding: 0.85rem 1rem;
        color: #334155;
        font-size: 0.82rem;
        border-bottom: 1px solid #f1f5f9;
        background-color: #fff;
        transition: background-color 0.15s;
    }
    .table-premium tr:last-child td { border-bottom: none; }
    .table-premium tr:hover td { background-color: #f8fafc; }
    .chart-container {
        background: #fff;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.02);
        border: 1px solid #f1f5f9;
        transition: box-shadow 0.3s;
    }
    .chart-container:hover {
        box-shadow: 0 8px 35px rgba(0, 0, 0, 0.04);
    }
</style>

{{-- Chart.js CDN --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Invertimos para orden cronológico: mes más antiguo a la izquierda
        const datos = @json($mesesFinal).reverse();

        const labels            = datos.map(d => d.label);
        const dataAdmonVigentes = datos.map(d => d.admon_vigentes);
        const dataAfilFecha     = datos.map(d => d.afil_por_fecha);
        const dataRetiros       = datos.map(d => d.total_retiros);
        const dataBalanceNeto   = datos.map(d => d.neto_periodo);

        const ctx = document.getElementById('consolidadoChart').getContext('2d');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Admon Vigentes',
                        data: dataAdmonVigentes,
                        backgroundColor: 'rgba(13, 148, 136, 0.85)',
                        borderColor: '#0d9488',
                        borderWidth: 1,
                        borderRadius: 6,
                        barPercentage: 0.55,
                        categoryPercentage: 0.6,
                    },
                    {
                        label: 'Afiliaciones del Mes',
                        data: dataAfilFecha,
                        backgroundColor: 'rgba(99, 102, 241, 0.85)',
                        borderColor: '#6366f1',
                        borderWidth: 1,
                        borderRadius: 6,
                        barPercentage: 0.55,
                        categoryPercentage: 0.6,
                    },
                    {
                        label: 'Total Retiros',
                        data: dataRetiros,
                        backgroundColor: 'rgba(239, 68, 68, 0.85)',
                        borderColor: '#ef4444',
                        borderWidth: 1,
                        borderRadius: 6,
                        barPercentage: 0.55,
                        categoryPercentage: 0.6,
                    },
                    {
                        label: 'Balance Neto',
                        data: dataBalanceNeto,
                        type: 'line',
                        borderColor: '#f59e0b',
                        borderWidth: 3,
                        pointBackgroundColor: '#f59e0b',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.35,
                        fill: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            font: { size: 11, weight: 'bold', family: "'Outfit', 'Inter', sans-serif" },
                            color: '#475569'
                        }
                    },
                    tooltip: {
                        padding: 12,
                        cornerRadius: 10,
                        backgroundColor: '#0f172a',
                        titleFont: { family: "'Outfit', 'Inter', sans-serif", size: 13, weight: 'bold' },
                        bodyFont: { family: "'Outfit', 'Inter', sans-serif", size: 12 },
                        callbacks: {
                            label: function (context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('es-CO').format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Cantidad de Contratos',
                            font: { size: 10, family: "'Outfit', 'Inter', sans-serif" },
                            color: '#64748b'
                        },
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            color: '#64748b',
                            font: { size: 10, family: "'Outfit', 'Inter', sans-serif" },
                            callback: function(value) { if (Number.isInteger(value)) return value; }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 11, weight: '600', family: "'Outfit', 'Inter', sans-serif" }
                        }
                    }
                }
            }
        });
    });
</script>

@endsection
