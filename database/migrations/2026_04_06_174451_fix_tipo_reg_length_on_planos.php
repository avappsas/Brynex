<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            // Ampliar tipo_reg: era varchar(2) o similar, ahora varchar(20)
            // para guardar 'afiliacion' o 'planilla'
            $table->string('tipo_reg', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->string('tipo_reg', 2)->nullable()->change();
        });
    }
};
