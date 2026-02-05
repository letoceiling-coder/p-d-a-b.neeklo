<template>
  <div class="space-y-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Настройки бота</h1>
      <p class="text-gray-600 mt-1">Настройте Telegram-бота. Бот может быть только один.</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-2 border-blue-500 border-t-transparent"></div>
    </div>

    <template v-else>
      <!-- Токен и обновление -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Токен бота</h2>
        <p class="text-sm text-gray-500 mb-2">Получите токен у @BotFather в Telegram</p>
        <div class="space-y-4">
          <div>
            <label for="token" class="block text-sm font-medium text-gray-700 mb-1">Токен бота</label>
            <input
              id="token"
              v-model="form.token"
              type="text"
              class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
              placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
            />
          </div>
          <div v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ error }}</div>
          <div v-if="success" class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ success }}</div>
          <p v-if="!bot" class="text-sm text-blue-600 bg-blue-50 rounded-md p-2">
            При сохранении токена webhook будет автоматически создан.
          </p>
          <div class="flex gap-3">
            <button
              type="button"
              :disabled="saving"
              class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
              @click="saveBot"
            >
              {{ saving ? 'Сохранение...' : (bot ? 'Обновить бота' : 'Сохранить токен') }}
            </button>
            <button
              type="button"
              :disabled="testing || !bot"
              class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
              @click="testWebhook"
            >
              {{ testing ? 'Проверка...' : 'Тест Webhook' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Информация о боте -->
      <div v-if="bot" class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Информация о боте</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm text-gray-600">
          <div><span class="font-medium text-gray-700">ID:</span> {{ bot.id }}</div>
          <div><span class="font-medium text-gray-700">Создан:</span> {{ formatDate(bot.created_at) }}</div>
          <div><span class="font-medium text-gray-700">Обновлён:</span> {{ formatDate(bot.updated_at) }}</div>
        </div>
      </div>

      <!-- Приветственное сообщение -->
      <div v-if="bot" class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2 flex items-center gap-2">
          <span class="text-xl">💬</span>
          Приветственное сообщение
        </h2>
        <p class="text-sm text-gray-600 mb-4">
          Это сообщение отправляется пользователю при запуске команды /start
        </p>
        <div class="space-y-3">
          <label class="block text-sm font-medium text-gray-700">Текст сообщения</label>
          <textarea
            v-model="welcomeMessage"
            rows="5"
            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            placeholder="Введите приветственное сообщение..."
          />
          <p class="text-sm text-gray-500">Оставьте пустым для использования сообщения по умолчанию</p>
          <div v-if="welcomeError" class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ welcomeError }}</div>
          <div v-if="welcomeSuccess" class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ welcomeSuccess }}</div>
          <button
            type="button"
            :disabled="savingWelcome"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2"
            @click="saveWelcome"
          >
            <span>💾</span> Сохранить сообщение
          </button>
        </div>
      </div>

      <!-- Описание бота -->
      <div v-if="bot" class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2 flex items-center gap-2">
          <span class="text-xl">📋</span>
          Описание бота
        </h2>
        <p class="text-sm text-gray-600 mb-4">
          Описание отображается в чате до нажатия кнопки «Старт». Краткое описание — в профиле бота.
        </p>
        <div v-if="loadingDesc" class="flex items-center gap-2 text-gray-600 py-2">
          <div class="animate-spin rounded-full h-5 w-5 border-2 border-gray-400 border-t-transparent"></div>
          Загрузка...
        </div>
        <div v-else class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Описание бота (до 512 символов)</label>
            <textarea
              v-model="botDescription"
              rows="5"
              maxlength="512"
              class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              placeholder="Что может делать этот бот? Опишите возможности..."
            />
            <p class="text-sm text-gray-500 mt-1">{{ botDescription.length }}/512 символов. Показывается в пустом чате до нажатия «Старт»</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Краткое описание (до 120 символов)</label>
            <input
              v-model="botShortDescription"
              type="text"
              maxlength="120"
              class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              placeholder="Краткое описание для профиля бота"
            />
            <p class="text-sm text-gray-500 mt-1">{{ botShortDescription.length }}/120 символов. Показывается в профиле бота</p>
          </div>
          <div v-if="descError" class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ descError }}</div>
          <div v-if="descSuccess" class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ descSuccess }}</div>
          <button
            type="button"
            :disabled="savingDesc"
            class="rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50 flex items-center gap-2"
            @click="saveDescription"
          >
            <span>💾</span> Сохранить описание в Telegram
          </button>
          <p class="text-xs text-gray-500">Описание сохраняется напрямую в Telegram через Bot API</p>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import apiClient from '@/api/axios.js';

