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
.badge-uso{display:inline-flex;align-items:center;gap:.22rem;padding:.18rem .5rem;border-radius:20px;font-size:.68rem;font-weight:700;cursor:pointer;border:none;transition:all .15s;white-space:nowrap}
.badge-uso.off{background:#f1f5f9;color:#94a3b8;}
.badge-uso.on[data-uso="cobro"]      {background:#dbeafe;color:#1d4ed8;}
.badge-uso.on[data-uso="facturacion"]{background:#dcfce7;color:#15803d;}
.badge-uso.on[data-uso="incapacidad"]{background:#fef3c7;color:#b45309;}
.badge-uso:hover{opacity:.75}
.usos-cell{display:flex;gap:.25rem;justify-content:center;flex-wrap:wrap}
.chk-uso{display:flex;align-items:center;gap:.35rem;font-size:.8rem;cursor:pointer;color:#334155;white-space:nowrap}
.chk-uso input{width:1rem;height:1rem}
.usos-box{display:flex;align-items:center;gap:.9rem;flex-wrap:wrap;padding-top:1.1rem}

/* Buscador y botón dentro del header oscuro */
.cc-buscar{
    padding:.34rem .7rem;font-size:.78rem;border-radius:7px;width:230px;max-width:100%;
    background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;outline:none;
}
.cc-buscar::placeholder{color:#94a3b8}
.cc-buscar:focus{border-color:#60a5fa;background:rgba(255,255,255,.16)}
.cc-btn-nueva{
    background:#2563eb;color:#fff;border:none;border-radius:7px;padding:.36rem .85rem;
    font-size:.78rem;cursor:pointer;font-weight:700;white-space:nowrap;
}
.cc-btn-nueva:hover{background:#1d4ed8}

/* Leyenda de usos (pie de página) */
.leyenda{padding:.9rem 1rem;display:flex;flex-direction:column;gap:.45rem;font-size:.78rem;color:#475569}
.badge-activo.on {background:#dcfce7;color:#15803d;padding:.1rem .45rem;border-radius:12px;font-size:.65rem;font-weight:700;}
.badge-activo.off{background:#fee2e2;color:#dc2626;padding:.1rem .45rem;border-radius:12px;font-size:.65rem;font-weight:700;}
.btn-sm{padding:.22rem .6rem;font-size:.72rem;border-radius:6px;border:none;cursor:pointer;font-weight:600}
.flb{display:block;font-size:.67rem;font-weight:700;color:#475569;margin-bottom:.15rem;text-transform:uppercase}
.finp{width:100%;padding:.36rem .48rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.82rem;box-sizing:border-box}
.finp:focus{outline:none;border-color:#3b82f6}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.6rem;padding:1rem}

/* th-select para filtros de cabecera como en cobros */
.th-select {
    width:100%; background:transparent; border:none; border-bottom:1px solid rgba(255,255,255,.15);
    color:#fff; font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
    padding:.2rem .2rem; cursor:pointer; outline:none; appearance:auto; -webkit-appearance:auto;
}
.th-select:hover { border-bottom-color:rgba(255,255,255,.5); }
.th-select:focus { border-bottom-color:#3b82f6; }
.th-select option { background:#0f172a; color:#fff; font-weight:600; text-transform:none; }
.th-select.activo { border-bottom-color:#3b82f6; color:#93c5fd; }
</style>

<div class="cc-header">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
        <div>
            <a href="{{ route('admin.configuracion.hub') }}" style="color:#94a3b8;font-size:.78rem;text-decoration:none">← Configuración</a>
            <div style="font-size:1.1rem;font-weight:800;margin-top:.2rem">🏦 Cuentas Bancarias</div>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
            <input type="text" id="filtro-titular" class="cc-buscar" placeholder="🔍 Buscar por titular..."
                   oninput="filtrarCuentas()">
            <button class="cc-btn-nueva"
                    onclick="document.getElementById('formNueva').style.display=document.getElementById('formNueva').style.display==='none'?'block':'none'">
                ➕ Nueva cuenta
            </button>
        </div>
    </div>
</div>

@if ($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:.8rem 1.1rem;margin-bottom:1rem;color:#b91c1c;font-size:.82rem">
    <div style="font-weight:700;margin-bottom:.3rem">⚠️ Se presentaron algunos errores al guardar:</div>
    <ul style="margin:0;padding-left:1.2rem">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- Tabla de cuentas --}}
<div class="card">

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
                    <label class="flb">Tipo (opcional)</label>
                    <select name="tipo_cuenta" class="finp">
                        <option value="">— Ninguno (Nequi, etc.) —</option>
                        <option value="Ahorros">Ahorros</option>
                        <option value="Corriente">Corriente</option>
                    </select>
                </div>
                <div>
                    <label class="flb">Número cuenta *</label>
                    <input type="text" name="numero_cuenta" class="finp" placeholder="Ej: 123-456789" required>
                </div>
                <div>
                    <label class="flb">Llave de Pago</label>
                    <input type="text" name="llave" class="finp" placeholder="Llave alfanumérica (opcional)">
                </div>
                <div class="usos-box" style="grid-column:1/-1">
                    <span class="flb" style="margin:0 .3rem 0 0">Usos:</span>
                    <label class="chk-uso"><input type="checkbox" name="cobro" value="1"> 💳 Cobro</label>
                    <label class="chk-uso"><input type="checkbox" name="facturacion" value="1" checked> 🧾 Facturación</label>
                    <label class="chk-uso"><input type="checkbox" name="incapacidad" value="1"> 🏥 Incapacidad</label>
                    <label class="chk-uso" style="margin-left:auto"><input type="checkbox" name="activo" value="1" checked> Activa</label>
                </div>
                <div style="padding-top:.9rem;grid-column:1/-1">
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
                <th>
                    <select id="filtro-banco" onchange="filtrarCuentas()" class="th-select">
                        <option value="">↓ Banco</option>
                        @foreach($cuentas->pluck('banco')->unique()->sort() as $banco)
                            <option value="{{ $banco }}">{{ $banco }}</option>
                        @endforeach
                    </select>
                </th>
                <th>Titular</th>
                <th>
                    <select id="filtro-tipo" onchange="filtrarCuentas()" class="th-select">
                        <option value="">↓ Tipo</option>
                        <option value="Ahorros">Ahorros</option>
                        <option value="Corriente">Corriente</option>
                        <option value="Ninguno">Ninguno</option>
                    </select>
                </th>
                <th>Número</th>
                <th style="text-align:center;min-width:250px">
                    <select id="filtro-uso" onchange="filtrarCuentas()" class="th-select">
                        <option value="">↓ Uso</option>
                        <option value="cobro">💳 Cobro</option>
                        <option value="facturacion">🧾 Facturación</option>
                        <option value="incapacidad">🏥 Incapacidad</option>
                        <option value="ninguno">Sin uso asignado</option>
                    </select>
                </th>
                <th style="text-align:center">
                    {{-- Por defecto solo se ven las activas --}}
                    <select id="filtro-activo" onchange="filtrarCuentas()" class="th-select">
                        <option value="1" selected>↓ Activas</option>
                        <option value="0">Inactivas</option>
                        <option value="">Todas</option>
                    </select>
                </th>
                <th style="text-align:center">Acciones</th>
            </tr>
        </thead>
        <tbody>
        @forelse($cuentas as $c)
        <tr id="row-{{ $c->id }}"
            data-banco="{{ strtolower($c->banco) }}"
            data-titular="{{ strtolower($c->nombre ?? '') }}"
            data-tipo="{{ $c->tipo_cuenta ?? '' }}"
            data-cobro="{{ $c->cobro ? '1' : '0' }}"
            data-facturacion="{{ $c->facturacion ? '1' : '0' }}"
            data-incapacidad="{{ $c->incapacidad ? '1' : '0' }}"
            data-activo="{{ $c->activo ? '1' : '0' }}"
            data-cuenta="{{ json_encode($c) }}">
            <td style="font-weight:700;color:#0f172a">{{ $c->banco }}</td>
            <td style="font-size:.75rem;color:#475569">{{ $c->nombre ?? '—' }}<br>
                @if($c->nit)<span style="color:#94a3b8;font-size:.7rem">{{ $c->nit }}</span>@endif
            </td>
            <td style="font-size:.75rem;color:#475569">{{ $c->tipo_cuenta ?? '—' }}</td>
            <td style="font-family:monospace;font-weight:600;color:#0f172a">
                {{ $c->numero_cuenta }}
                @if($c->llave)
                    <br><span style="font-family:sans-serif;font-size:.7rem;color:#475569;background:#f1f5f9;padding:.1rem .35rem;border-radius:4px;font-weight:600">🔑 {{ $c->llave }}</span>
                @endif
            </td>
            <td>
                <div class="usos-cell">
                    @foreach([
                        'cobro'       => ['💳', 'Cobro',       $c->cobro],
                        'facturacion' => ['🧾', 'Facturación', $c->facturacion],
                        'incapacidad' => ['🏥', 'Incapacidad', $c->incapacidad],
                    ] as $uso => [$icono, $etiqueta, $activa])
                        <button class="badge-uso {{ $activa ? 'on' : 'off' }}"
                                data-uso="{{ $uso }}"
                                id="{{ $uso }}-{{ $c->id }}"
                                onclick="toggleUso({{ $c->id }}, '{{ $uso }}', this)"
                                title="{{ $activa ? 'Quitar de' : 'Incluir en' }} {{ $etiqueta }}">
                            {{ $icono }} {{ $etiqueta }}
                        </button>
                    @endforeach
                </div>
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
                        onclick="abrirModalCuenta({{ $c->id }}, '{{ addslashes($c->banco) }}', '{{ addslashes($c->numero_cuenta) }}')"
                        title="Inactivar o Eliminar">🗑</button>
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8">No hay cuentas bancarias registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

{{-- Leyenda: qué significa cada marca de uso --}}
<div class="card">
    <div class="card-head">
        <span>ℹ️ Para qué sirve cada marca</span>
        <span style="font-weight:700">📋 Cuentas registradas <span id="contador-cuentas" style="color:#64748b;font-weight:600"></span></span>
    </div>
    <div class="leyenda">
        <div>💳 <strong style="color:#1d4ed8">Cobro</strong> — aparece en la Cuenta de Cobro que ve el cliente.</div>
        <div>🧾 <strong style="color:#15803d">Facturación</strong> — se puede escoger al facturar y al registrar movimientos.</div>
        <div>🏥 <strong style="color:#b45309">Incapacidad</strong> — cuenta donde se reportan las entradas de incapacidades.</div>
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
                    <label class="flb">Tipo (opcional)</label>
                    <select name="tipo_cuenta" id="e_tipo" class="finp">
                        <option value="">— Ninguno (Nequi, etc.) —</option>
                        <option value="Ahorros">Ahorros</option>
                        <option value="Corriente">Corriente</option>
                    </select>
                </div>
                <div>
                    <label class="flb">Número cuenta *</label>
                    <input type="text" name="numero_cuenta" id="e_numero" class="finp" required>
                </div>
                <div>
                    <label class="flb">Llave de Pago</label>
                    <input type="text" name="llave" id="e_llave" class="finp" placeholder="Llave alfanumérica (opcional)">
                </div>
                <div class="usos-box" style="grid-column:1/-1">
                    <span class="flb" style="margin:0 .3rem 0 0">Usos:</span>
                    <label class="chk-uso"><input type="checkbox" name="cobro" id="e_cobro" value="1"> 💳 Cobro</label>
                    <label class="chk-uso"><input type="checkbox" name="facturacion" id="e_facturacion" value="1"> 🧾 Facturación</label>
                    <label class="chk-uso"><input type="checkbox" name="incapacidad" id="e_incapacidad" value="1"> 🏥 Incapacidad</label>
                    <label class="chk-uso" style="margin-left:auto"><input type="checkbox" name="activo" id="e_activo" value="1"> Activa</label>
                </div>
                <div style="padding-top:.9rem;grid-column:1/-1">
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

const USOS = {
    cobro:       {etiqueta: 'Cobro'},
    facturacion: {etiqueta: 'Facturación'},
    incapacidad: {etiqueta: 'Incapacidad'},
};

// Toggle de un uso via AJAX (sin reload).
// El PATCH reescribe la cuenta completa, así que los otros dos usos se envían
// con su valor actual (leído de la fila) o el servidor los pondría en false.
async function toggleUso(id, uso, btn) {
    const row = document.getElementById(`row-${id}`);
    if (!row) return;

    const data     = JSON.parse(row.dataset.cuenta);
    const activada = btn.classList.contains('on');
    const flags    = {};
    Object.keys(USOS).forEach(u => {
        flags[u] = row.getAttribute(`data-${u}`) === '1' ? 1 : 0;
    });
    flags[uso] = activada ? 0 : 1;

    btn.disabled = true;
    const resp = await fetch(`{{ url('admin/configuracion/cuentas') }}/${id}`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-HTTP-Method-Override':'PATCH','Accept':'application/json'},
        body: JSON.stringify({
            _method: 'PATCH',
            ...flags,
            banco: data.banco,
            tipo_cuenta: data.tipo_cuenta || null,
            numero_cuenta: data.numero_cuenta,
            nombre: data.nombre || null,
            nit: data.nit || null,
            llave: data.llave || null,
            activo: data.activo !== false && data.activo !== 0 ? 1 : 0
        })
    });
    btn.disabled = false;

    if (resp.ok) {
        btn.classList.toggle('on', !activada);
        btn.classList.toggle('off', activada);
        btn.title = (activada ? 'Incluir en ' : 'Quitar de ') + USOS[uso].etiqueta;
        row.setAttribute(`data-${uso}`, activada ? '0' : '1');
        filtrarCuentas();
    } else {
        alert('No se pudo actualizar el uso de la cuenta.');
    }
}

function editarCuenta(id, data) {
    document.getElementById('formEditar').action = `{{ url('admin/configuracion/cuentas') }}/${id}`;
    document.getElementById('e_banco').value   = data.banco || '';
    document.getElementById('e_nombre').value  = data.nombre || '';
    document.getElementById('e_nit').value     = data.nit || '';
    document.getElementById('e_tipo').value    = data.tipo_cuenta || '';
    document.getElementById('e_numero').value  = data.numero_cuenta || '';
    document.getElementById('e_llave').value   = data.llave || '';
    // Los usos se leen de la fila, no del JSON: el toggle los cambia sin recargar
    const row = document.getElementById(`row-${id}`);
    Object.keys(USOS).forEach(u => {
        document.getElementById(`e_${u}`).checked = row
            ? row.getAttribute(`data-${u}`) === '1'
            : !!data[u];
    });
    document.getElementById('e_activo').checked = data.activo !== false && data.activo !== 0;
    document.getElementById('modal-editar').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modal-editar').style.display = 'none';
}

async function eliminarCuenta(id) {
    // Ahora delega al modal inteligente (se invoca desde el row)
    // Esta función ya no se usa directamente; la reemplaza abrirModalCuenta
}

function filtrarCuentas() {
    const txtTitular = document.getElementById('filtro-titular').value.toLowerCase().trim();
    const selBanco   = document.getElementById('filtro-banco').value;
    const selTipo    = document.getElementById('filtro-tipo').value;
    const selUso     = document.getElementById('filtro-uso').value;
    const selActivo  = document.getElementById('filtro-activo').value;

    const rows = document.querySelectorAll('table.tbl tbody tr[id^="row-"]');

    // Marcar como activo/inactivo las clases de los select según si están filtrando
    document.getElementById('filtro-banco').classList.toggle('activo', !!selBanco);
    document.getElementById('filtro-tipo').classList.toggle('activo', !!selTipo);
    document.getElementById('filtro-uso').classList.toggle('activo', !!selUso);
    document.getElementById('filtro-activo').classList.toggle('activo', !!selActivo);

    rows.forEach(row => {
        const banco   = row.getAttribute('data-banco');
        const titular = row.getAttribute('data-titular');
        const tipo    = row.getAttribute('data-tipo');
        const activo  = row.getAttribute('data-activo');

        let matchTitular = true;
        if (txtTitular) {
            matchTitular = titular.includes(txtTitular);
        }

        let matchBanco = true;
        if (selBanco) {
            matchBanco = (banco === selBanco.toLowerCase());
        }

        let matchTipo = true;
        if (selTipo) {
            if (selTipo === 'Ninguno') {
                matchTipo = (tipo === '');
            } else {
                matchTipo = (tipo === selTipo);
            }
        }

        let matchUso = true;
        if (selUso === 'ninguno') {
            matchUso = Object.keys(USOS).every(u => row.getAttribute(`data-${u}`) !== '1');
        } else if (selUso) {
            matchUso = (row.getAttribute(`data-${selUso}`) === '1');
        }

        let matchActivo = true;
        if (selActivo) {
            matchActivo = (activo === selActivo);
        }

        if (matchTitular && matchBanco && matchTipo && matchUso && matchActivo) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    // Controlar el mensaje de "sin resultados"
    const visibleRows = Array.from(rows).filter(r => r.style.display !== 'none');
    let noResultRow = document.getElementById('no-result-row');
    
    if (visibleRows.length === 0) {
        if (!noResultRow) {
            const tbody = document.querySelector('table.tbl tbody');
            noResultRow = document.createElement('tr');
            noResultRow.id = 'no-result-row';
            noResultRow.innerHTML = '<td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8">No se encontraron cuentas con los filtros aplicados.</td>';
            tbody.appendChild(noResultRow);
        } else {
            noResultRow.style.display = '';
        }
    } else {
        if (noResultRow) {
            noResultRow.style.display = 'none';
        }
    }

    const contador = document.getElementById('contador-cuentas');
    if (contador) {
        contador.textContent = visibleRows.length === rows.length
            ? `(${rows.length})`
            : `(${visibleRows.length} de ${rows.length})`;
    }
}

// Aplicar el filtro inicial: la tabla arranca mostrando solo las activas
filtrarCuentas();
</script>

{{-- Modal inteligente cuentas --}}
<div id="cuentaGestionModal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:430px;margin:1rem;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden">

        {{-- Header --}}
        <div style="padding:1.1rem 1.4rem .8rem;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <div style="font-size:.62rem;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.15rem">Cuenta Bancaria</div>
                <div id="cModal-banco" style="font-size:.92rem;font-weight:800;color:#0f172a"></div>
                <div id="cModal-numero" style="font-size:.75rem;color:#64748b;font-family:monospace"></div>
            </div>
            <button onclick="cerrarModalCuenta()" style="border:none;background:none;font-size:1.2rem;cursor:pointer;color:#94a3b8;line-height:1">&#215;</button>
        </div>

        {{-- Cuerpo --}}
        <div style="padding:1.2rem 1.4rem">

            {{-- Loading --}}
            <div id="cModal-loading" style="text-align:center;padding:1.5rem;color:#64748b;font-size:.85rem">
                <div style="font-size:1.4rem;margin-bottom:.4rem">⏳</div>
                Verificando registros...
            </div>

            {{-- Con registros: solo inactivar --}}
            <div id="cModal-solo-inactivar" style="display:none">
                <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:10px;padding:1rem;margin-bottom:1rem">
                    <div style="font-size:.85rem;font-weight:600;color:#92400e">📄 Tiene registros de facturas o consignaciones</div>
                    <div style="font-size:.78rem;color:#78350f;margin-top:.3rem">Esta cuenta tiene movimientos financieros registrados. Solo puede <strong>inactivarse</strong>, no eliminarse.</div>
                </div>
                <button id="cBtn-inactivar" onclick="ejecutarInactivar()" style="width:100%;padding:.62rem;background:linear-gradient(135deg,#f59e0b,#d97706);border:none;border-radius:10px;color:#fff;font-size:.88rem;font-weight:700;cursor:pointer">
                    🔒 Inactivar Cuenta
                </button>
            </div>

            {{-- Sin registros: inactivar O eliminar --}}
            <div id="cModal-sin-registros" style="display:none">
                <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:1rem;margin-bottom:1.1rem">
                    <div style="font-size:.85rem;font-weight:600;color:#15803d">✅ Sin registros asociados</div>
                    <div style="font-size:.78rem;color:#166534;margin-top:.3rem">Esta cuenta no tiene facturas ni consignaciones. Puede inactivarla o eliminarla definitivamente.</div>
                </div>
                <div style="display:flex;flex-direction:column;gap:.7rem">
                    <button id="cBtn-inactivar2" onclick="ejecutarInactivar()" style="width:100%;padding:.58rem;background:#f1f5f9;border:1.5px solid #cbd5e1;border-radius:10px;color:#334155;font-size:.84rem;font-weight:700;cursor:pointer">
                        🔒 Inactivar Cuenta
                    </button>
                    <div style="text-align:center;font-size:.72rem;color:#94a3b8">o</div>
                    <button onclick="ejecutarEliminar()" style="width:100%;padding:.58rem;background:linear-gradient(135deg,#ef4444,#dc2626);border:none;border-radius:10px;color:#fff;font-size:.84rem;font-weight:700;cursor:pointer">
                        🗑️ Eliminar definitivamente
                    </button>
                </div>
            </div>

            {{-- Ya inactiva: solo eliminar (si no tiene registros) o info --}}
            <div id="cModal-ya-inactiva" style="display:none">
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:1rem;margin-bottom:1rem">
                    <div style="font-size:.85rem;font-weight:600;color:#64748b">ℹ️ Cuenta ya inactiva</div>
                    <div id="cModal-ya-inactiva-texto" style="font-size:.78rem;color:#94a3b8;margin-top:.3rem">Esta cuenta ya está marcada como Inactiva.</div>
                </div>
                <button onclick="ejecutarEliminar()" id="cBtn-eliminar-inactiva" style="width:100%;padding:.58rem;background:linear-gradient(135deg,#ef4444,#dc2626);border:none;border-radius:10px;color:#fff;font-size:.84rem;font-weight:700;cursor:pointer">
                    🗑️ Eliminar definitivamente
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let _cuentaId   = null;
let _cuentaBanco = '';

function abrirModalCuenta(id, banco, numero) {
    _cuentaId    = id;
    _cuentaBanco = banco;
    document.getElementById('cModal-banco').textContent  = banco;
    document.getElementById('cModal-numero').textContent = numero;

    // reset
    document.getElementById('cModal-loading').style.display       = 'block';
    document.getElementById('cModal-solo-inactivar').style.display = 'none';
    document.getElementById('cModal-sin-registros').style.display  = 'none';
    document.getElementById('cModal-ya-inactiva').style.display    = 'none';

    document.getElementById('cuentaGestionModal').style.display = 'flex';

    fetch(`/admin/configuracion/cuentas/${id}/estado-registros`, {
        headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('cModal-loading').style.display = 'none';

        if (!data.activo) {
            // Ya está inactiva
            const btnEl = document.getElementById('cBtn-eliminar-inactiva');
            const infoText = document.getElementById('cModal-ya-inactiva-texto');
            if (data.tiene_registros) {
                // inactiva y con registros: no puede eliminar
                btnEl.style.display = 'none';
                if (infoText) infoText.textContent = 'Esta cuenta está inactiva y tiene registros de facturas/consignaciones. No se puede eliminar.';
            } else {
                btnEl.style.display = 'block';
                if (infoText) infoText.textContent = 'Esta cuenta ya está marcada como Inactiva.';
            }
            document.getElementById('cModal-ya-inactiva').style.display = 'block';

        } else if (data.tiene_registros) {
            // Activa con registros: solo inactivar
            document.getElementById('cModal-solo-inactivar').style.display = 'block';

        } else {
            // Activa sin registros: inactivar o eliminar
            document.getElementById('cModal-sin-registros').style.display = 'block';
        }
    })
    .catch(() => {
        document.getElementById('cModal-loading').innerHTML = '⚠️ Error al verificar. Inténtalo de nuevo.';
    });
}

function cerrarModalCuenta() {
    document.getElementById('cuentaGestionModal').style.display = 'none';
}

async function ejecutarInactivar() {
    const r = await fetch(`/admin/configuracion/cuentas/${_cuentaId}/inactivar`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': CSRF, 'X-HTTP-Method-Override':'PATCH', 'Accept':'application/json',
                  'Content-Type':'application/json'},
        body: JSON.stringify({_method:'PATCH'})
    });
    if (r.ok) {
        cerrarModalCuenta();
        // Actualizar badge en la fila
        const row = document.querySelector(`tr[id^="row-"]`);
        // Recarga la página para reflejar el cambio
        location.reload();
    }
}

async function ejecutarEliminar() {
    if (!confirm(`¿Eliminar definitivamente la cuenta ${_cuentaBanco}?\n\nEsta acción no se puede deshacer.`)) return;
    const r = await fetch(`/admin/configuracion/cuentas/${_cuentaId}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'}
    });
    const data = await r.json();
    if (data.ok) {
        cerrarModalCuenta();
        const row = document.getElementById(`row-${_cuentaId}`);
        if (row) row.remove();
    } else {
        alert(data.mensaje || 'No se pudo eliminar.');
    }
}

// Cerrar al clic fuera
document.getElementById('cuentaGestionModal').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalCuenta();
});
</script>
@endsection
