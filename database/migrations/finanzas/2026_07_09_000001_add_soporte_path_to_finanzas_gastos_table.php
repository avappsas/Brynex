<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Agregar columna soporte_path en finanzas_gastos
        if (!Schema::connection('finanzas')->hasColumn('finanzas_gastos', 'soporte_path')) {
            Schema::connection('finanzas')->table('finanzas_gastos', function (Blueprint $table) {
                $table->string('soporte_path', 255)->nullable()->after('patrimonio_id');
            });
        }

        // 2. Definir las categorías mejoradas basadas en la imagen verde del cliente
        $categoriasMejoradas = [
            ['old' => 'almuerzo',    'new' => 'Alimentación',             'icono' => '🍔', 'color' => '#f97316', 'orden' => 1],
            ['old' => 'MERCADO',     'new' => 'Mercado / Supermercado',   'icono' => '🛒', 'color' => '#10b981', 'orden' => 2],
            ['old' => 'OTROS',       'new' => 'Otros',                    'icono' => '📦', 'color' => '#64748b', 'orden' => 17],
            ['old' => 'NIÑOS',       'new' => 'Niños / Educación',        'icono' => '👶', 'color' => '#3b82f6', 'orden' => 4],
            ['old' => 'salidas',     'new' => 'Salidas / Entretenimiento', 'icono' => '🎬', 'color' => '#ec4899', 'orden' => 5],
            ['old' => 'parqueadero', 'new' => 'Parqueadero / Peajes',      'icono' => '🚗', 'color' => '#f59e0b', 'orden' => 6],
            ['old' => 'empresa',     'new' => 'Gastos Empresa',           'icono' => '🏢', 'color' => '#8b5cf6', 'orden' => 7],
            ['old' => 'gasolina',    'new' => 'Combustible / Gasolina',   'icono' => '⛽', 'color' => '#ef4444', 'orden' => 8],
            ['old' => 'isabella',    'new' => 'Isabella',                 'icono' => '👧', 'color' => '#d946ef', 'orden' => 9],
            ['old' => 'IGLESIA',     'new' => 'Iglesia / Diezmo',         'icono' => '⛪', 'color' => '#eab308', 'orden' => 10],
            ['old' => 'cadena',      'new' => 'Cadena / Ahorro',          'icono' => '⛓️', 'color' => '#06b6d4', 'orden' => 11],
            ['old' => 'ASEO',        'new' => 'Aseo / Limpieza',          'icono' => '🧹', 'color' => '#065f46', 'orden' => 12],
            ['old' => 'SERVICIOS',   'new' => 'Servicios Públicos',       'icono' => '⚡', 'color' => '#0284c7', 'orden' => 13],
            ['old' => 'APTO',        'new' => 'Apartamento / Arriendo',   'icono' => '🏠', 'color' => '#4f46e5', 'orden' => 14],
            ['old' => 'JP',          'new' => 'JP',                       'icono' => '💼', 'color' => '#1e3a8a', 'orden' => 15],
            ['old' => 'PM',          'new' => 'PM',                       'icono' => '🕒', 'color' => '#7c2d12', 'orden' => 16],
            ['old' => 'IMPUESTOS',   'new' => 'Impuestos / Tasas',        'icono' => '📄', 'color' => '#b91c1c', 'orden' => 3],
        ];

        // Obtener únicamente los usuarios que ya utilizan el módulo de finanzas para evitar consultas masivas innecesarias
        $userIds = DB::connection('finanzas')
            ->table('finanzas_categorias_gasto')
            ->distinct()
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            $userIds = DB::table('users')->pluck('id');
        }

        foreach ($userIds as $userId) {
            foreach ($categoriasMejoradas as $cat) {
                // Buscamos si existe alguna categoría con el nombre viejo (case-insensitive) o el nuevo
                $existing = DB::connection('finanzas')
                    ->table('finanzas_categorias_gasto')
                    ->where('user_id', $userId)
                    ->where(function($q) use ($cat) {
                        $q->where('nombre', 'like', $cat['old'])
                          ->orWhere('nombre', 'like', $cat['new']);
                    })
                    ->first();

                if ($existing) {
                    // Si ya existe la categoría, la actualizamos para adaptarla a la nueva nomenclatura, ícono y color
                    DB::connection('finanzas')
                        ->table('finanzas_categorias_gasto')
                        ->where('id', $existing->id)
                        ->update([
                            'nombre' => $cat['new'],
                            'icono' => $cat['icono'],
                            'color' => $cat['color'],
                            'orden' => $cat['orden'],
                            'updated_at' => now(),
                        ]);
                } else {
                    // Si no existe, la creamos desde cero
                    DB::connection('finanzas')
                        ->table('finanzas_categorias_gasto')
                        ->insert([
                            'user_id' => $userId,
                            'nombre' => $cat['new'],
                            'icono' => $cat['icono'],
                            'color' => $cat['color'],
                            'es_recurrente' => false,
                            'activo' => true,
                            'orden' => $cat['orden'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('finanzas')->hasColumn('finanzas_gastos', 'soporte_path')) {
            Schema::connection('finanzas')->table('finanzas_gastos', function (Blueprint $table) {
                $table->dropColumn('soporte_path');
            });
        }
    }
};
