{{--
    Sistema de UI base + capa responsive del módulo Finanzas Personales.
    Se incluye con @include('finanzas.partials._responsive_fin') al inicio del
    @section('contenido') de cada vista. Define las clases comunes (botones,
    barras, tablas, modales, formularios) para que ninguna vista dependa de
    CSS copiado; los estilos propios de cada vista se cargan después y pueden
    sobreescribir cualquier regla de esta capa.
--}}
@push('styles')
<style>
[x-cloak] { display: none !important; }

/* ══════════════ HEADER BANNER ══════════════ */
.fin-banner-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #1e40af 100%);
    border-radius: 14px; padding: 1.1rem 1.4rem; color: #fff;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.8rem;
    margin-bottom: 1.25rem;
}
.fin-banner-text { display: flex; flex-direction: column; gap: 0.15rem; }
.fin-banner-breadcrumb { display: flex; align-items: center; gap: 0.4rem; font-size: 0.72rem; color: #94a3b8; margin-bottom: 0.25rem; }
.fin-banner-breadcrumb a { color: #cbd5e1; text-decoration: none; font-weight: 500; }
.fin-banner-breadcrumb a:hover { color: #fff; }
.fin-banner-breadcrumb span { color: #94a3b8; }
.fin-banner-title { font-size: 1.3rem; font-weight: 800; color: #fff; }
.fin-banner-sub { font-size: 0.77rem; color: #94a3b8; margin-top: 0.15rem; }
.fin-banner-options { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }

/* ══════════════ BASE ══════════════ */
.finanzas-container { max-width: 1080px; margin: 0 auto; padding: 0.5rem; color: #0f172a; }

/* Barra superior: breadcrumb + acciones */
.fin-top-bar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.breadcrumb-bx { display: flex; align-items: center; gap: 0.4rem; font-size: 0.75rem; color: #94a3b8; flex-wrap: wrap; }
.breadcrumb-bx a { color: #475569; text-decoration: none; font-weight: 600; }
.breadcrumb-bx a:hover { color: #4f46e5; }
.breadcrumb-bx span:last-child { color: #0f172a; font-weight: 700; }

/* Encabezado de página */
.fin-header-section { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
.fin-header-section h1, .header-text h1 { font-size: 1.35rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; }
.fin-header-section p, .header-text p { font-size: 0.82rem; color: #64748b; margin-top: 0.25rem; }

/* ══════════════ BOTONES ══════════════ */
.btn-fin {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
    padding: 0.55rem 1.15rem; border: none; border-radius: 10px;
    font-size: 0.8rem; font-weight: 700; cursor: pointer; text-decoration: none;
    background: linear-gradient(135deg, #4f46e5, #4338ca); color: #fff;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.28);
    transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
    white-space: nowrap; line-height: 1.2;
}
.btn-fin:hover { transform: translateY(-1px); filter: brightness(1.06); box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35); }
.btn-fin:active { transform: translateY(0); }
.btn-fin.success { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 2px 8px rgba(16, 185, 129, 0.28); }
.btn-fin.success:hover { box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35); }
.btn-fin.danger { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 2px 8px rgba(239, 68, 68, 0.28); }

/* Botón secundario tipo enlace (borde suave, fondo blanco) */
.btn-fin-link {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 0.95rem; border: 1px solid #e2e8f0; border-radius: 10px;
    font-size: 0.76rem; font-weight: 700; cursor: pointer; text-decoration: none;
    background: #fff; color: #334155; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    transition: all 0.12s ease; white-space: nowrap;
}
.btn-fin-link:hover { border-color: #c7d2fe; color: #4f46e5; background: #f5f7ff; }
.btn-fin-link.primary { color: #4f46e5; border-color: #c7d2fe; background: #eef2ff; }
.btn-fin-link.success { color: #047857; border-color: #a7f3d0; background: #ecfdf5; }

.btn-glass-bx {
    padding: 0.5rem 1.1rem; border: 1px solid #e2e8f0; border-radius: 10px;
    font-size: 0.78rem; font-weight: 600; cursor: pointer; background: #fff; color: #475569;
    transition: all 0.12s ease;
}
.btn-glass-bx:hover { background: #f8fafc; border-color: #cbd5e1; }

.btn-icon-bx { background: none; border: none; font-size: 1rem; cursor: pointer; padding: 0.25rem 0.35rem; border-radius: 7px; transition: background 0.1s; }
.btn-icon-bx:hover { background: #f1f5f9; }

/* Selectores de período */
.period-selector-bx { display: flex; gap: 0.4rem; align-items: center; }
.select-fin {
    padding: 0.5rem 0.75rem; border-radius: 10px; border: 1px solid #e2e8f0;
    font-size: 0.78rem; font-weight: 600; color: #334155; background: #fff;
    outline: none; cursor: pointer; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
}
.select-fin:focus { border-color: #a5b4fc; }

/* ══════════════ KPIs ══════════════ */
.fin-kpis-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.kpi-card { background: #fff; border-radius: 14px; padding: 1rem 1.1rem; display: flex; align-items: center; gap: 0.9rem; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05); border: 1px solid #eef1f6; }
.kpi-icon { font-size: 1.6rem; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: #f8fafc; border-radius: 11px; flex-shrink: 0; }
.kpi-content { display: flex; flex-direction: column; min-width: 0; }
.kpi-label { font-size: 0.68rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; }
.kpi-val { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-top: 0.1rem; }

/* ══════════════ TABLAS ══════════════ */
.card-tabla-bx, .card-tabla { background: #fff; border-radius: 14px; border: 1px solid #eef1f6; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04); overflow: hidden; }
.tabla-brynex-bx { width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left; }
.tabla-brynex-bx th { background: #f8fafc; font-weight: 700; color: #64748b; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; padding: 0.7rem 1rem; border-bottom: 1px solid #eef1f6; }
.tabla-brynex-bx td { padding: 0.7rem 1rem; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
.tabla-brynex-bx tbody tr:last-child td { border-bottom: none; }
.tabla-brynex-bx tbody tr:hover td { background: #fafbff; }

/* ══════════════ MODALES ══════════════ */
.modal-overlay-bx { position: fixed; inset: 0; z-index: 9998; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal-box-bx { background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25); width: 100%; max-width: 470px; overflow: hidden; max-height: 92vh; overflow-y: auto; }
.modal-head-bx { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; color: #fff; background: linear-gradient(135deg, #4f46e5, #4338ca); }
.modal-head-bx h3 { color: #fff; font-size: 0.95rem; font-weight: 700; }
.modal-close-bx { background: none; border: none; font-size: 1.35rem; cursor: pointer; color: rgba(255,255,255,0.75); line-height: 1; }
.modal-close-bx:hover { color: #fff; }
.modal-body-bx { padding: 1.25rem; }
.modal-foot-bx { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.25rem; border-top: 1px solid #eef1f6; background: #f8fafc; }

/* ══════════════ FORMULARIOS ══════════════ */
.form-group-bx { display: flex; flex-direction: column; gap: 0.3rem; }
.form-label-bx { font-size: 0.75rem; font-weight: 700; color: #334155; }
.form-input-bx, .form-select-bx {
    padding: 0.55rem 0.8rem; border: 1px solid #e2e8f0; border-radius: 10px;
    font-size: 0.84rem; outline: none; background: #fff; color: #0f172a;
    transition: border-color 0.12s ease, box-shadow 0.12s ease;
}
.form-input-bx:focus, .form-select-bx:focus { border-color: #a5b4fc; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12); }
.form-select-bx { cursor: pointer; }

/* Utilidades responsive */
.solo-movil { display: none; }

/* ══════════════ RESPONSIVE (celular) ══════════════ */
@media (max-width: 768px) {
    .fin-banner-header { flex-direction: column !important; align-items: stretch !important; padding: 1rem !important; gap: 0.75rem !important; }
    .fin-banner-options { width: 100% !important; justify-content: flex-start !important; }
    .fin-banner-title { font-size: 1.15rem !important; }
    .fin-banner-sub { font-size: 0.72rem !important; }

    .solo-desktop { display: none !important; }
    .solo-movil { display: flex; flex-direction: column; gap: 0.5rem; }

    .finanzas-container { padding: 0.25rem !important; }
    .fin-top-bar { flex-direction: column !important; align-items: stretch !important; gap: 0.6rem !important; }
    .fin-top-bar > div { display: flex !important; flex-wrap: wrap !important; gap: 0.5rem !important; }
    .breadcrumb-bx { font-size: 0.7rem !important; }
    .fin-header-section { flex-direction: column !important; align-items: stretch !important; gap: 0.75rem !important; }
    .fin-header-section h1, .header-text h1 { font-size: 1.15rem !important; }
    .fin-header-section p, .header-text p { font-size: 0.78rem !important; }

    .fin-kpis-grid { grid-template-columns: 1fr 1fr !important; gap: 0.6rem !important; }
    .kpi-card { padding: 0.7rem !important; gap: 0.5rem !important; }
    .kpi-icon { width: 34px !important; height: 34px !important; font-size: 1.2rem !important; }
    .kpi-val { font-size: 0.95rem !important; }
    .kpi-label { font-size: 0.6rem !important; }

    .card-tabla-bx, .card-tabla { overflow-x: auto !important; -webkit-overflow-scrolling: touch; }
    .card-tabla-bx table, .card-tabla table { min-width: 560px; }
    .tabla-brynex-bx th, .tabla-brynex-bx td { padding: 0.5rem 0.6rem !important; font-size: 0.72rem !important; white-space: nowrap; }

    .btn-fin, .btn-fin-link { font-size: 0.74rem !important; padding: 0.5rem 0.85rem !important; flex: 1; }

    .modal-overlay-bx { padding: 0.5rem !important; align-items: flex-end !important; }
    .modal-box-bx { max-width: 100% !important; max-height: 94vh !important; border-radius: 16px 16px 0 0 !important; }

    .form-input-bx, .form-select-bx { font-size: 16px !important; padding: 0.6rem 0.75rem !important; }
    .modal-foot-bx { position: sticky; bottom: 0; }
}
</style>
@endpush
