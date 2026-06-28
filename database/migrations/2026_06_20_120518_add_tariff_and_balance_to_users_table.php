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
        Schema::table('users', function (Blueprint $table) {
            // Поле для стоимости тарифа. Округляем до 2 знаков после запятой (например, 1200.00)
            // По умолчанию ставим 0.00, чтобы у старых юзеров не было ошибки
            $table->decimal('tariff_price', 10, 2)->default(0.00)->after('email');

            // Поле для текущего баланса пользователя
            $table->decimal('balance', 10, 2)->default(0.00)->after('tariff_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Удаляем поля, если нужно будет откатить миграцию назад
            $table->dropColumn(['tariff_price', 'balance']);
        });
    }
};
