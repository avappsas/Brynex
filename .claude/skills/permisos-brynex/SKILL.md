---
name: permisos-brynex
description: >
  Modelo de roles, permisos y multi-tenancy por aliado en Brynex. Actívate
  cuando el usuario mencione: rol, permiso, Spatie, superadmin, es_brynex,
  aliado activo, cambiar de aliado, quién puede ver esto, autorización,
  middleware de auth, SetAlidoContext, aliado_user.
---

# Skill: Roles, Permisos y Multi-tenancy — Brynex

## Estado real: los roles existen, casi nadie los usa

`database/seeders/RolesSeeder.php` define 6 roles con Spatie Permission:

```
superadmin → todo el sistema de SU empresa aliada
admin      → todo excepto módulos contables restringidos
contable   → solo módulo financiero/contable
usuario    → empleado interno: clientes, facturación, afiliaciones
asesor     → solo sus propios clientes
cliente    → solo su información y pagos
```

**Pero de 474 rutas en `routes/web.php`, solo UNA tiene `middleware('role:...')`**
(`admin/traslados-rs`, role `superadmin|admin`). Todo lo demás cuelga de
`Route::middleware('auth')` sin más filtro — cualquier usuario autenticado,
sea `cliente` o `asesor`, puede invocar por URL cualquier acción de cualquier
módulo. Ver `docs/auditoria-seguridad.md`, hallazgo A-1.

**Antes de asumir que un módulo está protegido por rol, verificarlo** — la
intención del seeder (ej. "contable ve solo finanzas") casi nunca está
implementada en la ruta. Las únicas excepciones verificadas son:
- `admin/traslados-rs` → `role:superadmin|admin`
- `/finanzas/*` → NO usa Spatie, usa el middleware propio `finanzas.access`,
  que además no comprueba rol sino **una cédula hardcodeada** (ver
  [[finanzas-brynex]]).

Si vas a añadir control de acceso a un módulo, sigue el patrón de
`admin/traslados-rs`: `->middleware('role:admin|superadmin')` o
`->middleware('permission:nombre.permiso')`, no reinventes un middleware
custom salvo que sea un caso tan especial como Finanzas.

## `es_brynex`: no es un rol, es un flag de identidad

```php
// users.es_brynex (boolean)
```

Distingue **quién es empleado de BryNex (la empresa dueña de la plataforma)**
de quién es empleado de un aliado (cliente de la plataforma). No sustituye a
los roles de Spatie — un usuario puede ser `es_brynex=true` Y tener rol
`asesor`. Se usa para dos cosas:

1. **Multi-aliado**: solo un usuario `es_brynex` puede tener acceso a más de
   un aliado (tabla pivote `aliado_user`, con `rol` y `activo` propios del
   pivote — distinto de los roles de Spatie).
2. Checks de "solo BryNex" repartidos en controladores, ej.
   `BrynexBackupController`: `$user->es_brynex && $user->hasRole('superadmin')`.

## Aliado activo en sesión — cómo se resuelve en cada request

`SetAlidoContext` (middleware del grupo `web`, ver `app/Http/Kernel.php`) corre
en cada petición autenticada:

```php
// 1. Si es_brynex=true y viene ?aliado=ID en la URL → cambia el aliado activo
//    (solo si User::puedeAccederAliado($id) lo permite)
// 2. Si no hay aliado en sesión → usa el aliado_id propio del usuario
// 3. Comparte 'alidoActivo' con TODAS las vistas
```

`User::puedeAccederAliado(int $alidoId)`:
- El aliado propio (`aliado_id`) siempre es accesible.
- `es_brynex` + `superadmin` → cualquier aliado activo.
- `es_brynex` sin superadmin → solo los aliados asignados en `aliado_user`
  con `pivot.activo = true`.
- Usuario normal (no `es_brynex`) → nunca puede cambiar de aliado.

**Todo query multi-tenant debe filtrar por `session('aliado_id_activo')`**,
no por `Auth::user()->aliado_id` directamente — un usuario BryNex puede estar
operando sobre un aliado distinto al suyo. Ver `IncapacidadController` (tras
la corrección de C-5) como referencia del patrón correcto.

## No existe un scope global de aislamiento

91 de 92 modelos extienden `BaseModel`, que solo normaliza fechas — no hay
`addGlobalScope` que filtre por aliado automáticamente. Cada query nueva debe
recordar el `->where('aliado_id', session('aliado_id_activo'))` a mano. Ver
`docs/auditoria-seguridad.md`, hallazgo A-2, para el plan de introducir un
trait que lo automatice.
