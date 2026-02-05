<template>
  <div class="space-y-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">История анализов</h1>
      <p class="text-gray-600 mt-1">Результаты анализа договоров, загруженных через бота. В детальном просмотре видны имена обработанных файлов.</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-2 border-blue-500 border-t-transparent"></div>
    </div>

    <template v-else>
      <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Пользователь</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Выжимка (начало)</th>
              <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-sm text-gray-600">{{ item.id }}</td>
              <td class="px-4 py-2 text-sm text-gray-900">{{ item.bot_user?.first_name }} {{ item.bot_user?.last_name }} <span v-if="item.bot_user?.username" class="text-gray-500">@{{ item.bot_user.username }}</span></td>
              <td class="px-4 py-2 text-sm text-gray-600">{{ formatDate(item.created_at) }}</td>
              <td class="px-4 py-2 text-sm text-gray-700 max-w-xs truncate">{{ preview(item.summary_text) }}</td>
              <td class="px-4 py-2 text-right">
                <router-link
                  :to="{ name: 'admin.contract-analysis-detail', params: { id: item.id } }"
                  class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                >
                  Подробнее
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="items.length === 0" class="px-6 py-8 text-center text-gray-500">
          Анализов пока нет. Они появятся после загрузки договоров через бота.
        </div>
      </div>

      <div v-if="pagination.last_page > 1" class="flex justify-center gap-2">
        <button
          type="button"
          class="rounded-md border border-gray-300 px-3 py-1 text-sm disabled:opacity-50"
          :disabled="pagination.current_page <= 1"
          @click="fetchPage(pagination.current_page - 1)"
        >
          Назад
        </button>
        <span class="px-3 py-1 text-sm text-gray-600">
          {{ pagination.current_page }} / {{ pagination.last_page }}
        </span>
        <button
          type="button"
          class="rounded-md border border-gray-300 px-3 py-1 text-sm disabled:opacity-50"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="fetchPage(pagination.current_page + 1)"
        >
          Вперёд
        </button>
      </div>
    </template>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '@/api/axios.js';

const loading = ref(true);
const items = ref([]);
const pagination = ref({ current_page: 1, last_page: 1 });

function formatDate(val) {
  if (!val) return '—';
  const d = new Date(val);
  return d.toLocaleString('ru-RU');
}

function preview(text) {
  if (!text) return '—';
  return text.length > 80 ? text.slice(0, 80) + '…' : text;
}

async function fetchList(page = 1) {
  loading.value = true;
  try {
    const res = await apiClient.get('/contract-analyses', { params: { page, per_page: 20 } });
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

function fetchPage(page) {
  fetchList(page);
}

onMounted(() => {
  fetchList(1);
});
</script>
