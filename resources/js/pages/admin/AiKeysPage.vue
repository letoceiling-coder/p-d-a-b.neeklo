<template>
  <div class="space-y-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Ключи API</h1>
      <p class="text-gray-600 mt-1">Настройте ключи Gemini и OpenAI, добавьте модели для работы через API.</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-2 border-blue-500 border-t-transparent"></div>
    </div>

    <template v-else>
      <!-- Ключ Gemini -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Gemini</h2>
        <p class="text-sm text-gray-500 mb-3">
          <a :href="providers.gemini?.url" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Документация и получение ключа (quickstart)</a>
        </p>
        <div class="flex flex-wrap gap-3 items-end">
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">API ключ</label>
            <input
              v-model="keys.gemini"
              type="password"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              placeholder="Текущий: отображается маской, введите новый для смены"
            />
            <p v-if="providers.gemini?.key?.masked_key" class="text-xs text-gray-500 mt-1">Текущий: {{ providers.gemini.key.masked_key }}</p>
          </div>
          <button
            type="button"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            :disabled="savingKeys.gemini"
            @click="saveKey('gemini')"
          >
            {{ savingKeys.gemini ? 'Сохранение...' : 'Сохранить ключ' }}
          </button>
          <button
            type="button"
            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            :disabled="verifyLoading.gemini"
            @click="verifyKey('gemini')"
          >
            {{ verifyLoading.gemini ? 'Проверка...' : 'Проверить ключ' }}
          </button>
        </div>
        <p v-if="keysSuccess.gemini" class="mt-2 text-sm text-green-600">{{ keysSuccess.gemini }}</p>
        <p v-if="keysError.gemini" class="mt-2 text-sm text-red-600">{{ keysError.gemini }}</p>
        <p v-if="verifyStatus.gemini" class="mt-2 text-sm" :class="verifyStatus.gemini.valid ? 'text-green-600' : 'text-amber-600'">{{ verifyStatus.gemini.message }}</p>
        <p class="mt-1 text-xs text-gray-500">
          <a href="https://aistudio.google.com/usage" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Баланс и квоты Gemini →</a>
        </p>
      </div>

      <!-- Ключ OpenAI -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">OpenAI</h2>
        <p class="text-sm text-gray-500 mb-3">
          <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Получить API ключ</a>
        </p>
        <div class="flex flex-wrap gap-3 items-end">
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">API ключ</label>
            <input
              v-model="keys.openai"
              type="password"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              placeholder="Текущий: отображается маской, введите новый для смены"
            />
            <p v-if="providers.openai?.key?.masked_key" class="text-xs text-gray-500 mt-1">Текущий: {{ providers.openai.key.masked_key }}</p>
          </div>
          <button
            type="button"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            :disabled="savingKeys.openai"
            @click="saveKey('openai')"
          >
            {{ savingKeys.openai ? 'Сохранение...' : 'Сохранить ключ' }}
          </button>
          <button
            type="button"
            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            :disabled="verifyLoading.openai"
            @click="verifyKey('openai')"
          >
            {{ verifyLoading.openai ? 'Проверка...' : 'Проверить ключ' }}
          </button>
        </div>
        <p v-if="keysSuccess.openai" class="mt-2 text-sm text-green-600">{{ keysSuccess.openai }}</p>
        <p v-if="keysError.openai" class="mt-2 text-sm text-red-600">{{ keysError.openai }}</p>
        <p v-if="verifyStatus.openai" class="mt-2 text-sm" :class="verifyStatus.openai.valid ? 'text-green-600' : 'text-amber-600'">{{ verifyStatus.openai.message }}</p>
        <p class="mt-1 text-xs text-gray-500">
          <a href="https://platform.openai.com/usage" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Баланс и использование OpenAI →</a>
        </p>
      </div>

      <!-- Модели -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Модели</h2>
        <div class="text-sm text-gray-600 mb-4 space-y-2">
          <p><strong>Зачем нужны:</strong> каждая запись — это конкретная модель AI (Gemini или OpenAI), которую бот и админка могут вызывать. Без хотя бы одной активной модели <strong>анализ договоров в боте не заработает</strong> (загруженный документ не будет обработан).</p>
          <p><strong>Как создать:</strong> выберите провайдер (тот, для которого уже сохранён ключ выше), укажите название для удобства и <strong>ID модели</strong> — как в API провайдера (например <code class="bg-gray-100 px-1 rounded">gpt-4o</code>, <code class="bg-gray-100 px-1 rounded">gpt-4o-mini</code>, <code class="bg-gray-100 px-1 rounded">gemini-1.5-flash</code>). Отметьте «Активна» и нажмите «Добавить».</p>
          <p><strong>Какая модель используется для договоров:</strong> в разделе <router-link to="/admin/contract-settings" class="text-blue-600 hover:underline">Настройки анализа</router-link> можно выбрать модель по умолчанию или оставить «Первая активная».</p>
        </div>

        <!-- Добавить модель -->
        <div class="mb-6 p-4 bg-gray-50 rounded-lg space-y-3">
          <h3 class="font-medium text-gray-800">Добавить модель</h3>
          <div class="flex flex-wrap gap-3 items-end">
            <div>
              <label class="block text-sm text-gray-600 mb-1">Провайдер</label>
              <select v-model="newModel.provider" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                <option value="gemini">Gemini</option>
                <option value="openai">OpenAI</option>
              </select>
            </div>
            <div>
              <label class="block text-sm text-gray-600 mb-1">Название</label>
              <input v-model="newModel.name" type="text" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-48" placeholder="Gemini 1.5 Flash" />
            </div>
            <div>
              <label class="block text-sm text-gray-600 mb-1">ID модели (API)</label>
              <input v-model="newModel.model_id" type="text" class="rounded-md border border-gray-300 px-3 py-2 text-sm w-48" placeholder="gemini-1.5-flash" />
            </div>
            <label class="flex items-center gap-2">
              <input v-model="newModel.is_active" type="checkbox" class="rounded border-gray-300" />
              <span class="text-sm text-gray-700">Активна</span>
            </label>
            <button
              type="button"
              class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
              :disabled="savingModel || !newModel.name || !newModel.model_id"
              @click="addModel"
            >
              {{ savingModel ? '...' : 'Добавить' }}
            </button>
          </div>
          <p v-if="modelError" class="text-sm text-red-600">{{ modelError }}</p>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Провайдер</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Название</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID модели</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Активна</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="m in models" :key="m.id" class="bg-white">
                <td class="px-4 py-2 text-sm text-gray-700">{{ m.provider }}</td>
                <td class="px-4 py-2 text-sm text-gray-900">{{ m.name }}</td>
                <td class="px-4 py-2 text-sm text-gray-600 font-mono">{{ m.model_id }}</td>
                <td class="px-4 py-2">
                  <span :class="m.is_active ? 'text-green-600' : 'text-gray-400'" class="text-sm">{{ m.is_active ? 'Да' : 'Нет' }}</span>
                </td>
                <td class="px-4 py-2 text-right">
                  <button
                    type="button"
                    class="text-red-600 hover:text-red-800 text-sm font-medium"
                    @click="deleteModel(m.id)"
                  >
                    Удалить
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="models.length === 0" class="py-4 text-center text-gray-500 text-sm">Моделей пока нет. Добавьте первую выше.</p>
      </div>

      <!-- Справка по API -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Работа с моделями по API</h2>
        <p class="text-sm text-gray-600 mb-2">Для запроса к выбранной модели отправьте POST <code class="bg-gray-100 px-1 rounded">/api/ai/chat</code> (с заголовком Authorization: Bearer &lt;token&gt;):</p>
        <pre class="text-xs bg-gray-50 p-3 rounded overflow-x-auto">{{ apiExample }}</pre>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '@/api/axios.js';

