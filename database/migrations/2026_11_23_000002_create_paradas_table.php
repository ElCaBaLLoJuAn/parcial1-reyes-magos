<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caravana_id')->constrained('caravanas')->cascadeOnDelete();
            $table->string('ciudad');
            $table->unsignedInteger('orden');
            $table->unsignedTinyInteger('dias_estancia');
            $table->string('estado')->default('PLANIFICADA');
            $table->timestamps();
            $table->unique(['caravana_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paradas');
    }
};
