<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caravanas', function (Blueprint $table) {
            $table->id();
            $table->string('rey')->unique();
            $table->string('ofrenda')->unique();
            $table->string('origen');
            $table->unsignedTinyInteger('camellos');
            $table->string('estado')->default('DETENIDA')->index();
            $table->unsignedInteger('dias_restantes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caravanas');
    }
};
