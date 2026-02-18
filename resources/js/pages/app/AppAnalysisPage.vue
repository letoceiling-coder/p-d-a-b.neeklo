<template>
  <div class="flex flex-col h-full">
    <header class="shrink-0 border-b border-gray-200 bg-white px-4 py-3 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-lg font-medium text-gray-900">{{ analysis?.title ?? 'Анализ' }}</h1>
        <p class="text-sm text-gray-500">
          {{ statusLabel }}
          <span v-if="analysis?.processing_step && stepLabel" class="block mt-0.5">— {{ stepLabel }}</span>
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          v-if="analysis?.status === 'ready'"
          type="button"
          class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          @click="downloadPdf"
        >
          Скачать PDF
        </button>
        <button
          type="button"
          class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          Повторный анализ
        </button>
      </div>
    </header>
    <div class="flex-1 overflow-y-auto p-4">
      <template v-if="loading">
        <p class="text-gray-500">Загрузка...</p>
      </template>
      <template v-else-if="analysis">
        <div class="space-y-4 max-w-2xl">
          <div v-if="analysis.status === 'processing'" class="rounded-lg border border-gray-200 bg-white p-4 flex items-center gap-3">
            <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-300 border-t-gray-600 shrink-0"></div>
            <div>
              <p class="text-sm font-medium text-gray-700">Идёт анализ договора</p>
              <p class="text-xs text-gray-500">{{ stepLabel || 'Ожидание...' }}</p>
            </div>
          </div>
          <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-sm font-medium text-gray-700 mb-2">Загрузка документов</p>
            <p class="text-xs text-gray-500 mb-3">PDF, DOC, DOCX, JPG, PNG, ZIP. Несколько файлов по очереди.</p>
            <form @submit.prevent="uploadFiles" class="flex flex-wrap items-end gap-2">
              <input
                ref="fileInputRef"
                type="file"
                multiple
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip"
                class="block w-full text-sm text-gray-500 file:mr-2 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-gray-700"
              />
              <button
                type="submit"
                :disabled="uploading"
                class="rounded-lg bg-gray-900 text-white px-3 py-2 text-sm font-medium hover:bg-gray-800 disabled:opacity-50"
              >
                {{ uploading ? 'Загрузка...' : 'Загрузить файлы' }}
              </button>
            </form>
            <div v-if="uploadMessages.length" class="mt-3 space-y-1">
              <p v-for="(msg, i) in uploadMessages" :key="i" class="text-sm" :class="msg.includes('отклонён') ? 'text-amber-700' : 'text-gray-600'">
                {{ msg }}
              </p>
            </div>
            <div v-if="analysis.status === 'draft' && hasUploadedFiles" class="mt-3 pt-3 border-t border-gray-100">
              <button
                type="button"
                :disabled="starting"
                class="rounded-lg bg-gray-900 text-white px-3 py-2 text-sm font-medium hover:bg-gray-800 disabled:opacity-50"
                @click="startAnalysis"
              >
                {{ starting ? 'Запуск...' : 'Запустить анализ' }}
              </button>
            </div>
          </div>
          <template v-if="analysis.status === 'ready'">
            <div v-if="summaryCards.length || analysis.summary_text" class="rounded-lg border border-gray-200 bg-white p-4">
              <h3 class="text-sm font-semibold text-gray-900 mb-3">Выжимка договора</h3>
              <div v-if="summaryCards.length" class="space-y-2">
                <div
                  v-for="(item, i) in summaryCards"
                  :key="i"
                  class="rounded-lg border border-gray-100 bg-gray-50/50 p-3 text-sm"
                >
                  <p class="font-medium text-gray-700 mb-1">{{ item.label }}</p>
                  <p class="text-gray-600">{{ item.value }}</p>
                </div>
              </div>
              <pre v-else-if="analysis.summary_text" class="text-sm text-gray-600 whitespace-pre-wrap font-sans">{{ analysis.summary_text }}</pre>
            </div>
            <div v-if="counterpartyItems.length" class="rounded-lg border border-gray-200 bg-white p-4">
              <h3 class="text-sm font-semibold text-gray-900 mb-3">Проверка контрагента</h3>
              <div class="space-y-2">
                <div
                  v-for="(item, i) in counterpartyItems"
                  :key="i"
                  class="rounded-lg border border-gray-100 bg-gray-50/50 p-3 text-sm flex flex-wrap items-center justify-between gap-2"
                >
                  <span class="font-medium text-gray-700">{{ item.name }}</span>
                  <span
                    class="rounded px-2 py-0.5 text-xs font-medium shrink-0"
                    :class="{
                      'bg-green-100 text-green-800': item.status === 'OK',
                      'bg-amber-100 text-amber-800': item.status === 'Warning',
                      'bg-red-100 text-red-800': item.status === 'Risk',
                    }"
                  >
                    {{ item.status }}
                  </span>
                  <span class="w-full text-xs text-gray-500">{{ item.source }} · {{ formatDate(item.checked_at) }}</span>
                </div>
              </div>
            </div>
            <div v-if="analysis.status === 'ready'" class="rounded-lg border border-gray-200 bg-white p-4">
              <h3 class="text-sm font-semibold text-gray-900 mb-3">Уточняющие вопросы</h3>
              <div class="space-y-3 mb-3 max-h-64 overflow-y-auto">
                <div
                  v-for="msg in chatMessages"
                  :key="msg.id"
                  class="flex flex-col"
                  :class="msg.role === 'user' ? 'items-end' : 'items-start'"
                >
                  <span class="text-xs text-gray-500 mb-0.5">{{ msg.role === 'user' ? 'Вы' : 'Ассистент' }}</span>
                  <p class="rounded-lg px-3 py-2 text-sm max-w-full" :class="msg.role === 'user' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-800'">
                    {{ msg.content }}
                  </p>
                </div>
                <p v-if="chatLoading" class="text-sm text-gray-500">Ответ...</p>
              </div>
              <form @submit.prevent="sendChatMessage" class="flex gap-2">
                <input
                  v-model="chatInput"
                  type="text"
                  placeholder="Задать вопрос по договору..."
                  class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500"
                  :disabled="chatLoading"
                />
                <button
                  type="submit"
                  :disabled="!chatInput.trim() || chatLoading"
                  class="rounded-lg bg-gray-900 text-white px-3 py-2 text-sm font-medium hover:bg-gray-800 disabled:opacity-50"
                >
                  Отправить
                </button>
              </form>
            </div>
          </template>
        </div>
      </template>
      <template v-else>
        <p class="text-gray-500">Анализ не найден.</p>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import apiClient from '../../api/axios.js';

