@extends('layouts.app')
@section('modulo', 'Publicidad')

@section('contenido')
<div style="max-width:900px;margin:0 auto;">

<a href="{{ route('admin.publicidad.index') }}"
   style="font-size:.73rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.85rem">
    ← Volver al historial
</a>
<h1 style="font-size:1.2rem;font-weight:700;color:#0f172a;margin:0 0 1rem;">🎨 Nueva pieza publicitaria</h1>

@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
  @foreach($errors->all() as $e) · {{ $e }} @endforeach
</div>
@endif

<div id="alertaJs" style="display:none;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;"></div>

<div style="display:grid;grid-template-columns:1.1fr 0.9fr;gap:1.5rem;align-items:start;">

  {{-- ══ COLUMNA IZQUIERDA: formulario ══ --}}
  <div>
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:1rem;">
      <label style="display:block;font-size:0.75rem;font-weight:600;color:#334155;margin-bottom:0.3rem;">Título (uso interno)</label>
      <input type="text" id="fTitulo" maxlength="150" placeholder="Ej: Promo plan completo julio"
             style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;">
    </div>

    {{-- ── Imagen ── --}}
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:1rem;">
      <div style="font-size:0.72rem;font-weight:700;color:#2563eb;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.75rem;">🖼️ Imagen</div>

      <div style="display:flex;gap:0.4rem;margin-bottom:0.9rem;flex-wrap:wrap;">
        <button type="button" class="tab-imagen activa" data-tab="plantilla" style="flex:1;padding:0.5rem;border-radius:8px;border:1.5px solid #2563eb;background:#eff6ff;color:#1e40af;font-size:0.8rem;font-weight:700;cursor:pointer;">📐 Plantilla</button>
        <button type="button" class="tab-imagen" data-tab="ia" style="flex:1;padding:0.5rem;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;color:#475569;font-size:0.8rem;font-weight:700;cursor:pointer;">✨ Generar con IA</button>
        <button type="button" class="tab-imagen" data-tab="subida" style="flex:1;padding:0.5rem;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;color:#475569;font-size:0.8rem;font-weight:700;cursor:pointer;">📤 Subir</button>
      </div>

      {{-- Panel: Plantilla --}}
      <div class="panel-imagen" data-panel="plantilla">
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.5rem;margin-bottom:0.9rem;">
          <button type="button" class="tipo-plantilla activa" data-tipo="promo_precio" style="padding:0.5rem;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;font-size:0.78rem;font-weight:600;cursor:pointer;">💰 Promo de precio</button>
          <button type="button" class="tipo-plantilla" data-tipo="tarjeta_plan" style="padding:0.5rem;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;font-size:0.78rem;font-weight:600;cursor:pointer;">🗂️ Tarjeta de plan</button>
          <button type="button" class="tipo-plantilla" data-tipo="dato_educativo" style="padding:0.5rem;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;font-size:0.78rem;font-weight:600;cursor:pointer;">💡 ¿Sabías que...?</button>
          <button type="button" class="tipo-plantilla" data-tipo="recordatorio" style="padding:0.5rem;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;font-size:0.78rem;font-weight:600;cursor:pointer;">📅 Recordatorio</button>
        </div>

        {{-- Campos: promo_precio --}}
        <div class="campos-tipo" data-para="promo_precio">
          <label style="display:block;font-size:0.72rem;font-weight:600;color:#334155;margin-bottom:0.25rem;">Plan (precio en vivo)</label>
          <select id="ppPlan" style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;margin-bottom:0.6rem;">
            <option value="">— Escribir manualmente —</option>
            @foreach($planes as $p)
              <option value="{{ $p['nombre'] }}|{{ $p['valor_mensual'] }}">{{ $p['nombre'] }} — ${{ number_format($p['valor_mensual'],0,',','.') }}/mes</option>
            @endforeach
          </select>
          <input type="text" id="ppNombrePlan" placeholder="Nombre del plan" style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;margin-bottom:0.5rem;">
          <input type="text" id="ppValor" placeholder="Valor mensual (ej: 125400)" style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;margin-bottom:0.5rem;">
          <input type="text" id="ppExtra" placeholder="Texto adicional (ej: Afiliación en línea)" style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;">
        </div>

        {{-- Campos: tarjeta_plan --}}
        <div class="campos-tipo" data-para="tarjeta_plan" style="display:none;">
          <input type="text" id="tpNombrePlan" placeholder="Nombre del plan" style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;margin-bottom:0.5rem;">
          <div style="display:flex;gap:0.8rem;margin-bottom:0.5rem;flex-wrap:wrap;">
            <label style="font-size:0.8rem;"><input type="checkbox" id="tpEps" checked> EPS</label>
            <label style="font-size:0.8rem;"><input type="checkbox" id="tpArl" checked> ARL</label>
            <label style="font-size:0.8rem;"><input type="checkbox" id="tpPension"> Pensión</label>
            <label style="font-size:0.8rem;"><input type="checkbox" id="tpCaja"> Caja</label>
          </div>
          <input type="text" id="tpCta" placeholder="Texto de llamado a la acción (ej: Escríbenos ya)" style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;">
        </div>

        {{-- Campos: dato_educativo --}}
        <div class="campos-tipo" data-para="dato_educativo" style="display:none;">
          <input type="text" id="dePregunta" placeholder="¿Sabías que...?" style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;margin-bottom:0.5rem;">
          <textarea id="deRespuesta" rows="3" placeholder="Respuesta / dato" style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;font-family:inherit;"></textarea>
        </div>

        {{-- Campos: recordatorio --}}
        <div class="campos-tipo" data-para="recordatorio" style="display:none;">
          <textarea id="rcMensaje" rows="2" placeholder="Mensaje (ej: No olvides tu pago de seguridad social)" style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;font-family:inherit;margin-bottom:0.5rem;"></textarea>
          <input type="text" id="rcFecha" placeholder="Fecha límite (ej: 10 de agosto)" style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;">
        </div>
      </div>

      {{-- Panel: IA --}}
      <div class="panel-imagen" data-panel="ia" style="display:none;">
        @if(!$tieneGemini)
          <p style="font-size:0.78rem;color:#94a3b8;background:#f8fafc;border-radius:8px;padding:0.6rem 0.8rem;">
            No hay una clave de Gemini configurada. Ve a <strong>Asistente Virtual</strong> para agregarla, o usa una plantilla mientras tanto.
          </p>
        @else
          <textarea id="iaPrompt" rows="2" placeholder="Describe la imagen que quieres (ej: ilustración moderna de una familia protegida, colores azules)"
                    style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;font-family:inherit;margin-bottom:0.6rem;"></textarea>
          <button type="button" id="btnGenerarImagenIa" style="background:#7c3aed;color:#fff;border:none;font-size:0.8rem;font-weight:700;padding:0.5rem 1rem;border-radius:8px;cursor:pointer;">✨ Generar imágenes</button>
          <div id="resultadosImagenIa" style="display:flex;gap:0.6rem;margin-top:0.8rem;flex-wrap:wrap;"></div>
        @endif
      </div>

      {{-- Panel: Subida --}}
      <div class="panel-imagen" data-panel="subida" style="display:none;">
        <input type="file" id="fArchivoSubido" accept="image/png,image/jpeg">
      </div>
    </div>

    {{-- ── Texto del post ── --}}
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:1rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;">
        <div style="font-size:0.72rem;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:0.06em;">✍️ Texto del post</div>
        @if($tieneIaTexto)
          <button type="button" id="btnGenerarCopia" style="background:#f0fdf4;color:#16a34a;border:1px solid #86efac;font-size:0.72rem;font-weight:700;padding:0.3rem 0.6rem;border-radius:6px;cursor:pointer;">✨ Generar con IA</button>
        @endif
      </div>
      <div style="margin-bottom:0.6rem;">
        <input type="text" id="tipoPiezaIa" placeholder="Tipo de pieza (ej: promo de precio)" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.75rem;margin-bottom:0.4rem;" value="promo de precio">
        <input type="text" id="contextoIa" placeholder="Contexto para la IA (ej: Plan completo EPS+ARL+AFP+CCF)" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.75rem;">
      </div>
      <div id="variantesCopia" style="display:none;flex-direction:column;gap:0.4rem;margin-bottom:0.6rem;"></div>
      <textarea id="fCopy" rows="3" maxlength="2000" placeholder="Texto que acompaña la imagen al publicar en redes..."
                style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;font-family:inherit;"></textarea>
    </div>

    {{-- ── Destinos y programación ── --}}
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:1rem;">
      <div style="font-size:0.72rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.75rem;">📡 Destinos</div>
      <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:0.9rem;">
        <label style="font-size:0.85rem;"><input type="checkbox" id="destWeb" checked disabled> Página web</label>
        <label style="font-size:0.85rem;{{ !$redesActivas->contains('facebook') ? 'color:#94a3b8;' : '' }}">
          <input type="checkbox" id="destFacebook" {{ $redesActivas->contains('facebook') ? '' : 'disabled' }}> Facebook
          @if(!$redesActivas->contains('facebook'))<span style="font-size:0.68rem;"> (activar en Redes Sociales)</span>@endif
        </label>
        <label style="font-size:0.85rem;{{ !$redesActivas->contains('instagram') ? 'color:#94a3b8;' : '' }}">
          <input type="checkbox" id="destInstagram" {{ $redesActivas->contains('instagram') ? '' : 'disabled' }}> Instagram
          @if(!$redesActivas->contains('instagram'))<span style="font-size:0.68rem;"> (activar en Redes Sociales)</span>@endif
        </label>
      </div>
      <label style="display:block;font-size:0.72rem;font-weight:600;color:#334155;margin-bottom:0.3rem;">Programar publicación (opcional)</label>
      <input type="datetime-local" id="fProgramada" style="padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;">
      <p style="font-size:0.7rem;color:#94a3b8;margin:0.4rem 0 0;">Si la dejas vacía, se publica en cuanto se apruebe.</p>
    </div>

    <button type="button" id="btnEnviar" style="width:100%;background:#2563eb;color:#fff;border:none;font-size:0.9rem;font-weight:700;padding:0.75rem;border-radius:9px;cursor:pointer;">
      Enviar a aprobación
    </button>
  </div>

  {{-- ══ COLUMNA DERECHA: preview ══ --}}
  <div style="position:sticky;top:1rem;">
    <div style="background:#0f172a;border-radius:16px;padding:1rem;display:flex;justify-content:center;">
      <canvas id="lienzo" width="1080" height="1080" style="width:100%;max-width:340px;border-radius:8px;"></canvas>
    </div>
    <div id="previewSubidaImg" style="display:none;margin-top:0.75rem;">
      <img id="previewSubidaImgTag" style="width:100%;border-radius:12px;">
    </div>
  </div>

