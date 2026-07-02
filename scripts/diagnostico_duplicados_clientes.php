<?php
/**
 * Script de diagnóstico de clientes duplicados en Brynex.
 *
 * Uso: php artisan tinker --execute="require base_path('scripts/diagnostico_duplicados_clientes.php');"
 *
 * Este script ES DE SOLO LECTURA — no borra nada.
 */

use Illuminate\Support\Facades\DB;

// SQL Server compatible: usar JOIN con subconsulta GROUP BY
$duplicados = DB::select("
    SELECT c.id,
           c.cedula,
           c.aliado_id,
           c.created_at,
           RTRIM(LTRIM(
               ISNULL(c.primer_nombre,'') + ' ' +
               ISNULL(c.segundo_nombre,'') + ' ' +
               ISNULL(c.primer_apellido,'') + ' ' +
               ISNULL(c.segundo_apellido,'')
           )) AS nombre
    FROM clientes c
    INNER JOIN (
        SELECT cedula, aliado_id
        FROM clientes
        GROUP BY cedula, aliado_id
        HAVING COUNT(*) > 1
    ) AS dup ON c.cedula = dup.cedula AND c.aliado_id = dup.aliado_id
    ORDER BY c.aliado_id, c.cedula, c.id
");

if (empty($duplicados)) {
    echo "✅ No hay clientes duplicados.\n";
    return;
}

echo "⚠️  CLIENTES DUPLICADOS ENCONTRADOS\n";
echo str_repeat('─', 90) . "\n";

$grupos = [];
foreach ($duplicados as $row) {
    $key = $row->cedula . '_' . $row->aliado_id;
    $grupos[$key][] = $row;
}

foreach ($grupos as $key => $rows) {
    $cedula   = $rows[0]->cedula;
    $aliadoId = $rows[0]->aliado_id;
    echo "\n🔴 Cédula: {$cedula}  |  aliado_id: {$aliadoId}\n";
    foreach ($rows as $row) {
        echo sprintf(
            "   ID %-6d | %-40s | Creado: %s\n",
            $row->id,
            mb_substr(trim($row->nombre), 0, 40),
            $row->created_at
        );
    }
    // Sugerencia: conservar el de MENOR ID (el primero registrado)
    $ids = array_column($rows, 'id');
    $conservar = min($ids);
    $eliminar  = array_filter($ids, fn($id) => $id != $conservar);
    echo "   💡 Sugerencia: conservar ID {$conservar} (más antiguo), revisar ID(s): " . implode(', ', $eliminar) . "\n";
    echo "   🔍 Antes de eliminar, verificar que el ID a eliminar no tenga contratos:\n";
    foreach ($eliminar as $idElim) {
        $contratos = DB::table('contratos')->where('cedula', $cedula)->count();
        echo "      Contratos con cédula {$cedula}: {$contratos}\n";
        break;
    }
}

echo "\n" . str_repeat('─', 90) . "\n";
echo "Total grupos duplicados: " . count($grupos) . "\n";
echo "Total registros: " . count($duplicados) . "\n\n";
echo "⚠️  Para eliminar un duplicado de forma segura, confirma con el usuario qué ID borrar.\n";
echo "   Nunca eliminar sin verificar que no haya contratos, facturas u otros datos asociados.\n";
