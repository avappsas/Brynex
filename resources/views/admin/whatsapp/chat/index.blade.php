@extends('layouts.app')
@section('titulo', 'WhatsApp')
@section('modulo', 'Chat WhatsApp')

@push('styles')
<style>
/* ── Layout chat tipo WhatsApp ───────────────────────── */
.chat-layout {
    display: flex;
    height: calc(100vh - 120px);
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 8px rgba(0,0,0,.1);
    overflow: hidden;
}

/* ── Panel izquierdo: lista de conversaciones ────────── */
.chat-sidebar {
    width: 310px;
    min-width: 280px;
    border-right: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    background: #fff;
}

.sidebar-header {
    padding: .85rem 1rem;
    border-bottom: 1px solid #f1f5f9;
}

.sidebar-title {
    font-size: .95rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: .65rem;
}

.sidebar-badge {
    background: #ef4444;
    color: #fff;
    font-size: .65rem;
    font-weight: 700;
    padding: .12rem .45rem;
    border-radius: 999px;
    min-width: 18px;
    text-align: center;
}

.sidebar-search {
    width: 100%;
    padding: .45rem .75rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: .82rem;
    color: #0f172a;
    outline: none;
    transition: border-color .15s;
    margin-bottom: .5rem;
}
.sidebar-search:focus { border-color: #2563eb; }

.sidebar-tabs {
    display: flex;
    gap: .25rem;
}

.sidebar-tab {
    flex: 1;
    text-align: center;
    padding: .35rem;
    border-radius: 7px;
    font-size: .74rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    color: #64748b;
    transition: background .12s, color .12s;
}
.sidebar-tab.active { background: #eff6ff; color: #2563eb; }
.sidebar-tab:hover { background: #f8fafc; }

.conv-list { flex: 1; overflow-y: auto; }
.conv-list::-webkit-scrollbar { width: 4px; }
.conv-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 999px; }

.conv-item {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .75rem 1rem;
    cursor: pointer;
    text-decoration: none;
    border-bottom: 1px solid #f8fafc;
    transition: background .1s;
}
.conv-item:hover { background: #f8fafc; }
.conv-item.activa { background: #eff6ff; border-right: 3px solid #2563eb; }

.conv-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: #fff;
    font-weight: 700;
    flex-shrink: 0;
}

.conv-info { flex: 1; min-width: 0; }
.conv-name {
    font-size: .83rem;
    font-weight: 600;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.conv-preview {
    font-size: .74rem;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: .1rem;
}

.conv-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: .25rem;
    flex-shrink: 0;
}
.conv-time { font-size: .68rem; color: #cbd5e1; }
.conv-unread {
    background: #ef4444;
    color: #fff;
    font-size: .63rem;
    font-weight: 700;
    padding: .1rem .38rem;
    border-radius: 999px;
    min-width: 18px;
    text-align: center;
}

/* ── Panel derecho: bienvenida ───────────────────────── */
.chat-main {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
}

.welcome-box {
    text-align: center;
    color: #94a3b8;
}
.welcome-box .wa-icon { font-size: 4rem; margin-bottom: 1rem; }
.welcome-box h3 { font-size: 1.1rem; font-weight: 600; color: #475569; margin-bottom: .5rem; }
.welcome-box p { font-size: .83rem; }
</style>
@endpush

@section('content')
<div style="padding:1rem 1.5rem 0">
    @if(session('ok'))
        <div class="flash success" style="margin-bottom:.75rem">✅ {{ session('ok') }}</div>
    @endif
</div>

<div style="padding:0 1.5rem 1.5rem">
<div class="chat-layout">

    {{-- Panel izquierdo --}}
    <aside class="chat-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">
                💬 WhatsApp
                @if($totalNoLeidos > 0)
                    <span class="sidebar-badge">{{ $totalNoLeidos }}</span>
                @endif
            </div>

            <form method="GET">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="text" name="buscar" class="sidebar-search"
                       value="{{ $buscar }}" placeholder="Buscar conversación...">
            </form>

            <div class="sidebar-tabs">
                <a href="{{ route('admin.whatsapp.chat.index', ['tab' => 'general', 'buscar' => $buscar]) }}"
                   class="sidebar-tab {{ $tab === 'general' ? 'active' : '' }}">📥 General</a>
                <a href="{{ route('admin.whatsapp.chat.index', ['tab' => 'mias', 'buscar' => $buscar]) }}"
                   class="sidebar-tab {{ $tab === 'mias' ? 'active' : '' }}">👤 Mis chats</a>
            </div>
        </div>

        <div class="conv-list">
            @forelse($conversaciones as $conv)
                @php
                    $ultimoMsg = $conv->mensajes->first();
                    $inicial   = mb_strtoupper(mb_substr($conv->nombreMostrar(), 0, 1));
                    $preview   = $conv->previewUltimoMensaje();
                    $hora      = $conv->ultimo_mensaje_at
                        ? ($conv->ultimo_mensaje_at->isToday()
                            ? $conv->ultimo_mensaje_at->format('H:i')
                            : $conv->ultimo_mensaje_at->format('d/m'))
                        : '';
                @endphp
                <a href="{{ route('admin.whatsapp.chat.show', $conv->id) }}" class="conv-item">
                    <div class="conv-avatar">{{ $inicial }}</div>
                    <div class="conv-info">
                        <div class="conv-name">{{ $conv->nombreMostrar() }}</div>
                        <div class="conv-preview">
                            @if($conv->estado === 'asignada' && $conv->asignado)
                                <span style="color:#10b981">● {{ $conv->asignado->nombre }}</span>
                            @else
                                {{ $preview ?: 'Sin mensajes' }}
                            @endif
                        </div>
                    </div>
                    <div class="conv-meta">
                        <span class="conv-time">{{ $hora }}</span>
                        @if($conv->total_mensajes_no_leidos > 0)
                            <span class="conv-unread">{{ $conv->total_mensajes_no_leidos }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <div style="text-align:center;padding:2rem 1rem;color:#94a3b8;font-size:.82rem">
                    No hay conversaciones {{ $tab === 'mias' ? 'asignadas a ti' : 'activas' }}.
                </div>
            @endforelse
        </div>
    </aside>

    {{-- Panel derecho --}}
    <main class="chat-main">
        <div class="welcome-box">
            <div class="wa-icon">💬</div>
            <h3>Chat de WhatsApp</h3>
            <p>Selecciona una conversación del panel izquierdo para comenzar.</p>
            @if($totalNoLeidos > 0)
                <p style="margin-top:.5rem;color:#ef4444;font-weight:600">
                    {{ $totalNoLeidos }} mensaje(s) sin leer
                </p>
            @endif
        </div>
    </main>

</div>
</div>
@endsection
