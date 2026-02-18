<template>
  <aside
    class="flex flex-col w-64 shrink-0 border-r border-gray-200 bg-white overflow-hidden"
    aria-label="Боковая панель"
  >
    <div class="p-3 border-b border-gray-200">
      <button
        type="button"
        :disabled="creating"
        class="flex items-center justify-center gap-2 w-full rounded-lg bg-gray-900 text-white px-4 py-2.5 text-sm font-medium hover:bg-gray-800 disabled:opacity-50 transition-colors"
        @click="createNewAnalysis"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        {{ creating ? 'Создание...' : 'Новый анализ' }}
      </button>
    </div>
    <div class="flex-1 min-h-0 flex flex-col overflow-hidden">
      <div class="px-2 py-1">
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Поиск по истории..."
          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm placeholder-gray-400 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500"
        />
      </div>
      <div class="flex-1 overflow-y-auto px-2">
        <ul class="space-y-0.5">
          <li v-for="item in filteredAnalyses" :key="item.id">
            <router-link
              :to="{ name: 'app.analysis', params: { id: item.id } }"
              class="block rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-100 truncate"
              active-class="bg-gray-100 font-medium"
            >
              {{ item.title }}
            </router-link>
          </li>
          <li v-if="!analyses.length && !loading" class="px-3 py-4 text-sm text-gray-500 text-center">
            Нет анализов
          </li>
          <li v-if="loading" class="px-3 py-4 text-sm text-gray-500 text-center">
            Загрузка...
          </li>
        </ul>
      </div>
    </div>
    <nav class="border-t border-gray-200 p-2 space-y-0.5">
      <template v-if="isAdmin">
        <router-link
          to="/admin/dashboard"
          class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-100"
        >
          <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          Настройки
        </router-link>
      </template>
      <router-link
        :to="{ name: 'app.profile' }"
        class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-100"
      >
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        Профиль
      </router-link>
      <button
        type="button"
        @click="handleLogout"
        class="flex items-center gap-2 w-full rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-100"
      >
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1z" />
        </svg>
        Выйти
      </button>
    </nav>
  </aside>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth.js';
import apiClient from '../../api/axios.js';

defineOptions({
  name: 'AppSidebar',
});

const router = useRouter();
const authStore = useAuthStore();
const searchQuery = ref('');
const analyses = ref([]);
const loading = ref(false);
const creating = ref(false);

const isAdmin = computed(() => authStore.user?.role === 'admin');

const filteredAnalyses = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return analyses.value;
  return analyses.value.filter((a) => a.title?.toLowerCase().includes(q));
});

async function loadAnalyses() {
  loading.value = true;
  try {
    const { data } = await apiClient.get('/app/analyses');
    analyses.value = data.data ?? data ?? [];
  } catch {
    analyses.value = [];
  } finally {
    loading.value = false;
  }
}

async function createNewAnalysis() {
  if (creating.value) return;
  creating.value = true;
  try {
    const { data } = await apiClient.post('/app/analyses');
    const item = data.data ?? data;
    if (item?.id) {
      analyses.value = [item, ...analyses.value];
      router.push({ name: 'app.analysis', params: { id: item.id } });
    }
  } catch {
    // ошибка — список не меняем
  } finally {
    creating.value = false;
  }
}

function handleLogout() {
  authStore.logout();
}

onMounted(() => {
  loadAnalyses();
});
</script>
