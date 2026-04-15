@extends('layouts.app')
@section('modulo', 'Facturación')

@section('contenido')
<style>
.fat-header { background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);padding:1.5rem 2rem;border-radius:14px;color:#fff;margin-bottom:1.5rem; }
.fat-title  { font-size:1.5rem;font-weight:800;letter-spacing:0.02em; }
.fat-sub    { font-size:0.82rem;color:#94a3b8;margin-top:0.2rem; }
.search-box { position:relative;max-width:480px; }
.search-box input { width:100%;padding:0.7rem 1rem 0.7rem 2.8rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:0.95rem;outline:none;transition:border .2s; }
.search-box input:focus { border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.12); }
.search-icon { position:absolute;left:0.85rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:1rem; }
.emp-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:1rem;margin-top:1.2rem; }
.emp-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem 1.3rem;cursor:pointer;transition:all .18s;text-decoration:none;color:inherit; }
.emp-card:hover { border-color:#3b82f6;box-shadow:0 4px 18px rgba(59,130,246,0.12);transform:translateY(-2px); }
.emp-card h3 { font-size:1rem;font-weight:700;color:#0f172a;margin:0 0 0.35rem; }
.emp-meta  { font-size:0.78rem;color:#64748b;display:flex;gap:1rem;flex-wrap:wrap; }
.emp-badge { font-size:0.65rem;font-weight:700;padding:0.18rem 0.55rem;border-radius:20px; }
.badge-si  { background:#dcfce7;color:#15803d; }
.badge-no  { background:#f1f5f9;color:#64748b; }
.emp-icon  { font-size:1.5rem;margin-bottom:0.4rem; }
.empty-state { text-align:center;padding:3rem;color:#94a3b8; }
</style>

<div class="fat-header">
    <div class="fat-title">🧾 Facturación</div>
    <div class="fat-sub">Seleccione una empresa para gestionar la facturación del período</div>
</div>

<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1.2rem 1.4rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.8rem;margin-bottom:0.6rem;">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" id="buscadorEmpresa" placeholder="Buscar empresa por nombre o NIT..." autocomplete="off">
        </div>
        <div style="font-size:0.8rem;color:#64748b;">
            <span id="contadorEmpresas">{{ $empresas->count() }}</span> empresa(s)
        </div>
    </div>

    @if($empresas->isEmpty())
        <div class="empty-state">
            <div style="font-size:3rem;">🏢</div>
            <div>No hay empresas registradas para este aliado.</div>
        </div>
    @else
    <div class="emp-grid" id="gridEmpresas">
        @foreach($empresas as $emp)
        <a class="emp-card" href="{{ route('admin.facturacion.empresa', $emp->id) }}"
           data-nombre="{{ strtolower($emp->empresa) }}" data-nit="{{ $emp->nit ?? '' }}">
            <div class="emp-icon">🏢</div>
            <h3>{{ $emp->empresa }}</h3>
            <div class="emp-meta">
                @if($emp->nit)
                    <span>NIT: {{ $emp->nit }}</span>
                @endif
                @if($emp->telefono || $emp->celular)
                    <span>📞 {{ $emp->celular ?: $emp->telefono }}</span>
                @endif
                <span class="emp-badge {{ strtoupper($emp->iva) === 'SI' ? 'badge-si' : 'badge-no' }}">
                    IVA {{ strtoupper($emp->iva) === 'SI' ? 'SÍ' : 'NO' }}
                </span>
            </div>
        </a>
        @endforeach
    </div>
    @endif
</div>

@push('scripts')
<script>
const cards = document.querySelectorAll('.emp-card');
document.getElementById('buscadorEmpresa').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    let visibles = 0;
    cards.forEach(c => {
        const match = !q || c.dataset.nombre.includes(q) || c.dataset.nit.includes(q);
        c.style.display = match ? '' : 'none';
        if (match) visibles++;
    });
    document.getElementById('contadorEmpresas').textContent = visibles;
});
</script>
@endpush
@endsection
