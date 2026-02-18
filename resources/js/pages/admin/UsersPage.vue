<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Пользователи</h1>
      <p class="text-gray-600 mt-1">Список пользователей веб-сервиса. Отключение доступа не удаляет записи анализов.</p>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-2 border-gray-300 border-t-gray-600"></div>
    </div>

    <div v-else class="bg-white rounded-lg border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Имя</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Роль</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Доступ</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="u in items" :key="u.id" class="text-sm">
            <td class="px-4 py-3 font-medium text-gray-900">{{ u.name }}</td>
            <td class="px-4 py-3 text-gray-600">{{ u.email }}</td>
            <td class="px-4 py-3 text-gray-600">{{ u.role === 'admin' ? 'Администратор' : 'Сотрудник' }}</td>
            <td class="px-4 py-3">
              <span
                :class="u.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                class="rounded-full px-2 py-0.5 text-xs font-medium"
              >
                {{ u.is_active ? 'Включён' : 'Отключён' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <button
                v-if="u.id !== currentUserId && u.role !== 'admin'"
                type="button"
                class="text-sm font-medium"
                :class="u.is_active ? 'text-amber-600 hover:text-amber-800' : 'text-green-600 hover:text-green-800'"
                @click="toggleActive(u)"
              >
                {{ u.is_active ? 'Отключить доступ' : 'Включить доступ' }}
              </button>
              <span v-else class="text-gray-400">—</span>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!items.length" class="px-6 py-8 text-center text-gray-500">Нет пользователей</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import apiClient from '@/api/axios.js';
import { useAuthStore } from '@/stores/auth.js';

const authStore = useAuthStore();
const currentUserId = computed(() => authStore.user?.id);

const loading = ref(true);
const items = ref([]);

async function fetchList() {
  loading.value = true;
  try {
    const res = await apiClient.get('/users', { params: { per_page: 100 } });
    items.value = res.data.data ?? res.data ?? [];
    if (Array.isArray(res.data) && !items.value.length) {
      items.value = res.data;
    }
  } catch (_) {
    items.value = [];
  } finally {
    loading.value = false;
  }
}

async function toggleActive(user) {
  try {
    await apiClient.put(`/users/${user.id}`, { is_active: !user.is_active });
    user.is_active = !user.is_active;
  } catch (e) {
    alert(e.response?.data?.message ?? 'Ошибка');
  }
}

onMounted(fetchList);
</script>
