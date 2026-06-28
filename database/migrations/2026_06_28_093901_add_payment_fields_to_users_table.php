<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Прапорець: чи оплачено поточний місяць
            $table->boolean('is_paid')->default(false);
            // Поле для зберігання фіксованої суми (щоб занулювати її)
            $table->decimal('amount_to_pay', 8, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_paid', 'amount_to_pay']);
        });
    }
};
