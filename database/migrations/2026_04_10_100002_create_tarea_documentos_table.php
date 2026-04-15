<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarea_documentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tarea_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->string('nombre', 200);
            $table->string('ruta', 500);
            $table->string('tipo_archivo', 10)->nullable(); // pdf, jpg, png, docx
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarea_documentos');
    }
};
