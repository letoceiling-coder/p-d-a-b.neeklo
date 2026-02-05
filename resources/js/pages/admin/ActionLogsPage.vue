<template>
  <div class="space-y-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Логи действий</h1>
      <p class="text-gray-600 mt-1">История действий администраторов в панели.</p>
    </div>

    <div class="flex gap-2 mb-4">
      <select v-model="filterAction" class="rounded-md border border-gray-300 px-3 py-2 text-sm" @change="fetchLogs(1)">
        <option value="">Все действия</option>
        <option v-for="a in actionOptions" :key="a" :value="a">{{ actionLabel(a) }}</option>
      </select>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-2 border-blue-500 border-t-transparent"></div>
    </div>

    <div v-else class="bg-white rounded-lg border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Действие</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Пользователь</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Сущность</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="log in items" :key="log.id" class="hover:bg-gray-50">
            <td class="px-4 py-2 text-sm text-gray-600">{{ formatDate(log.created_at) }}</td>
            <td class="px-4 py-2 text-sm text-gray-900">{{ actionLabel(log.action) }}</td>
            <td class="px-4 py-2 text-sm text-gray-700">{{ log.user?.name || '—' }} <span class="text-gray-500">{{ log.user?.email }}</span></td>
            <td class="px-4 py-2 text-sm text-gray-600">{{ log.entity_type }} {{ log.entity_id ? '#' + log.entity_id : '' }}</td>
            <td class="px-4 py-2 text-sm text-gray-500">{{ log.ip || '—' }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="items.length === 0" class="px-6 py-8 text-center text-gray-500">
        Записей пока нет.
      </div>
    </div>

    <div v-if="pagination.last_page > 1" class="flex justify-center gap-2">
      <button
        type="button"
        class="rounded-md border border-gray-300 px-3 py-1 text-sm disabled:opacity-50"
        :disabled="pagination.current_page <= 1"
        @click="fetchLogs(pagination.current_page - 1)"
      >
        Назад
      </button>
      <span class="px-3 py-1 text-sm text-gray-600">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
      <button
        type="button"
        class="rounded-md border border-gray-300 px-3 py-1 text-sm disabled:opacity-50"
        :disabled="pagination.current_page >= pagination.last_page"
        @click="fetchLogs(pagination.current_page + 1)"
      >
        Вперёд
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '@/api/axios.js';

const loading = ref(true);
const items = ref([]);
const pagination = ref({ current_page: 1, last_page: 1 });
const filterAction = ref('');
const actionOptions = ref([
  'access_request.approved',
  'access_request.rejected',
  'bot.created',
  'bot.updated',
  'bot.settings_updated',
  'ai.keys_updated',
  'ai.model_created',
  'ai.model_updated',
  'ai.model_deleted',
  'contract_settings.updated',
]);

const actionLabels = {
  'access_request.approved': 'Одобрен доступ',
  'access_request.rejected': 'Отклонён доступ',
  'bot.created': 'Создан бот',
  'bot.updated': 'Обновлён бот',
  'bot.settings_updated': 'Настройки бота',
  'ai.keys_updated': 'Обновлены ключи AI',
  'ai.model_created': 'Добавлена модель AI',
  'ai.model_updated': 'Обновлена модель AI',
  'ai.model_deleted': 'Удалена модель AI',
  'contract_settings.updated': 'Настройки анализа',
};

function actionLabel(action) {
  return actionLabels[action] || action;
}

function formatDate(val) {
  if (!val) return '—';
  return new Date(val).toLocaleString('ru-RU');
}

async function fetchLogs(page = 1) {
  loading.value = true;
  try {
    const params = { page, per_page: 20 };
    if (filterAction.value) params.action = filterAction.value;
    const res = await apiClient.get('/action-logs', { params });
    items.value = res.data.data ?? [];
    pagination.value = {
      current_page: res.data.current_page ?? 1,
      last_page: res.data.last_page ?? 1,
    };
  } catch (_) {
    items.value = [];
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchLogs(1);
});
</script>
