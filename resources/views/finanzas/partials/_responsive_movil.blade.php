{{--
    Estilos responsive compartidos del módulo Finanzas.
    Incluir al final de cada vista con: @include('finanzas.partials._responsive_movil')
    Hace que las vistas de escritorio funcionen bien en celular:
    tablas con scroll horizontal, barras y botones apilados, modales a pantalla completa.
--}}
<style>
@media (max-width: 768px) {
    .finanzas-container { padding: 0.25rem; }

    /* Barra superior y encabezados apilados */
    .fin-top-bar { flex-direction: column; align-items: stretch; gap: 0.6rem; }
    .fin-top-bar > div { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    .fin-header-section { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
    .fin-header-section .header-text h1 { font-size: 1.15rem; }
    .breadcrumb-bx { font-size: 0.7rem; flex-wrap: wrap; }

    /* Botones cómodos para el dedo */
    .btn-fin, .btn-fin-link { padding: 0.55rem 0.9rem; font-size: 0.78rem; }

    /* KPIs en columna de a 2 */
    .fin-kpis-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.6rem; }
    .kpi-card { padding: 0.7rem; gap: 0.5rem; }
    .kpi-card .kpi-val { font-size: 0.95rem; }
    .kpi-card .kpi-icon { width: 32px; height: 32px; font-size: 1.15rem; }

    /* Tablas: scroll horizontal dentro de la tarjeta, nunca en la página */
    .card-tabla-bx, .card-tabla { overflow-x: auto !important; -webkit-overflow-scrolling: touch; }
    .card-tabla-bx table, .card-tabla table { min-width: 640px; }

    /* Grids de cards a una columna */
    .prestamos-grid, .patrimonio-grid, .proyectos-grid { grid-template-columns: 1fr !important; gap: 0.75rem; }

    /* Modales a casi pantalla completa */
    .modal-overlay-bx { padding: 0.5rem; align-items: flex-end; }
    .modal-box-bx { max-width: 100% !important; max-height: 94vh; overflow-y: auto; border-radius: 14px 14px 0 0; }

    /* Selector de período y filtros */
    .period-selector-bx { width: 100%; display: flex; gap: 0.4rem; }
    .period-selector-bx select { flex: 1; }
    .filtros-dropdown-bx { flex-direction: column; align-items: stretch !important; }
    .filtros-dropdown-bx select { width: 100%; }

    /* Formularios: campos en columna cuando estaban en fila */
    .form-row-flex { flex-direction: column !important; }
}
</style>
