{{--
    Capa responsive común del módulo Finanzas Personales.
    Se incluye con @include('finanzas.partials._responsive_fin') en cada vista
    para que todas las pantallas funcionen bien en celular sin duplicar vistas.
--}}
@push('styles')
<style>
@media (max-width: 768px) {
    /* Contenedor y barras superiores */
    .finanzas-container { padding: 0.25rem !important; }
    .fin-top-bar { flex-direction: column !important; align-items: stretch !important; gap: 0.6rem !important; }
    .fin-top-bar > div { display: flex !important; flex-wrap: wrap !important; gap: 0.5rem !important; }
    .breadcrumb-bx { font-size: 0.7rem !important; flex-wrap: wrap !important; }
    .fin-header-section { flex-direction: column !important; align-items: stretch !important; gap: 0.75rem !important; }
    .fin-header-section h1 { font-size: 1.15rem !important; }
    .fin-header-section p { font-size: 0.78rem !important; }

    /* KPIs en dos columnas compactas */
    .fin-kpis-grid { grid-template-columns: 1fr 1fr !important; gap: 0.6rem !important; }
    .kpi-card { padding: 0.7rem !important; gap: 0.5rem !important; }
    .kpi-icon { width: 34px !important; height: 34px !important; font-size: 1.2rem !important; }
    .kpi-val { font-size: 0.95rem !important; }
    .kpi-label { font-size: 0.62rem !important; }

    /* Tablas: scroll horizontal táctil en lugar de desbordar la pantalla */
    .card-tabla-bx, .card-tabla { overflow-x: auto !important; -webkit-overflow-scrolling: touch; }
    .card-tabla-bx table, .card-tabla table { min-width: 560px; }
    .tabla-brynex-bx th, .tabla-brynex-bx td { padding: 0.5rem 0.6rem !important; font-size: 0.72rem !important; white-space: nowrap; }

    /* Botones */
    .btn-fin, .btn-fin-link { font-size: 0.72rem !important; padding: 0.45rem 0.75rem !important; }

    /* Modales a casi pantalla completa con scroll interno */
    .modal-overlay-bx { padding: 0.5rem !important; align-items: flex-end !important; }
    .modal-box-bx { max-width: 100% !important; max-height: 94vh !important; overflow-y: auto !important; border-radius: 14px 14px 0 0 !important; }

    /* Formularios: inputs más cómodos para el dedo */
    .form-input-bx, .form-select-bx { font-size: 16px !important; padding: 0.6rem 0.75rem !important; }
    .modal-foot-bx { position: sticky; bottom: 0; }
}
</style>
@endpush
