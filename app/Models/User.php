<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
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

        // 1. Безпечно збільшуємо баланс в БД (захист від race condition)
        $this->increment('balance', $amount);
        $this->refresh();

        // 2. Рахуємо різницю між ціною тарифу та поточним балансом
        $diff = $this->tariff_price - $this->balance;
        if ($diff <= 0) {
            // ВИПАДОК 1: Балансу достатньо для оплати тарифу
            // Списуємо гроші з балансу на оплату тарифу (баланс зменшується на ціну тарифу)
            $newBalance = $this->balance - $this->tariff_price;
            $newAmountToPay = 0;
            $isPaid = true;
        } else {
            // ВИПАДОК 2: Балансу все ще не вистачає
            $newBalance = $this->balance; // Гроші залишаються на балансі
            $newAmountToPay = $diff;       // Треба доплатити різницю
            $isPaid = false;
        }

        // 3. Записуємо всі оновлені дані в базу
        return $this->update([
            'balance'       => $newBalance,
            'amount_to_pay' => $newAmountToPay,
            'is_paid'       => $isPaid,
        ]);
    }
}
