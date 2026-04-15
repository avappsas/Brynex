<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Crear tabla empresas ──
        Schema::create('empresas', function (Blueprint $table) {
            $table->integer('id')->primary();  // Mantener ID original de legacy
            $table->bigInteger('nit')->nullable();
            $table->string('empresa', 255)->nullable();
            $table->string('contacto', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('celular', 50)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->string('cliente_de', 255)->nullable();
            $table->string('tipo_facturacion', 30)->nullable();
            $table->string('iva', 20)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('actividad_economica', 1000)->nullable();
            $table->unsignedBigInteger('aliado_id')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->nullOnDelete();
        });

        // ── Importar datos desde Brygar_BD.Empresas ──
        $legacy = DB::connection('sqlsrv_legacy')
            ->table('Empresas')
            ->get();

        $brygarId = DB::table('aliados')->where('nit', '901918923')->value('id');

        foreach ($legacy as $e) {
            DB::table('empresas')->updateOrInsert(
                ['id' => (int) $e->Id],
                [
                    'nit'                => $e->NIT ? (int) $e->NIT : null,
                    'empresa'            => trim($e->Empresa ?? ''),
                    'contacto'           => trim($e->Contacto ?? '') ?: null,
                    'telefono'           => trim($e->Telefono ?? '') ?: null,
                    'celular'            => trim($e->Celular ?? '') ?: null,
                    'direccion'          => trim($e->Direccion ?? '') ?: null,
                    'observacion'        => trim($e->Observacion ?? '') ?: null,
                    'cliente_de'         => trim($e->Cliente_De ?? '') ?: null,
                    'tipo_facturacion'   => trim($e->Tipo_Facturacion ?? '') ?: null,
                    'iva'                => trim($e->IVA ?? '') ?: null,
                    'correo'             => trim($e->Correo ?? '') ?: null,
                    'actividad_economica'=> is_string($e->Actividad_economica) ? trim($e->Actividad_economica) : null,
                    'aliado_id'          => $brygarId,
                    'updated_at'         => now(),
                    'created_at'         => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
