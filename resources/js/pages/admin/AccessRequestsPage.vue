<template>
  <div class="space-y-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Запросы доступа</h1>
      <p class="text-gray-600 mt-1">
        Пользователи отправляют в боте команду /admin — здесь можно одобрить или отклонить запрос. Одобренным выдаётся роль администратора.
      </p>
    </div>

    <div class="flex gap-2 mb-4">
      <button
        type="button"
        :class="filter === null ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'"
        class="rounded-md px-4 py-2 text-sm font-medium"
        @click="filter = null; fetchRequests()"
      >
        Все
      </button>
      <button
        type="button"
        :class="filter === 'pending' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'"
        class="rounded-md px-4 py-2 text-sm font-medium"
        @click="filter = 'pending'; fetchRequests()"
      >
        Ожидают
      </button>
      <button
        type="button"
        :class="filter === 'approved' ? 'bg-green-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'"
        class="rounded-md px-4 py-2 text-sm font-medium"
        @click="filter = 'approved'; fetchRequests()"
      >
        Одобрены
      </button>
      <button
        type="button"
        :class="filter === 'rejected' ? 'bg-red-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'"
        class="rounded-md px-4 py-2 text-sm font-medium"
        @click="filter = 'rejected'; fetchRequests()"
      >
        Отклонены
      </button>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-2 border-blue-500 border-t-transparent"></div>
    </div>

    <div v-else-if="requests.length === 0" class="bg-white rounded-lg border border-gray-200 p-8 text-center text-gray-500">
      Нет запросов по выбранному фильтру.
    </div>

    <div v-else class="bg-white rounded-lg border border-gray-200 overflow-hidden">
      <ul class="divide-y divide-gray-200">
        <li
          v-for="req in requests"
          :key="req.id"
          class="px-6 py-4 flex flex-wrap items-center justify-between gap-4"
        >
          <div class="min-w-0">
            <p class="font-medium text-gray-900">{{ req.display_name }}</p>
            <p v-if="req.username" class="text-sm text-gray-500">@{{ req.username }}</p>
            <p class="text-xs text-gray-400 mt-1">
              Запросил: {{ formatDate(req.requested_at) }}
              <span v-if="req.decided_at"> · Решён: {{ formatDate(req.decided_at) }}</span>
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span
              :class="{
                'bg-amber-100 text-amber-800': req.status === 'pending',
                'bg-green-100 text-green-800': req.status === 'approved',
                'bg-red-100 text-red-800': req.status === 'rejected',
              }"
              class="rounded-full px-3 py-1 text-xs font-medium"
            >
              {{ statusLabel(req.status) }}
            </span>
            <template v-if="req.status === 'pending'">
              <button
                type="button"
                class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                :disabled="actionId === req.id"
                @click="approve(req.id)"
              >
                {{ actionId === req.id ? '…' : 'Одобрить' }}
              </button>
              <button
                type="button"
                class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                :disabled="actionId === req.id"
                @click="reject(req.id)"
              >
                {{ actionId === req.id ? '…' : 'Отклонить' }}
              </button>
            </template>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '@/api/axios.js';

const requests = ref([]);
const loading = ref(true);
const actionId = ref(null);
const filter = ref('pending');

function formatDate(val) {
  if (!val) return '—';
  try {
    return new Date(val).toLocaleString('ru-RU');
  } catch {
    return val;
  }
}

function statusLabel(status) {
  const labels = { pending: 'Ожидает', approved: 'Одобрен', rejected: 'Отклонён' };
  return labels[status] || status;
}

async function fetchRequests() {
  loading.value = true;
  try {
    const params = filter.value ? { status: filter.value } : {};
    const res = await apiClient.get('/access-requests', { params });
    requests.value = res.data.requests || [];
  } catch (_) {
    requests.value = [];
  } finally {
    loading.value = false;
  }
}

async function approve(id) {
  actionId.value = id;
  try {
    await apiClient.post(`/access-requests/${id}/approve`);
    await fetchRequests();
  } catch (_) {}
  finally {
    actionId.value = null;
  }
}

async function reject(id) {
  actionId.value = id;
  try {
    await apiClient.post(`/access-requests/${id}/reject`);
    await fetchRequests();
  } catch (_) {}
  finally {
    actionId.value = null;
  }
}

onMounted(() => {
  fetchRequests();
});
</script>
