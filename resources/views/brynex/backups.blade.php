@extends('layouts.app')

@section('titulo', 'BryNex')
@section('modulo', 'Copias de Seguridad')

@section('contenido')
<div style="max-width:960px;margin:0 auto">

    {{-- Breadcrumb --}}
    <div style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:#64748b;margin-bottom:1.25rem">
        <a href="{{ route('brynex.hub') }}" style="color:#3b82f6;text-decoration:none">🔵 BryNex</a>
        <span>›</span>
        <span>Copias de Seguridad</span>
    </div>

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
        <div>
            <h1 style="font-size:1.3rem;font-weight:700;color:#1e293b;margin:0">💾 Copias de Seguridad BryNex</h1>
            <p style="font-size:.82rem;color:#64748b;margin:.2rem 0 0">
                Visualiza, descarga y genera de forma manual los backups de la base de datos y documentos de Brynex.
            </p>
        </div>
        <div>
            <form id="formCrearBackup" action="{{ route('brynex.backups.crear') }}" method="POST" style="display:inline">
                @csrf
                <button type="submit" id="btnCrearBackup" class="btn-bx-action success">
                    <span style="margin-right:4px">➕</span> Crear Backup Manual
                </button>
            </form>
        </div>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="alert-bx success">
            <span style="margin-right:8px">✅</span>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    @if(session('error'))
        <div class="alert-bx error">
            <span style="margin-right:8px">❌</span>
            {{-- Escapado: el mensaje incluye $e->getMessage() de SQL Server,
                 contenido que no está bajo control de la aplicación. --}}
            <div>{{ session('error') }}</div>
        </div>
    @endif

    {{-- Info Card --}}
    <div class="info-bx-card">
        <span style="font-size:1.2rem;margin-right:10px">ℹ️</span>
        <div>
            <strong>Información:</strong> Las copias de seguridad de la base de datos se almacenan de forma segura en la ruta autorizada del servidor. Solo los usuarios con perfil superadmin de Brynex tienen acceso para visualizarlas y descargarlas.
        </div>
    </div>

    {{-- Listado de Backups --}}
    @if(count($backups) > 0)
        <div class="table-bx-container">
            <table class="table-bx">
                <thead>
                    <tr>
                        <th style="width:50%;text-align:left">Nombre del Archivo</th>
                        <th style="width:25%;text-align:left">Fecha de Creación</th>
                        <th style="width:15%;text-align:center">Tamaño</th>
                        <th style="width:10%;text-align:center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($backups as $backup)
                        <tr>
                            <td style="text-align:left;font-weight:600;color:#334155">
                                <span style="margin-right:6px">📦</span>
                                {{ $backup['nombre'] }}
                            </td>
                            <td style="text-align:left;color:#64748b;font-size:0.85rem">
                                {{ $backup['fecha'] }}
                            </td>
                            <td style="text-align:center">
                                <span class="badge-bx-size">
                                    {{ $backup['tamano'] }}
                                </span>
                            </td>
                            <td style="text-align:center">
                                <a href="{{ route('brynex.backups.descargar', ['file' => $backup['nombre']]) }}" 
                                   class="btn-bx-download" 
                                   title="Descargar Backup">
                                    ⬇️
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-bx-card">
            <div style="font-size:3rem;margin-bottom:1rem">📂</div>
            <h3 style="font-size:1.05rem;font-weight:700;color:#475569;margin:0 0 0.25rem">No se encontraron backups</h3>
            <p style="font-size:0.82rem;color:#94a3b8;margin:0">No se detectaron archivos de copia de seguridad (.bak, .tar.gz, .zip) en la carpeta configurada en el servidor.</p>
        </div>
    @endif

</div>
@endsection

@push('styles')
<style>
    .btn-bx-action {
        display: inline-flex;
        align-items: center;
        padding: 0.6rem 1.2rem;
        font-size: 0.82rem;
        font-weight: 700;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .btn-bx-action.success {
        background-color: #10b981;
        color: #ffffff;
    }
    .btn-bx-action.success:hover {
        background-color: #059669;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);
    }
    .btn-bx-action:disabled {
        background-color: #cbd5e1;
        color: #94a3b8;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .alert-bx {
        display: flex;
        align-items: flex-start;
        padding: 0.85rem 1.2rem;
        border-radius: 8px;
        margin-bottom: 1.25rem;
        font-size: 0.82rem;
        line-height: 1.4;
    }
    .alert-bx.success {
        background-color: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
    }
    .alert-bx.error {
        background-color: #fef2f2;
        border: 1px solid #fca5a5;
        color: #991b1b;
    }

    .info-bx-card {
        display: flex;
        align-items: center;
        background-color: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e3a8a;
        padding: 0.85rem 1.2rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        font-size: 0.82rem;
        line-height: 1.4;
    }

    .table-bx-container {
        background: #ffffff;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 6px rgba(0,0,0,0.05);
    }
    .table-bx {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    .table-bx th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 700;
        padding: 0.95rem 1.2rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-bx td {
        padding: 0.95rem 1.2rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .table-bx tr:last-child td {
        border-bottom: none;
    }
    .table-bx tr:hover td {
        background-color: #f8fafc;
    }

    .badge-bx-size {
        background-color: #f1f5f9;
        color: #475569;
        font-weight: 700;
        font-size: 0.78rem;
        padding: 0.35rem 0.65rem;
        border-radius: 20px;
        display: inline-block;
    }

    .btn-bx-download {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        background-color: #f1f5f9;
        border-radius: 50%;
        text-decoration: none;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
        cursor: pointer;
    }
    .btn-bx-download:hover {
        background-color: #e2e8f0;
        transform: scale(1.08);
    }

    .empty-bx-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 3.5rem;
        text-align: center;
        box-shadow: 0 1px 6px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
    }
</style>
@endpush

@push('scripts')
<script>
    document.getElementById('formCrearBackup').addEventListener('submit', function() {
        var btn = document.getElementById('btnCrearBackup');
        btn.disabled = true;
        btn.innerHTML = '<span style="margin-right:6px">⏳</span> Generando Backup...';
    });
</script>
@endpush
