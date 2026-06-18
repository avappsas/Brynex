<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anticipos', function (Blueprint $table) {
            $table->string('origen', 15)->default('empresa')->after('usuario_id');
            $table->unsignedBigInteger('anticipo_padre_id')->nullable()->after('origen');
            $table->unsignedTinyInteger('periodo_mes')->nullable()->after('anticipo_padre_id');
            $table->unsignedSmallInteger('periodo_anio')->nullable()->after('periodo_mes');

            $table->foreign('anticipo_padre_id')
                ->references('id')
                ->on('anticipos')
                ->onDelete('no action'); // Evitamos ON DELETE CASCADE en SQL Server para prevenir ciclos
        });
    }

    public function down(): void
    {
        Schema::table('anticipos', function (Blueprint $table) {
            $table->dropForeign(['anticipo_padre_id']);
            $table->dropColumn(['origen', 'anticipo_padre_id', 'periodo_mes', 'periodo_anio']);
        });
    }
};
