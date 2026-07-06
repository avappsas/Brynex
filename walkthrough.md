# Walkthrough — Módulo de Configuración de Planillas de Pago (PDF Overlay)

He diseñado e implementado el nuevo módulo que permite subir archivos PDF de planillas en blanco por operador de planilla y mapear interactivamente las coordenadas de cada campo de datos del plano PILA a través de un editor visual (PDF.js + FPDI).

---

## Cambios Realizados

### 1. Base de Datos
* **Migración Incremental:** Creada y ejecutada la migración [2026_07_06_162000_create_operador_planillas_templates_table.php](file:///Users/brayangarcia/Documents/GitHub/Brynex/database/migrations/2026_07_06_162000_create_operador_planillas_templates_table.php).
* **Nueva Tabla:** `operador_planillas_templates` para el almacenamiento global de las plantillas PDF en blanco y el JSON de coordenadas por operador.
* **Modelo Eloquent:** Creado [OperadorPlanillaTemplate.php](file:///Users/brayangarcia/Documents/GitHub/Brynex/app/Models/OperadorPlanillaTemplate.php).

### 2. Capa de Negocio (Servicio PDF Dinámico)
* **Nuevo Servicio:** Creado [PlanillaFormularioService.php](file:///Users/brayangarcia/Documents/GitHub/Brynex/app/Services/PlanillaFormularioService.php) que:
  1. Autodetecta el operador por el que se reportó o pagó el plano (consultando el historial de la API `operador_planillas_api` o el operador por defecto asignado al cliente).
  2. Si existe una plantilla configurada en BD, carga el archivo PDF base desde `storage/app/formularios/planillas/` y reemplaza los datos dinámicos usando las coordenadas del JSON.
  3. Si no existe plantilla en BD para el operador, se apoya de forma segura en el renderizado estructurado por código en `SuaportePdfService` (fallback).

### 3. Editor Visual de Planillas (Admin UI)
* **Rutas:** Registradas en [web.php](file:///Users/brayangarcia/Documents/GitHub/Brynex/routes/web.php).
* **Controlador Administrativo:** Creado [OperadorPlanillaFormularioController.php](file:///Users/brayangarcia/Documents/GitHub/Brynex/app/Http/Controllers/Admin/OperadorPlanillaFormularioController.php).
* **Hub de Configuración:** Añadida la tarjeta de "Editor de Planillas de Pago" en [hub.blade.php](file:///Users/brayangarcia/Documents/GitHub/Brynex/resources/views/admin/configuracion/hub.blade.php).
* **Lienzo Interactivo:** Creada la vista [planilla_formulario.blade.php](file:///Users/brayangarcia/Documents/GitHub/Brynex/resources/views/admin/configuracion/planilla_formulario.blade.php) que permite a los administradores subir el PDF del operador, elegir el campo (Aportante, Afiliado, Aportes, Totales), arrastrar y dibujar el área interactiva sobre el canvas, y calibrar tamaño de fuente, estilos (negrita, cursiva), alineación y limpieza de celda.

### 4. Siembra Inicial (Seeder)
* **Script de Siembra:** Creado y ejecutado [sembrar_planilla_suaporte.php](file:///Users/brayangarcia/.gemini/antigravity/brain/300d20ea-4d33-4511-a7d6-8bad5ac1c292/scratch/sembrar_planilla_suaporte.php) para inicializar al operador Enlace (Suaporte) en la tabla de plantillas con las coordenadas de alineación y tapado exactas que calibramos para el afiliado y la liquidación.

---

## Verificación de Correctitud

1. **Migración de base de datos:** Ejecutada con éxito.
2. **Siembra de plantilla:** Copió el PDF base y guardó el JSON con 42 campos mapeados.
3. **Compilación Tinker:** Ejecutado con éxito:
   `(new \App\Services\PlanillaFormularioService())->generar($plano)`
   generando el PDF rellenado dinámicamente desde base de datos sin ningún tipo de error.
