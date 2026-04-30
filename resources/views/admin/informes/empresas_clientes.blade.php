@extends('layouts.app')
@section('modulo','Empresas Clientes')
@section('contenido')
<div style="max-width:1000px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">🏭 Empresas Clientes</h1>
    </div>
    <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:.84rem;">
            <thead><tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Empresa</th>
                <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">NIT</th>
                <th style="padding:.65rem 1rem;text-align:center;font-size:.72rem;text-transform:uppercase;color:#64748b;">Clientes</th>
                <th style="padding:.65rem 1rem;text-align:center;font-size:.72rem;text-transform:uppercase;color:#64748b;">Contratos Vigentes</th>
            </tr></thead>
            <tbody>
                @forelse($data as $e)
                <tr style="border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                    <td style="padding:.6rem 1rem;font-weight:600;color:#0d2550;">{{ $e->empresa ?? '—' }}</td>
                    <td style="padding:.6rem 1rem;color:#64748b;font-size:.8rem;">{{ $e->nit ?? '—' }}</td>
                    <td style="padding:.6rem 1rem;text-align:center;font-weight:700;color:#0891b2;">{{ $e->clientes }}</td>
                    <td style="padding:.6rem 1rem;text-align:center;font-weight:800;font-size:1.05rem;color:#2563eb;">{{ $e->contratos }}</td>
                </tr>
                @empty
                <tr><td colspan="4" style="padding:2rem;text-align:center;color:#94a3b8;">Sin empresas</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
