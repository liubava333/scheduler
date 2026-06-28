<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class BalanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Якщо статус в базі true, то до сплати автоматично 0
        if ($user->is_paid) {
            $amountToPay = 0.00;
            $dueDate = Carbon::now()->addMonth()->startOfMonth()->format('d.m.Y');
        } else {
            $tariffPrice = $user->tariff_price ?? 160.00;
            $balance = $user->balance ?? 0;
            $amountToPay = $user->amount_to_pay ?? ($tariffPrice - $balance);
            $dueDate = Carbon::now()->addDays(5)->format('d.m.Y');
        }

        $paymentData = [
            'balance' => $user->balance ?? 150.00,
            'tariff_name' => 'Premium Single (Latina + High Heels)',
            'tariff_price' => $user->tariff_price ?? 160.00,
            'amount_to_pay' => $amountToPay, // Буде 0, якщо оплачено
            'due_date' => $dueDate,
            'is_paid' => (bool)$user->is_paid,
        ];

        return Inertia::render('Balance', [
            'payment' => $paymentData
        ]);
    }
}
