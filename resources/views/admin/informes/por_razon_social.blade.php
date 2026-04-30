@extends('layouts.app')
@section('modulo','Por Razón Social')
@section('contenido')
<div style="max-width:900px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">🏢 Clientes por Razón Social</h1>
        <span style="background:#dbeafe;color:#1e40af;font-size:.82rem;font-weight:700;padding:.3rem .75rem;border-radius:999px;">{{ $data->sum('total') }} vigentes</span>
    </div>

    <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:.84rem;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">#</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Razón Social</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Estado</th>
                    <th style="padding:.65rem 1rem;text-align:center;font-size:.72rem;text-transform:uppercase;color:#64748b;">Contratos</th>
                    <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Distribución</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $i=>$rs)
                <tr style="border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                    <td style="padding:.6rem 1rem;color:#94a3b8;font-size:.78rem;">{{ $i+1 }}</td>
                    <td style="padding:.6rem 1rem;font-weight:600;color:#0d2550;">{{ $rs->razon_social }}</td>
                    <td style="padding:.6rem 1rem;">
                        <span style="background:{{ $rs->estado==='Activa'?'#d1fae5':'#fef3c7' }};color:{{ $rs->estado==='Activa'?'#065f46':'#92400e' }};padding:.2rem .6rem;border-radius:999px;font-size:.72rem;font-weight:600;">{{ $rs->estado }}</span>
                    </td>
                    <td style="padding:.6rem 1rem;text-align:center;font-size:1.1rem;font-weight:800;color:#2563eb;">{{ $rs->total }}</td>
                    <td style="padding:.6rem 1rem;min-width:150px;">
                        <div style="height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                            <div style="height:100%;width:{{ round($rs->total/$max*100) }}%;background:#3b82f6;border-radius:4px;transition:width .4s;"></div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
