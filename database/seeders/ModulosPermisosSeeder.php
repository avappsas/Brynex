<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Catálogo de módulos + permisos + set por defecto de cada rol.
 *
 * Es idempotente: se puede correr las veces que haga falta. Lo único que
 * BORRA es la asignación rol→permiso (`role_has_permissions`), que se
 * reconstruye entera en cada corrida. Los permisos otorgados a dedo a un
 * usuario (`model_has_permissions`) NO se tocan nunca.
 *
 * ── Cómo leer la matriz ─────────────────────────────────────────────────
 *
 * `superadmin` no aparece: lo cubre el Gate::before de AuthServiceProvider,
 * que le da todo MENOS los permisos marcados `restringido`.
 *
 *   A = admin      → mano derecha del dueño. Todo lo operativo y financiero,
 *                    pero no configura el aliado, no crea usuarios, no ve la
 *                    auditoría y no edita contratos ya radicados.
 *   C = contable   → solo lectura y exportación de lo financiero (+ emitir
 *                    factura electrónica, que fue decisión explícita).
 *   U = usuario    → trabajador del aliado: opera todo el día a día.
 *   E = asesor     → HOY solo lectura, igual a lo que ya veía. Sin filtro por
 *                    asesor todavía: ver el aviso en el pie de este archivo.
 *
 * `cliente` no recibe nada: el portal del cliente está sin construir.
 */
