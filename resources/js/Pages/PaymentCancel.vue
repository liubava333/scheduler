<template>
  <AuthenticatedLayout>
    <!-- Заголовок страницы внутри вашего макета -->
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Оплата отменена
      </h2>
    </template>

    <!-- Основной контейнер -->
    <div class="cancel-container py-12">
      <div class="card cancel-card">
        <!-- Иконка предупреждения / отмены -->
        <div class="icon cancel-icon">!</div>

        <h2>Оформление прервано</h2>
        <p class="message">
          Вы отменили процесс оплаты или произошел сбой. Деньги с вашего счета списаны не были.
        </p>

        <!-- Детали / Пояснение -->
        <div class="info-box">
          <p>Если это произошло по ошибке, вы можете вернуться в корзину и попробовать еще раз.</p>
        </div>

        <!-- Кнопки действий -->
        <div class="actions">
          <a href="/checkout" class="btn btn-primary">Повторить попытку</a>
          <a href="/profile" class="btn btn-secondary">В личный кабинет</a>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
</script>

<style scoped>
.cancel-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 50vh;
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
  font-size: 2.5rem;
  width: 70px;
  height: 70px;
  line-height: 65px;
  margin: 0 auto 1.5rem;
  border-radius: 50%;
  font-weight: bold;
}
.cancel-icon {
  background: #fef7ea;
  color: #b06000;
  border: 2px solid #ffe2b3;
}
h2 {
  font-size: 1.5rem;
  color: #202124;
  margin-bottom: 0.5rem;
  font-weight: 600;
}
.message {
  color: #5f6368;
  margin-bottom: 1.5rem;
  font-size: 1rem;
  line-height: 1.5;
}
.info-box {
  background: #f8f9fa;
  padding: 1rem;
  border-radius: 8px;
  margin-bottom: 2rem;
  text-align: left;
  font-size: 0.9rem;
  color: #5f6368;
}
.actions {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.btn {
  display: inline-block;
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  text-decoration: none;
  font-weight: bold;
  width: 100%;
  box-sizing: border-box;
  transition: background 0.2s;
}
.btn-primary {
  background: #1a73e8;
  color: white;
}
.btn-primary:hover {
  background: #1557b0;
}
.btn-secondary {
  background: #f1f3f4;
  color: #3c4043;
}
.btn-secondary:hover {
  background: #e8eaed;
}
</style>
