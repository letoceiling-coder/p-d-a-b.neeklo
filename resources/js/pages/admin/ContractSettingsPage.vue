<template>
  <div class="space-y-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Настройки анализа</h1>
      <p class="text-gray-600 mt-1">Режим выдачи результата в Telegram, лимиты и модель AI по умолчанию.</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-2 border-blue-500 border-t-transparent"></div>
    </div>

    <form v-else class="bg-white rounded-lg border border-gray-200 p-6 space-y-6" @submit.prevent="save">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Формат выдачи в Telegram</label>
        <select v-model="form.telegram_summary_mode" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full max-w-xs">
          <option value="full">Полная выжимка (одно сообщение)</option>
          <option value="short">Краткая выжимка (одно сообщение)</option>
          <option value="both">Сначала краткая, затем полная (два сообщения)</option>
        </select>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Макс. символов в сообщении (полная выжимка)</label>
          <input v-model.number="form.telegram_max_message_chars" type="number" min="100" max="4096" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Длина краткой выжимки (символов)</label>
          <input v-model.number="form.telegram_short_summary_chars" type="number" min="100" max="4096" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Макс. фото в одном запросе</label>
          <input v-model.number="form.max_photos_per_request" type="number" min="1" max="20" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Срок хранения анализов (месяцев)</label>
          <input v-model.number="form.analysis_retention_months" type="number" min="1" max="120" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full" />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Модель AI по умолчанию для анализа договоров</label>
        <select v-model.number="form.default_ai_model_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full max-w-md">
          <option :value="0">Первая активная модель (по порядку)</option>
          <option v-for="m in aiModels" :key="m.id" :value="m.id">{{ m.name }} ({{ m.provider }})</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Системный промпт для AI</label>
        <p class="text-xs text-gray-500 mb-2">Этот текст задаёт инструкцию модели при анализе договора. Пустое поле = значение по умолчанию (выжимка по 15–20 пунктам).</p>
        <textarea
          v-model="form.ai_system_prompt"
          rows="14"
          class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full font-mono"
          placeholder="Оставьте пустым для стандартного промпта..."
        />
      </div>

      <div class="flex items-center gap-4">
        <button
          type="submit"
          class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          :disabled="saving"
        >
          {{ saving ? 'Сохранение...' : 'Сохранить' }}
        </button>
        <p v-if="success" class="text-sm text-green-600">Настройки сохранены.</p>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '@/api/axios.js';

const loading = ref(true);
const saving = ref(false);
const success = ref(false);
const error = ref('');
const form = ref({
  telegram_summary_mode: 'full',
  telegram_max_message_chars: 4090,
  telegram_short_summary_chars: 600,
  max_photos_per_request: 5,
  analysis_retention_months: 6,
  default_ai_model_id: 0,
  ai_system_prompt: '',
});
const aiModels = ref([]);

async function fetchData() {
  loading.value = true;
  try {
    const res = await apiClient.get('/contract-settings');
    const s = res.data.settings || {};
    form.value = {
      telegram_summary_mode: s.telegram_summary_mode ?? 'full',
      telegram_max_message_chars: s.telegram_max_message_chars ?? 4090,
      telegram_short_summary_chars: s.telegram_short_summary_chars ?? 600,
      max_photos_per_request: s.max_photos_per_request ?? 5,
      analysis_retention_months: s.analysis_retention_months ?? 6,
      default_ai_model_id: s.default_ai_model_id ?? 0,
      ai_system_prompt: (s.ai_system_prompt != null ? s.ai_system_prompt : '') || '',
    };
    aiModels.value = res.data.ai_models || [];
  } catch (_) {
    error.value = 'Не удалось загрузить настройки';
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  success.value = false;
  error.value = '';
  try {
    await apiClient.put('/contract-settings', form.value);
    success.value = true;
  } catch (e) {
    error.value = e.response?.data?.message || 'Ошибка сохранения';
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  fetchData();
});
</script>