class ModulosPermisosSeeder extends Seeder
{
    /**
     * [codigo, nombre, grupo, icono, ruta_nombre, restringido, solo_brynex, modulo_brynex_codigo]
     * y la lista de acciones: accion => [etiqueta, [roles], restringido]
     */
    private function catalogo(): array
    {
        return [
            // ══ OPERACIÓN ══════════════════════════════════════════════════
            ['clientes', 'Clientes', 'operacion', '👥', 'admin.clientes.index', [
                'ver' => ['Ver clientes',            ['A', 'C', 'U', 'E']],
                'crear' => ['Crear clientes',          ['A', 'U']],
                'editar' => ['Editar clientes',         ['A', 'U']],
            ]],
            ['beneficiarios', 'Beneficiarios', 'operacion', '👨‍👩‍👧', null, [
                'ver' => ['Ver beneficiarios',     ['A', 'C', 'U', 'E']],
                'gestionar' => ['Crear y editar',        ['A', 'U']],
                'eliminar' => ['Eliminar beneficiarios', ['A', 'U']],
            ]],
            ['documentos', 'Documentos del cliente', 'operacion', '📎', null, [
                'ver' => ['Ver documentos',       ['A', 'C', 'U', 'E']],
                'subir' => ['Subir documentos',     ['A', 'U']],
                'descargar' => ['Descargar documentos', ['A', 'C', 'U']],
                'eliminar' => ['Eliminar documentos',  ['A', 'U']],
            ]],
            // Claves de EPS, ARL, cajas y operadores por cliente / razón social.
            // NO son sensibles en el sentido de "solo algunos": el trabajador
            // las necesita para poder afiliar, así que las trae el rol usuario
            // completas. Si algún día hay un módulo de claves de BANCOS, ese sí
            // se crea aparte y marcado restringido.
            ['claves_acceso', 'Claves de acceso', 'operacion', '🔑', 'admin.clave_accesos.global', [
                'ver' => ['Ver listado de claves',     ['A', 'U']],
                'ver_contrasena' => ['Ver la contraseña en claro', ['A', 'U']],
                'gestionar' => ['Crear y editar claves',      ['A', 'U']],
                'eliminar' => ['Eliminar claves',            ['A', 'U']],
            ]],
            ['contratos', 'Contratos', 'operacion', '📃', 'admin.contratos.index', [
                'ver' => ['Ver contratos',               ['A', 'C', 'U', 'E']],
                'crear' => ['Crear contratos',             ['A', 'U']],
                'editar' => ['Editar contratos',            ['A', 'U']],
                // Solo superadmin: un contrato ya radicado ante EPS/ARL no se
                // toca sin que el dueño lo sepa.
                'editar_radicado' => ['Editar contrato ya radicado',  []],
                'retirar' => ['Retirar contratos',            ['A', 'U']],
            ]],
            ['afiliaciones', 'Afiliaciones', 'operacion', '🤝', 'admin.afiliaciones.index', [
                'ver' => ['Ver afiliaciones',      ['A', 'U']],
                'gestionar' => ['Gestionar afiliaciones', ['A', 'U']],
            ]],
            ['gestion_arl', 'Gestión ARL', 'operacion', '🚦', 'admin.gestion-arl.index', [
                'ver' => ['Ver semáforo ARL',      ['A', 'U']],
                'gestionar' => ['Renovar vigencias ARL', ['A', 'U']],
            ]],
            ['radicados', 'Radicados', 'operacion', '📮', 'admin.radicados.index', [
                'ver' => ['Ver radicados',         ['A', 'U']],
                'gestionar' => ['Mover estado de radicados', ['A', 'U']],
            ]],
            ['incapacidades', 'Incapacidades', 'operacion', '🏥', 'admin.incapacidades.index', [
                'ver' => ['Ver incapacidades',     ['A', 'C', 'U', 'E']],
                'gestionar' => ['Registrar y gestionar', ['A', 'U']],
                'abonos' => ['Registrar abonos',      ['A', 'U']],
                'eliminar' => ['Eliminar incapacidades', ['A']],
            ]],
            ['tareas', 'Tareas', 'operacion', '📌', 'admin.tareas.index', [
                'ver' => ['Ver el tablero completo',        ['A', 'C', 'U', 'E']],
                'gestionar' => ['Crear y gestionar las propias',  ['A', 'U']],
                'gestionar_todas' => ['Reasignar y cerrar las de otros', ['A']],
            ]],
            // Con esto se liquida y se paga la planilla: lo trae admin, y a los
            // trabajadores se les habilita uno por uno desde la pantalla de
            // permisos.
            ['planos', 'Planos SS (PILA)', 'operacion', '📄', 'admin.planos.index', [
                'ver' => ['Ver planos',            ['A']],
                'generar' => ['Generar planilla',      ['A']],
                'descargar' => ['Descargar plano/Excel', ['A']],
            ]],
            ['cotizaciones', 'Cotizaciones y prospectos', 'operacion', '💼', 'admin.cotizaciones.index', [
                'ver' => ['Ver cotizaciones',      ['A', 'U']],
                'gestionar' => ['Cotizar y convertir',   ['A', 'U']],
            ]],
            ['razones_sociales', 'Razones sociales', 'operacion', '🏛️', 'admin.configuracion.razones.index', [
                'ver' => ['Ver razones sociales',  ['A', 'C', 'U']],
                'gestionar' => ['Crear, editar, inactivar', ['A']],
                'eliminar' => ['Eliminar razón social',  ['A']],
            ]],

            // ══ FINANCIERO ═════════════════════════════════════════════════
            ['facturacion', 'Facturación', 'financiero', '🧾', 'admin.facturacion.index', [
                'ver' => ['Ver empresas y facturas',   ['A', 'C', 'U', 'E']],
                'generar' => ['Generar facturas',          ['A', 'U']],
                'editar' => ['Editar facturas',           ['A', 'U']],
                'anular' => ['Anular / eliminar facturas', ['A']],
                'exportar' => ['Exportar a Excel/PDF',      ['A', 'C']],
                'cobros_adicionales' => ['Cobros adicionales',        ['A', 'U']],
            ]],
            ['facturacion_electronica', 'Facturación electrónica (DIAN)', 'financiero', '📤', null, [
                'ver' => ['Ver documentos electrónicos', ['A', 'C']],
                'emitir' => ['Emitir a la DIAN',            ['A', 'C']],
            ]],
            ['cobros', 'Cobros', 'financiero', '💰', 'admin.cobros.index', [
                'ver' => ['Ver cobros',                ['A', 'C', 'U']],
                'registrar' => ['Registrar cobros',          ['A', 'U']],
                'eliminar' => ['Eliminar cobros',           ['A']],
                'exportar' => ['Exportar cobros',           ['A', 'C']],
            ]],
            ['cuadre_diario', 'Cuadre diario', 'financiero', '📋', 'admin.cuadre-diario.index', [
                'ver' => ['Ver el cuadre',             ['A', 'C', 'U']],
                'gestionar' => ['Abrir, cerrar y registrar', ['A', 'U']],
            ]],
            ['caja_menor', 'Caja menor', 'financiero', '🪙', 'admin.caja-menor.index', [
                'ver' => ['Ver caja menor',            ['A', 'C', 'U']],
                'gestionar' => ['Registrar movimientos',     ['A', 'U']],
            ]],
            ['anticipos', 'Anticipos', 'financiero', '💵', 'admin.anticipos.index', [
                'ver' => ['Ver anticipos',             ['A', 'C', 'U']],
                'gestionar' => ['Registrar y aplicar',       ['A', 'U']],
            ]],
            ['prestamos', 'Préstamos / cartera', 'financiero', '🏦', 'admin.prestamos.index', [
                'ver' => ['Ver préstamos',             ['A', 'C', 'U']],
                'gestionar' => ['Registrar y abonar',        ['A', 'U']],
            ]],
            ['gastos', 'Gastos administrativos', 'financiero', '🧮', null, [
                'ver' => ['Ver gastos',                ['A', 'C']],
                'gestionar' => ['Registrar gastos',          ['A']],
            ]],
            ['cuentas_bancarias', 'Cuentas bancarias', 'financiero', '💳', 'admin.configuracion.cuentas', [
                'ver' => ['Ver cuentas',                    ['A', 'C', 'U']],
                // El usuario puede dar de alta una cuenta SOLO de incapacidad
                // (facturacion_incapacidad = 1). Las de facturación normal no.
                'crear_incapacidad' => ['Crear cuenta de incapacidad',    ['A', 'U']],
                'gestionar' => ['Crear, editar e inactivar todas', ['A']],
            ]],

            // ══ REPORTES ═══════════════════════════════════════════════════
            ['informes', 'Informes', 'reportes', '📊', 'admin.informes.hub', [
                'ver' => ['Ver informes',      ['A', 'C']],
                'exportar' => ['Exportar informes', ['A', 'C']],
                // El estado financiero (ingresos, egresos, utilidad, saldos en
                // banco y efectivo del aliado) no va con el resto de informes:
                // un admin gestiona la operación sin tener por qué ver la plata.
                // Queda para superadmin y contable; a un admin puntual se le
                // otorga a mano desde Usuarios → Permisos.
                'financiero' => ['Ver el estado financiero',   ['C']],
                'financiero_editar' => ['Corregir consignaciones y subir soportes desde el financiero', []],
            ]],
            ['comisiones', 'Comisiones de asesores', 'reportes', '💼', 'admin.informes.comisiones.index', [
                'ver' => ['Ver comisiones',            ['A', 'C']],
                'gestionar' => ['Registrar y marcar pagadas', ['A']],
            ]],

            // ══ COMUNICACIÓN ═══════════════════════════════════════════════
            // Ojo: los permisos de WhatsApp ya existían con este mismo nombre
            // (WhatsappPermissionsSeeder). Aquí solo se les cuelga el módulo,
            // la etiqueta y el set de roles definitivo.
            ['whatsapp', 'WhatsApp', 'comunicacion', '💬', 'admin.whatsapp.chat.index', [
                'ver' => ['Ver el inbox',              ['A', 'C', 'U']],
                'responder' => ['Responder mensajes',        ['A', 'U']],
                'asignar' => ['Asignar conversaciones',    ['A', 'U']],
                'plantillas' => ['Gestionar plantillas',      ['A']],
                'masivo' => ['Lanzar envíos masivos',     ['A']],
                'configurar' => ['Configurar credenciales Meta', [], true],
            ], 'wa_conversaciones'],
            ['marketing', 'Marketing', 'comunicacion', '📣', 'admin.marketing.hub', [
                'ver' => ['Ver campañas y listas',  ['A', 'U']],
                'gestionar' => ['Crear listas y campañas', ['A', 'U']],
                'enviar' => ['Lanzar tandas de envío', ['A', 'U']],
            ]],
            ['publicidad', 'Generador de publicidad', 'comunicacion', '🎨', 'admin.publicidad.index', [
                'ver' => ['Ver piezas',                 ['A', 'U']],
                'gestionar' => ['Crear piezas (con costo IA)', ['A', 'U']],
                'publicar' => ['Publicar en redes',          ['A', 'U']],
            ]],
            ['redes_sociales', 'Redes sociales', 'comunicacion', '📱', 'admin.redes-sociales.index', [
                'ver' => ['Ver cuentas conectadas',    ['A', 'U']],
                'configurar' => ['Conectar cuentas y tokens', [], true],
            ]],
            ['pagina_web', 'Página web pública', 'comunicacion', '🌐', 'admin.pagina.index', [
                'ver' => ['Ver el CMS',              ['A', 'U']],
                'editar' => ['Editar textos y planes',  ['A', 'U']],
            ]],
            ['asistente_ia', 'Asistente virtual IA', 'comunicacion', '🤖', 'asistente_ia.index', [
                'usar' => ['Usar el asistente', ['A', 'C', 'U']],
            ]],

            // ══ ADMINISTRACIÓN ═════════════════════════════════════════════
            ['asesores', 'Asesores', 'administracion', '🧑‍💼', 'admin.asesores.index', [
                'ver' => ['Ver asesores',                ['A', 'C', 'U']],
                'gestionar' => ['Crear, editar y comisiones',  ['A']],
            ]],
            ['usuarios', 'Usuarios', 'administracion', '👤', 'admin.usuarios.index', [
                'ver' => ['Ver usuarios',                 ['A']],
                'gestionar' => ['Crear y editar usuarios',      []],
                'permisos' => ['Otorgar permisos por usuario', []],
            ]],
            ['configuracion', 'Configuración del aliado', 'administracion', '⚙️', 'admin.configuracion.hub', [
                'ver' => ['Ver la configuración',          ['A']],
                'editar' => ['Editar tarifas, planes y ARL',  []],
            ]],
            ['operadores_planilla', 'Operadores de planilla', 'administracion', '🖨️', 'admin.configuracion.operadores.index', [
                'ver' => ['Ver operadores',              ['A', 'U']],
                'configurar' => ['Activar y ordenar operadores', []],
                'credenciales' => ['Ver y editar credenciales',   [], true],
            ]],
            ['bitacora', 'Auditoría', 'administracion', '👁️', 'admin.bitacora.index', [
                'ver' => ['Ver la bitácora', []],
            ]],
            ['traslados_rs', 'Traslados masivos de RS', 'administracion', '🔄', 'admin.traslados.index', [
                'ver' => ['Ver el módulo',           []],
                'ejecutar' => ['Ejecutar traslado masivo', []],
            ]],

            // ══ BRYNEX GLOBAL ══════════════════════════════════════════════
            // Estos no salen en la pantalla de permisos: son de la empresa
            // dueña de la plataforma, no de ningún aliado, y se reparten con
            // `permisos:aplicar-inicial`. Además exigen es_brynex (Gate::before).
            ['aliados', 'Aliados', 'brynex', '🏢', 'admin.aliados.index', [
                'ver' => ['Ver aliados',           [], false, true],
                'gestionar' => ['Crear y editar aliados', [], false, true],
            ]],
            ['brynex_hub', 'Hub BryNex', 'brynex', '🔵', 'brynex.hub', [
                'ver' => ['Entrar al hub', [], false, true],
            ]],
            ['brynex_cobros', 'Cobros a aliados', 'brynex', '🧾', null, [
                'ver' => ['Ver consumo y cobros',  [], false, true],
                'gestionar' => ['Generar y cobrar',      [], false, true],
            ]],
            ['brynex_ia', 'Entrenamiento IA', 'brynex', '🧠', null, [
                'ver' => ['Ver conocimiento',        [], false, true],
                'entrenar' => ['Aprobar y entrenar',      [], false, true],
            ]],
            ['brynex_backup', 'Backup de la BD', 'brynex', '💾', null, [
                'ejecutar' => ['Generar y descargar backup', [], true, true],
            ]],
            // Cuadre de cierre: qué vigentes se quedaron por fuera de la
            // planilla. Es solo de BryNex a propósito — una razón social
            // agrupa varias empresas cliente, así que ver "faltan 239" sin
            // ese contexto siembra dudas en el aliado en vez de resolverlas.
            ['brynex_cierre', 'Validación de cierre', 'brynex', '🧾', 'admin.informes.validacion_cierre', [
                'ver' => ['Ver pendientes de planilla', [], false, true],
            ]],
        ];
    }

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $mapaRoles = [
            'A' => 'admin',
            'C' => 'contable',
            'U' => 'usuario',
            'E' => 'asesor',
        ];

