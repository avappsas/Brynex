{{-- Estilos compartidos por las pantallas de expedientes: el módulo de
     finanzas no tiene una hoja global con estas clases. --}}
<style>
    .exp-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        transition: box-shadow 0.15s ease, transform 0.15s ease;
    }
    a.exp-panel:hover {
        box-shadow: 0 8px 18px -8px rgba(15, 23, 42, 0.25);
        transform: translateY(-1px);
    }
    .form-label-bx { display:block; font-size:0.72rem; font-weight:700; color:#475569; margin-bottom:0.25rem; }
    .form-input-bx, .form-select-bx {
        width: 100%;
        padding: 0.5rem 0.7rem;
        border: 1px solid #cbd5e1;
        border-radius: 9px;
        font-size: 0.82rem;
        box-sizing: border-box;
        outline: none;
        background: #ffffff;
    }
    .form-input-bx:focus, .form-select-bx:focus { border-color: #f59e0b; }
    .btn-guardar-bx {
        height: 36px; padding: 0 1.1rem; border: none; border-radius: 9px;
        font-size: 0.8rem; font-weight: 700; color: #fff; cursor: pointer;
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }
    .btn-cancelar-bx {
        height: 36px; display:inline-flex; align-items:center; padding: 0 1.1rem;
        border-radius: 9px; font-size: 0.8rem; font-weight: 600; color: #475569;
        background: #f1f5f9; text-decoration: none;
    }
</style>
