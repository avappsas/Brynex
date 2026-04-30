@extends('layouts.app')
@section('modulo','Retirados del Mes')
@section('contenido')
@php $mesesEs=['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']; $years=range(now()->year-3,now()->year); @endphp
<div style="max-width:1100px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">🚪 Retirados del Mes</h1>
        <span style="background:#fef3c7;color:#92400e;font-size:.82rem;font-weight:700;padding:.3rem .75rem;border-radius:999px;">{{ $retirados->count() }} retirados</span>
    </div>

    <div style="display:flex;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center;">
        <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
            <select name="mes" style="padding:.45rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.84rem;">
                @foreach($mesesEs as $n=>$nm) @if($n>0) <option value="{{ $n }}" {{ $mes==$n?'selected':'' }}>{{ $nm }}</option> @endif @endforeach
            </select>
            <select name="anio" style="padding:.45rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.84rem;">
                @foreach($years as $y) <option value="{{ $y }}" {{ $anio==$y?'selected':'' }}>{{ $y }}</option> @endforeach
            </select>
            <button type="submit" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:.45rem 1.25rem;font-size:.85rem;cursor:pointer;">Ver</button>
        </form>
        <a href="?mes={{ $mes }}&anio={{ $anio }}&excel=1" style="background:#16a34a;color:#fff;border-radius:8px;padding:.45rem 1rem;font-size:.82rem;font-weight:600;text-decoration:none;">📥 Excel</a>
    </div>

    <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:.83rem;">
            <thead><tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Cédula</th>
                <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Nombre</th>
                <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Razón Social</th>
                <th style="padding:.65rem 1rem;text-align:center;font-size:.72rem;text-transform:uppercase;color:#64748b;">Fecha Retiro</th>
                <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Motivo</th>
                <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Observación</th>
            </tr></thead>
            <tbody>
                @forelse($retirados as $r)
                <tr style="border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background=''">
                    <td style="padding:.6rem 1rem;font-weight:600;color:#b45309;">{{ $r->cedula }}</td>
                    <td style="padding:.6rem 1rem;">{{ $r->nombre_completo }}</td>
                    <td style="padding:.6rem 1rem;color:#475569;">{{ $r->razon_social ?? '—' }}</td>
                    <td style="padding:.6rem 1rem;text-align:center;color:#92400e;font-weight:600;">{{ sqldate($r->fecha_retiro)?->format('d/m/Y') }}</td>
                    <td style="padding:.6rem 1rem;color:#475569;">{{ $r->motivo ?? '—' }}</td>
                    <td style="padding:.6rem 1rem;color:#64748b;font-size:.8rem;max-width:220px;">{{ $r->observacion ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="padding:2rem;text-align:center;color:#94a3b8;">Sin retirados para este período</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
