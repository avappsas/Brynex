---
name: laravel-migracion
description: >
  Crea migraciones incrementales en Laravel 10 para SQL Server (sqlsrv).
  Actívate cuando el usuario pida: agregar columna, crear tabla nueva, crear índice,
  modificar tipo de dato, agregar relación FK, cualquier cambio de estructura de BD.
  También actívate si el usuario menciona: "migración", "tabla", "columna", "índice", "FK".
---

# Skill: Migración Laravel para SQL Server (Brynex)

## Regla Absoluta
> ⚠️ La BD local y producción son LA MISMA. NUNCA generar `migrate:fresh`, `migrate:reset`, `db:wipe`, `DROP TABLE`, ni `TRUNCATE`. Solo migraciones `addColumn` / `createTable` incrementales.

## Patrón de Migración

### Agregar columna a tabla existente
```php
<?php
// database/migrations/YYYY_MM_DD_HHMMSS_add_campo_to_tabla_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nombre_tabla', function (Blueprint $table) {
            $table->string('nueva_columna')->nullable()->after('columna_referencia');
            // Para FK:
            // $table->unsignedBigInteger('entidad_id')->nullable()->after('campo');
            // $table->foreign('entidad_id')->references('id')->on('entidades');
        });
    }

    public function down(): void
    {
        Schema::table('nombre_tabla', function (Blueprint $table) {
            // $table->dropForeign(['entidad_id']);
            $table->dropColumn('nueva_columna');
        });
    }
};
```

### Crear tabla nueva
```php
Schema::create('nueva_tabla', function (Blueprint $table) {
    $table->id();                          // IDENTITY bigint
    $table->unsignedBigInteger('aliado_id'); // SIEMPRE incluir aliado_id
    $table->string('nombre', 200);
    $table->decimal('valor', 18, 2)->default(0);
    $table->boolean('activo')->default(true);
    $table->timestamps();                  // created_at, updated_at
    $table->softDeletes();                 // deleted_at (si aplica)

    $table->foreign('aliado_id')->references('id')->on('aliados');
});
```

## Tipos SQL Server frecuentes en Brynex
| Eloquent | SQL Server | Uso |
|---|---|---|
| `$table->id()` | `bigint IDENTITY` | PK de tablas |
| `$table->unsignedBigInteger()` | `bigint` | FK hacia otras tablas |
| `$table->string('col', 255)` | `nvarchar(255)` | Textos cortos |
| `$table->text()` | `nvarchar(max)` | Textos largos |
| `$table->decimal(18, 2)` | `decimal(18,2)` | Valores monetarios |
| `$table->integer()` | `int` | Contadores, días |
| `$table->boolean()` | `tinyint(1)` | Flags |
| `$table->date()` | `date` | Fechas sin hora |
| `$table->timestamp()` | `datetime2` | Timestamps |

## Nomenclatura
- Archivo: `YYYY_MM_DD_HHMMSS_verbo_descripcion_tabla.php`
- Verbos: `add_`, `create_`, `rename_`, `drop_`, `update_`, `fix_`, `extend_`
- Tabla en plural: `contratos`, `facturas`, `clientes`

## Después de crear la migración
```bash
php artisan migrate
```
**No usar** `--step`, `--pretend` en producción sin revisar el output primero.

## Recordatorio Multi-Aliado
Toda tabla nueva de datos de negocio DEBE tener `aliado_id` como columna obligatoria con FK hacia `aliados.id`.
