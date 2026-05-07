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
        // 1. Сначала преобразуем формат строк внутри БД (заменяем T на пробел)
        // Это нужно, чтобы MySQL смог сконвертировать строку в timestamp
        DB::statement("UPDATE additional_cells SET start = REPLACE(start, 'T', ' ')");

        // 2. Меняем тип колонки
        Schema::table('additional_cells', function (Blueprint $table) {
            $table->timestamp('start')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('additional_cells', function (Blueprint $table) {
            $table->string('start')->change();
        });
    }
};
