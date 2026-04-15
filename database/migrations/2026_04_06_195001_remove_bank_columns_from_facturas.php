<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // En SQL Server, las FK pueden no existir con el nombre convencional de Laravel.
        // Usamos IF EXISTS con SQL nativo para eliminación segura.
        $constraints = ['facturas_banco_cuenta_id_foreign', 'facturas_banco_cuenta2_id_foreign'];
        foreach ($constraints as $fk) {
            DB::statement("
                IF EXISTS (
                    SELECT 1 FROM sys.foreign_keys
                    WHERE name = '$fk' AND parent_object_id = OBJECT_ID('facturas')
                )
                ALTER TABLE facturas DROP CONSTRAINT [$fk]
            ");
        }

        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['banco_cuenta_id', 'banco_cuenta2_id', 'valor_banco2']);
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->unsignedBigInteger('banco_cuenta_id')->nullable();
            $table->unsignedBigInteger('banco_cuenta2_id')->nullable();
            $table->decimal('valor_banco2', 14, 0)->default(0);
        });
    }
};
