<template>
  <div class="space-y-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">LEXAUTO — Заявки на розыгрыш</h1>
      <p class="text-gray-600 mt-1">Заявки с чеками на проверке. Одобрить или отклонить.</p>
    </div>

    <div class="flex gap-2 mb-4">
      <button
        type="button"
        :class="filter === null ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'"
        class="rounded-md px-4 py-2 text-sm font-medium"
        @click="filter = null; fetchOrders()"
      >
        Все
      </button>
      <button
        type="button"
        :class="filter === 'review' ? 'bg-amber-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'"
        class="rounded-md px-4 py-2 text-sm font-medium"
        @click="filter = 'review'; fetchOrders()"
      >
        На проверке
      </button>
      <button
        type="button"
        :class="filter === 'reserved' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'"
        class="rounded-md px-4 py-2 text-sm font-medium"
        @click="filter = 'reserved'; fetchOrders()"
      >
        Забронировано
      </button>
      <button
        type="button"
        :class="filter === 'sold' ? 'bg-green-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'"
        class="rounded-md px-4 py-2 text-sm font-medium"
        @click="filter = 'sold'; fetchOrders()"
      >
        Продано
      </button>
      <button
        type="button"
        :class="filter === 'rejected' ? 'bg-red-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'"
        class="rounded-md px-4 py-2 text-sm font-medium"
        @click="filter = 'rejected'; fetchOrders()"
      >
        Отклонено
      </button>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-2 border-blue-500 border-t-transparent"></div>
    </div>

    <div v-else-if="orders.length === 0" class="bg-white rounded-lg border border-gray-200 p-8 text-center text-gray-500">
      Нет заявок по выбранному фильтру.
    </div>

    <div v-else class="bg-white rounded-lg border border-gray-200 overflow-hidden">
      <ul class="divide-y divide-gray-200">
        <li
          v-for="order in orders"
          :key="order.id"
          class="px-6 py-4 flex flex-wrap items-center justify-between gap-4"
        >
          <div class="min-w-0">
            <p class="font-medium text-gray-900">{{ order.fio }}</p>
            <p class="text-sm text-gray-500">{{ order.phone }}</p>
            <p class="text-xs text-gray-400 mt-1">
              {{ order.quantity }} шт. · {{ order.amount }} руб.
              <span v-if="order.created_at"> · {{ formatDate(order.created_at) }}</span>
            </p>
            <p v-if="order.ticket_numbers?.length" class="text-xs text-green-600 mt-1">
              Номера: {{ order.ticket_numbers.join(', ') }}
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span
              :class="{
                'bg-amber-100 text-amber-800': order.status === 'review',
                'bg-blue-100 text-blue-800': order.status === 'reserved',
                'bg-green-100 text-green-800': order.status === 'sold',
                'bg-red-100 text-red-800': order.status === 'rejected',
              }"
              class="rounded-full px-3 py-1 text-xs font-medium"
            >
              {{ statusLabel(order.status) }}
            </span>
            <template v-if="order.status === 'review'">
              <button
                type="button"
                class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                :disabled="actionId === order.id"
                @click="approve(order.id)"
              >
                {{ actionId === order.id ? '…' : '✅ Одобрить' }}
              </button>
              <button
                type="button"
                class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                :disabled="actionId === order.id"
                @click="reject(order.id)"
              >
                {{ actionId === order.id ? '…' : '❌ Отклонить' }}
              </button>
              <button
                type="button"
                class="rounded-md bg-gray-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                :disabled="actionId === order.id"
                @click="openEdit(order)"
              >
                ✏️ Редактировать
              </button>
            </template>
          </div>
        </li>
      </ul>
    </div>

    <!-- Модалка редактирования -->
    <div
      v-if="editOrder"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      @click.self="editOrder = null"
    >
      <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Редактировать заявку #{{ editOrder.id }}</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Количество билетов</label>
            <input v-model.number="editForm.quantity" type="number" min="1" class="rounded-md border border-gray-300 px-3 py-2 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Сумма (руб.)</label>
            <input v-model.number="editForm.amount" type="number" min="0" step="0.01" class="rounded-md border border-gray-300 px-3 py-2 w-full" />
          </div>
        </div>
        <div class="flex gap-2 mt-6">
          <button
            type="button"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
            @click="saveEdit"
          >
            Сохранить
          </button>
          <button
            type="button"
            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="editOrder = null"
          >
            Отмена
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '@/api/axios.js';

const orders = ref([]);
const loading = ref(true);
const actionId = ref(null);
const filter = ref('review');
const editOrder = ref(null);
const editForm = ref({ quantity: 1, amount: 0 });

function formatDate(val) {
  if (!val) return '—';
  try {
    return new Date(val).toLocaleString('ru-RU');
  } catch {
    return val;
  }
}

function statusLabel(status) {
  const labels = { reserved: 'Бронь', review: 'На проверке', sold: 'Продано', rejected: 'Отклонено' };
  return labels[status] || status;
}

async function fetchOrders() {
  loading.value = true;
  try {
    const params = filter.value ? { status: filter.value } : {};
    const res = await apiClient.get('/lexauto/orders', { params });
    orders.value = res.data.orders || [];
  } catch (_) {
    orders.value = [];
  } finally {
    loading.value = false;
  }
}

async function approve(id) {
  actionId.value = id;
  try {
    await apiClient.post(`/lexauto/orders/${id}/approve`);
    await fetchOrders();
  } catch (_) {}
  finally {
    actionId.value = null;
  }
}

async function reject(id) {
  actionId.value = id;
  try {
    await apiClient.post(`/lexauto/orders/${id}/reject`);
    await fetchOrders();
  } catch (_) {}
  finally {
    actionId.value = null;
  }
}

function openEdit(order) {
  editOrder.value = order;
  editForm.value = { quantity: order.quantity, amount: order.amount };
}

async function saveEdit() {
  if (!editOrder.value) return;
  try {
    await apiClient.put(`/lexauto/orders/${editOrder.value.id}`, editForm.value);
    editOrder.value = null;
    await fetchOrders();
  } catch (_) {}
}

onMounted(() => {
  fetchOrders();
});
</script>
