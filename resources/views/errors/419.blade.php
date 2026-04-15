<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="3;url={{ route('login') }}">
    <title>Sesión Expirada</title>
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
        }
        .card {
            text-align: center;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 48px 40px;
            max-width: 420px;
            width: 90%;
        }
        .icon { font-size: 56px; margin-bottom: 20px; }
        h1 { font-size: 22px; font-weight: 700; margin-bottom: 8px; color: #f8fafc; }
        p  { font-size: 14px; color: #94a3b8; margin-bottom: 24px; line-height: 1.6; }
        a {
            display: inline-block;
            background: #3b82f6;
            color: #fff;
            padding: 10px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background .2s;
        }
        a:hover { background: #2563eb; }
        .note { font-size: 12px; color: #475569; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⏱️</div>
        <h1>Sesión expirada</h1>
        <p>Tu sesión o el token de seguridad han expirado.<br>
           Serás redirigido al inicio de sesión en unos segundos.</p>
        <a href="{{ route('login') }}">Ir al Login ahora</a>
        <p class="note">Redirigiendo automáticamente en 3 segundos…</p>
    </div>
</body>
</html>