        // ── 0. Unificar contador → contable ────────────────────────────────
        $this->unificarContador();

        // Asegurar que existan los 6 roles (sin 'contador')
        foreach (['superadmin', 'admin', 'contable', 'usuario', 'asesor', 'cliente'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        // ── 1. Catálogo de módulos y permisos ──────────────────────────────
        $asignaciones = [];   // rol => [permiso, ...]
        $vistos = [];   // nombres de permiso creados en esta corrida
        $ordenModulo = 0;

        foreach ($this->catalogo() as $fila) {
            [$codigo, $nombre, $grupo, $icono, $ruta, $acciones] = $fila;
            $moduloBrynex = $fila[6] ?? null;

            $restringidoModulo = collect($acciones)->every(fn ($a) => ($a[2] ?? false) === true);
            $soloBrynex = collect($acciones)->every(fn ($a) => ($a[3] ?? false) === true);

            $modulo = Modulo::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre' => $nombre,
                    'grupo' => $grupo,
                    'icono' => $icono,
                    'ruta_nombre' => $ruta,
                    'restringido' => $restringidoModulo,
                    'solo_brynex' => $soloBrynex,
                    'modulo_brynex_codigo' => $moduloBrynex,
                    'orden' => ++$ordenModulo * 10,
                    'activo' => true,
                ]
            );

            $ordenPermiso = 0;
            foreach ($acciones as $accion => $def) {
                [$etiqueta, $roles] = $def;
                $restringido = $def[2] ?? false;

                $nombrePermiso = "{$codigo}.{$accion}";
                $vistos[] = $nombrePermiso;

                $permiso = Permission::firstOrCreate(
                    ['name' => $nombrePermiso, 'guard_name' => 'web']
                );
                // ¿Se pinta en la pantalla de permisos por usuario?
                // Si el rol `usuario` ya lo trae, no hay nada que decidir: el
                // sistema solo otorga permisos, nunca los quita, así que la
                // casilla estaría siempre marcada y en gris. Se oculta.
                // Se pintan los que distinguen: lo que solo tiene admin, lo que
                // no trae nadie, y los restringidos.
                $asignable = $restringido || ! in_array('U', $roles, true);

                $permiso->forceFill([
                    'modulo_id' => $modulo->id,
                    'etiqueta' => $etiqueta,
                    'accion' => $accion,
                    'restringido' => $restringido,
                    'asignable' => $asignable,
                    'orden' => ++$ordenPermiso * 10,
                ])->save();

                foreach ($roles as $letra) {
                    $asignaciones[$mapaRoles[$letra]][] = $nombrePermiso;
                }
            }
        }

