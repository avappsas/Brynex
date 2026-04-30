@extends('layouts.app')
@section('modulo','Clientes Activos')
@section('contenido')
@php $meses=['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']; @endphp
<div style="max-width:1200px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">👥 Clientes Activos</h1>
        <span style="background:#dbeafe;color:#1e40af;font-size:.82rem;font-weight:700;padding:.3rem .75rem;border-radius:999px;">{{ $total }} vigentes</span>
    </div>

    <div style="display:flex;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap;">
        <form method="GET" style="display:flex;gap:.5rem;flex:1;min-width:200px;">
            <input name="q" value="{{ $buscar }}" placeholder="Buscar cédula o nombre…" style="flex:1;padding:.45rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.85rem;">
            <button type="submit" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:.45rem 1rem;font-size:.85rem;cursor:pointer;">Buscar</button>
        </form>
        <a href="?{{ http_build_query(array_merge(request()->all(),['excel'=>1])) }}" style="background:#16a34a;color:#fff;border-radius:8px;padding:.45rem 1rem;font-size:.82rem;font-weight:600;text-decoration:none;">📥 Excel</a>
    </div>

    <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:.83rem;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Cédula</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Nombre</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Razón Social</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Empresa</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">EPS</th>
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
                    <td style="padding:.6rem 1rem;text-align:right;color:#64748b;">{{ sqldate($c->fecha_ingreso)?->format('d/m/Y') }}</td>
                    <td style="padding:.6rem 1rem;text-align:right;">$ {{ number_format($c->salario,0,',','.') }}</td>
                </tr>
                @empty
                <tr><td colspan="7" style="padding:2rem;text-align:center;color:#94a3b8;">Sin resultados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:1rem;">{{ $clientes->links() }}</div>
</div>
@endsection
