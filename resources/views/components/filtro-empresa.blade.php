{{--
    Filtro de empresa del cliente para las barras de filtros.

    Es un buscador y no un <select>: se escribe parte del nombre y la lista se
    va reduciendo. La lista la arma cada controlador con SOLO las empresas que
    tienen registros en esa pantalla, para no ofrecer opciones que siempre
    devolverían la tabla vacía.

    Parámetros:
      $empresas     colección de {id, empresa} (obligatorio)
      $formId       id del <form> que se envía al elegir (por defecto filtrosForm)
      $ancho        ancho del campo de texto
      $estiloInput  estilos extra del campo, para calzar con cada barra
--}}
@php
    $empresasFiltro = $empresas ?? collect();
    $formIdFiltro   = $formId ?? 'filtrosForm';
    $anchoFiltro    = $ancho ?? '190px';
    $empresaSel     = $empresasFiltro->firstWhere('id', request('empresa_id'));
@endphp
<div id="empresaBox" style="position:relative;">
    <input type="hidden" name="empresa_id" id="empresaId" value="{{ request('empresa_id') }}">
    <input type="text" id="empresaBuscar" autocomplete="off"
           value="{{ $empresaSel->empresa ?? '' }}"
           placeholder="🏢 Empresa..."
           style="width:{{ $anchoFiltro }}; {{ $estiloInput ?? '' }}">
    <div id="empresaLista" style="display:none; position:absolute; z-index:60; top:30px; left:0; min-width:260px; max-height:240px; overflow-y:auto; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 6px 18px rgba(15,23,42,.12);">
        <div class="emp-opt" data-id="" data-nombre="" style="padding:.35rem .6rem; font-size:.75rem; cursor:pointer; color:#64748b; border-bottom:1px solid #f1f5f9;">🏢 Todas las empresas</div>
        @foreach($empresasFiltro as $e)
            <div class="emp-opt" data-id="{{ $e->id }}" data-nombre="{{ $e->empresa }}" style="padding:.35rem .6rem; font-size:.75rem; cursor:pointer;">{{ $e->empresa }}</div>
        @endforeach
        <div id="empresaVacio" style="display:none; padding:.4rem .6rem; font-size:.72rem; color:#94a3b8;">Sin coincidencias</div>
    </div>
</div>
<script>
(function () {
    const box = document.getElementById('empresaBox');
    if (!box) return;
    const input  = document.getElementById('empresaBuscar');
    const hidden = document.getElementById('empresaId');
    const lista  = document.getElementById('empresaLista');
    const vacio  = document.getElementById('empresaVacio');
    const opts   = Array.from(lista.querySelectorAll('.emp-opt'));
    const form   = document.getElementById('{{ $formIdFiltro }}');
    const nombreSel = input.value; // empresa filtrada actualmente, para restaurar

    // Sin tildes y en minúscula: "peña" y "pena" deben encontrar lo mismo.
    const norm = t => (t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    function filtrar() {
        const q = norm(input.value);
        let visibles = 0;
        opts.forEach(o => {
            const esTodas = o.dataset.id === '';
            const ok = esTodas || norm(o.dataset.nombre).includes(q);
            o.style.display = ok ? '' : 'none';
            if (ok && !esTodas) visibles++;
        });
        vacio.style.display = visibles ? 'none' : '';
        lista.style.display = '';
    }

    function elegir(id, nombre) {
        // Si no cambió nada, no se recarga la página por gusto.
        if ((hidden.value || '') === (id || '')) { lista.style.display = 'none'; return; }
        hidden.value = id;
        input.value  = nombre;
        form.submit();
    }

    input.addEventListener('input', filtrar);
    input.addEventListener('focus', filtrar);
    opts.forEach(o => o.addEventListener('mousedown', e => {
        e.preventDefault();
        elegir(o.dataset.id, o.dataset.nombre);
    }));
    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') { lista.style.display = 'none'; }
        if (e.key === 'Enter') {
            e.preventDefault();
            const visible = opts.find(o => o.dataset.id && o.style.display !== 'none');
            if (visible) elegir(visible.dataset.id, visible.dataset.nombre);
        }
    });
    input.addEventListener('blur', () => setTimeout(() => {
        lista.style.display = 'none';
        if (!input.value.trim()) {
            // Texto borrado a mano con un filtro puesto = quitar el filtro.
            if (hidden.value) elegir('', '');
        } else if (input.value !== nombreSel) {
            // Se escribió sin elegir nada: se devuelve lo que sí está filtrado
            // para no dejar en pantalla un nombre que no corresponde.
            input.value = nombreSel;
        }
    }, 150));
    opts.forEach(o => {
        o.addEventListener('mouseenter', () => o.style.background = '#f1f5f9');
        o.addEventListener('mouseleave', () => o.style.background = '');
    });
})();
</script>