defineOptions({
  name: 'AppAnalysisPage',
});

const route = useRoute();
const analysis = ref(null);
const loading = ref(true);
const uploading = ref(false);
const uploadMessages = ref([]);
const fileInputRef = ref(null);
const starting = ref(false);
const stepLabel = ref('');
const chatMessages = ref([]);
const chatInput = ref('');
const chatLoading = ref(false);
let statusPollTimer = null;

const hasUploadedFiles = computed(() => {
  const info = analysis.value?.file_info;
  return Array.isArray(info) && info.length > 0;
});

const counterpartyItems = computed(() => {
  const check = analysis.value?.counterparty_check;
  return Array.isArray(check) ? check : [];
});

const summaryCards = computed(() => {
  const json = analysis.value?.summary_json;
  if (!Array.isArray(json) || !json.length) return [];
  return json.map((item, i) => ({
    label: item.title || `Пункт ${i + 1}`,
    value: item.value ?? '',
  })).filter(c => c.value);
});

function formatDate(iso) {
  if (!iso) return '—';
  try {
    const d = new Date(iso);
    return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
  } catch {
    return iso;
  }
}

const statusLabel = computed(() => {
  if (!analysis.value) return '';
  const s = analysis.value.status;
  return s === 'ready' ? 'Готово' : s === 'processing' ? 'В обработке' : 'Черновик';
});

async function loadAnalysis() {
  const id = route.params.id;
  if (!id) return;
  loading.value = true;
  stopStatusPoll();
  try {
    const { data } = await apiClient.get(`/app/analyses/${id}`);
    analysis.value = data.data ?? data;
    if (analysis.value?.status === 'processing') {
      startStatusPoll();
    }
    if (analysis.value?.status === 'ready' && analysis.value?.id) {
      loadChatMessages();
    }
  } catch {
    analysis.value = null;
  } finally {
    loading.value = false;
  }
}