</div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const aliadoNombre = @json($aliado->nombre);
    const colorPrimario = @json($aliado->color_primario ?: '#2563eb');
    const logoUrl = @json($aliado->logo ? asset('storage/' . $aliado->logo) : null);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    let logoImg = null;
    if (logoUrl) {
        logoImg = new Image();
        logoImg.onload = () => redibujar();
        logoImg.src = logoUrl;
    }

    const canvas = document.getElementById('lienzo');
    const ctx = canvas.getContext('2d');
    let tipoActivo = 'promo_precio';
    let fuenteImagen = 'plantilla'; // plantilla|ia|subida
    let imagenIaSeleccionada = null; // {path, url}
    let archivoSubido = null;

    function oscurecer(hex, factor) {
        hex = hex.replace('#', '');
        const r = Math.max(0, parseInt(hex.substr(0, 2), 16) * factor);
        const g = Math.max(0, parseInt(hex.substr(2, 2), 16) * factor);
        const b = Math.max(0, parseInt(hex.substr(4, 2), 16) * factor);
        return `rgb(${r},${g},${b})`;
    }

    function envolverTexto(context, texto, x, y, maxAncho, alturaLinea, alineacion = 'left') {
        const palabras = texto.split(' ');
        let linea = '';
        let lineas = [];
        for (const palabra of palabras) {
            const prueba = linea ? linea + ' ' + palabra : palabra;
            if (context.measureText(prueba).width > maxAncho && linea) {
                lineas.push(linea);
                linea = palabra;
            } else {
                linea = prueba;
            }
        }
        if (linea) lineas.push(linea);
        lineas.forEach((l, i) => {
            const anchoLinea = context.measureText(l).width;
            const px = alineacion === 'center' ? x - anchoLinea / 2 : x;
            context.fillText(l, px, y + i * alturaLinea);
        });
        return lineas.length;
    }

    function fondoBase() {
        const grad = ctx.createLinearGradient(0, 0, 1080, 1080);
        grad.addColorStop(0, colorPrimario);
        grad.addColorStop(1, oscurecer(colorPrimario, 0.55));
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, 1080, 1080);
    }

    function encabezadoMarca() {
        if (logoImg && logoImg.complete && logoImg.naturalWidth > 0) {
            ctx.save();
            ctx.beginPath();
            ctx.arc(100, 100, 50, 0, Math.PI * 2);
            ctx.closePath();
            ctx.clip();
            ctx.fillStyle = '#fff';
            ctx.fill();
            ctx.drawImage(logoImg, 50, 50, 100, 100);
            ctx.restore();
        }
        ctx.fillStyle = '#fff';
        ctx.font = '700 34px Arial';
        ctx.textAlign = 'left';
        ctx.fillText(aliadoNombre, 170, 112);
    }

    function piePagina() {
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        ctx.font = '600 26px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('Escríbenos por WhatsApp', 540, 1010);
    }

    function dibujarPromoPrecio() {
        fondoBase();
        encabezadoMarca();

        const nombrePlan = document.getElementById('ppNombrePlan').value || 'Plan de seguridad social';
        const valor = document.getElementById('ppValor').value.replace(/\D/g, '');
        const extra = document.getElementById('ppExtra').value;

        ctx.fillStyle = '#fff';
        ctx.textAlign = 'center';
        ctx.font = '700 46px Arial';
        envolverTexto(ctx, nombrePlan, 540, 420, 880, 54, 'center');

        if (valor) {
            ctx.font = '800 130px Arial';
            ctx.fillText('$' + Number(valor).toLocaleString('es-CO'), 540, 620);
            ctx.font = '600 40px Arial';
            ctx.fillText('al mes', 540, 680);
        }

        if (extra) {
            ctx.font = '500 32px Arial';
            envolverTexto(ctx, extra, 540, 780, 800, 40, 'center');
        }

        piePagina();
    }

    function dibujarTarjetaPlan() {
        fondoBase();
        encabezadoMarca();

        const nombrePlan = document.getElementById('tpNombrePlan').value || 'Plan de seguridad social';
        const servicios = [];
        if (document.getElementById('tpEps').checked) servicios.push('Salud (EPS)');
        if (document.getElementById('tpArl').checked) servicios.push('Riesgos laborales (ARL)');
        if (document.getElementById('tpPension').checked) servicios.push('Pensión (AFP)');
        if (document.getElementById('tpCaja').checked) servicios.push('Caja de compensación');
        const cta = document.getElementById('tpCta').value || 'Escríbenos ya';

        ctx.fillStyle = '#fff';
        ctx.textAlign = 'center';
        ctx.font = '700 50px Arial';
        envolverTexto(ctx, nombrePlan, 540, 330, 880, 58, 'center');

        let y = 460;
        ctx.textAlign = 'left';
        servicios.forEach((s) => {
            ctx.fillStyle = 'rgba(255,255,255,0.15)';
            ctx.beginPath();
            ctx.roundRect(190, y - 40, 700, 64, 32);
            ctx.fill();
            ctx.fillStyle = '#fff';
            ctx.font = '600 32px Arial';
            ctx.fillText('✓ ' + s, 230, y + 4);
            y += 90;
        });

        ctx.textAlign = 'center';
        ctx.font = '700 38px Arial';
        ctx.fillText(cta, 540, y + 60);

        piePagina();
    }

    function dibujarDatoEducativo() {
        fondoBase();
        encabezadoMarca();

        const pregunta = document.getElementById('dePregunta').value || '¿Sabías que...?';
        const respuesta = document.getElementById('deRespuesta').value || '';

        ctx.fillStyle = '#fff';
        ctx.font = '800 30px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('💡 ¿SABÍAS QUE...?', 540, 300);

        ctx.font = '700 46px Arial';
        const lineasPregunta = envolverTexto(ctx, pregunta, 540, 420, 860, 56, 'center');

        ctx.font = '500 34px Arial';
        envolverTexto(ctx, respuesta, 540, 420 + lineasPregunta * 56 + 60, 820, 44, 'center');

        piePagina();
    }

    function dibujarRecordatorio() {
        fondoBase();
        encabezadoMarca();

        const mensaje = document.getElementById('rcMensaje').value || 'No olvides tu pago de seguridad social';
        const fecha = document.getElementById('rcFecha').value;

        ctx.fillStyle = '#fff';
        ctx.font = '800 32px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('📅 RECORDATORIO', 540, 330);

        ctx.font = '700 48px Arial';
        const n = envolverTexto(ctx, mensaje, 540, 460, 860, 58, 'center');

        if (fecha) {
            ctx.font = '700 40px Arial';
            ctx.fillStyle = '#fde047';
            ctx.fillText(fecha, 540, 460 + n * 58 + 70);
        }

        piePagina();
    }

    const dibujantes = {
        promo_precio: dibujarPromoPrecio,
        tarjeta_plan: dibujarTarjetaPlan,
        dato_educativo: dibujarDatoEducativo,
        recordatorio: dibujarRecordatorio,
    };

    function redibujar() {
        if (fuenteImagen !== 'plantilla') return;
        (dibujantes[tipoActivo] || dibujarPromoPrecio)();
    }

    // ── Tabs de fuente de imagen ──────────────────────────────────────
    document.querySelectorAll('.tab-imagen').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-imagen').forEach((b) => {
                b.classList.remove('activa');
                b.style.border = '1.5px solid #cbd5e1'; b.style.background = '#fff'; b.style.color = '#475569';
            });
            btn.classList.add('activa');
            btn.style.border = '1.5px solid #2563eb'; btn.style.background = '#eff6ff'; btn.style.color = '#1e40af';
            fuenteImagen = btn.dataset.tab;
            document.querySelectorAll('.panel-imagen').forEach((p) => p.style.display = p.dataset.panel === fuenteImagen ? 'block' : 'none');
            document.getElementById('previewSubidaImg').style.display = fuenteImagen === 'subida' && archivoSubido ? 'block' : 'none';
            canvas.style.display = fuenteImagen === 'plantilla' ? 'block' : 'none';
            if (fuenteImagen === 'plantilla') redibujar();
        });
    });

    // ── Selector de tipo de plantilla ──────────────────────────────────
    document.querySelectorAll('.tipo-plantilla').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tipo-plantilla').forEach((b) => { b.classList.remove('activa'); b.style.border = '1.5px solid #cbd5e1'; b.style.background = '#fff'; });
            btn.classList.add('activa');
            btn.style.border = '1.5px solid #2563eb'; btn.style.background = '#eff6ff';
            tipoActivo = btn.dataset.tipo;
            document.querySelectorAll('.campos-tipo').forEach((c) => c.style.display = c.dataset.para === tipoActivo ? 'block' : 'none');
            redibujar();
        });
    });

    // Redibujar en vivo con cualquier input dentro de "campos-tipo"
    document.querySelectorAll('.campos-tipo input, .campos-tipo textarea, .campos-tipo select').forEach((el) => {
        el.addEventListener('input', redibujar);
    });

    document.getElementById('ppPlan').addEventListener('change', function () {
        if (!this.value) return;
        const [nombre, valor] = this.value.split('|');
        document.getElementById('ppNombrePlan').value = nombre;
        document.getElementById('ppValor').value = valor;
        redibujar();
    });

    // ── Subida directa ──────────────────────────────────────────────
    document.getElementById('fArchivoSubido').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        archivoSubido = file;
        const url = URL.createObjectURL(file);
        document.getElementById('previewSubidaImgTag').src = url;
        document.getElementById('previewSubidaImg').style.display = 'block';
    });

    // ── Generación de imagen con IA (Gemini) ────────────────────────
    const btnGenImg = document.getElementById('btnGenerarImagenIa');
    if (btnGenImg) {
        btnGenImg.addEventListener('click', function () {
            const prompt = document.getElementById('iaPrompt').value.trim();
            if (!prompt) return;
            btnGenImg.disabled = true;
            btnGenImg.textContent = '⏳ Generando...';

            fetch(@json(route('admin.publicidad.generar_imagen')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ prompt }),
            })
            .then((r) => r.json())
            .then((data) => {
                const cont = document.getElementById('resultadosImagenIa');
                cont.innerHTML = '';
                if (!data.ok) {
                    mostrarAlerta(data.error || 'No se pudo generar la imagen.');
                    return;
                }
                data.urls.forEach((url, i) => {
                    const img = document.createElement('img');
                    img.src = url;
                    img.style = 'width:100px;height:100px;object-fit:cover;border-radius:8px;cursor:pointer;border:2px solid transparent;';
                    img.addEventListener('click', () => {
                        cont.querySelectorAll('img').forEach((im) => im.style.borderColor = 'transparent');
                        img.style.borderColor = '#2563eb';
                        imagenIaSeleccionada = { path: data.rutas[i], url };
                    });
                    cont.appendChild(img);
                });
            })
            .catch(() => mostrarAlerta('Error de conexión al generar la imagen.'))
            .finally(() => { btnGenImg.disabled = false; btnGenImg.textContent = '✨ Generar imágenes'; });
        });
    }

    // ── Generación de copy con IA ────────────────────────────────────
    const btnGenCopia = document.getElementById('btnGenerarCopia');
    if (btnGenCopia) {
        btnGenCopia.addEventListener('click', function () {
            const tipo = document.getElementById('tipoPiezaIa').value.trim() || 'promo';
            const contexto = document.getElementById('contextoIa').value.trim() || tipoActivo;
            btnGenCopia.disabled = true;
            btnGenCopia.textContent = '⏳ Generando...';

            fetch(@json(route('admin.publicidad.generar_copia')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ tipo_pieza: tipo, contexto }),
            })
            .then((r) => r.json())
            .then((data) => {
                const cont = document.getElementById('variantesCopia');
                cont.innerHTML = '';
                if (!data.ok) {
                    mostrarAlerta(data.error || 'No se pudo generar el texto.');
                    return;
                }
                cont.style.display = 'flex';
                data.variantes.forEach((v) => {
                    const div = document.createElement('div');
                    div.textContent = v;
                    div.style = 'background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:0.5rem 0.7rem;font-size:0.78rem;cursor:pointer;';
                    div.addEventListener('click', () => { document.getElementById('fCopy').value = v; });
                    cont.appendChild(div);
                });
            })
            .catch(() => mostrarAlerta('Error de conexión al generar el texto.'))
            .finally(() => { btnGenCopia.disabled = false; btnGenCopia.textContent = '✨ Generar con IA'; });
        });
    }

    function mostrarAlerta(msg) {
        const el = document.getElementById('alertaJs');
        el.textContent = '❌ ' + msg;
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // ── Envío final ───────────────────────────────────────────────────
    document.getElementById('btnEnviar').addEventListener('click', function () {
        const btn = this;
        const titulo = document.getElementById('fTitulo').value.trim();
        if (!titulo) { mostrarAlerta('El título es obligatorio.'); return; }

        const destinos = ['web'];
        if (document.getElementById('destFacebook').checked) destinos.push('facebook');
        if (document.getElementById('destInstagram').checked) destinos.push('instagram');

        const formData = new FormData();
        formData.append('titulo', titulo);
        formData.append('copy', document.getElementById('fCopy').value);
        destinos.forEach((d) => formData.append('destinos[]', d));
        const programada = document.getElementById('fProgramada').value;
        if (programada) formData.append('programada_at', programada);

        function enviar(origen, plantillaUsada) {
            formData.append('origen', origen);
            if (plantillaUsada) formData.append('plantilla_usada', plantillaUsada);

            btn.disabled = true;
            btn.textContent = 'Enviando...';

            fetch(@json(route('admin.publicidad.store')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData,
            })
            .then((r) => {
                if (r.redirected) { window.location.href = r.url; return; }
                return r.json().then((data) => {
                    const primerError = data.errors ? Object.values(data.errors)[0][0] : 'No se pudo guardar la pieza.';
                    mostrarAlerta(primerError);
                    btn.disabled = false;
                    btn.textContent = 'Enviar a aprobación';
                });
            })
            .catch(() => {
                mostrarAlerta('Error de conexión al guardar.');
                btn.disabled = false;
                btn.textContent = 'Enviar a aprobación';
            });
        }

        if (fuenteImagen === 'plantilla') {
            canvas.toBlob((blob) => {
                formData.append('imagen', blob, 'plantilla.png');
                enviar('plantilla', tipoActivo);
            }, 'image/png');
        } else if (fuenteImagen === 'subida') {
            if (!archivoSubido) { mostrarAlerta('Sube una imagen primero.'); return; }
            formData.append('imagen', archivoSubido);
            enviar('subida', null);
        } else if (fuenteImagen === 'ia') {
            if (!imagenIaSeleccionada) { mostrarAlerta('Genera y selecciona una imagen primero.'); return; }
            formData.append('imagen_path_generado', imagenIaSeleccionada.path);
            enviar('ia', null);
        }
    });

    redibujar();
})();
</script>
@endpush
