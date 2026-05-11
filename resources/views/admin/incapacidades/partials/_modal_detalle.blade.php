{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- PARTIAL: MODAL DETALLE INCAPACIDAD (shell vacío)            --}}
{{-- El contenido se inyecta via JS en verDetalle(id)            --}}
{{-- Uso: @include('admin.incapacidades.partials._modal_detalle') --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="modalDetalle">
<div class="modal" style="max-width:920px">
    <div class="modal-header">
        <div>
            <h3 id="detalleTitle">Detalle de Incapacidad</h3>
            <div id="detalleSubtitle" style="font-size:.78rem;color:#64748b;margin-top:.2rem"></div>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center">
            <div id="detalleSemaforo"></div>
            <button class="btn-close-modal" onclick="cerrarModal('modalDetalle')">✕</button>
        </div>
    </div>
    <div class="modal-body" id="detalleCuerpo">
        <div style="text-align:center;padding:2rem;color:#94a3b8">Cargando...</div>
    </div>
</div>
</div>