async function fetchStatus() {
  const id = analysis.value?.id;
  if (!id) return;
  try {
    const { data } = await apiClient.get(`/app/analyses/${id}/status`);
    const d = data.data ?? data;
    stepLabel.value = d.step_label ?? '';
    if (analysis.value) {
      analysis.value.status = d.status;
      analysis.value.processing_step = d.processing_step;
    }
    if (d.status === 'ready') {
      stopStatusPoll();
      await loadAnalysis();
    }
  } catch {
    stopStatusPoll();
  }
}

function startStatusPoll() {
  stopStatusPoll();
  fetchStatus();
  statusPollTimer = setInterval(fetchStatus, 2500);
}

function stopStatusPoll() {
  if (statusPollTimer) {
    clearInterval(statusPollTimer);
    statusPollTimer = null;
  }
  stepLabel.value = '';
}

async function startAnalysis() {
  if (!analysis.value?.id || starting.value) return;
  starting.value = true;
  try {
    await apiClient.post(`/app/analyses/${analysis.value.id}/start`);
    analysis.value.status = 'processing';
    analysis.value.processing_step = 'extracting';
    stepLabel.value = 'Извлечение текста';
    startStatusPoll();
  } catch (e) {
    const msg = e.response?.data?.message ?? 'Ошибка запуска';
    uploadMessages.value = [msg];
  } finally {
    starting.value = false;
  }
}

async function downloadPdf() {
  const id = analysis.value?.id;
  if (!id) return;
  try {
    const response = await apiClient.get(`/app/analyses/${id}/download-pdf`, { responseType: 'blob' });
    const blob = response.data;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `otchet-analiz-${id}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e) {
    const msg = e.response?.data?.message ?? e.response?.status === 404 ? 'PDF не найден.' : 'Ошибка скачивания';
    uploadMessages.value = [msg];
  }
}

async function uploadFiles() {
  const input = fileInputRef.value;
  const files = input?.files;
  if (!files?.length || !analysis.value?.id) return;
  uploading.value = true;
  uploadMessages.value = [];
  try {
    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
      formData.append('files[]', files[i]);
    }
    const { data } = await apiClient.post(`/app/analyses/${analysis.value.id}/upload`, formData);
    uploadMessages.value = data.messages ?? (data.message ? [data.message] : []);
    if (data.file_info?.length) {
      analysis.value.file_info = data.file_info;
    }
    if (input) input.value = '';
  } catch (e) {
    const msg = e.response?.data?.message ?? e.response?.data?.errors?.files?.[0] ?? 'Ошибка загрузки';
    uploadMessages.value = [msg];
  } finally {
    uploading.value = false;
  }
}

async function loadChatMessages() {
  const id = analysis.value?.id;
  if (!id) return;
  try {
    const { data } = await apiClient.get(`/app/analyses/${id}/messages`);
    chatMessages.value = data.data ?? [];
  } catch {
    chatMessages.value = [];
  }
}

async function sendChatMessage() {
  const text = chatInput.value?.trim();
  if (!text || !analysis.value?.id || chatLoading.value) return;
  chatLoading.value = true;
  chatInput.value = '';
  const prevLen = chatMessages.value.length;
  chatMessages.value = [...chatMessages.value, { id: null, role: 'user', content: text }];
  try {
    const { data } = await apiClient.post(`/app/analyses/${analysis.value.id}/messages`, { content: text });
    const payload = data.data ?? data;
    const list = Array.isArray(payload) ? payload : (payload.messages ?? [payload]);
    if (list.length) {
      chatMessages.value = [...chatMessages.value.slice(0, prevLen), ...list];
    }
  } catch (e) {
    chatMessages.value = chatMessages.value.slice(0, prevLen);
    const msg = e.response?.data?.message ?? 'Ошибка отправки';
    chatMessages.value = [...chatMessages.value, { id: null, role: 'assistant', content: msg }];
  } finally {
    chatLoading.value = false;
  }
}

onMounted(loadAnalysis);
onUnmounted(stopStatusPoll);
watch(() => route.params.id, () => {
  stopStatusPoll();
  loadAnalysis();
});
</script>