        // ── 2. Reconstruir la matriz rol → permisos ────────────────────────
        // syncPermissions borra lo anterior del rol y deja exactamente esto.
        // Los permisos otorgados a un USUARIO puntual no se tocan.
        foreach ($mapaRoles as $rol) {
            $rolModelo = Role::where('name', $rol)->first();
            $rolModelo->syncPermissions($asignaciones[$rol] ?? []);
        }

        // superadmin y cliente no llevan permisos en la tabla:
        //  - superadmin los recibe del Gate::before (todo menos restringidos)
        //  - cliente todavía no tiene portal
        Role::where('name', 'superadmin')->first()->syncPermissions([]);
        Role::where('name', 'cliente')->first()->syncPermissions([]);

        // ── 3. Limpiar permisos huérfanos del pasado ───────────────────────
        // `ver-planos` se creó en su día y nunca se usó en ninguna ruta ni
        // vista; lo reemplaza planos.ver.
        Permission::whereNotIn('name', $vistos)->get()->each(function ($p) {
            $this->command->warn("  · permiso huérfano eliminado: {$p->name}");
            $p->delete();
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✅ '.Modulo::count().' módulos y '.Permission::count().' permisos sembrados '
            .'('.Permission::where('asignable', true)->count().' visibles en la pantalla de permisos).');
        $this->command->warn('⚠️  El rol `asesor` quedó SOLO LECTURA sobre todo el aliado: todavía no existe');
        $this->command->warn('   el vínculo users ↔ asesores, así que no se puede filtrar "solo sus clientes".');
    }

