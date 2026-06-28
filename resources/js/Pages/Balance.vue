<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Мій кабінет танцюриста 💃
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- Головна картка стану рахунку -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="border-b border-gray-200 pb-4 mb-6">
                        <h3 class="text-lg font-medium text-gray-900">Фінансовий стан абонемента</h3>
                        <p class="text-sm text-gray-500">Інформація про ваші поточні розрахунки із клубом.</p>
                    </div>

                    <!-- Сітка з інформаційними блоками -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

                        <!-- Поточний тариф -->
                        <div class="p-4 bg-purple-50 rounded-xl border border-purple-100">
                            <span class="text-xs font-semibold text-purple-600 uppercase tracking-wider">Ваш абонемент</span>
                            <div class="text-xl font-bold text-gray-800 mt-1">{{ payment.tariff_name }}</div>
                            <div class="text-sm text-gray-600 mt-1">Вартість: <span class="font-semibold">{{ payment.tariff_price }} ₴</span> / міс.</div>
                        </div>

                        <!-- Баланс рахунку -->
                        <div class="p-4 bg-green-50 rounded-xl border border-green-100">
                            <span class="text-xs font-semibold text-green-600 uppercase tracking-wider">На балансі</span>
                            <div class="text-3xl font-black text-green-700 mt-1">{{ payment.balance }} ₴</div>
                            <p class="text-xs text-gray-500 mt-2">Ці кошти враховуються при розрахунку суми наступного платежу.</p>
                        </div>

                        <!-- Скільки треба сплатити -->
                        <div class="p-4 bg-red-50 rounded-xl border border-red-100">
                            <span class="text-xs font-semibold text-red-600 uppercase tracking-wider">До сплати</span>
                            <div class="text-3xl font-black text-red-700 mt-1">{{ payment.amount_to_pay }} ₴</div>
                            <p class="text-xs text-gray-500 mt-2">З урахуванням вашого поточного балансу.</p>
                        </div>

                        <!-- Коли треба сплатити / Наступний платіж -->
                        <div
                            :class="[ payment.is_paid
                                ? 'bg-blue-50 border-blue-100'
                                : 'bg-amber-50 border-amber-100',
                                'p-4 rounded-xl border flex flex-col justify-between']"
                        >
                            <div>
                                <span
                                    :class="payment.is_paid ? 'text-blue-600' : 'text-amber-600'"
                                    class="text-xs font-semibold uppercase tracking-wider"
                                >
                                    {{ payment.is_paid ? 'Цього місяця сплачено ✨' : 'Оплатити до' }}
                                </span>

                                <div
                                    :class="payment.is_paid ? 'text-blue-800' : 'text-amber-800'"
                                    class="text-2xl font-bold mt-1"
                                >
                                    {{ payment.is_paid ? 'Наступний: ' + payment.due_date : payment.due_date }}
                                </div>
                            </div>

                            <p :class="payment.is_paid ? 'text-blue-600' : 'text-amber-600'" class="text-xs mt-2">
                                {{ payment.is_paid
                                ? 'Дякуємо! Абонемент активний. Наступне списання відбудеться за вказаною датою.'
                                : 'Будь ласка, внесіть оплату вчасно, щоб не втратити запис у групу.'
                                }}
                            </p>
                        </div>

                    </div>

                    <!-- Блок оплати -->
                    <div class="bg-gray-50 -mx-6 -mb-6 p-6 flex flex-col sm:flex-row items-center justify-between gap-4 rounded-b-lg border-t border-gray-100">
                        <div class="text-sm text-gray-600 text-center sm:text-left">
                            Натискаючи кнопку, ви перейдете на захищену сторінку оплати <span class="font-semibold text-indigo-600">Stripe</span>.
                        </div>
                        <button
                            @click="handlePayment"
                            :disabled="isProcessing || payment.amount_to_pay <= 0"
                            class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        >
                            <svg v-if="isProcessing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{  payment.amount_to_pay <= 0 ? 'Все сплачено ✨' : 'Перейти до оплати ' + payment.amount_to_pay + ' ₴' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';
import axios from 'axios';

// Отримуємо пропси від Laravel Inertia
const props = defineProps({
    payment: {
        type: Object,
        required: true
    }
});

const isProcessing = ref(false);

const handlePayment = async () => {
    if (props.payment.amount_to_pay <= 0) return;

    isProcessing.value = true;

    try {
        const response = await axios.post('/payment/create-checkout-session', { amount: props.payment.amount_to_pay });
        if (response.data.url) {
            // Редирект на платіжну сторінку Stripe Checkout
            window.location.href = response.data.url;
        } else {
            alert('Помилка: не вдалося отримати посилання на оплату.');
            isProcessing.value = false;
        }
    } catch (error) {
        console.error('Payment error:', error);

        // 1. Проверяем, пришел ли ответ с текстом ошибки от Laravel
        if (error.response && error.response.data && error.response.data.error) {
            // Выводим текст ошибки, который вернул сервер
            alert(error.response.data.error);
        } else {
            // 2. Если сервер "упал" (500) или нет сети, показываем стандартную заглушку
            alert('Сталася помилка при ініціалізації платежу. Спробуйте пізніше.');
        }

        isProcessing.value = false;
    }
};
</script>
