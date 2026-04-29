<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_cells', function (Blueprint $table) {
            $table->id();
            // Сначала создаем колонку
            $table->unsignedBigInteger('event_id');

            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->onDelete('cascade'); // This handles the automatic deletion
            $table->string('start');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_cells');
    }
};
