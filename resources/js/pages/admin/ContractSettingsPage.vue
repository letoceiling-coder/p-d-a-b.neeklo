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

      <hr class="border-gray-200 my-6" />
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Тексты и поддержка бота (TZ_UX)</h2>

      <div class="grid grid-cols-1 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Текст главного экрана (IDLE)</label>
          <textarea v-model="form.welcome_text" rows="4" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full" placeholder="Главное меню..." />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Текст «Доступ ограничен» (UNAUTHORIZED)</label>
          <textarea v-model="form.unauthorized_text" rows="4" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Текст экрана загрузки (UPLOAD)</label>
          <textarea v-model="form.upload_text" rows="4" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Текст «Информация» (ℹ️)</label>
          <textarea v-model="form.info_text" rows="4" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full" />
        </div>
      </div>

      <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-4">
        <h3 class="font-medium text-gray-800">Поддержка</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Имя</label>
            <input v-model="form.support_name" type="text" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Telegram</label>
            <input v-model="form.support_tg" type="text" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full" placeholder="@username или ссылка" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input v-model="form.support_email" type="text" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Часы работы</label>
            <input v-model="form.support_hours" type="text" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full" placeholder="Пн–Пт 9:00–18:00" />
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="form.allow_public_info" type="checkbox" class="rounded border-gray-300" />
            <span class="text-sm font-medium text-gray-700">Показывать «Информация» и «Поддержка» неавторизованным</span>
          </label>
          <p class="text-xs text-gray-500 mt-1">Если выключено — только авторизованные увидят эти экраны</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Код доступа (OTP)</label>
          <input v-model="form.bot_otp_code" type="text" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full max-w-xs" placeholder="Оставьте пустым для только whitelist" />
          <p class="text-xs text-gray-500 mt-1">Если задан — пользователь может ввести его в боте для авторизации</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Количество записей в истории (в боте)</label>
          <input v-model.number="form.history_limit" type="number" min="1" max="50" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-full max-w-xs" />
        </div>
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
  welcome_text: '',
  unauthorized_text: '',
  upload_text: '',
  processing_text: '',
  busy_text: '',
  error_file_text: '',
  info_text: '',
  compare_stub_text: '',
  support_name: '',
  support_tg: '',
  support_email: '',
  support_hours: '',
  support_text: '',
  allow_public_info: true,
  bot_otp_code: '',
  history_limit: 10,
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
      welcome_text: s.welcome_text ?? '',
      unauthorized_text: s.unauthorized_text ?? '',
      upload_text: s.upload_text ?? '',
      processing_text: s.processing_text ?? '',
      busy_text: s.busy_text ?? '',
      error_file_text: s.error_file_text ?? '',
      info_text: s.info_text ?? '',
      compare_stub_text: s.compare_stub_text ?? '',
      support_name: s.support_name ?? '',
      support_tg: s.support_tg ?? '',
      support_email: s.support_email ?? '',
      support_hours: s.support_hours ?? '',
      support_text: s.support_text ?? '',
      allow_public_info: s.allow_public_info !== false,
      bot_otp_code: s.bot_otp_code ?? '',
      history_limit: s.history_limit ?? 10,
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
