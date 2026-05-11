{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- PARTIAL: ESTILOS CSS MÓDULO INCAPACIDADES                   --}}
{{-- Uso: @push('styles') @include(...) @endpush                 --}}
{{--      O incluir directamente dentro de @push('styles')       --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<style>
:root{--v:#10b981;--a:#f59e0b;--r:#ef4444;--g:#6b7280;}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:.6rem;}
.page-header h1{font-size:1.35rem;font-weight:700;color:#1e293b;}
.kpi-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin-bottom:1.2rem;}
.kpi{background:#fff;border-radius:12px;padding:.9rem 1.1rem;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.kpi .num{font-size:1.7rem;font-weight:700;line-height:1;}
.kpi .lbl{font-size:.72rem;color:#64748b;margin-top:.25rem;}
.kpi.warn .num{color:#d97706;} .kpi.danger .num{color:#dc2626;} .kpi.ok .num{color:#059669;}
.filter-bar{background:#fff;border-radius:12px;padding:.85rem 1rem;margin-bottom:1rem;border:1px solid #e2e8f0;display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;}
.filter-bar select,.filter-bar input{border:1px solid #cbd5e1;border-radius:8px;padding:.38rem .65rem;font-size:.8rem;background:#f8fafc;}
.filter-bar label{font-size:.72rem;color:#64748b;display:block;margin-bottom:.2rem;}
.btn{display:inline-flex;align-items:center;gap:.35rem;padding:.42rem .9rem;border-radius:8px;font-size:.8rem;font-weight:600;border:none;cursor:pointer;text-decoration:none;transition:all .15s;}
.btn-primary{background:#2563eb;color:#fff;} .btn-primary:hover{background:#1d4ed8;}
.btn-sm{padding:.3rem .65rem;font-size:.75rem;}
.btn-success{background:#059669;color:#fff;} .btn-success:hover{background:#047857;}
.btn-warning{background:#d97706;color:#fff;}
.btn-danger{background:#dc2626;color:#fff;}
.btn-secondary{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;}
.btn-info{background:#0891b2;color:#fff;}
.card{background:#fff;border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,.07);border:1px solid #e2e8f0;overflow:hidden;}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.82rem;}
thead th{background:#f8fafc;color:#475569;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:.65rem .85rem;border-bottom:2px solid #e2e8f0;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .1s;}
tbody tr:hover{background:#f8fafc;}
tbody td{padding:.6rem .85rem;vertical-align:middle;}
.semaforo{display:inline-flex;align-items:center;gap:.3rem;font-size:.78rem;font-weight:600;padding:.25rem .6rem;border-radius:999px;}
.sem-verde{background:rgba(16,185,129,.12);color:#059669;}
.sem-amarillo{background:rgba(245,158,11,.12);color:#d97706;}
.sem-rojo{background:rgba(239,68,68,.12);color:#dc2626;}
.sem-gris{background:rgba(107,114,128,.12);color:#6b7280;}
.badge{display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.7rem;font-weight:600;}
.badge-warning{background:#fef3c7;color:#92400e;}
.badge-success{background:#d1fae5;color:#065f46;}
.badge-info{background:#dbeafe;color:#1e40af;}
.badge-danger{background:#fee2e2;color:#991b1b;}
.badge-secondary{background:#f1f5f9;color:#475569;}
.badge-primary{background:#eff6ff;color:#1d4ed8;}
.alerta-180{background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:.3rem .6rem;font-size:.72rem;color:#991b1b;font-weight:600;}
/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:flex-start;justify-content:center;padding:1.5rem;overflow-y:auto;}
.modal-overlay.open{display:flex;}
.modal{background:#fff;border-radius:16px;width:100%;max-width:820px;box-shadow:0 20px 60px rgba(0,0,0,.2);margin:auto;}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0;}
.modal-header h3{font-size:1.05rem;font-weight:700;color:#1e293b;}
.modal-body{padding:1.25rem;}
.modal-footer{padding:.9rem 1.25rem;border-top:1px solid #e2e8f0;display:flex;gap:.6rem;justify-content:flex-end;}
.btn-close-modal{background:none;border:none;font-size:1.3rem;cursor:pointer;color:#94a3b8;padding:.2rem;}
.form-group{margin-bottom:.85rem;}
.form-group label{display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:.3rem;}
.form-group input,.form-group select,.form-group textarea{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:.45rem .7rem;font-size:.83rem;font-family:inherit;}
.form-group textarea{min-height:70px;resize:vertical;}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem;}
.section-title{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;border-bottom:1px solid #e2e8f0;padding-bottom:.4rem;margin-bottom:.8rem;margin-top:.5rem;}
/* Timeline gestiones */
.timeline{display:flex;flex-direction:column;gap:.6rem;max-height:280px;overflow-y:auto;padding-right:.25rem;}
.timeline-item{display:flex;gap:.75rem;align-items:flex-start;}
.tl-dot{width:32px;height:32px;border-radius:50%;background:#eff6ff;border:2px solid #bfdbfe;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;}
.tl-content{flex:1;background:#f8fafc;border-radius:10px;padding:.55rem .75rem;border:1px solid #e2e8f0;}
.tl-content .tl-tipo{font-size:.72rem;font-weight:700;color:#2563eb;}
.tl-content .tl-tramite{font-size:.8rem;color:#374151;margin:.2rem 0;}
.tl-content .tl-meta{font-size:.68rem;color:#94a3b8;}
/* Tabs */
.tabs{display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:1rem;}
.tab-btn{padding:.5rem 1rem;font-size:.82rem;font-weight:600;background:none;border:none;cursor:pointer;color:#64748b;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s;}
.tab-btn.active{color:#2563eb;border-bottom-color:#2563eb;}
.tab-pane{display:none;} .tab-pane.active{display:block;}
/* Prórrogas */
.proroga-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:.85rem 1rem;margin-bottom:.6rem;}
.proroga-card h5{font-size:.82rem;font-weight:700;color:#1e293b;margin-bottom:.5rem;}
</style>
