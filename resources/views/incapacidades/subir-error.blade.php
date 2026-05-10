<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enlace Inválido</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:linear-gradient(135deg,#1e40af,#2563eb);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
.card{background:#fff;border-radius:20px;padding:2.5rem;text-align:center;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.icon{font-size:3.5rem;margin-bottom:1rem}
h1{font-size:1.2rem;font-weight:700;color:#1e293b;margin-bottom:.75rem}
p{font-size:.88rem;color:#475569;line-height:1.6}
</style>
</head>
<body>
<div class="card">
    <div class="icon">🔗</div>
    <h1>Enlace no disponible</h1>
    <p>{{ $mensaje ?? 'Este enlace no es válido, ha expirado o ya fue utilizado. Por favor contacte a su asesor para solicitar un nuevo enlace.' }}</p>
</div>
</body>
</html>