const bot = ref(null);
const loading = ref(true);
const form = ref({ token: '' });
const saving = ref(false);
const testing = ref(false);
const error = ref(null);
const success = ref(null);

const welcomeMessage = ref('');
const savingWelcome = ref(false);
const welcomeError = ref(null);
const welcomeSuccess = ref(null);

const botDescription = ref('');
const botShortDescription = ref('');
const loadingDesc = ref(false);
const savingDesc = ref(false);
const descError = ref(null);
const descSuccess = ref(null);

function formatDate(val) {
  if (!val) return '—';
  try {
    return new Date(val).toLocaleString('ru-RU');
  } catch {
    return val;
  }
}

async function fetchBot() {
  loading.value = true;
  error.value = null;
  try {
    const res = await apiClient.get('/bot');
    bot.value = res.data.bot ?? null;
    if (bot.value) {
      form.value.token = bot.value.token || '';
      welcomeMessage.value = bot.value.welcome_message || '';
      fetchDescription();
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Ошибка загрузки';
  } finally {
    loading.value = false;
  }
}

async function fetchDescription() {
  if (!bot.value) return;
  loadingDesc.value = true;
  try {
    const res = await apiClient.get('/bot/description');
    botDescription.value = res.data.description || '';
    botShortDescription.value = res.data.short_description || '';
  } catch (_) {}
  finally {
    loadingDesc.value = false;
  }
}

async function saveBot() {
  saving.value = true;
  error.value = null;
  success.value = null;
  try {
    const res = await apiClient.post('/bot', { token: form.value.token });
    bot.value = res.data.bot;
    success.value = res.data.message || 'Бот обновлён. Webhook зарегистрирован.';
  } catch (e) {
    error.value = e.response?.data?.message || 'Ошибка при сохранении';
  } finally {
    saving.value = false;
  }
}

async function testWebhook() {
  testing.value = true;
  error.value = null;
  success.value = null;
  try {
    const res = await apiClient.post('/bot/test-webhook');
    success.value = res.data.message || 'Webhook настроен.';
  } catch (e) {
    error.value = e.response?.data?.message || 'Ошибка проверки webhook';
  } finally {
    testing.value = false;
  }
}

async function saveWelcome() {
  savingWelcome.value = true;
  welcomeError.value = null;
  welcomeSuccess.value = null;
  try {
    await apiClient.put('/bot/settings', { welcome_message: welcomeMessage.value || null });
    welcomeSuccess.value = 'Приветственное сообщение сохранено.';
  } catch (e) {
    welcomeError.value = e.response?.data?.message || 'Ошибка сохранения';
  } finally {
    savingWelcome.value = false;
  }
}

async function saveDescription() {
  savingDesc.value = true;
  descError.value = null;
  descSuccess.value = null;
  try {
    const res = await apiClient.put('/bot/description', {
      description: botDescription.value,
      short_description: botShortDescription.value,
    });
    descSuccess.value = res.data.message || 'Описание обновлено.';
  } catch (e) {
    descError.value = e.response?.data?.message || e.response?.data?.error || 'Ошибка сохранения';
  } finally {
    savingDesc.value = false;
  }
}

onMounted(() => {
  fetchBot();
});

watch(bot, (newBot) => {
  if (newBot) {
    welcomeMessage.value = newBot.welcome_message || '';
  }
}, { immediate: true });
</script>
