<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sin permiso</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #e2e8f0;
            padding: 20px;
        }
        .card {
            text-align: center;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 48px 40px;
            max-width: 480px;
            width: 100%;
        }
        .icon { font-size: 56px; margin-bottom: 20px; }
        h1 { font-size: 22px; font-weight: 700; margin-bottom: 12px; color: #f8fafc; }
        p  { font-size: 14px; color: #94a3b8; margin-bottom: 24px; line-height: 1.6; }
        .permiso {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 24px;
            font-size: 13.5px;
            color: #cbd5e1;
            line-height: 1.6;
        }
        .acciones { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        a {
            display: inline-block;
            background: #3b82f6;
            color: #fff;
            padding: 10px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background .2s;
        }
        a:hover { background: #2563eb; }
        a.secundario { background: #334155; }
        a.secundario:hover { background: #475569; }
        .note { font-size: 12px; color: #64748b; margin-top: 20px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🔒</div>
        <h1>No tienes permiso para esta sección</h1>

        @if($exception && $exception->getMessage())
            <div class="permiso">{{ $exception->getMessage() }}</div>
        @else
            <p>Tu usuario no tiene habilitado este módulo.<br>
               Pídeselo a un administrador o al superadministrador de tu empresa.</p>
        @endif

        <div class="acciones">
            <a href="{{ url()->previous() }}" class="secundario">← Volver</a>
            <a href="{{ Auth::check() ? route('dashboard') : route('login') }}">Ir al inicio</a>
        </div>

        @auth
            <p class="note">
                Conectado como <strong>{{ Auth::user()->nombre }}</strong>
                @if(Auth::user()->roles->isNotEmpty())
                    · rol <strong>{{ Auth::user()->roles->pluck('name')->join(', ') }}</strong>
                @else
                    · <strong>sin rol asignado</strong>
                @endif
                <br>El intento quedó registrado en la auditoría.
            </p>
        @endauth
    </div>
</body>
</html>
