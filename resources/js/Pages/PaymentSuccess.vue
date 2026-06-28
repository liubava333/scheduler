<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Статус оплаты
      </h2>
    </template>
    <div class="success-container">
      <!-- Loading State -->
      <div v-if="loading" class="card loading-card">
        <div class="spinner"></div>
        <p>Verifying your payment, please wait...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="card error-card">
        <div class="icon error-icon">✕</div>
        <h2>Verification Failed</h2>
        <p>{{ error }}</p>
        <a href="/checkout" class="btn btn-secondary">Return to Checkout</a>
      </div>

      <!-- Success State -->
      <div v-else class="card success-card">
        <div class="icon success-icon">✓</div>
        <h2>Payment Successful!</h2>
        <p class="thank-you">Thank you for your purchase.</p>

        <div class="details" v-if="session">
          <div class="detail-row">
            <span>Amount Paid:</span>
            <strong>${{ session.amount.toFixed(2) }}</strong>
          </div>
          <div class="detail-row" v-if="session.customer_email">
            <span>Receipt sent to:</span>
            <strong>{{ session.customer_email }}</strong>
          </div>
        </div>

        <a href="/" class="btn btn-primary">На головну сторінку</a>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const loading = ref(true);
const error = ref(null);
const session = ref(null);

onMounted(async () => {
  // Extract session_id from URL query string
  const urlParams = new URLSearchParams(window.location.search);
  const sessionId = urlParams.get('session_id');

  if (!sessionId) {
    error.value = "No session ID found. We cannot verify this payment.";
    loading.value = false;
    return;
  }

  try {
    // Fetch data from Laravel API
    const response = await fetch(`/api/stripe/session-status/${sessionId}`);
    const data = await response.json();

    if (!response.ok) throw new Error(data.error || 'Failed to verify payment');

    // Double check that the payment state is actually paid
    if (data.payment_status === 'paid' || data.status === 'complete') {
      session.value = data;
    } else {
      error.value = "Your payment status is incomplete.";
    }
  } catch (err) {
    error.value = err.message || "An unexpected error occurred.";
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.success-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 70vh;
  font-family: sans-serif;
  background-color: #f9fafb;
}
.card {
  background: white;
  padding: 2.5rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  text-align: center;
  max-width: 450px;
  width: 100%;
}
.icon {
  font-size: 3rem;
  width: 70px;
  height: 70px;
  line-height: 70px;
  margin: 0 auto 1.5rem;
  border-radius: 50%;
}
.success-icon {
  background: #e6f4ea;
  color: #137333;
}
.error-icon {
  background: #fce8e6;
  color: #c5221f;
}
.thank-you {
  color: #5f6368;
  margin-bottom: 1.5rem;
}
.details {
  background: #f8f9fa;
  padding: 1rem;
  border-radius: 8px;
  margin-bottom: 2rem;
  text-align: left;
}
.detail-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
  font-size: 0.95rem;
}
.btn {
  display: inline-block;
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  text-decoration: none;
  font-weight: bold;
  width: 100%;
  box-sizing: border-box;
}
.btn-primary {
  background: #1a73e8;
  color: white;
}
.btn-secondary {
  background: #f1f3f4;
  color: #3c4043;
}
.spinner {
  border: 4px solid #f3f3f3;
  border-top: 4px solid #1a73e8;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
