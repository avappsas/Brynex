import { createRequire } from 'node:module';

// pdf-parse es CommonJS y su index.js corre un bloque de prueba que lee un PDF
// de ejemplo al importarlo por el paquete. Se entra directo al módulo real.
const require = createRequire(import.meta.url);
const pdfParse = require('pdf-parse/lib/pdf-parse.js');

/**
 * Una fila de la tabla "Periodos Compensados" viene concatenada sin separadores:
 *
 *   Suramericana06/202630COTIZANTEPago con cotización
 *   └─ eps ────┘└ per ─┘└d┘└ tipo ┘└─ observación ──┘
 *
 * Se ancla en el período (año de exactamente 4 dígitos, si no se comería el
 * primer dígito de los días). Para separar tipo de observación no basta con
 * "mayúsculas sostenidas": un cuantificador voraz se lleva la P de "Pago". Se
 * corta en el punto donde una mayúscula va seguida de minúscula, que es donde
 * arranca la observación.
 */
const RE_FILA_CONCATENADA =
    /^(?<eps>.+?)(?<periodo>(?:0[1-9]|1[0-2])\/(?:19|20)\d{2})(?<dias>\d{1,3})(?<tipo>[A-ZÑÁÉÍÓÚ ]{4,}?)(?<obs>[A-ZÑÁÉÍÓÚ][a-zñáéíóú].*)$/;

// Respaldo por si alguna observación llegara en mayúsculas sostenidas y la
// anterior no enganchara: se acepta cualquier cosa como observación.
const RE_FILA_CONCATENADA_LAXA =
    /^(?<eps>.+?)(?<periodo>(?:0[1-9]|1[0-2])\/(?:19|20)\d{2})(?<dias>\d{1,3})(?<tipo>[A-ZÑÁÉÍÓÚ ]{4,})(?<obs>.*)$/;

const RE_PERIODO_SOLO = /^(0[1-9]|1[0-2])\/((?:19|20)\d{2})$/;

const armarFila = ({ eps, periodo, dias, tipo, obs }) => {
    const [mes, anio] = periodo.split('/');
    return {
        eps: (eps || '').trim() || null,
        periodo,
        anio: Number(anio),
        mes: Number(mes),
        dias: Number(dias),
        tipo_afiliado: (tipo || '').trim() || null,
        observacion: (obs || '').trim() || null,
    };
};

/** Formato de pdf-parse: una fila por línea, todo pegado. */
function porLineaConcatenada(lineas) {
    const filas = [];
    for (const linea of lineas) {
        const m = linea.match(RE_FILA_CONCATENADA) || linea.match(RE_FILA_CONCATENADA_LAXA);
        if (m) filas.push(armarFila(m.groups));
    }
    return filas;
}

/**
 * Respaldo: si algún día la extracción vuelve a partir cada celda en su propia
 * línea (así lo hace pypdf hoy), se ancla igual en el período y se leen los
 * vecinos. Los encabezados y pies que se cuelan entre páginas quedan fuera solos
 * porque no tienen un número de días detrás.
 */
function porCeldaSuelta(lineas) {
    const filas = [];
    for (let i = 1; i < lineas.length - 3; i++) {
        const m = lineas[i].match(RE_PERIODO_SOLO);
        if (!m) continue;
        if (!/^\d{1,3}$/.test(lineas[i + 1])) continue;

        filas.push(
            armarFila({
                eps: lineas[i - 1],
                periodo: lineas[i],
                dias: lineas[i + 1],
                tipo: lineas[i + 2],
                obs: lineas[i + 3],
            })
        );
    }
    return filas;
}

export function extraerFilas(textoPlano) {
    const lineas = textoPlano.split('\n').map((l) => l.trim());
    const filas = porLineaConcatenada(lineas);
    return filas.length ? filas : porCeldaSuelta(lineas);
}

export async function leerReporte(buffer) {
    const { text, numpages } = await pdfParse(buffer);
    const filas = extraerFilas(text);

    return {
        paginas: numpages,
        filas,
        total_filas: filas.length,
    };
}