const loading = ref(true);
const providers = ref({ gemini: {}, openai: {} });
const models = ref([]);
const keys = ref({ gemini: '', openai: '' });
const savingKeys = ref({ gemini: false, openai: false });
const keysSuccess = ref({ gemini: '', openai: '' });
const keysError = ref({ gemini: '', openai: '' });
const verifyLoading = ref({ gemini: false, openai: false });
const verifyStatus = ref({ gemini: null, openai: null });
const newModel = ref({ provider: 'gemini', name: '', model_id: '', is_active: true });
const savingModel = ref(false);
const modelError = ref('');

const apiExample = `{
  "ai_model_id": 1,
  "messages": [
    { "role": "user", "content": "Привет, ответь кратко." }
  ]
}`;

async function fetchData() {
  loading.value = true;
  try {
    const res = await apiClient.get('/ai');
    providers.value = res.data.providers || { gemini: {}, openai: {} };
    models.value = res.data.models || [];
  } catch (_) {
    providers.value = { gemini: {}, openai: {} };
    models.value = [];
  } finally {
    loading.value = false;
  }
}

async function saveKey(provider) {
  savingKeys.value[provider] = true;
  keysSuccess.value[provider] = '';
  keysError.value[provider] = '';
  verifyStatus.value[provider] = null;
  const payload = { [provider]: keys.value[provider] || '' };
  try {
    const res = await apiClient.put('/ai/keys', payload);
    keysSuccess.value[provider] = res.data?.message || 'Ключ сохранён.';
    keys.value[provider] = '';
    await fetchData();
  } catch (e) {
    keysError.value[provider] = e.response?.data?.message || 'Ошибка сохранения';
  } finally {
    savingKeys.value[provider] = false;
  }
}

async function verifyKey(provider) {
  verifyLoading.value[provider] = true;
  verifyStatus.value[provider] = null;
  try {
    const res = await apiClient.get(provider === 'gemini' ? '/ai/verify/gemini' : '/ai/verify/openai');
    verifyStatus.value[provider] = { valid: res.data?.valid === true, message: res.data?.message || '' };
  } catch (e) {
    verifyStatus.value[provider] = { valid: false, message: e.response?.data?.message || 'Ошибка проверки' };
  } finally {
    verifyLoading.value[provider] = false;
  }
}

async function addModel() {
  savingModel.value = true;
  modelError.value = '';
  try {
    await apiClient.post('/ai/models', {
      provider: newModel.value.provider,
      name: newModel.value.name,
      model_id: newModel.value.model_id,
      is_active: newModel.value.is_active,
    });
    newModel.value = { provider: 'gemini', name: '', model_id: '', is_active: true };
    await fetchData();
  } catch (e) {
    modelError.value = e.response?.data?.message || 'Ошибка добавления';
  } finally {
    savingModel.value = false;
  }
}

async function deleteModel(id) {
  if (!confirm('Удалить модель?')) return;
  try {
    await apiClient.delete(`/ai/models/${id}`);
    await fetchData();
  } catch (_) {}
}

onMounted(() => {
  fetchData();
});
</script>
