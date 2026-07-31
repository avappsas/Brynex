@extends('layouts.app')
@section('modulo','Clientes Activos')
@section('contenido')
@php $meses=['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']; @endphp
<div style="max-width:1200px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">👥 Clientes Activos</h1>
        <span style="background:#dbeafe;color:#1e40af;font-size:.82rem;font-weight:700;padding:.3rem .75rem;border-radius:999px;">{{ $totalClientes }} clientes / {{ $total }} contratos</span>
    </div>

    <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);padding:1.25rem;margin-bottom:1.25rem;">
        <form method="GET" style="display:flex;flex-direction:column;gap:1rem;">
            {{-- Buscador Principal y Controles --}}
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;">
                <input name="q" value="{{ $buscar }}" placeholder="Buscar cédula o nombre…" style="flex:1;min-width:200px;padding:.45rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.85rem;">
                <button type="submit" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:.45rem 1.25rem;font-size:.85rem;font-weight:600;cursor:pointer;">Filtrar</button>
                <a href="{{ route('admin.informes.clientes_activos') }}" style="background:#64748b;color:#fff;border-radius:8px;padding:.45rem 1rem;font-size:.82rem;font-weight:600;text-decoration:none;text-align:center;">Limpiar</a>
                <a href="?{{ http_build_query(array_merge(request()->all(),['excel'=>1])) }}" style="background:#16a34a;color:#fff;border-radius:8px;padding:.45rem 1rem;font-size:.82rem;font-weight:600;text-decoration:none;text-align:center;">📥 Excel</a>
            </div>

            {{-- Filtros Secundarios --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(180px, 1fr));gap:.75rem;">
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:.25rem;">Razón Social</label>
                    <select name="razon_social_id" onchange="this.form.submit()" style="width:100%;padding:.45rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;">
                        <option value="">Todas</option>
                        @foreach($razones as $r)
                            <option value="{{ $r->id }}" {{ $fRazon == $r->id ? 'selected' : '' }}>{{ $r->razon_social }} ({{ $r->total }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:.25rem;">EPS</label>
                    <select name="eps_id" onchange="this.form.submit()" style="width:100%;padding:.45rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;">
                        <option value="">Todas</option>
                        @foreach($epsList as $e)
                            <option value="{{ $e->id }}" {{ $fEps == $e->id ? 'selected' : '' }}>{{ $e->nombre }} ({{ $e->total }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:.25rem;">Caja</label>
                    <select name="caja_id" onchange="this.form.submit()" style="width:100%;padding:.45rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;">
                        <option value="">Todas</option>
                        @foreach($cajas as $c)
                            <option value="{{ $c->id }}" {{ $fCaja == $c->id ? 'selected' : '' }}>{{ $c->nombre }} ({{ $c->total }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:.25rem;">Pensión</label>
                    <select name="pension_id" onchange="this.form.submit()" style="width:100%;padding:.45rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;">
                        <option value="">Todas</option>
                        @foreach($pensiones as $p)
                            <option value="{{ $p->id }}" {{ $fPension == $p->id ? 'selected' : '' }}>{{ $p->razon_social }} ({{ $p->total }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:.25rem;">Modalidad</label>
                    <select name="tipo_modalidad_id" onchange="this.form.submit()" style="width:100%;padding:.45rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;">
                        <option value="">Todas</option>
                        @foreach($modalidades as $m)
                            <option value="{{ $m->id }}" {{ $fModalidad == $m->id ? 'selected' : '' }}>{{ $m->observacion ?: $m->tipo_modalidad }} ({{ $m->total }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:.25rem;">Plan</label>
                    <select name="plan_id" onchange="this.form.submit()" style="width:100%;padding:.45rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;">
                        <option value="">Todos</option>
                        @foreach($planes as $p)
                            <option value="{{ $p->id }}" {{ $fPlan == $p->id ? 'selected' : '' }}>{{ $p->nombre }} ({{ $p->total }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
    </div>

    <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:.83rem;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Cédula</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Nombre</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Razón Social</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Empresa</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">EPS</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Caja</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Pensión</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Modalidad</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;white-space:nowrap;">Plan</th>
                    <th style="padding:.65rem 1rem;text-align:right;font-size:.72rem;text-transform:uppercase;color:#64748b;">F. Ingreso</th>
                    <th style="padding:.65rem 1rem;text-align:right;font-size:.72rem;text-transform:uppercase;color:#64748b;">Salario</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $c)
                <tr style="border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                    <td style="padding:.6rem 1rem;font-weight:600;color:#1e40af;">{{ $c->cedula }}</td>
                    <td style="padding:.6rem 1rem;">{{ $c->nombre_completo }}</td>
                    <td style="padding:.6rem 1rem;color:#475569;">{{ $c->razon_social ?? '—' }}</td>
                    <td style="padding:.6rem 1rem;color:#475569;">{{ $c->empresa ?? '—' }}</td>
                    <td style="padding:.6rem 1rem;color:#475569;">{{ $c->eps_nombre ?? '—' }}</td>
                    <td style="padding:.6rem 1rem;color:#475569;">{{ $c->caja_nombre ?? '—' }}</td>
                    <td style="padding:.6rem 1rem;color:#475569;">{{ $c->pension_nombre ?? '—' }}</td>
                    <td style="padding:.6rem 1rem;color:#475569;">{{ $c->modalidad_nombre ?? '—' }}</td>
                    <td style="padding:.6rem 1rem;color:#475569;white-space:nowrap;">{{ $c->plan_nombre ?? '—' }}</td>
                    <td style="padding:.6rem 1rem;text-align:right;color:#64748b;">{{ sqldate($c->fecha_ingreso)?->format('d/m/Y') }}</td>
                    <td style="padding:.6rem 1rem;text-align:right;">$ {{ number_format($c->salario,0,',','.') }}</td>
                </tr>
                @empty
                <tr><td colspan="11" style="padding:2rem;text-align:center;color:#94a3b8;">Sin resultados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:1rem;">{{ $clientes->links() }}</div>
</div>
@endsection
