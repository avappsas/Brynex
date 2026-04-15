@extends('layouts.app')
@section('modulo', 'Cuentas Bancarias')

@section('contenido')
<style>
.cc-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1rem}
.card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:1rem}
.card-head{background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:.65rem 1rem;font-size:.85rem;font-weight:700;color:#0f172a;display:flex;align-items:center;justify-content:space-between}
table.tbl{width:100%;border-collapse:collapse;font-size:.8rem}
.tbl th{background:#0f172a;color:#94a3b8;font-size:.63rem;text-transform:uppercase;padding:.42rem .6rem;white-space:nowrap}
.tbl td{padding:.4rem .6rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.tbl tr:hover td{background:#f8fafc}
.badge-cobro{display:inline-flex;align-items:center;gap:.25rem;padding:.18rem .55rem;border-radius:20px;font-size:.7rem;font-weight:700;cursor:pointer;border:none;transition:all .15s}
.badge-cobro.on {background:#dbeafe;color:#1d4ed8;}
.badge-cobro.off{background:#f1f5f9;color:#94a3b8;}
.badge-cobro:hover{opacity:.8}
.badge-activo.on {background:#dcfce7;color:#15803d;padding:.1rem .45rem;border-radius:12px;font-size:.65rem;font-weight:700;}
.badge-activo.off{background:#fee2e2;color:#dc2626;padding:.1rem .45rem;border-radius:12px;font-size:.65rem;font-weight:700;}
.btn-sm{padding:.22rem .6rem;font-size:.72rem;border-radius:6px;border:none;cursor:pointer;font-weight:600}
.flb{display:block;font-size:.67rem;font-weight:700;color:#475569;margin-bottom:.15rem;text-transform:uppercase}
.finp{width:100%;padding:.36rem .48rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.82rem;box-sizing:border-box}
.finp:focus{outline:none;border-color:#3b82f6}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.6rem;padding:1rem}
</style>

<div class="cc-header">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
        <div>
            <a href="{{ route('admin.configuracion.hub') }}" style="color:#94a3b8;font-size:.78rem;text-decoration:none">← Configuración</a>
            <div style="font-size:1.1rem;font-weight:800;margin-top:.2rem">🏦 Cuentas Bancarias</div>
            <div style="font-size:.75rem;color:#94a3b8;margin-top:.15rem">
                Marca con 💳 <strong>Para Cobro</strong> las cuentas que aparecerán en la Cuenta de Cobro.
            </div>
        </div>
    </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border-radius:8px;padding:.55rem 1rem;margin-bottom:.7rem;font-size:.82rem;color:#15803d;font-weight:600">
    ✅ {{ session('success') }}
</div>
@endif

{{-- Tabla de cuentas --}}
<div class="card">
    <div class="card-head">
        <span>📋 Cuentas registradas</span>
        <button onclick="document.getElementById('formNueva').style.display=document.getElementById('formNueva').style.display==='none'?'block':'none'"
                style="background:#2563eb;color:#fff;border:none;border-radius:7px;padding:.3rem .8rem;font-size:.78rem;cursor:pointer;font-weight:700">
            ➕ Nueva cuenta
        </button>
    </div>

    {{-- Formulario nueva cuenta (oculto por defecto) --}}
    <div id="formNueva" style="display:none;border-bottom:1px solid #e2e8f0;background:#f0f9ff">
        <form method="POST" action="{{ route('admin.configuracion.cuentas.store') }}">
            @csrf
            <div class="form-grid">
                <div>
                    <label class="flb">Banco *</label>
                    <input type="text" name="banco" class="finp" placeholder="Ej: Bancolombia" required>
                </div>
                <div>
                    <label class="flb">Nombre titular</label>
                    <input type="text" name="nombre" class="finp" placeholder="Nombre persona/empresa">
                </div>
                <div>
                    <label class="flb">NIT / C.C.</label>
                    <input type="text" name="nit" class="finp" placeholder="NIT o cédula">
                </div>
                <div>
                    <label class="flb">Tipo *</label>
                    <select name="tipo_cuenta" class="finp">
                        <option value="Ahorros">Ahorros</option>
                        <option value="Corriente">Corriente</option>
                    </select>
                </div>
                <div>
                    <label class="flb">Número cuenta *</label>
                    <input type="text" name="numero_cuenta" class="finp" placeholder="Ej: 123-456789" required>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.1rem">
                    <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;cursor:pointer">
                        <input type="checkbox" name="cobro" value="1" style="width:1rem;height:1rem"> Para Cobro
                    </label>
                    <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;cursor:pointer">
                        <input type="checkbox" name="activo" value="1" checked style="width:1rem;height:1rem"> Activa
                    </label>
                </div>
                <div style="padding-top:.9rem">
                    <button type="submit" style="background:#16a34a;color:#fff;border:none;border-radius:7px;padding:.4rem 1.2rem;font-size:.82rem;font-weight:700;cursor:pointer;width:100%">
                        💾 Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div style="overflow-x:auto">
    <table class="tbl">
        <thead>
            <tr>
                <th>Banco</th>
                <th>Titular</th>
                <th>Tipo</th>
                <th>Número</th>
                <th style="text-align:center">Para Cobro</th>
                <th style="text-align:center">Activa</th>
                <th style="text-align:center">Acciones</th>
            </tr>
        </thead>
        <tbody>
        @forelse($cuentas as $c)
        <tr id="row-{{ $c->id }}">
            <td style="font-weight:700">{{ $c->banco }}</td>
            <td style="font-size:.75rem;color:#475569">{{ $c->nombre ?? '—' }}<br>
                @if($c->nit)<span style="color:#94a3b8;font-size:.7rem">{{ $c->nit }}</span>@endif
            </td>
            <td style="font-size:.75rem">{{ $c->tipo_cuenta }}</td>
            <td style="font-family:monospace;font-weight:600">{{ $c->numero_cuenta }}</td>
            <td style="text-align:center">
                <button class="badge-cobro {{ $c->cobro ? 'on' : 'off' }}"
                        id="cobro-{{ $c->id }}"
                        onclick="toggleCobro({{ $c->id }}, this)"
                        title="{{ $c->cobro ? 'Quitar de Cuenta de Cobro' : 'Incluir en Cuenta de Cobro' }}">
                    💳 {{ $c->cobro ? 'Cobro ✓' : 'Sin cobro' }}
                </button>
            </td>
            <td style="text-align:center">
                <span class="badge-activo {{ $c->activo ? 'on' : 'off' }}">
                    {{ $c->activo ? 'Activa' : 'Inactiva' }}
                </span>
            </td>
            <td style="text-align:center">
                <button class="btn-sm" style="background:#eff6ff;color:#1d4ed8"
                        onclick="editarCuenta({{ $c->id }}, {{ json_encode($c) }})">✏️</button>
                <button class="btn-sm" style="background:#fee2e2;color:#dc2626;margin-left:3px"
                        onclick="eliminarCuenta({{ $c->id }})">🗑</button>
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8">No hay cuentas bancarias registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

{{-- Modal editar --}}
<div id="modal-editar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center"
     onclick="if(event.target.id==='modal-editar')cerrarModal()">
    <div style="background:#fff;border-radius:14px;width:min(580px,96vw);overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.35)">
        <div style="background:#1e3a5f;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center">
            <span style="color:#fff;font-weight:700;font-size:.9rem">✏️ Editar Cuenta Bancaria</span>
            <button onclick="cerrarModal()" style="background:rgba(255,255,255,.18);color:#fff;border:none;border-radius:5px;width:28px;height:28px;cursor:pointer;font-weight:800">×</button>
        </div>
        <form id="formEditar" method="POST">
            @csrf @method('PATCH')
            <div class="form-grid">
                <div>
                    <label class="flb">Banco *</label>
                    <input type="text" name="banco" id="e_banco" class="finp" required>
                </div>
                <div>
                    <label class="flb">Nombre titular</label>
                    <input type="text" name="nombre" id="e_nombre" class="finp">
                </div>
                <div>
                    <label class="flb">NIT / C.C.</label>
                    <input type="text" name="nit" id="e_nit" class="finp">
                </div>
                <div>
                    <label class="flb">Tipo *</label>
                    <select name="tipo_cuenta" id="e_tipo" class="finp">
                        <option value="Ahorros">Ahorros</option>
                        <option value="Corriente">Corriente</option>
                    </select>
                </div>
                <div>
                    <label class="flb">Número cuenta *</label>
                    <input type="text" name="numero_cuenta" id="e_numero" class="finp" required>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.1rem">
                    <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;cursor:pointer">
                        <input type="checkbox" name="cobro" id="e_cobro" value="1" style="width:1rem;height:1rem"> Para Cobro
                    </label>
                    <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;cursor:pointer">
                        <input type="checkbox" name="activo" id="e_activo" value="1" style="width:1rem;height:1rem"> Activa
                    </label>
                </div>
                <div style="padding-top:.9rem">
                    <button type="submit" style="background:#2563eb;color:#fff;border:none;border-radius:7px;padding:.4rem 1.2rem;font-size:.82rem;font-weight:700;cursor:pointer;width:100%">
                        💾 Actualizar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// Toggle cobro via AJAX (sin reload)
async function toggleCobro(id, btn) {
    const esCobro = btn.classList.contains('on');
    const resp = await fetch(`{{ url('admin/configuracion/cuentas') }}/${id}`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-HTTP-Method-Override':'PATCH','Accept':'application/json'},
        body: JSON.stringify({ cobro: esCobro ? 0 : 1, _method: 'PATCH',
            banco: btn.closest('tr').cells[0].textContent.trim(),
            tipo_cuenta: btn.closest('tr').cells[2].textContent.trim(),
            numero_cuenta: btn.closest('tr').cells[3].textContent.trim() })
    });
    if(resp.ok) {
        btn.classList.toggle('on', !esCobro);
        btn.classList.toggle('off', esCobro);
        btn.textContent = !esCobro ? '💳 Cobro ✓' : '💳 Sin cobro';
        btn.title = !esCobro ? 'Quitar de Cuenta de Cobro' : 'Incluir en Cuenta de Cobro';
    }
}

function editarCuenta(id, data) {
    document.getElementById('formEditar').action = `{{ url('admin/configuracion/cuentas') }}/${id}`;
    document.getElementById('e_banco').value   = data.banco || '';
    document.getElementById('e_nombre').value  = data.nombre || '';
    document.getElementById('e_nit').value     = data.nit || '';
    document.getElementById('e_tipo').value    = data.tipo_cuenta || 'Ahorros';
    document.getElementById('e_numero').value  = data.numero_cuenta || '';
    document.getElementById('e_cobro').checked  = !!data.cobro;
    document.getElementById('e_activo').checked = data.activo !== false && data.activo !== 0;
    document.getElementById('modal-editar').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modal-editar').style.display = 'none';
}

async function eliminarCuenta(id) {
    if (!confirm('¿Eliminar esta cuenta bancaria?')) return;
    const r = await fetch(`{{ url('admin/configuracion/cuentas') }}/${id}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'}
    });
    if (r.ok) {
        const row = document.getElementById(`row-${id}`);
        if (row) row.remove();
    }
}
</script>
@endsection
