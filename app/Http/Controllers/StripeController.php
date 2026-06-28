<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use App\Models\User;

class StripeController extends Controller
{
    public function createSession(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($user->is_paid || $user->amount_to_pay === 0.00) {
            return response()->json(['error' => 'У вас немає заборгованості, абонемент вже сплачено! ✨'], 400);
        }

        $tariffPrice = $user->tariff_price ?? 160.00;
        $balance = $user->balance ?? 0;

        $amountToPay = $user->amount_to_pay ?? ($tariffPrice - $balance);

        if ($amountToPay <= 0) {
            return response()->json(['error' => 'У вас немає заборгованості для оплати.'], 400);
        }

        $amountInCents = (int) round($amountToPay * 100);

        Stripe::setApiKey(env('STRIPE_SECRET'));
        $session = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'Оплата абонемента: ' . ($user->tariff_name ?? 'Клуб танців'),
                        'description' => 'Продовження доступу для ' . $user->email,
                    ],
                    'unit_amount' =>  $amountInCents,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
            'success_url' => route('payment.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('payment.cancel'),
        ]);

        return response()->json(['url' => $session->url]);
    }

    /**
     * Сторінка успішної оплати (Обробка повернення зі Stripe)
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect()->route('balance.index')->with('error', 'Недійсне посилання сесії.');
        }

        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));
            $session = StripeSession::retrieve($sessionId);

            // Перевіряємо, чи платіж успішний
            if ($session->payment_status === 'paid') {

                // Перенаправляємо на сторінку Балансу з флеш-повідомленням успіху
                return redirect()->route('balance.index')->with('success', 'Оплата пройшла успішно! Ваш абонемент продовжено. 🎉');
            }

            return redirect()->route('balance.index')->with('error', 'Платіж не був підтверджений.');

        } catch (\Exception $e) {
            return redirect()->route('balance.index')->with('error', 'Помилка при перевірці платежу.');
        }
    }
}
