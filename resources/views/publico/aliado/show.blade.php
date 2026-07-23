@extends('layouts.public')

@section('titulo', $config->seo_titulo ?: ($aliado->nombre . ' — Afiliación a seguridad social'))
@section('descripcion', $config->seo_descripcion ?: 'Afíliate a EPS, ARL, pensión y caja de compensación con ' . $aliado->nombre . '. Gestión 100% en línea.')

@section('estilos')
    .hero {
        position: relative;
        overflow: hidden;
        padding: 3.5rem 0 5rem;
        background:
            radial-gradient(1200px 500px at 85% -10%, var(--brand-soft), transparent 60%),
            linear-gradient(180deg, var(--blanco), var(--fondo));
    }
    .hero-grid {
        display: grid;
        grid-template-columns: 1.1fr 0.9fr;
        gap: 3rem;
        align-items: center;
    }
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: var(--brand-soft);
        color: var(--brand-dark);
        border: 1px solid var(--brand-line);
        border-radius: 999px;
        padding: 0.35rem 0.9rem;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 1.25rem;
    }
    .badge svg { width: 14px; height: 14px; }
    .hero h1 {
        font-size: clamp(2rem, 4vw, 2.9rem);
        font-weight: 800;
        letter-spacing: -0.02em;
        line-height: 1.12;
        margin-bottom: 1rem;
    }
    .hero h1 span { color: var(--brand-dark); }
    .hero p.lead {
        font-size: 1.1rem;
        color: var(--tinta-suave);
        max-width: 32rem;
        margin-bottom: 2rem;
    }
    .hero-actions { display: flex; gap: 0.9rem; flex-wrap: wrap; }
    .hero-art { display: flex; justify-content: center; align-items: center; }

    .logo-top {
        display: flex; align-items: center; gap: 0.6rem;
        padding: 1.25rem 0;
    }
    .logo-top img { height: 40px; width: auto; border-radius: 8px; }
    .logo-top strong { font-size: 1.05rem; letter-spacing: -0.01em; }

    .servicios { background: var(--blanco); }
    .titulo-seccion {
        text-align: center;
        max-width: 40rem;
        margin: 0 auto 2.75rem;
    }
    .titulo-seccion h2 { font-size: clamp(1.6rem, 3vw, 2.1rem); font-weight: 800; letter-spacing: -0.02em; margin-bottom: 0.6rem; }
    .titulo-seccion p { color: var(--tinta-suave); }

    .grid-servicios {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.25rem;
    }
    .card-servicio {
        background: var(--fondo);
        border: 1px solid var(--borde);
        border-radius: 20px;
        padding: 1.75rem 1.5rem;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .card-servicio:hover { transform: translateY(-3px); box-shadow: 0 16px 32px -16px rgba(10,22,40,0.15); }
    .card-servicio .icono {
        width: 46px; height: 46px;
        border-radius: 12px;
        background: var(--brand-soft);
        color: var(--brand-dark);
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 1rem;
    }
    .card-servicio .icono svg { width: 24px; height: 24px; }
    .card-servicio h3 { font-size: 1.02rem; font-weight: 700; margin-bottom: 0.4rem; }
    .card-servicio p { font-size: 0.9rem; color: var(--tinta-suave); }

    .planes { background: var(--fondo); }
    .grid-planes { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; align-items: stretch; }
    .card-plan {
        background: var(--blanco);
        border: 1.5px solid var(--borde);
        border-radius: 22px;
        padding: 1.75rem 1.5rem;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    .card-plan.destacado {
        border-color: var(--brand);
        box-shadow: 0 20px 40px -20px color-mix(in srgb, var(--brand) 45%, transparent);
        transform: translateY(-6px);
    }
    .card-plan .cinta {
        position: absolute; top: -0.7rem; left: 1.5rem;
        background: linear-gradient(135deg, var(--brand), var(--brand-dark));
        color: var(--brand-text);
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
        padding: 0.3rem 0.8rem; border-radius: 999px;
    }
    .card-plan h3 { font-size: 1.1rem; font-weight: 800; margin-bottom: 0.4rem; }
    .card-plan .desc-plan { font-size: 0.85rem; color: var(--tinta-suave); margin-bottom: 1.1rem; min-height: 2.6em; }
    .chips-plan { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1.25rem; }
    .chip { background: var(--brand-soft); color: var(--brand-dark); font-size: 0.72rem; font-weight: 600; padding: 0.28rem 0.65rem; border-radius: 999px; }
    .precio-plan { margin-top: auto; margin-bottom: 1.1rem; }
    .precio-plan .valor { font-size: 1.7rem; font-weight: 800; color: var(--tinta); }
    .precio-plan .valor small { font-size: 0.85rem; font-weight: 600; color: var(--tinta-suave); }
    .precio-plan .afiliacion { font-size: 0.78rem; color: var(--tinta-suave); margin-top: 0.2rem; }
    .precio-plan .oculto { font-size: 0.95rem; font-weight: 700; color: var(--tinta); }

    .cotizador { background: var(--blanco); }
    .caja-cotizador {
        max-width: 42rem; margin: 0 auto;
        background: var(--fondo);
        border: 1px solid var(--borde);
        border-radius: 24px;
        padding: 2rem;
    }
    .cotizador-pasos { display: flex; gap: 0.5rem; margin-bottom: 1.75rem; }
    .cotizador-pasos span {
        flex: 1; height: 5px; border-radius: 999px; background: var(--borde);
        transition: background .2s ease;
    }
    .cotizador-pasos span.activo { background: var(--brand); }
    .cot-paso { display: none; }
    .cot-paso.activo { display: block; }
    .cot-titulo { font-size: 1.15rem; font-weight: 800; margin-bottom: 0.3rem; }
    .cot-sub { font-size: 0.85rem; color: var(--tinta-suave); margin-bottom: 1.25rem; }
    .opciones-perfil { display: grid; grid-template-columns: 1fr 1fr; gap: 0.9rem; }
    .opcion-perfil {
        border: 1.5px solid var(--borde); border-radius: 16px; padding: 1.1rem;
        background: var(--blanco); cursor: pointer; text-align: left; font-family: inherit;
    }
    .opcion-perfil:hover { border-color: var(--brand); }
    .opcion-perfil strong { display: block; font-size: 0.95rem; margin-bottom: 0.25rem; }
    .opcion-perfil span { font-size: 0.78rem; color: var(--tinta-suave); }
    .lista-coberturas { display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1.25rem; }
    .cobertura {
        display: flex; align-items: center; gap: 0.65rem;
        border: 1.5px solid var(--borde); border-radius: 12px; padding: 0.75rem 0.9rem;
        background: var(--blanco); cursor: pointer;
    }
    .cobertura input { width: 17px; height: 17px; accent-color: var(--brand); }
    .cobertura span { font-size: 0.88rem; font-weight: 600; }
    .campo-cot { margin-bottom: 1rem; }
    .campo-cot label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--tinta-suave); margin-bottom: 0.3rem; }
    .campo-cot input, .campo-cot select {
        width: 100%; padding: 0.6rem 0.75rem; border: 1.5px solid var(--borde); border-radius: 10px;
        font-size: 0.9rem; font-family: inherit; background: var(--blanco);
    }
    .cot-nav { display: flex; justify-content: space-between; gap: 0.75rem; margin-top: 1.25rem; }
    .cot-nav .btn-volver { background: none; border: none; color: var(--tinta-suave); font-size: 0.85rem; font-weight: 600; cursor: pointer; padding: 0.6rem; }
    .resultado-cot { background: var(--blanco); border: 1.5px solid var(--brand-line); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.25rem; }
    .resultado-cot .plan-elegido { font-size: 0.78rem; font-weight: 700; color: var(--brand-dark); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.5rem; }
    .resultado-cot .valor-grande { font-size: 2.1rem; font-weight: 800; color: var(--tinta); }
    .resultado-cot .valor-grande small { font-size: 0.9rem; font-weight: 600; color: var(--tinta-suave); }
    .resultado-cot .linea { display: flex; justify-content: space-between; font-size: 0.85rem; padding: 0.5rem 0; border-top: 1px dashed var(--borde); color: var(--tinta-suave); }
    .caja-ahorro { background: color-mix(in srgb, #16a34a 8%, white); border: 1px solid color-mix(in srgb, #16a34a 30%, white); border-radius: 14px; padding: 1rem 1.2rem; margin-bottom: 1.25rem; }
    .caja-ahorro .titulo-ahorro { font-size: 0.8rem; font-weight: 700; color: #15803d; margin-bottom: 0.3rem; }
    .caja-ahorro .monto-ahorro { font-size: 1.3rem; font-weight: 800; color: #15803d; }
    .caja-ahorro p { font-size: 0.78rem; color: #166534; margin-top: 0.3rem; }
    .form-lead .campo-cot { margin-bottom: 0.85rem; }
    .form-lead label.check {
        display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.76rem; color: var(--tinta-suave); cursor: pointer;
    }
    .form-lead input[type=checkbox] { margin-top: 0.15rem; }
    .cot-error { background: #fee2e2; color: #991b1b; border-radius: 10px; padding: 0.7rem 0.9rem; font-size: 0.82rem; margin-bottom: 1rem; }
    .cot-ok { text-align: center; padding: 1.5rem 0; }
    .cot-ok .icono-ok { width: 56px; height: 56px; border-radius: 50%; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; }

    .promos { background: var(--blanco); }
    .grid-promos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
    .card-promo { border: 1px solid var(--borde); border-radius: 18px; overflow: hidden; background: var(--fondo); }
    .card-promo img { width: 100%; aspect-ratio: 1/1; object-fit: cover; }
    .card-promo p { font-size: 0.85rem; color: var(--tinta-suave); padding: 0.9rem 1rem; margin: 0; }

    .pasos { background: var(--fondo); }
    .grid-pasos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
    .paso { position: relative; background: var(--blanco); border: 1px solid var(--borde); border-radius: 20px; padding: 2rem 1.5rem 1.75rem; }
    .paso .num {
        width: 36px; height: 36px; border-radius: 50%;
        background: linear-gradient(135deg, var(--brand), var(--brand-dark));
        color: var(--brand-text);
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; margin-bottom: 1rem;
    }
    .paso h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.4rem; }
    .paso p { font-size: 0.92rem; color: var(--tinta-suave); }

    .faq { background: var(--blanco); }
    .lista-faq { max-width: 44rem; margin: 0 auto; display: flex; flex-direction: column; gap: 0.75rem; }
    .lista-faq details {
        border: 1px solid var(--borde);
        border-radius: 14px;
        padding: 1.1rem 1.3rem;
        background: var(--fondo);
    }
    .lista-faq summary {
        cursor: pointer;
        font-weight: 600;
        list-style: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }
    .lista-faq summary::-webkit-details-marker { display: none; }
    .lista-faq summary::after {
        content: '+';
        font-size: 1.3rem;
        color: var(--brand-dark);
        flex-shrink: 0;
    }
    .lista-faq details[open] summary::after { content: '−'; }
    .lista-faq p.respuesta { margin-top: 0.75rem; color: var(--tinta-suave); font-size: 0.95rem; }

    .contacto {
        background: linear-gradient(135deg, var(--tinta), var(--brand-dark));
        color: white;
    }
    .grid-contacto { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 2.5rem; align-items: start; }
    .contacto h2 { font-size: clamp(1.6rem, 3vw, 2.1rem); font-weight: 800; margin-bottom: 0.75rem; letter-spacing: -0.02em; }
    .contacto p.sub { color: rgba(255,255,255,0.75); margin-bottom: 1.75rem; max-width: 30rem; }
    .lista-datos { display: flex; flex-direction: column; gap: 0.9rem; }
    .dato { display: flex; align-items: flex-start; gap: 0.75rem; }
    .dato .icono {
        width: 34px; height: 34px; border-radius: 9px;
        background: rgba(255,255,255,0.12);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .dato .icono svg { width: 17px; height: 17px; }
    .dato-texto small { display: block; color: rgba(255,255,255,0.6); font-size: 0.78rem; margin-bottom: 0.1rem; }
    .panel-cta {
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 20px;
        padding: 2rem;
        backdrop-filter: blur(6px);
    }
    .panel-cta h3 { font-size: 1.2rem; margin-bottom: 0.5rem; }
    .panel-cta p { color: rgba(255,255,255,0.75); font-size: 0.92rem; margin-bottom: 1.5rem; }

    footer {
        background: var(--tinta);
        color: rgba(255,255,255,0.55);
        padding: 1.75rem 0;
        font-size: 0.8rem;
        text-align: center;
    }
    footer strong { color: rgba(255,255,255,0.8); }

    @media (max-width: 860px) {
        .hero-grid, .grid-contacto { grid-template-columns: 1fr; }
        .hero-art { order: -1; max-width: 260px; margin: 0 auto 1rem; }
        .grid-servicios { grid-template-columns: repeat(2, 1fr); }
        .grid-pasos { grid-template-columns: 1fr; }
        .grid-planes { grid-template-columns: 1fr; }
        .grid-promos { grid-template-columns: repeat(2, 1fr); }
        .card-plan.destacado { transform: none; }
        .opciones-perfil { grid-template-columns: 1fr; }
        .caja-cotizador { padding: 1.5rem 1.1rem; }
    }
@endsection

@section('contenido')

    <header class="contenedor logo-top">
        @if($aliado->logo)
            <img src="{{ asset('storage/' . $aliado->logo) }}" alt="{{ $aliado->nombre }}">
        @endif
        <strong>{{ $aliado->nombre }}</strong>
    </header>

    <section class="hero">
        <div class="contenedor hero-grid">
            <div>
                <span class="badge">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/></svg>
                    Afiliación 100% en línea
                </span>
                <h1>{{ $config->hero_titulo ?: 'Tu seguridad social, sin filas ni papeleo' }}</h1>
                <p class="lead">
                    {{ $config->hero_subtitulo ?: ($aliado->nombre . ' te afilia a EPS, ARL, pensión y caja de compensación de forma rápida y segura, desde donde estés.') }}
                </p>
                <div class="hero-actions">
                    <a href="#contacto" class="btn btn-brand">{{ $config->hero_cta_texto ?: 'Quiero afiliarme' }}</a>
                    <a href="#faq" class="btn btn-ghost">Ver preguntas frecuentes</a>
                </div>
            </div>
            <div class="hero-art" aria-hidden="true">
                <svg viewBox="0 0 320 320" width="100%" style="max-width:320px;">
                    <defs>
                        <linearGradient id="g1" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="var(--brand)"/>
                            <stop offset="1" stop-color="var(--brand-dark)"/>
                        </linearGradient>
                    </defs>
                    <circle cx="160" cy="160" r="150" fill="var(--brand-soft)"/>
                    <path d="M160 45 L245 80 V165 C245 220 205 255 160 275 C115 255 75 220 75 165 V80 Z"
                          fill="url(#g1)" opacity="0.95"/>
                    <path d="M125 165 L150 190 L200 130" stroke="white" stroke-width="10"
                          stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <circle cx="245" cy="90" r="14" fill="var(--brand-dark)" opacity="0.5"/>
                    <circle cx="70" cy="230" r="10" fill="var(--brand)" opacity="0.4"/>
                </svg>
            </div>
        </div>
    </section>

    @if($config->seccionActiva('servicios'))
    <section class="servicios">
        <div class="contenedor">
            <div class="titulo-seccion">
                <h2>Todo lo que necesitas, en un solo lugar</h2>
                <p>Gestionamos tu afiliación completa al sistema de seguridad social colombiano.</p>
            </div>
            <div class="grid-servicios">
                <div class="card-servicio">
                    <div class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14c1.5-1.5 3-3.5 3-6a5 5 0 0 0-10 0 5 5 0 0 0-10 0c0 2.5 1.5 4.5 3 6l7 7z"/></svg></div>
                    <h3>Salud (EPS)</h3>
                    <p>Afiliación a la entidad promotora de salud de tu elección.</p>
                </div>
                <div class="card-servicio">
                    <div class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/></svg></div>
                    <h3>Riesgos laborales (ARL)</h3>
                    <p>Protección ante accidentes de trabajo según tu nivel de riesgo.</p>
                </div>
                <div class="card-servicio">
                    <div class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg></div>
                    <h3>Pensión (AFP)</h3>
                    <p>Aportes a tu fondo de pensión para tu futuro.</p>
                </div>
                <div class="card-servicio">
                    <div class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg></div>
                    <h3>Caja de compensación</h3>
                    <p>Acceso a subsidios y beneficios familiares.</p>
                </div>
            </div>
        </div>
    </section>
    @endif

    @if($config->seccionActiva('planes') && $planes->isNotEmpty())
    <section class="planes" id="planes">
        <div class="contenedor">
            <div class="titulo-seccion">
                <h2>Planes disponibles</h2>
                <p>Precios configurados directamente por {{ $aliado->nombre }} — se actualizan automáticamente.</p>
            </div>
            <div class="grid-planes">
                @foreach($planes as $plan)
                    <div class="card-plan {{ $plan['destacado'] ? 'destacado' : '' }}">
                        @if($plan['destacado'])<span class="cinta">Más elegido</span>@endif
                        <h3>{{ $plan['nombre'] }}</h3>
                        <p class="desc-plan">{{ $plan['descripcion'] }}</p>
                        <div class="chips-plan">
                            @if($plan['componentes']['incluye_eps']) <span class="chip">Salud (EPS)</span> @endif
                            @if($plan['componentes']['incluye_arl']) <span class="chip">ARL</span> @endif
                            @if($plan['componentes']['incluye_pension']) <span class="chip">Pensión</span> @endif
                            @if($plan['componentes']['incluye_caja']) <span class="chip">Caja</span> @endif
                        </div>
                        <div class="precio-plan">
                            @if($plan['valor_mensual'] !== null)
                                <div class="valor">
                                    @if($config->precios_modo === 'desde')Desde @endif
                                    ${{ number_format($plan['valor_mensual'], 0, ',', '.') }}<small>/mes</small>
                                </div>
                                @if($plan['costo_afiliacion'] > 0)
                                    <div class="afiliacion">+ ${{ number_format($plan['costo_afiliacion'], 0, ',', '.') }} afiliación (valor de referencia)</div>
                                @endif
                            @else
                                <div class="oculto">Cotización personalizada</div>
                            @endif
                        </div>
                        <a href="#cotizador" class="btn btn-brand" style="justify-content:center;">Cotizar este plan</a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    @if($config->seccionActiva('cotizador'))
    <section class="cotizador" id="cotizador">
        <div class="contenedor">
            <div class="titulo-seccion">
                <h2>Arma tu plan</h2>
                <p>Responde 2 preguntas y calculamos tu valor mensual al instante.</p>
            </div>
            <div class="caja-cotizador"
                 id="cotizadorApp"
                 data-cotizar-url="{{ route('publico.aliado.cotizar', $aliado->slug) }}"
                 data-lead-url="{{ route('publico.aliado.lead', $aliado->slug) }}"
                 data-whatsapp="{{ $whatsapp }}"
                 data-mensaje-base="{{ $config->whatsapp_mensaje_base ?: ('Hola ' . $aliado->nombre . ', quiero información sobre afiliación a seguridad social.') }}"
                 data-mostrar-ahorro="{{ $config->seccionActiva('ahorro') ? '1' : '0' }}">

                <div class="cotizador-pasos">
                    <span class="paso-ind activo" data-paso="1"></span>
                    <span class="paso-ind" data-paso="2"></span>
                    <span class="paso-ind" data-paso="3"></span>
                </div>

                {{-- Paso 1: perfil --}}
                <div class="cot-paso activo" data-paso="1">
                    <div class="cot-titulo">¿Cómo trabajas?</div>
                    <div class="cot-sub">Esto define el tipo de afiliación que necesitas.</div>
                    <div class="opciones-perfil">
                        <button type="button" class="opcion-perfil" data-perfil="dependiente">
                            <strong>Dependiente</strong>
                            <span>Trabajas para alguien (empleado, empleada doméstica, etc.)</span>
                        </button>
                        <button type="button" class="opcion-perfil" data-perfil="independiente">
                            <strong>Independiente</strong>
                            <span>Trabajas por cuenta propia</span>
                        </button>
                    </div>
                </div>

                {{-- Paso 2: coberturas --}}
                <div class="cot-paso" data-paso="2">
                    <div class="cot-titulo">¿Qué necesitas?</div>
                    <div class="cot-sub">Selecciona todo lo que quieras incluir.</div>
                    <div class="lista-coberturas">
                        <label class="cobertura"><input type="checkbox" data-cob="incluye_eps" checked> <span>Salud (EPS)</span></label>
                        <label class="cobertura"><input type="checkbox" data-cob="incluye_arl"> <span>Riesgos laborales (ARL)</span></label>
                        <label class="cobertura"><input type="checkbox" data-cob="incluye_pension"> <span>Pensión (AFP)</span></label>
                        <label class="cobertura"><input type="checkbox" data-cob="incluye_caja"> <span>Caja de compensación</span></label>
                    </div>
                    <div class="campo-cot" id="campoNivelArl" style="display:none;">
                        <label>Nivel de riesgo ARL</label>
                        <select id="cotNivelArl">
                            <option value="1">1 — Riesgo mínimo</option>
                            <option value="2">2 — Riesgo bajo</option>
                            <option value="3">3 — Riesgo medio</option>
                            <option value="4">4 — Riesgo alto</option>
                            <option value="5">5 — Riesgo máximo</option>
                        </select>
                    </div>
                    <div class="campo-cot">
                        <label>Ingreso mensual aproximado (opcional)</label>
                        <input type="number" id="cotSalario" placeholder="Ej: 1423500" min="0">
                    </div>
                    <div id="cotError"></div>
                    <div class="cot-nav">
                        <button type="button" class="btn-volver" data-volver="1">← Atrás</button>
                        <button type="button" class="btn btn-brand" id="btnVerPlan">Ver mi plan</button>
                    </div>
                </div>

                {{-- Paso 3: resultado + captura de lead --}}
                <div class="cot-paso" data-paso="3" id="cotPaso3">
                    <div id="cotResultado"></div>
                </div>

            </div>
        </div>
    </section>
    @endif

    @if($config->seccionActiva('promos') && $promos->isNotEmpty())
    <section class="promos" id="promos">
        <div class="contenedor">
            <div class="titulo-seccion">
                <h2>Novedades</h2>
                <p>Lo último de {{ $aliado->nombre }}.</p>
            </div>
            <div class="grid-promos">
                @foreach($promos as $promo)
                    <div class="card-promo">
                        <img src="{{ asset('storage/' . $promo->imagen_path) }}" alt="{{ $promo->titulo }}" loading="lazy">
                        @if($promo->copy)
                            <p>{{ Str::limit($promo->copy, 140) }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    @if($config->seccionActiva('pasos'))
    <section class="pasos">
        <div class="contenedor">
            <div class="titulo-seccion">
                <h2>Así de simple funciona</h2>
                <p>Tres pasos y quedas afiliado, sin moverte de donde estás.</p>
            </div>
            <div class="grid-pasos">
                <div class="paso">
                    <div class="num">1</div>
                    <h3>Escríbenos</h3>
                    <p>Cuéntanos qué necesitas por WhatsApp y te asesoramos sin costo.</p>
                </div>
                <div class="paso">
                    <div class="num">2</div>
                    <h3>Envía tus datos</h3>
                    <p>Comparte tu documentación de forma segura, sin desplazarte.</p>
                </div>
                <div class="paso">
                    <div class="num">3</div>
                    <h3>Quedas afiliado</h3>
                    <p>Recibes la confirmación y el soporte de tu afiliación.</p>
                </div>
            </div>
        </div>
    </section>
    @endif

    @if($config->seccionActiva('faq') && $faqs->isNotEmpty())
        <section class="faq" id="faq">
            <div class="contenedor">
                <div class="titulo-seccion">
                    <h2>Preguntas frecuentes</h2>
                </div>
                <div class="lista-faq">
                    @foreach($faqs as $faq)
                        <details>
                            <summary>{{ $faq->pregunta }}</summary>
                            <p class="respuesta">{{ $faq->respuesta }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="contacto" id="contacto">
        <div class="contenedor grid-contacto">
            <div>
                <h2>Hablemos</h2>
                <p class="sub">Escríbenos y un asesor te contacta para resolver tus dudas y afiliarte.</p>
                <div class="lista-datos">
                    @if($aliado->direccion)
                        <div class="dato">
                            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 21s7-6.5 7-12a7 7 0 1 0-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></span>
                            <div class="dato-texto"><small>Dirección</small>{{ $aliado->direccion }}@if($aliado->ciudad), {{ $aliado->ciudad }}@endif</div>
                        </div>
                    @endif
                    @if($whatsapp)
                        <div class="dato">
                            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.32 1.78.6 2.63a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.45-1.17a2 2 0 0 1 2.11-.45c.85.28 1.73.48 2.63.6A2 2 0 0 1 22 16.92z"/></svg></span>
                            <div class="dato-texto"><small>WhatsApp</small>{{ $whatsapp }}</div>
                        </div>
                    @endif
                    @if($aliado->correo)
                        <div class="dato">
                            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="M4 4l8 8 8-8"/></svg></span>
                            <div class="dato-texto"><small>Correo</small>{{ $aliado->correo }}</div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="panel-cta">
                <h3>¿Listo para afiliarte?</h3>
                <p>Escríbenos por WhatsApp y un asesor de {{ $aliado->nombre }} te acompaña en todo el proceso.</p>
                @if($whatsapp)
                    @php
                        $numeroWaCta = preg_replace('/\D/', '', $whatsapp);
                        if ($numeroWaCta && !str_starts_with($numeroWaCta, '57')) { $numeroWaCta = '57' . $numeroWaCta; }
                        $mensajeWaCta = rawurlencode($config->whatsapp_mensaje_base ?: ('Hola ' . $aliado->nombre . ', quiero información sobre afiliación a seguridad social.'));
                    @endphp
                    <a href="https://wa.me/{{ $numeroWaCta }}?text={{ $mensajeWaCta }}" target="_blank" rel="noopener" onclick="registrarClicWa()" class="btn btn-brand" style="width:100%; justify-content:center;">
                        Escribir por WhatsApp
                    </a>
                @endif
            </div>
        </div>
    </section>

    <footer>
        <div class="contenedor">
            <strong>{{ $aliado->nombre }}</strong> es un intermediario privado de afiliación al sistema de seguridad social colombiano, no una entidad estatal.
            &copy; {{ now()->year }} {{ $aliado->nombre }}. Todos los derechos reservados.
            <div style="margin-top:0.5rem;">
                <a href="{{ route('publico.aliado.privacidad', $aliado->slug) }}" style="text-decoration:underline;">Política de privacidad</a>
                &nbsp;·&nbsp;
                <a href="{{ route('publico.aliado.terminos', $aliado->slug) }}" style="text-decoration:underline;">Términos del servicio</a>
                &nbsp;·&nbsp;
                <a href="{{ route('publico.aliado.eliminacion_datos', $aliado->slug) }}" style="text-decoration:underline;">Eliminación de datos</a>
            </div>
        </div>
    </footer>

@endsection

@section('scripts')
<script>
(function () {
    var app = document.getElementById('cotizadorApp');
    if (!app) return;

    var estado = { independiente: null, componentes: {}, salario: null, nivel_arl: 1 };
    var csrfToken   = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var cotizarUrl  = app.dataset.cotizarUrl;
    var leadUrl     = app.dataset.leadUrl;
    var whatsapp    = app.dataset.whatsapp;
    var mensajeBase = app.dataset.mensajeBase;
    var mostrarAhorro = app.dataset.mostrarAhorro === '1';

    var chkArl        = app.querySelector('[data-cob="incluye_arl"]');
    var campoNivelArl = document.getElementById('campoNivelArl');

    function irAPaso(n) {
        app.querySelectorAll('.cot-paso').forEach(function (el) {
            el.classList.toggle('activo', el.dataset.paso === String(n));
        });
        app.querySelectorAll('.paso-ind').forEach(function (el) {
            el.classList.toggle('activo', parseInt(el.dataset.paso, 10) <= n);
        });
    }

    function formatoMoneda(v) {
        return '$' + Math.round(v).toLocaleString('es-CO');
    }

    app.querySelectorAll('.opcion-perfil').forEach(function (btn) {
        btn.addEventListener('click', function () {
            estado.independiente = btn.dataset.perfil === 'independiente';
            app.querySelector('[data-cob="incluye_eps"]').checked = true;
            chkArl.checked = !estado.independiente;
            campoNivelArl.style.display = chkArl.checked ? 'block' : 'none';
            irAPaso(2);
        });
    });

    app.querySelectorAll('[data-volver="1"]').forEach(function (btn) {
        btn.addEventListener('click', function () { irAPaso(1); });
    });

    chkArl.addEventListener('change', function () {
        campoNivelArl.style.display = chkArl.checked ? 'block' : 'none';
    });

    document.getElementById('btnVerPlan').addEventListener('click', function () {
        var btn = this;
        var cobs = {};
        app.querySelectorAll('[data-cob]').forEach(function (c) { cobs[c.dataset.cob] = c.checked; });

        var errorBox = document.getElementById('cotError');
        errorBox.innerHTML = '';

        if (![cobs.incluye_eps, cobs.incluye_arl, cobs.incluye_pension, cobs.incluye_caja].some(Boolean)) {
            errorBox.innerHTML = '<div class="cot-error">Selecciona al menos una cobertura.</div>';
            return;
        }

        var salarioInput = document.getElementById('cotSalario').value;
        estado.componentes = cobs;
        estado.salario   = salarioInput ? parseFloat(salarioInput) : null;
        estado.nivel_arl = parseInt(document.getElementById('cotNivelArl').value || '1', 10);

        btn.disabled = true;
        btn.textContent = 'Calculando...';

        var body = Object.assign({ independiente: estado.independiente }, cobs, {
            nivel_arl: estado.nivel_arl,
            salario: estado.salario
        });

        fetch(cotizarUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(body)
        })
        .then(function (r) { return r.json().then(function (data) { return { status: r.status, data: data }; }); })
        .then(function (res) {
            btn.disabled = false;
            btn.textContent = 'Ver mi plan';
            if (res.status !== 200) {
                errorBox.innerHTML = '<div class="cot-error">' + (res.data.error || 'No pudimos calcular tu plan.') + '</div>';
                return;
            }
            renderResultado(res.data);
            irAPaso(3);
        })
        .catch(function () {
            btn.disabled = false;
            btn.textContent = 'Ver mi plan';
            errorBox.innerHTML = '<div class="cot-error">Hubo un problema de conexión. Intenta de nuevo.</div>';
        });
    });

    function renderResultado(data) {
        var html = '<div class="resultado-cot">';
        html += '<div class="plan-elegido">' + data.plan_nombre + '</div>';

        if (data.precios_visibles) {
            html += '<div class="valor-grande">' + (data.precios_modo === 'desde' ? 'Desde ' : '')
                  + formatoMoneda(data.valor_mensual_total) + '<small>/mes</small></div>';

            if (data.plan_pago_inicial) {
                var p = data.plan_pago_inicial;
                html += '<div class="linea"><span>' + p.mes_1_nombre + ' (solo afiliación)</span><strong>' + formatoMoneda(p.mes_1_afiliacion) + '</strong></div>';
                html += '<div class="linea"><span>' + p.mes_2_nombre + ' (proporcional)</span><strong>' + formatoMoneda(p.mes_2_valor) + '</strong></div>';
                html += '<div class="linea"><span>' + p.mes_3_nombre + ' en adelante</span><strong>' + formatoMoneda(p.mes_3_en_adelante) + '</strong></div>';
            } else if (data.costo_afiliacion_sugerido > 0) {
                html += '<div class="linea"><span>Afiliación (referencia)</span><strong>' + formatoMoneda(data.costo_afiliacion_sugerido) + '</strong></div>';
            }
        } else {
            html += '<div class="valor-grande" style="font-size:1.25rem;">Un asesor te confirma el valor por WhatsApp</div>';
        }

        if (mostrarAhorro && data.ahorro) {
            html += '<div class="caja-ahorro">'
                  + '<div class="titulo-ahorro">💰 Yendo directo como independiente pagarías</div>'
                  + '<div class="monto-ahorro">' + formatoMoneda(data.ahorro.total) + '/mes</div>'
                  + '<p>12,5% salud + 16% pensión sobre tu ingreso base — nosotros te asesoramos sin ese sobrecosto.</p>'
                  + '</div>';
        }

        html += '</div>';

        html += '<form class="form-lead" id="formLead">'
              + '<input type="text" name="sitio_web" style="position:absolute;left:-9999px;" tabindex="-1" autocomplete="off">'
              + '<div class="campo-cot"><label>Tu nombre</label><input type="text" id="leadNombre" required maxlength="150"></div>'
              + '<div class="campo-cot"><label>Tu celular (WhatsApp)</label><input type="tel" id="leadCelular" required maxlength="30" placeholder="Ej: 3001234567"></div>'
              + '<label class="check"><input type="checkbox" id="leadConsiento" required> Acepto que me contacten para esta cotización.</label>'
              + '<div class="cot-nav"><button type="button" class="btn-volver" data-volver="2">← Cambiar selección</button>'
              + '<button type="submit" class="btn btn-brand">Enviar por WhatsApp</button></div>'
              + '</form>';

        document.getElementById('cotResultado').innerHTML = html;

        document.querySelectorAll('#cotPaso3 [data-volver="2"]').forEach(function (btn) {
            btn.addEventListener('click', function () { irAPaso(2); });
        });

        document.getElementById('formLead').addEventListener('submit', function (e) {
            e.preventDefault();
            enviarLead(data);
        });
    }

    function enviarLead(resultado) {
        var nombre    = document.getElementById('leadNombre').value;
        var celular   = document.getElementById('leadCelular').value;
        var honeypot  = document.querySelector('#formLead [name="sitio_web"]').value;

        if (honeypot) return; // relleno solo por bots

        var body = {
            nombre: nombre,
            celular: celular,
            perfil: estado.independiente ? 'independiente' : 'dependiente',
            incluye_eps: !!estado.componentes.incluye_eps,
            incluye_arl: !!estado.componentes.incluye_arl,
            incluye_pension: !!estado.componentes.incluye_pension,
            incluye_caja: !!estado.componentes.incluye_caja,
            ingreso_mensual: estado.salario,
            valor_mensual_cotizado: resultado.precios_visibles ? resultado.valor_mensual_total : null,
            plan_interes: resultado.plan_nombre,
            origen: 'cotizador',
            consiento_datos: document.getElementById('leadConsiento').checked,
            sitio_web: honeypot
        };

        // No se espera la respuesta (keepalive) para poder abrir WhatsApp en el mismo gesto
        // síncrono del click — si se espera un fetch, algunos navegadores bloquean el popup.
        fetch(leadUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(body),
            keepalive: true
        }).catch(function () {});

        if (whatsapp) {
            var numero = whatsapp.replace(/\D/g, '');
            if (numero && numero.indexOf('57') !== 0) numero = '57' + numero;
            var msg = 'Hola, soy ' + nombre + '. Me interesa el plan "' + resultado.plan_nombre + '"'
                    + (resultado.precios_visibles ? ' (' + formatoMoneda(resultado.valor_mensual_total) + '/mes)' : '')
                    + '. ' + mensajeBase;
            window.open('https://wa.me/' + numero + '?text=' + encodeURIComponent(msg), '_blank');
        }

        document.getElementById('cotResultado').innerHTML =
            '<div class="cot-ok"><div class="icono-ok">✓</div><h3>¡Listo!</h3><p>Te escribimos por WhatsApp en un momento.</p></div>';
    }
})();
</script>
@endsection
