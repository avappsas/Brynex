<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagina_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('nombre', 150);
            $table->string('celular', 30);
            $table->string('perfil', 30)->nullable();       // dependiente|independiente
            $table->json('coberturas')->nullable();          // {eps,arl,pension,caja}
            $table->decimal('ingreso_mensual', 12, 2)->nullable();
            $table->decimal('valor_mensual_cotizado', 12, 2)->nullable();
            $table->string('plan_interes', 150)->nullable();
            $table->string('origen', 30)->default('cotizador'); // cotizador|contacto
            $table->string('estado', 20)->default('nuevo');     // nuevo|contactado|convertido|descartado
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->cascadeOnDelete();
            $table->index(['aliado_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagina_leads');
    }
};
