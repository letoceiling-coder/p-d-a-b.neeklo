<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <router-link
        to="/admin/contract-analyses"
        class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        К списку
      </router-link>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-2 border-blue-500 border-t-transparent"></div>
    </div>

    <div v-else-if="analysis" class="space-y-6">
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h1 class="text-xl font-bold text-gray-900 mb-4">Анализ #{{ analysis.id }}</h1>
        <div class="flex flex-wrap gap-4 text-sm text-gray-600 mb-4">
          <template v-if="analysis.user">
            <span>{{ analysis.user.name }}</span>
            <span class="text-gray-500">{{ analysis.user.email }}</span>
            <span class="text-gray-400">(веб)</span>
          </template>
          <template v-else>
            <span>{{ analysis.bot_user?.first_name }} {{ analysis.bot_user?.last_name }}</span>
            <span v-if="analysis.bot_user?.username" class="text-gray-500">@{{ analysis.bot_user.username }}</span>
            <span class="text-gray-400">(бот)</span>
          </template>
          <span>{{ formatDate(analysis.created_at) }}</span>
        </div>
        <div v-if="analysis.user_id && analysis.pdf_path" class="mb-4">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
            @click="downloadPdf"
          >
            Скачать PDF
          </button>
        </div>

        <section class="mb-6">
          <h2 class="text-sm font-semibold text-gray-700 mb-2">Обработанные файлы</h2>
          <ul v-if="fileInfoList.length" class="space-y-2">
            <li
              v-for="(f, i) in fileInfoList"
              :key="i"
              class="flex items-center gap-2 text-sm text-gray-800 bg-gray-50 rounded-lg px-3 py-2"
            >
              <span v-if="f.type === 'document'" class="text-blue-600">📄</span>
              <span v-else-if="f.type === 'photo'" class="text-green-600">🖼</span>
              <span v-else>📎</span>
              <span>{{ f.name }}</span>
            </li>
          </ul>
          <p v-else class="text-sm text-gray-500">Данные о файлах отсутствуют (запись создана до обновления системы).</p>
          <p v-if="fileInfoList.length" class="text-xs text-gray-500 mt-2">Исходные файлы не хранятся, отображаются только имена.</p>
        </section>

        <section>
          <h2 class="text-sm font-semibold text-gray-700 mb-2">Выжимка</h2>
          <pre class="text-sm text-gray-800 whitespace-pre-wrap bg-gray-50 p-4 rounded-lg border border-gray-200">{{ analysis.summary_text }}</pre>
        </section>

        <section v-if="analysis.summary_json && analysis.summary_json.length" class="mt-6">
          <h2 class="text-sm font-semibold text-gray-700 mb-2">Структурированные данные (JSON)</h2>
          <pre class="text-xs text-gray-700 bg-gray-50 p-4 rounded-lg border border-gray-200 overflow-x-auto">{{ JSON.stringify(analysis.summary_json, null, 2) }}</pre>
        </section>
      </div>
    </div>

    <div v-else class="bg-white rounded-lg border border-gray-200 p-8 text-center text-gray-500">
      Анализ не найден или удалён.
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import apiClient from '@/api/axios.js';

const route = useRoute();
const loading = ref(true);
const analysis = ref(null);

const fileInfoList = computed(() => {
  const fi = analysis.value?.file_info;
  if (!Array.isArray(fi) || fi.length === 0) return [];
  return fi;
});

function formatDate(val) {
  if (!val) return '—';
  return new Date(val).toLocaleString('ru-RU');
}

async function fetchDetail() {
  const id = route.params.id;
  if (!id) return;
  loading.value = true;
  try {
    const res = await apiClient.get(`/contract-analyses/${id}`);
    analysis.value = res.data;
  } catch (_) {
    analysis.value = null;
  } finally {
    loading.value = false;
  }
}

async function downloadPdf() {
  const id = route.params.id;
  if (!id) return;
  try {
    const response = await apiClient.get(`/app/analyses/${id}/download-pdf`, { responseType: 'blob' });
    const blob = response.data;
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `otchet-analiz-${id}.pdf`;
    a.click();
    window.URL.revokeObjectURL(url);
  } catch (e) {
    alert(e.response?.data?.message ?? 'Ошибка скачивания PDF');
  }
}

onMounted(fetchDetail);
watch(() => route.params.id, fetchDetail);
</script>