    /**
     * `contador` lo creó por error WhatsappPermissionsSeeder; el rol bueno es
     * `contable`. Mueve cualquier usuario que lo tuviera y borra el duplicado.
     */
    private function unificarContador(): void
    {
        $contador = Role::where('name', 'contador')->where('guard_name', 'web')->first();
        if (! $contador) {
            return;
        }

        $contable = Role::firstOrCreate(['name' => 'contable', 'guard_name' => 'web']);

        $movidos = DB::table('model_has_roles')->where('role_id', $contador->id)->count();
        if ($movidos > 0) {
            // Puede que alguien ya tuviera los dos: insertar solo los que faltan.
            $filas = DB::table('model_has_roles')->where('role_id', $contador->id)->get();
            foreach ($filas as $f) {
                $yaTiene = DB::table('model_has_roles')
                    ->where('role_id', $contable->id)
                    ->where('model_id', $f->model_id)
                    ->where('model_type', $f->model_type)
                    ->exists();
                if (! $yaTiene) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $contable->id,
                        'model_id' => $f->model_id,
                        'model_type' => $f->model_type,
                    ]);
                }
            }
            DB::table('model_has_roles')->where('role_id', $contador->id)->delete();
        }

        DB::table('role_has_permissions')->where('role_id', $contador->id)->delete();
        $contador->delete();

        $this->command->info("✅ Rol `contador` eliminado (usuarios movidos a `contable`: {$movidos}).");
    }
}
