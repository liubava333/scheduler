<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'tariff_price', 'balance', 'is_paid', 'amount_to_pay'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'tariff_price' => 'float',
            'balance' => 'float',
            'is_paid' => 'boolean',
        ];
    }

    /**
     * Виртуальное пополнение баланса с автоматическим пересчетом остатка к оплате.
     */
    public function deposit(float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        // 1. Безопасно увеличиваем баланс в базе данных (защита от race condition)
        $this->increment('balance', $amount);

        // 2. Обновляем текущую модель актуальными данными из БД
        $this->refresh();

        // 3. Считаем разницу: цена тарифа минус новый баланс
        $diff = $this->tariff_price - $this->balance;

        // 4. Применяем ваше условие:
        // Если разница меньше или равна 0 (баланс больше или равен тарифу) -> пишем 0
        // Если разница больше 0 (баланса не хватает) -> пишем эту разницу
        $newAmountToPay = $diff > 0 ? $diff : 0;

        // 5. Записываем результат в поле amount_to_pay
        return $this->update([
            'amount_to_pay' => $newAmountToPay
        ]);
    }
}
