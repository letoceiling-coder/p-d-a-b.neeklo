<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
    <div class="w-full max-w-md space-y-6">
      <div class="text-center">
        <h1 class="text-3xl font-bold text-gray-900">Вход в систему</h1>
        <p class="text-gray-600 mt-2">Введите ваши учетные данные</p>
      </div>
      <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-800">
            {{ error }}
          </div>
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
              placeholder="your@email.com"
            />
          </div>
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Пароль</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
              placeholder="••••••••"
            />
          </div>
          <button
            type="submit"
            :disabled="loading"
            class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <span v-if="!loading">Войти</span>
            <span v-else>Вход...</span>
          </button>
        </form>
        <p class="mt-4 text-center text-sm text-gray-500">
          Нет аккаунта?
          <router-link to="/register" class="font-medium text-blue-600 hover:text-blue-500">Регистрация по invite-коду</router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';

const router = useRouter();
const authStore = useAuthStore();

const form = ref({
  email: '',
  password: '',
});

const loading = computed(() => authStore.loading);
const error = computed(() => authStore.error);

onMounted(() => {
  authStore.clearError();
});

const handleSubmit = async () => {
  const result = await authStore.login(form.value);
  if (result.success) {
    const redirect = router.currentRoute.value.query.redirect || '/app';
    router.push(redirect);
  }
};
</script>
