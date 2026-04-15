<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BryNex — Seleccionar Aliado</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0a1628 0%, #0d2550 50%, #1a3a72 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .header img { height: 60px; object-fit: contain; margin-bottom: 0.75rem; }

        .header h1 {
            color: #fff;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .header p {
            color: rgba(255,255,255,0.5);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.25rem;
            width: 100%;
            max-width: 900px;
        }

        .card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.75rem 1.25rem;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s, background 0.2s;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }

        .card:hover {
            transform: translateY(-4px);
            border-color: #3b82f6;
            background: rgba(59,130,246,0.1);
            box-shadow: 0 12px 40px rgba(59,130,246,0.25);
        }

        .card-logo {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            object-fit: cover;
            background: rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }

        .card-logo img {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            object-fit: cover;
        }

        .card-nombre {
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .card-nit {
            color: rgba(255,255,255,0.4);
            font-size: 0.75rem;
        }

        .card-badge {
            background: rgba(59,130,246,0.2);
            border: 1px solid rgba(59,130,246,0.4);
            color: #93c5fd;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .btn-logout {
            margin-top: 2rem;
            color: rgba(255,255,255,0.4);
            font-size: 0.8rem;
            background: none;
            border: none;
            cursor: pointer;
            text-decoration: underline;
        }

        .btn-logout:hover { color: rgba(255,255,255,0.7); }

        form { display: contents; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ asset('img/logo-brynex.png') }}" alt="BryNex">
        <h1>Seleccione un aliado</h1>
        <p>Hola, <strong style="color:#93c5fd">{{ Auth::user()->nombre }}</strong> — ¿con qué empresa desea trabajar hoy?</p>
    </div>

    <div class="grid">
        @foreach ($aliados as $aliado)
            <form method="POST" action="{{ route('aliado.seleccionar') }}">
                @csrf
                <input type="hidden" name="aliado_id" value="{{ $aliado->id }}">
                <button type="submit" class="card" title="{{ $aliado->nombre }}">
                    <div class="card-logo">
                        @if ($aliado->logo)
                            <img src="{{ asset('storage/' . $aliado->logo) }}" alt="{{ $aliado->nombre }}">
                        @else
                            🏢
                        @endif
                    </div>
                    <div class="card-nombre">{{ $aliado->nombre }}</div>
                    @if ($aliado->nit)
                        <div class="card-nit">NIT: {{ $aliado->nit }}</div>
                    @endif
                    @if ($aliado->id === Auth::user()->aliado_id)
                        <span class="card-badge">Mi empresa</span>
                    @endif
                </button>
            </form>
        @endforeach
    </div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn-logout">← Cerrar sesión</button>
    </form>
</body>
</html>
