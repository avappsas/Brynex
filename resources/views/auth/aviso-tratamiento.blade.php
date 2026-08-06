<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tratamiento de datos · BryNex</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; padding: 2rem 1rem;
            background: #0f1723; color: #e8edf4;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
        }
        .caja {
            max-width: 720px; margin: 0 auto; background: #172131;
            border: 1px solid rgba(255,255,255,.08); border-radius: 14px;
            padding: 2rem;
        }
        h1 { font-size: 1.35rem; margin: 0 0 .35rem; }
        .sub { color: #8b9bb4; font-size: .85rem; margin: 0 0 1.75rem; }
        h2 {
            font-size: .78rem; text-transform: uppercase; letter-spacing: .06em;
            color: #6ba8ff; margin: 1.6rem 0 .4rem;
        }
        p { margin: 0 0 .7rem; font-size: .93rem; }
        ul { margin: 0 0 .7rem; padding-left: 1.1rem; font-size: .93rem; }
        li { margin-bottom: .3rem; }
        strong { color: #fff; }
        .nota {
            background: rgba(107,168,255,.08); border-left: 3px solid #6ba8ff;
            padding: .8rem 1rem; border-radius: 0 8px 8px 0; margin: 1rem 0;
            font-size: .89rem;
        }
        .acepto {
            display: flex; gap: .7rem; align-items: flex-start;
            background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1);
            border-radius: 10px; padding: 1rem; margin: 1.75rem 0 1rem;
            cursor: pointer;
        }
        .acepto input { margin-top: .25rem; width: 18px; height: 18px; flex-shrink: 0; cursor: pointer; }
        .acepto span { font-size: .93rem; }
        button {
            width: 100%; padding: .85rem; border: 0; border-radius: 10px;
            background: #2f6fd0; color: #fff; font-size: .98rem; font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #3a7ee4; }
        .error {
            background: rgba(255,90,90,.12); border: 1px solid rgba(255,90,90,.35);
            color: #ffb3b3; padding: .7rem 1rem; border-radius: 8px;
            margin-bottom: 1rem; font-size: .9rem;
        }
        .pie { text-align: center; margin-top: 1.2rem; font-size: .82rem; color: #6d7d95; }
        .pie a { color: #8b9bb4; }
    </style>
</head>
<body>
    <div class="caja">
        <h1>Tratamiento de tus datos como usuario de BryNex</h1>
        <p class="sub">Léelo antes de continuar. Solo se muestra una vez · versión {{ $version }}</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <h2>Qué registramos</h2>
        <p>
            Tu nombre, documento de identidad, teléfono y correo. Y cada vez que entras:
            la fecha y hora, la dirección IP, un identificador del equipo desde el que
            ingresas y las características técnicas de tu navegador. También queda
            registro de las operaciones que realizas dentro del sistema.
        </p>

        <h2>Para qué</h2>
        <p>Únicamente para tres cosas:</p>
        <ul>
            <li>Darte acceso al sistema.</li>
            <li>Dejar constancia de quién realizó cada operación.</li>
            <li>Detectar accesos no autorizados.</li>
        </ul>
        <div class="nota">
            <strong>No usamos estos datos para evaluar tu desempeño laboral</strong>, no los
            compartimos con tu empleador con ese fin, y no los vendemos ni cedemos a terceros.
        </div>

        <h2>Cuánto tiempo</h2>
        <p>
            Los registros de acceso se conservan <strong>{{ $aniosAccesos }} años</strong> y el
            registro de operaciones <strong>{{ $aniosBitacora }} años</strong>. Cumplido el plazo
            se eliminan automáticamente.
        </p>

        <h2>Tus derechos</h2>
        <p>
            Puedes conocer, actualizar y rectificar tus datos; solicitar prueba de esta
            autorización; ser informado sobre el uso que les damos; presentar quejas ante la
            Superintendencia de Industria y Comercio; y revocar esta autorización o pedir la
            supresión de tus datos.
        </p>
        <p>
            Ten en cuenta que los registros de acceso y actividad soportan la seguridad del
            sistema: <strong>mientras tu cuenta esté activa no es posible suprimirlos</strong>,
            porque sin ellos no podríamos acreditar quién realizó cada operación. Si revocas la
            autorización, procederemos a cerrar tu cuenta.
        </p>

        <h2>Cómo ejercerlos</h2>
        <p>
            Escribe a <strong><a href="mailto:{{ $correo }}" style="color:#6ba8ff">{{ $correo }}</a></strong>.
            Respondemos consultas en 10 días hábiles y reclamos en 15, conforme a la
            Ley 1581 de 2012.
        </p>

        <form method="POST" action="{{ route('tratamiento.aceptar') }}">
            @csrf
            <label class="acepto">
                <input type="checkbox" name="acepto" value="1" required>
                <span>He leído este aviso y <strong>autorizo</strong> el tratamiento de mis datos
                personales en los términos descritos.</span>
            </label>
            <button type="submit">Aceptar y continuar</button>
        </form>

        <p class="pie">
            Si no estás de acuerdo, <a href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('salir').submit();">cierra sesión</a>
            y comunícate con tu administrador.
        </p>
        <form id="salir" method="POST" action="{{ route('logout') }}" style="display:none">@csrf</form>
    </div>
</body>
</html>
