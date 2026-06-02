@extends('layouts.app')

@section('titulo', 'WhatsApp')
@section('modulo', 'Configuración WhatsApp')

@push('styles')
<style>
.wa-config-table { width: 100%; border-collapse: collapse; }
.wa-config-table th, .wa-config-table td { padding: .65rem .9rem; border-bottom: 1px solid #e2e8f0; font-size: .83rem; }
.wa-config-table th { background: #f8fafc; font-weight: 600; color: #475569; text-align: left; }
.wa-config-table tr:hover td { background: #f1f5f9; }
.badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 600; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-danger  { background: #fee2e2; color: #991b1b; }
.badge-secondary { background: #f1f5f9; color: #475569; }
.btn-sm { padding: .3rem .75rem; border-radius: 7px; font-size: .78rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: .3rem; transition: opacity .15s; }
.btn-sm:hover { opacity: .85; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-success { background: #10b981; color: #fff; }
.btn-outline  { background: transparent; border: 1px solid #cbd5e1; color: #475569; }
.page-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 8px rgba(0,0,0,.08); padding: 1.5rem; margin-bottom: 1.5rem; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.page-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; }
</style>
@endpush

@section('contenido')
<div class="contenido">
    @if(session('ok'))
        <div class="flash success">✅ {{ session('ok') }}</div>
    @endif

    <div class="page-card">
        <div class="page-header">
            <div>
                <div class="page-title">📱 Configuración WhatsApp por Aliado</div>
                <small style="color:#64748b">Solo el superadmin de Brynex puede configurar las credenciales de WhatsApp.</small>
            </div>
            <div>
                <a href="{{ route('admin.whatsapp.config.global') }}" class="btn-sm btn-primary" style="padding:.5rem 1rem; border-radius:8px; text-decoration:none;">
                    🔵 Cuenta BRYNEX Global
                </a>
            </div>
        </div>

        <table class="wa-config-table">
            <thead>
                <tr>
                    <th>Aliado</th>
                    <th>Modo</th>
                    <th>Phone Number ID</th>
                    <th>Número</th>
                    <th>Webhook</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($aliados as $aliado)
                @php $cfg = $aliado->whatsappConfig; @endphp
                <tr>
                    <td>
                        <strong>{{ $aliado->nombre }}</strong><br>
                        <small style="color:#94a3b8">{{ $aliado->nit }}</small>
                    </td>
                    <td>
                        @if(!$cfg)
                            <span class="badge badge-secondary">Sin configurar</span>
                        @elseif($cfg->usa_cuenta_brynex)
                            <span class="badge badge-warning">🔵 Cuenta Brynex</span>
                        @else
                            <span class="badge badge-success">✅ Cuenta propia</span>
                        @endif
                    </td>
                    <td style="font-family:monospace;font-size:.75rem;color:#475569">
                        {{ $cfg?->phone_number_id ?? '—' }}
                    </td>
                    <td>{{ $cfg?->numero_telefono ?? '—' }}</td>
                    <td>
                        @if($cfg?->webhook_verificado)
                            <span class="badge badge-success">✅ OK</span>
                        @else
                            <span class="badge badge-secondary">⬜ Pendiente</span>
                        @endif
                    </td>
                    <td>
                        @if($cfg?->activo)
                            <span class="badge badge-success">Activo</span>
                        @else
                            <span class="badge badge-danger">Inactivo</span>
                        @endif
                    </td>
                    <td style="display:flex;gap:.4rem;flex-wrap:wrap;">
                        <a href="{{ route('admin.whatsapp.config.edit', $aliado->id) }}" class="btn-sm btn-outline">⚙️ Editar</a>
                        @if($cfg?->credencialesCompletas())
                        <button class="btn-sm btn-success" onclick="verificarConexion({{ $aliado->id }}, this)">🔗 Verificar</button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
function verificarConexion(alidoId, btn) {
    btn.disabled = true;
    btn.textContent = '⏳ Verificando...';

    fetch('{{ route('admin.whatsapp.config.verificar') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ aliado_id: alidoId })
    })
    .then(r => r.json())
    .then(data => {
        alert(data.ok ? '✅ ' + data.mensaje : '❌ ' + data.mensaje);
        if (data.ok) location.reload();
    })
    .catch(() => alert('❌ Error de conexión'))
    .finally(() => {
        btn.disabled = false;
        btn.textContent = '🔗 Verificar';
    });
}
</script>
@endpush
