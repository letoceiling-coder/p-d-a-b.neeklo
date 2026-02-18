<template>
  <div class="max-w-lg mx-auto p-4">
    <h1 class="text-xl font-semibold text-gray-900 mb-4">Профиль</h1>
    <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-3">
      <p class="text-sm">
        <span class="text-gray-500">Имя:</span>
        <span class="font-medium">{{ user?.name }}</span>
      </p>
      <p class="text-sm">
        <span class="text-gray-500">Email:</span>
        <span class="font-medium">{{ user?.email }}</span>
      </p>
      <p class="text-sm">
        <span class="text-gray-500">Роль:</span>
        <span class="font-medium">{{ roleLabel }}</span>
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../../stores/auth.js';

defineOptions({
  name: 'AppProfilePage',
});

const authStore = useAuthStore();
const user = computed(() => authStore.user);

const roleLabel = computed(() => {
  const r = user.value?.role;
  return r === 'admin' ? 'Администратор' : 'Сотрудник';
});
</script>
