@extends('layouts.app')

@section('titulo', 'Finanzas — Cargando')
@section('modulo', 'Dashboard')

@section('contenido')
<div style="
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 70vh;
    text-align: center;
    gap: 1.5rem;
    padding: 2rem;
">
    {{-- Spinner animado --}}
    <div style="
        width: 64px;
        height: 64px;
        border: 5px solid #e2e8f0;
        border-top-color: #6366f1;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    "></div>

    <div>
        <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0 0 0.5rem;">
            Preparando tu Dashboard
        </h2>
        <p style="color: #64748b; margin: 0; font-size: 0.95rem;">
            Estamos calculando tus datos financieros.<br>
            La página se actualizará automáticamente en unos segundos.
        </p>
    </div>

    <div style="
        background: #f1f5f9;
        border-radius: 12px;
        padding: 1rem 1.5rem;
        font-size: 0.85rem;
        color: #94a3b8;
        max-width: 400px;
    ">
        ⏱️ Primera carga: conectando con la base de datos remota.<br>
        Las cargas siguientes serán instantáneas (&lt; 1 segundo).
    </div>

    {{-- Botón manual por si demora --}}
    <a href="{{ route('finanzas.dashboard', ['anio' => $anio, 'mes' => $mes]) }}"
       style="
           background: #6366f1;
           color: white;
           padding: 0.6rem 1.5rem;
           border-radius: 8px;
           text-decoration: none;
           font-size: 0.9rem;
           margin-top: 0.5rem;
       "
       id="btn-reintentar">
        Verificar si está listo
    </a>
</div>

<style>
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

{{-- Auto-recarga cada 8 segundos hasta que el caché esté listo --}}
<script>
    (function () {
        let intentos = 0;
        const maxIntentos = 20; // máximo ~160 segundos esperando

        function verificarCache() {
            intentos++;
            if (intentos > maxIntentos) {
                document.querySelector('#btn-reintentar').textContent = 'Recargar manualmente';
                return;
            }

            fetch(window.location.href, { headers: { 'X-Check-Cache': '1' } })
                .then(res => {
                    // Si el servidor responde con redirect o HTML sin la pantalla de cargando,
                    // recargar la página completa
                    if (res.url && res.url !== window.location.href) {
                        window.location.reload();
                        return;
                    }
                    return res.text();
                })
                .then(html => {
                    // Si la respuesta no contiene el indicador de "cargando", recargar
                    if (html && !html.includes('Preparando tu Dashboard')) {
                        window.location.reload();
                    } else {
                        setTimeout(verificarCache, 8000);
                    }
                })
                .catch(() => setTimeout(verificarCache, 8000));
        }

        // Primera verificación a los 8 segundos
        setTimeout(verificarCache, 8000);
    })();
</script>
@endsection
