<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = env('STRIPE_WEBHOOK_SECRET');

        try {
            // Верификация, что запрос пришел именно от Stripe, а не от мошенников
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Обрабатываем только успешные платежи
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            // Вытаскиваем user_id, который мы ранее бережно сохранили в metadata в StripeController
            $userId = $session->metadata->user_id ?? null;
            $amountPaid = $session->amount_total / 100;  // Переводим обратно в гривны/доллары

            if ($userId) {
                $user = User::find($userId);
                if ($user) {
                    $user->update([
                        'is_paid' => true,         // Абонемент официально оплачен
                        'amount_to_pay' => 0.00,   // Обнуляем долг, вычислять больше не нужно
                        'balance' => 0.00          // Если баланс учитывался при оплате, обнуляем его
                    ]);

                    Log::info("Вебхук Stripe: Пользователь {$userId} успешно оплатил {$amountPaid}. Долг обнулен, абонемент активен.");
                } else {
                    Log::error("Пользователь с ID {$userId} не найден в базе данных при обработке вебхука.");
                }
            }
        }

        // Stripe требует всегда возвращать 200 OK, иначе он будет присылать это уведомление повторно
        return response()->json(['status' => 'success'], 200);
    }
}
