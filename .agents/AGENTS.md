# 🧠 Contexto del Proyecto Brynex

## Stack Tecnológico

| Capa | Tecnología |
|---|---|
| Backend | Laravel 10 (PHP 8.1+) |
| Base de Datos Principal | SQL Server (via `sqlsrv`) |
| Base de Datos Legacy | SQL Server (Brygar_BD — solo lectura para migración) |
| Frontend | Blade + Alpine.js + Vite |
| PDF | DomPDF (`barryvdh/laravel-dompdf`) + FPDF/FPDI |
| Excel | PhpSpreadsheet (`phpoffice/phpspreadsheet`) |
| Permisos | Spatie Laravel Permission v6 |
| WebSockets | Laravel Reverb |
| WhatsApp | API HTTP personalizada via Guzzle |

## Bases de Datos

> ⚠️ **CRÍTICO**: La base de datos local y la de producción son LA MISMA.
> NUNCA usar `migrate:fresh`, `migrate:reset`, `db:wipe`, `DROP TABLE` ni `TRUNCATE` sin autorización explícita.

- **Conexión principal**: `DB_CONNECTION=sqlsrv` → `207.244.249.160:1433` → BD `BryNex`
- **Conexión legacy**: `DB_LEGACY_*` → `200.29.120.228:1533` → BD `Brygar_BD` (solo migración/consulta)
- **Regla**: Siempre usar migraciones incrementales (`addColumn`, `createIndex`). Nunca recrear tablas existentes.
- **Naming BD**: `snake_case` en columnas y tablas. IDs como `IDENTITY` (auto-incremental) en SQL Server.

## Arquitectura y Estructura

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Admin/          ← Todos los controladores del panel admin (38 controllers)
│   └── Middleware/
├── Models/                 ← 63 modelos Eloquent
├── Services/               ← Lógica de negocio pesada (Excel, PDF, Planos, WhatsApp)
└── Traits/                 ← Traits reutilizables

resources/views/
└── admin/                  ← Vistas Blade por módulo (25 módulos)

database/migrations/        ← 148+ migraciones incrementales
routes/web.php              ← Todas las rutas (~46KB, muy extenso)
```

## Módulos del Sistema

### 📋 Core del Negocio
- **Clientes / Contratos**: Gestión de afiliados al sistema de seguridad social
  - Un cliente tiene múltiples contratos (`cedula` como FK entre cliente y contrato)
  - Un contrato tiene `plan_id`, `tipo_modalidad_id`, `eps_id`, `pension_id`, `arl_id`, `caja_id`
- **Facturación**: Módulo central (~167KB controller). Genera facturas mensuales por contrato
  - Factura tiene: administración, admon_asesor, distribución, mora, anticipo_aplicado, otros_ingresos
  - Estados: `pendiente`, `pagada`, `vencida`
- **Planos**: Archivos planos (.txt) para operadores de planilla (PILA, NI, MiPlanilla, etc.)
  - Servicio: `PlanoPilaTxtService`, `ExcelPlanoNIService`, `ExcelAportesEnLineaService`
- **Incapacidades**: Gestión completa de incapacidades médicas con abonos y gestiones EPS/ARL

### 💰 Financiero
- **Cobros**: Módulo de cobro a clientes con WhatsApp integrado (`CobrosController` ~101KB)
- **Anticipos**: Pagos anticipados aplicables a facturas futuras (con soft delete)
- **Consignaciones**: Registro de pagos bancarios vinculados a facturas
- **Cuadre Diario**: Cierre de caja con conciliación bancaria
- **Comisiones**: Cálculo y pago de comisiones a asesores

### 👥 Gestión
- **Asesores**: Gestión de asesores comerciales con comisiones
- **Aliados**: Empresas socias (multi-aliado con `aliado_id` en toda la BD)
- **Radicados**: Seguimiento de documentos y trámites
- **Tareas**: Sistema de gestión de tareas con semáforo de colores
- **Cotizaciones**: Prospección y seguimiento de nuevos clientes

### 🔗 Integraciones
- **WhatsApp**: Conversaciones, plantillas, envíos masivos y webhook (Meta API)
- **Facturación Electrónica**: `FacturacionElectronicaController`
- **Formularios EPS**: Generación de formularios para EPS

## Convenciones de Código

### PHP / Laravel
- Namespace: `App\Models`, `App\Http\Controllers\Admin`, `App\Services`
- Todos los modelos extienden `BaseModel` (que extiende `Model`)
- Uso de `$fillable` explícito en todos los modelos
- Relaciones: siempre tipadas con PHPDoc o return type
- Queries: preferir Eloquent. Raw SQL solo en casos de performance crítica

### Migraciones
- SIEMPRE crear nueva migración para cada cambio de BD — nunca editar migraciones existentes
- Nombrado: `YYYY_MM_DD_HHMMSS_descripcion_corta.php`
- Para SQL Server: usar `$table->unsignedBigInteger()` para FK, `$table->string()->nullable()` para opcionales
- Rollback: siempre implementar `down()` cuando sea seguro

### Vistas Blade
- Layout base: `layouts.admin` para panel admin
- Partials en: `resources/views/admin/partials/`
- Alpine.js para interactividad client-side
- Modales con Alpine.js `x-show` / `x-data`

### Rutas
- Todas en `routes/web.php` bajo middleware `auth` y `aliado`
- Prefix admin: `/admin/`
- Naming: `admin.modulo.accion`

## Reglas de Seguridad

- Permiso del sistema: Spatie Laravel Permission
- Multi-aliado: cada registro tiene `aliado_id` — SIEMPRE filtrar por `aliado_id` del usuario autenticado
- Validación: usar Form Requests cuando sea posible
- Archivos subidos: almacenar en `storage/app/` — NUNCA en `public/` directamente

## Notas Importantes del Negocio

- **IBC** = Ingreso Base de Cotización (columna clave en contratos)
- **Tiempo Parcial**: modalidad especial con `dias_tiempo_parcial` que afecta cálculos de ARL y aportes
- **Plano PILA**: formato estándar para pagos de seguridad social en Colombia
- **Operadores de Planilla**: entidades autorizadas para procesar planillas (SOI, Aportes en Línea, etc.)
- Las **razones sociales** son las empresas clientes; los **clientes** son las personas naturales afiliadas
