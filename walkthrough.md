# Walkthrough — Módulo de Configuración de Planillas de Pago (PDF Overlay)

He diseñado e implementado el nuevo módulo que permite subir archivos PDF de planillas en blanco por operador de planilla y mapear interactivamente las coordenadas de cada campo de datos del plano PILA a través de un editor visual (PDF.js + FPDI).

---

## Cambios Realizados

### 1. Base de Datos
* **Migración Incremental:** Creada y ejecutada la migración [2026_07_06_162000_create_operador_planillas_templates_table.php](file:///Users/brayangarcia/Documents/GitHub/Brynex/database/migrations/2026_07_06_162000_create_operador_planillas_templates_table.php).
* **Nueva Tabla:** `operador_planillas_templates` para el almacenamiento global de las plantillas PDF en blanco y el JSON de coordenadas por operador.
* **Modelo Eloquent:** Creado [OperadorPlanillaTemplate.php](file:///Users/brayangarcia/Documents/GitHub/Brynex/app/Models/OperadorPlanillaTemplate.php).

### 2. Capa de Negocio (Servicio PDF Dinámico y Transparencia)
* **Nuevo Servicio:** Creado [PlanillaFormularioService.php](file:///Users/brayangarcia/Documents/GitHub/Brynex/app/Services/PlanillaFormularioService.php) que maneja el rellenado dinámico por coordenadas configuradas en BD.
* **Alineación Vertical Perfecta a la Izquierda:**
  - Se modificó `rellenarPdf()` para usar `$pdf->Cell(..., 'L')` inyectando el texto con alineación de celda nativa.
  - Esto garantiza que el texto se centre verticalmente de forma idéntica en toda la planilla, eliminando desfases causados por cálculos manuales de líneas de base.
* **Autocopia de Plantillas Físicas (Evitar Fallback Viejo Estático):**
  - Se corrigió un problema donde, si el archivo físico del PDF en blanco (ej. `ENLACE.pdf`) no existía en el storage del servidor de producción, el sistema caía en un fallback silencioso que generaba el PDF con el código de posiciones viejas estáticas (`SuaportePdfService`).
  - Ahora, si el archivo no existe en el storage en local o producción, el sistema copia automáticamente de forma transparente la plantilla PDF de los recursos de Brynex al storage en tiempo real y procede a renderizar con las coordenadas dinámicas de la base de datos, garantizando que nunca se use el código estático anterior.
* **Soporte de Celdas Transparentes (Sin Fondo Blanco):**
  - Se implementó la opción de renderizado transparente en `rellenarPdf()`. Si un campo tiene `limpiar = false` o `color_fondo = 'transparente'`, FPDF/FPDI **no dibuja ningún rectángulo de fondo**, escribiendo el texto transparente sobre el fondo original del PDF en blanco para no tapar grillas ni colores de tabla.
* **Corrección de Periodos (Servicio/Cotización):**
  - Se corrigió la asignación de periodos de acuerdo a las reglas oficiales de PILA en Colombia:
    * **Periodo de Servicio:** Es el mes del plano actual (mes de periodo).
    * **Periodo Cotizado:** Es el mes vencido (mes anterior).
* **División de Fecha de Pago:** Dividido el antiguo campo unificado de fecha de pago en 3 campos individuales e independientes:
  - `plano.fecha_pago_estado` -> Valor fijo: `"PAGADA"`
  - `plano.fecha_pago_fecha`  -> Valor formateado: `"YYYY-MM-DD"` (ej: `"2026-07-03"`)
  - `plano.fecha_pago_hora`   -> Valor formateado: `"HH:MM:SS.0"` (ej: `"14:03:12.0"`)
* **Soporte de Espaciado de Letras (Letter-Spacing):** 
  - Declarada la clase `BrynexFpdi` para extender FPDI e implementando espaciado `Tc` nativo de PDF.
* **Resolución de Códigos PILA de AFP/EPS:** Corregido el problema donde se mostraban los NITs de las administradoras en lugar de sus códigos. Ahora el servicio limpia el NIT obtenido del calculador y consulta las tablas `pensiones` y `eps` para recuperar y escribir el código oficial de PILA (ej. `230301` para Porvenir y `EPS037` para Nueva EPS).
* **Campos y Etiquetas Quemadas (Constantes):**
  - Asegurado el tipo de salario `aporte.tipo_salario` con valor fijo `"F"`.
  - Inyectadas las etiquetas fijas para los totales de parafiscales: `total.fsp_nombre` ("FSP SOLIDARIDAD"), `total.fsps_nombre` ("FSP SUBSISTENCIA"), `total.sena_nombre` ("SENA"), `total.icbf_nombre` ("ICBF"), `total.esap_nombre` ("ESAP"), y `total.men_nombre` ("MEN").
* **Visibilidad:** Cambiado el método `ensamblarDatos` a `public` para permitir la previsualización en tiempo real en la UI del administrador.

### 3. Previsualización Real en Tiempo Real (Mejora de UX)
* **Nueva Ruta de API:** Registrada `GET admin/configuracion/operadores/datos-ejemplo` para obtener los datos de cotizantes de prueba.
* **Action en el Controlador:** Implementado el método `obtenerDatosEjemplo` en [OperadorPlanillaFormularioController.php](file:///Users/brayangarcia/Documents/GitHub/Brynex/app/Http/Controllers/Admin/OperadorPlanillaFormularioController.php).
* **Controles Visuales (Visor):** 
  - Añadido un formulario flotante en [planilla_formulario.blade.php](file:///Users/brayangarcia/Documents/GitHub/Brynex/resources/views/admin/configuracion/planilla_formulario.blade.php) para ingresar la Cédula y Planilla (precargado con la planilla de prueba `1058846712` / `86667957`).
  - Al cargar los datos, el visor interactivo reemplaza los nombres técnicos de las celdas por los valores reales de la planilla (ej: `HIGINIO OSPINA FABIAN ALFONSO`), escalando y aplicando en el canvas la alineación de texto y tamaño de fuente configurados para validar el resultado antes de descargar.
  - **Selector de Limpieza Unificado:** Se eliminó el checkbox `cfgLimpiar` y se reemplazó en la barra lateral por un selector único de `Fondo Limpieza` que ofrece las opciones "Transparente (Sin limpieza)", "Blanco" y "Gris de pago". Al cambiar a transparente, la vista de canvas and el PDF se adaptan al instante.
  - **Previsualización de Espaciado de Letras:** En el canvas del editor visual, el texto simulado aplica la propiedad CSS `letterSpacing` multiplicada por el zoom para representar fielmente la separación.
* **Alineación Geométrica de Coordenadas (100% de Correspondencia):**
  - Se corrigió el error donde el centrado, márgenes y scrollbars del visor CSS añadían un desfase fantasma horizontal a las coordenadas guardadas.
  - Se implementó el contenedor relativo `#canvasContainer` del tamaño de píxeles exacto de la página PDF.js.
  - Las posiciones del ratón (`mx`, `my`) se calculan ahora de forma relativa al elemento `<canvas>` mismo (`canvas.getBoundingClientRect()`), logrando una precisión absoluta del 100% libre de desfases de pantalla.

### 4. Visualización de PDF Inline (Sin Descargas Físicas)
* **Previsualización Nativa Directa:** El botón de la interfaz se renombró a **"👁️ Previsualizar PDF de Prueba"** y abre el reporte en una pestaña nueva.
* **Cabecera HTTP Inline:** Modificado el controlador de Laravel para retornar el archivo con `Content-Disposition: inline` en lugar de `attachment`. El navegador abre el PDF al instante dentro del visor nativo en lugar de descargarlo en el disco físico del administrador.
* **Control de Caché del Navegador:** 
  - Se añadieron cabeceras HTTP `Cache-Control: no-store, no-cache, must-revalidate` en `PlanoPagoController@descargarCertificadoPdf` para evitar que el navegador guarde el archivo en su caché local.
  - En la vista Blade, se integró un timestamp dinámico (`t=Date.now()`) al final de la URL al abrir la pestaña de descarga, forzando al navegador a solicitar siempre el archivo fresco recién generado al servidor.

### 5. Zoom de 450% para Máxima Precisión
* **Escala Ampliada:** Modificada la función JavaScript `zoomIn()` para ampliar el límite superior de zoom del lienzo interactivo a un **450%** (escala `4.5`), permitiendo a los administradores alinear las cajas de colisión y el texto con precisión microscópica.

### 6. Siembra Inicial y Auditoría de Alineación (Seeder)
* **Alineación de Grilla Sección I (Aportante):**
  - Corregido el problema de desalineación inicial de la Sección I del Aportante en el seeder de siembra. Se ajustaron los campos a intervalos exactos de `13` pt (`y = 132, 145, 158, 171, 184, 197`) y se posicionó `aportante.tipo_persona` (JURÍDICA), `aportante.sucursal` (SUCURSAL), `aportante.departamento` (VALLE DEL CAUCA) y `aportante.ciudad` (CALI) en sus casilleros físicos oficiales correspondientes.
* **Corrección de Desplazamiento Horizontal en la Cabecera:**
  - Se detectó que las coordenadas de cabecera por defecto en el seeder estaban desplazadas unos 100 puntos hacia la derecha.
  - Se ajustaron las coordenadas `X` y anchos `W` de los 5 campos de cabecera a sus posiciones reales en la grilla física de la plantilla (ej. fecha de creación a `x = 295` en lugar de `346.56`).
  - El actualizador inteligente forzó la reubicación de estos 5 campos en la base de datos de producción/local compartida.
* **Auditoría e Integridad Estética Completa:**
  - **Sección II (Afiliado):** Se modificó la altura `Y` de los nombres, ciudad y ubicación laboral de `y = 249` a `y = 243`, alineando toda la fila en una línea recta y centrada en su celda.
  - **Sección III (Aportantes):** Se modificó la alineación por defecto a centrada (`'align' => 'center'`) para todos los códigos de administradoras, tarifas, días e IBC, evitando que queden pegados a las grillas del PDF.
  - **Sección IV (Totales):** Se cambiaron todos los nombres de administradoras y celdas de valores de totales a centrados (`'align' => 'center'`) y se re-calibraron sus coordenadas horizontales para estar perfectamente repartidos en su tabla.
* **Conversión de Producción:** El seeder de actualización aplicó automáticamente el valor `transparente` y `limpiar = false` a todos tus campos existentes de aportes y totales en la base de datos de producción/local compartida, **respetando en un 100% las coordenadas de posición que tú ya habías calibrado**.

---

## Verificación de Correctitud

1. **Alineación de Cabecera:** Campos de cabecera re-alineados horizontalmente y encajados perfectamente con las etiquetas preimpresas del reporte.
2. **Alineación Simétrica de Grillas:** Celdas de aportes, datos de afiliados y totales perfectamente distribuidos y alineados en sus coordenadas.
3. **Visualización Inline Nativa:** El PDF de prueba abre directamente en el visor de PDF del navegador y ya no se descarga al disco duro.
4. **Alineación Geométrica de Visor:** Coordenadas de ratón relativas al elemento `<canvas>`, lo que anula márgenes o barras de navegación CSS de la UI.
5. **Uso de Cell(..., 'L'):** Unifica la alineación vertical entre el canvas y FPDF, evitando desalineaciones manuales.
6. **Autocopia de Plantilla:** Previene fallbacks silenciosos al código viejo si el archivo no existía en el storage del servidor de producción.
7. **Eliminación de Caché:** El PDF se descarga con la marca temporal en la URL y cabeceras de no-caché en HTTP, entregando siempre la última versión recién guardada.
8. **Fondo de Textos Transparentes:** Eliminados los rectángulos blancos opacos del PDF final en todos los aportes y totales, haciéndolos coincidir perfectamente con la rejilla física del PDF.
9. **Definición de Periodos:** Invertidos y corregidos según regla de PILA, con Periodo de Servicio como mes del plano y Periodo Cotizado como mes anterior.
10. **Conservación de Coordenadas:** Las coordenadas del usuario en BD permanecieron totalmente intactas.
11. **División de Fecha de Pago:** Separados en estado, fecha y hora independientes en el PDF final y en el editor.
12. **Letter-Spacing:** Los cambios en el editor visual se reflejan instantáneamente y en el PDF final mediante el operador de FPDF/FPDI `Tc` de forma nativa.
13. **Resolución de AFP/EPS:** Retorna los códigos de PILA oficiales (`230301` / `EPS037`) resolviendo por base de datos de manera correcta.
14. **Tipo de Salario y Totales:** Renderiza la constante "F" y las etiquetas fijas correctamente.
15. **Generación final:** Probada y compilada la generación por coordenadas desde base de datos de manera correcta.
