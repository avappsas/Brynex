#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
generar_plano_asopagos.py
Genera archivo plano TXT de ancho fijo para Asopagos
desde el Excel exportado por BryNex (hoja activa, datos desde fila 2).

Uso:
    python generar_plano_asopagos.py archivo.xlsx

Salida:
    archivo_plano_asopagos.txt
    reporte_validacion.txt
"""

import sys
import re
import unicodedata
from pathlib import Path
from datetime import datetime

try:
    import openpyxl
except ImportError:
    sys.exit("ERROR: instala openpyxl ->  pip install openpyxl")

# ── Estructura oficial del instructivo ──────────────────────────────────────
# Suma total: 609 caracteres  (el operador pide 693 → delta = 84 sin justificacion)
ESTRUCTURA = [
    {"col": 0,  "campo": "TIPO DOCUMENTO",                            "tipo": "A", "lon": 2,  "oblig": "X"},
    {"col": 1,  "campo": "NUMERO DOCUMENTO",                          "tipo": "A", "lon": 16, "oblig": "X"},
    {"col": 2,  "campo": "TIPO COTIZANTE",                            "tipo": "N", "lon": 2,  "oblig": "X"},
    {"col": 3,  "campo": "SUBTIPO DE COTIZANTE",                      "tipo": "N", "lon": 2,  "oblig": "X"},
    {"col": 4,  "campo": "EXTRANJERO NO OBLIGADO A COTIZAR PENSION",  "tipo": "A", "lon": 1,  "oblig": ""},
    {"col": 5,  "campo": "COLOMBIANO EN EL EXTERIOR",                 "tipo": "A", "lon": 1,  "oblig": ""},
    {"col": 6,  "campo": "FECHA DE RADICACION EN EL EXTERIOR",        "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 7,  "campo": "EXONERADO",                                 "tipo": "A", "lon": 1,  "oblig": "X"},
    {"col": 8,  "campo": "CODIGO DE DEPARTAMENTO",                    "tipo": "A", "lon": 2,  "oblig": "C"},
    {"col": 9,  "campo": "CODIGO DE MUNICIPIO",                       "tipo": "A", "lon": 3,  "oblig": "C"},
    {"col": 10, "campo": "PRIMER APELLIDO",                           "tipo": "A", "lon": 20, "oblig": "X"},
    {"col": 11, "campo": "SEGUNDO APELLIDO",                          "tipo": "A", "lon": 30, "oblig": ""},
    {"col": 12, "campo": "PRIMER NOMBRE",                             "tipo": "A", "lon": 20, "oblig": "X"},
    {"col": 13, "campo": "SEGUNDO NOMBRE",                            "tipo": "A", "lon": 30, "oblig": ""},
    {"col": 14, "campo": "SALARIO BASICO",                            "tipo": "N", "lon": 9,  "oblig": "X"},
    {"col": 15, "campo": "SALARIO INTEGRAL",                          "tipo": "A", "lon": 1,  "oblig": ""},
    {"col": 16, "campo": "ING",                                       "tipo": "A", "lon": 1,  "oblig": ""},
    {"col": 17, "campo": "FECHA INGRESO",                             "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 18, "campo": "RET",                                       "tipo": "A", "lon": 1,  "oblig": ""},
    {"col": 19, "campo": "FECHA RETIRO",                              "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 20, "campo": "TDE",                                       "tipo": "A", "lon": 1,  "oblig": ""},
    {"col": 21, "campo": "TAE",                                       "tipo": "A", "lon": 1,  "oblig": ""},
    {"col": 22, "campo": "EPS A LA QUE SE TRASLADA",                  "tipo": "A", "lon": 6,  "oblig": "C"},
    {"col": 23, "campo": "TDP",                                       "tipo": "A", "lon": 1,  "oblig": ""},
    {"col": 24, "campo": "TAP",                                       "tipo": "A", "lon": 1,  "oblig": ""},
    {"col": 25, "campo": "AFP A LA QUE SE TRASLADA",                  "tipo": "A", "lon": 6,  "oblig": "C"},
    {"col": 26, "campo": "VSP",                                       "tipo": "A", "lon": 1,  "oblig": ""},
    {"col": 27, "campo": "FECHA INICIO VSP",                          "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 28, "campo": "VST",                                       "tipo": "A", "lon": 1,  "oblig": "C"},
    {"col": 29, "campo": "SLN",                                       "tipo": "A", "lon": 1,  "oblig": "C"},
    {"col": 30, "campo": "FECHA INICIO SLN",                          "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 31, "campo": "FECHA FIN SLN",                             "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 32, "campo": "IGE",                                       "tipo": "A", "lon": 1,  "oblig": ""},
    {"col": 33, "campo": "FECHA INICIO IGE",                          "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 34, "campo": "FECHA FIN IGE",                             "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 35, "campo": "LMA",                                       "tipo": "A", "lon": 1,  "oblig": "C"},
    {"col": 36, "campo": "FECHA INICIO LMA",                          "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 37, "campo": "FECHA FIN LMA",                             "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 38, "campo": "VAC",                                       "tipo": "A", "lon": 1,  "oblig": "C"},
    {"col": 39, "campo": "FECHA INICIO VAC-LR",                       "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 40, "campo": "FECHA FIN VAC-LR",                          "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 41, "campo": "VCT",                                       "tipo": "A", "lon": 1,  "oblig": "C"},
    {"col": 42, "campo": "FECHA INICIO VCT",                          "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 43, "campo": "FECHA FIN VCT",                             "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 44, "campo": "IRL",                                       "tipo": "N", "lon": 2,  "oblig": ""},
    {"col": 45, "campo": "FECHA INICIO IRL",                          "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 46, "campo": "FECHA FIN IRL",                             "tipo": "A", "lon": 10, "oblig": ""},
    {"col": 47, "campo": "EPS",                                       "tipo": "A", "lon": 6,  "oblig": "X"},
    {"col": 48, "campo": "DIAS COTIZADOS SALUD",                      "tipo": "N", "lon": 2,  "oblig": "X"},
    {"col": 49, "campo": "IBC SALUD",                                 "tipo": "N", "lon": 9,  "oblig": "X"},
    {"col": 50, "campo": "TARIFA SALUD",                              "tipo": "N", "lon": 7,  "oblig": ""},
    {"col": 51, "campo": "COTIZACION EPS",                            "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 52, "campo": "VALOR UPC",                                 "tipo": "N", "lon": 9,  "oblig": "C"},
    {"col": 53, "campo": "TIPO DOCUMENTO COTIZANTE PRINCIPAL UPC",    "tipo": "A", "lon": 2,  "oblig": "C"},
    {"col": 54, "campo": "NUMERO DOCUMENTO COTIZANTE PRINCIPAL UPC",  "tipo": "A", "lon": 16, "oblig": "C"},
    {"col": 55, "campo": "INDICADOR TARIFA ESPECIAL",                 "tipo": "A", "lon": 1,  "oblig": ""},
    {"col": 56, "campo": "AFP",                                       "tipo": "A", "lon": 6,  "oblig": "X"},
    {"col": 57, "campo": "DIAS COTIZADOS PENSION",                    "tipo": "N", "lon": 2,  "oblig": "X"},
    {"col": 58, "campo": "IBC PENSION",                               "tipo": "N", "lon": 9,  "oblig": "X"},
    {"col": 59, "campo": "TARIFA PENSION",                            "tipo": "N", "lon": 7,  "oblig": ""},
    {"col": 60, "campo": "COTIZACION AFP",                            "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 61, "campo": "APORTE VOLUNTARIO AFILIADO",                "tipo": "N", "lon": 9,  "oblig": "C"},
    {"col": 62, "campo": "APORTE VOLUNTARIO APORTANTE",               "tipo": "N", "lon": 9,  "oblig": "C"},
    {"col": 63, "campo": "VALOR NO RETENIDO",                         "tipo": "N", "lon": 9,  "oblig": "C"},
    {"col": 64, "campo": "ARL AFILIADO",                              "tipo": "A", "lon": 6,  "oblig": ""},
    {"col": 65, "campo": "DIAS COTIZADOS RIESGOS",                    "tipo": "N", "lon": 2,  "oblig": ""},
    {"col": 66, "campo": "IBC RIESGOS",                               "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 67, "campo": "CENTRO DE TRABAJO",                         "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 68, "campo": "CLASE DE RIESGO",                           "tipo": "A", "lon": 1,  "oblig": "C"},
    {"col": 69, "campo": "TARIFA RIESGO",                             "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 70, "campo": "COTIZACION ARL",                            "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 71, "campo": "CCF",                                       "tipo": "A", "lon": 6,  "oblig": ""},
    {"col": 72, "campo": "DIAS CCF",                                  "tipo": "N", "lon": 2,  "oblig": ""},
    {"col": 73, "campo": "IBC CCF",                                   "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 74, "campo": "TARIFA CCF",                                "tipo": "N", "lon": 7,  "oblig": ""},
    {"col": 75, "campo": "COTIZACION CCF",                            "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 76, "campo": "IBC OTROS PARAFISCALES",                    "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 77, "campo": "TARIFA SENA",                               "tipo": "N", "lon": 7,  "oblig": ""},
    {"col": 78, "campo": "COTIZACION SENA",                           "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 79, "campo": "TARIFA ICBF",                               "tipo": "N", "lon": 7,  "oblig": ""},
    {"col": 80, "campo": "COTIZACION ICBF",                           "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 81, "campo": "TARIFA ESAP",                               "tipo": "N", "lon": 7,  "oblig": ""},
    {"col": 82, "campo": "COTIZACION ESAP",                           "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 83, "campo": "TARIFA MIN",                                "tipo": "N", "lon": 7,  "oblig": ""},
    {"col": 84, "campo": "COTIZACION MIN",                            "tipo": "N", "lon": 9,  "oblig": ""},
    {"col": 85, "campo": "HORAS LABORADAS",                           "tipo": "N", "lon": 3,  "oblig": ""},
    {"col": 86, "campo": "ACTIVIDAD ECONOMICA",                       "tipo": "N", "lon": 7,  "oblig": "C"},
]

LONGITUD_SPEC    = sum(f["lon"] for f in ESTRUCTURA)   # debe ser 609
LONGITUD_OPERADOR = 693
DELTA             = LONGITUD_OPERADOR - LONGITUD_SPEC


# ── Helpers ─────────────────────────────────────────────────────────────────

def quitar_acentos(texto: str) -> str:
    return "".join(
        c for c in unicodedata.normalize("NFD", texto)
        if unicodedata.category(c) != "Mn"
    )

def limpiar_valor(valor) -> str:
    if valor is None:
        return ""
    s = str(valor).strip()
    s = s.replace("\n", " ").replace("\r", " ")
    return s

def normalizar_texto(valor, longitud: int) -> tuple[str, str | None]:
    """Alfanumérico: mayúsculas, sin acentos, relleno con espacios a derecha."""
    s = quitar_acentos(limpiar_valor(valor)).upper()
    s = re.sub(r"[^A-Z0-9 \-./]", "", s)
    if len(s) > longitud:
        return s[:longitud], f"truncado de {len(s)} a {longitud}"
    return s.ljust(longitud), None

def normalizar_numero(valor, longitud: int) -> tuple[str, str | None]:
    """Numérico: quitar puntos/comas/pesos, rellenar con ceros a izquierda."""
    s = limpiar_valor(valor)
    s = re.sub(r"[.,\s$]", "", s)
    # Si tiene punto decimal (ej 0.04000), convertir a representación entera
    if "." in s:
        try:
            f = float(s)
            s = str(int(round(f * (10 ** (longitud - 1)))) if f < 1 and f > 0 else int(f))
        except ValueError:
            s = re.sub(r"\.", "", s)
    s = re.sub(r"[^0-9]", "", s)
    if len(s) > longitud:
        return s[:longitud], f"truncado de {len(s)} a {longitud}"
    return s.zfill(longitud), None

def normalizar_fecha(valor) -> tuple[str, str | None]:
    """Fecha → AAAA-MM-DD (10 chars). Vacío → 10 espacios."""
    s = limpiar_valor(valor)
    if not s:
        return " " * 10, None
    # intentar varios formatos
    for fmt in ("%Y-%m-%d", "%d/%m/%Y", "%d-%m-%Y", "%Y%m%d"):
        try:
            d = datetime.strptime(s[:10], fmt)
            return d.strftime("%Y-%m-%d"), None
        except ValueError:
            continue
    return s[:10].ljust(10), f"fecha no reconocida: {s}"

def formatear_campo(valor, cfg: dict) -> tuple[str, list[str]]:
    errores = []
    campo   = cfg["campo"]
    lon     = cfg["lon"]
    tipo    = cfg["tipo"]
    oblig   = cfg["oblig"]
    s_raw   = limpiar_valor(valor)

    # Obligatorio vacío
    if oblig == "X" and not s_raw:
        errores.append(f"{campo}: OBLIGATORIO vacío")

    # Fechas
    if "FECHA" in campo.upper() and tipo == "A" and lon == 10:
        resultado, err = normalizar_fecha(s_raw)
        if err:
            errores.append(f"{campo}: {err}")
        return resultado, errores

    if tipo == "N":
        resultado, err = normalizar_numero(s_raw, lon)
    else:
        resultado, err = normalizar_texto(s_raw, lon)

    if err:
        errores.append(f"{campo}: {err}")

    return resultado, errores


def generar_linea(fila_valores: list, estructura: list) -> tuple[str, list[str]]:
    partes  = []
    errores = []
    tiene_arl = False

    for cfg in estructura:
        col_idx = cfg["col"]
        valor   = fila_valores[col_idx] if col_idx < len(fila_valores) else ""
        parte, errs = formatear_campo(valor, cfg)
        partes.append(parte)
        errores.extend(errs)

        # Detectar si hay aporte ARL (para validar ACTIVIDAD ECONÓMICA)
        if cfg["campo"] in ("ARL AFILIADO", "COTIZACION ARL") and parte.strip():
            tiene_arl = True

    # Validar ACTIVIDAD ECONÓMICA si hay ARL
    if tiene_arl:
        act_eco_cfg = next((c for c in estructura if c["campo"] == "ACTIVIDAD ECONOMICA"), None)
        if act_eco_cfg:
            idx = act_eco_cfg["col"]
            val = limpiar_valor(fila_valores[idx] if idx < len(fila_valores) else "")
            if not val or val == "0":
                errores.append("ACTIVIDAD ECONOMICA: obligatoria cuando hay aporte a riesgos (ARL)")

    return "".join(partes), errores


def validar_linea(linea: str) -> dict:
    lon = len(linea)
    return {
        "longitud"      : lon,
        "es_spec"       : lon == LONGITUD_SPEC,
        "es_operador"   : lon == LONGITUD_OPERADOR,
        "delta_operador": LONGITUD_OPERADOR - lon,
    }


def leer_excel(ruta: str):
    wb = openpyxl.load_workbook(ruta, data_only=True)
    ws = wb.active
    filas = []
    for fila in ws.iter_rows(min_row=2, values_only=True):
        if any(c is not None for c in fila):
            filas.append(list(fila))
    return filas


def generar_txt(ruta_excel: str):
    base        = Path(ruta_excel).stem
    ruta_txt    = Path(ruta_excel).parent / f"{base}_plano_asopagos.txt"
    ruta_rep    = Path(ruta_excel).parent / f"{base}_reporte_validacion.txt"

    filas       = leer_excel(ruta_excel)
    lineas_txt  = []
    reporte     = []

    reporte.append("=" * 70)
    reporte.append("REPORTE DE VALIDACION - ASOPAGOS")
    reporte.append(f"Archivo: {ruta_excel}")
    reporte.append(f"Fecha  : {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    reporte.append(f"Longitud segun instructivo (spec): {LONGITUD_SPEC} chars")
    reporte.append(f"Longitud esperada por operador   : {LONGITUD_OPERADOR} chars")
    reporte.append(f"Delta sin justificacion oficial  : {DELTA} chars")
    if DELTA != 0:
        reporte.append(
            f"ADVERTENCIA: Con los {len(ESTRUCTURA)} campos del instructivo "
            f"({LONGITUD_SPEC} chars) NO se alcanza la longitud del operador "
            f"({LONGITUD_OPERADOR}). Faltan {DELTA} chars de estructura oficial "
            f"no documentada. NO se agregan rellenos arbitrarios."
        )
    reporte.append("=" * 70)
    reporte.append("")

    total_errores = 0

    for i, fila in enumerate(filas):
        fila_excel = i + 2   # fila 2 en adelante en el Excel
        doc = limpiar_valor(fila[1]) if len(fila) > 1 else "?"

        linea, errores = generar_linea(fila, ESTRUCTURA)
        val            = validar_linea(linea)

        lineas_txt.append(linea)

        estado_spec     = "OK" if val["es_spec"] else f"ERROR({val['longitud']})"
        estado_operador = "OK" if val["es_operador"] else f"NO({val['longitud']}, delta={val['delta_operador']})"

        reporte.append(f"Fila Excel {fila_excel} | Doc: {doc}")
        reporte.append(f"  Longitud generada : {val['longitud']}")
        reporte.append(f"  Mide {LONGITUD_SPEC}  (spec)     : {estado_spec}")
        reporte.append(f"  Mide {LONGITUD_OPERADOR} (operador) : {estado_operador}")

        if errores:
            total_errores += len(errores)
            for err in errores:
                reporte.append(f"  ⚠  {err}")
        else:
            reporte.append("  Sin errores de campo.")

        reporte.append("")

    # Resumen
    reporte.append("=" * 70)
    reporte.append(f"RESUMEN: {len(filas)} cotizantes | {total_errores} errores/advertencias")
    reporte.append(f"Longitud por linea generada: {LONGITUD_SPEC}")
    if LONGITUD_SPEC != LONGITUD_OPERADOR:
        reporte.append(
            f"CONCLUSION: El instructivo suministrado define {LONGITUD_SPEC} chars. "
            f"El operador Asopagos pide {LONGITUD_OPERADOR} chars. "
            f"Los {DELTA} chars restantes no estan definidos en el instructivo oficial "
            f"y NO se pueden agregar sin documentacion formal del operador."
        )
    reporte.append("=" * 70)

    # Escribir TXT
    with open(ruta_txt, "w", encoding="utf-8") as f:
        f.write("\n".join(lineas_txt))

    # Escribir reporte
    with open(ruta_rep, "w", encoding="utf-8") as f:
        f.write("\n".join(reporte))

    print("\n".join(reporte))
    print(f"\nArchivo TXT  : {ruta_txt}")
    print(f"Reporte      : {ruta_rep}")


if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.exit(f"Uso: python {Path(__file__).name} archivo.xlsx")
    generar_txt(sys.argv[1])
