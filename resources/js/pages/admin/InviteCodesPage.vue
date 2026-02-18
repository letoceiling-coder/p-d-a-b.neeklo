<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Invite-коды</h1>
        <p class="text-gray-600 mt-1">Генерация кодов для регистрации сотрудников в веб-сервисе анализа договоров.</p>
      </div>
      <div class="flex items-center gap-2">
        <input
          v-model.number="expiresInDays"
          type="number"
          min="1"
          max="365"
          placeholder="Срок (дней)"
          class="rounded-md border border-gray-300 px-3 py-2 text-sm w-24"
        />
        <button
          type="button"
          :disabled="creating"
          class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
          @click="createCode"
        >
          {{ creating ? 'Создание...' : 'Создать код' }}
        </button>
      </div>
    </div>

    <div v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-800">
      {{ error }}
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-2 border-gray-300 border-t-gray-600"></div>
    </div>

    <div v-else class="bg-white rounded-lg border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Код</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Создан</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Использован</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Срок</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="item in items" :key="item.id" class="text-sm">
            <td class="px-4 py-3 font-mono font-medium">{{ item.code }}</td>
            <td class="px-4 py-3 text-gray-600">{{ formatDate(item.created_at) }}</td>
            <td class="px-4 py-3 text-gray-600">
              <span v-if="item.used_by_user">{{ item.used_by_user.name ?? item.used_by_user.email }}</span>
              <span v-else class="text-gray-400">—</span>
            </td>
            <td class="px-4 py-3 text-gray-600">
              <span v-if="item.expires_at">{{ formatDate(item.expires_at) }}</span>
              <span v-else class="text-gray-400">Без срока</span>
            </td>
          </tr>
          <tr v-if="!items.length">
            <td colspan="4" class="px-4 py-8 text-center text-gray-500">Нет invite-кодов</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '@/api/axios.js';

const items = ref([]);
const loading = ref(true);
const creating = ref(false);
const error = ref(null);
const expiresInDays = ref(30);

function formatDate(v) {
  if (!v) return '—';
  const d = new Date(v);
  return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

async function fetchList() {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await apiClient.get('/invite-codes');
    items.value = data.data ?? (Array.isArray(data) ? data : []);
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Ошибка загрузки';
    items.value = [];
  } finally {
    loading.value = false;
  }
}

async function createCode() {
  creating.value = true;
  error.value = null;
  try {
    const payload = expiresInDays.value ? { expires_in_days: expiresInDays.value } : {};
    const { data } = await apiClient.post('/invite-codes', payload);
    await fetchList();
  } catch (e) {
    error.value = e.response?.data?.message ?? e.response?.data?.errors?.invite_code?.[0] ?? 'Ошибка создания';
  } finally {
    creating.value = false;
  }
}

onMounted(fetchList);
</script>
