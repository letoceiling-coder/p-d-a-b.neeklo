<template>
  <header class="relative flex h-16 items-center justify-between border-b border-gray-200 bg-white backdrop-blur-xl px-4 sm:px-6 gap-2 sm:gap-4 z-30">
    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
      <button
        @click="toggleMobileMenu"
        class="lg:hidden flex-shrink-0 h-11 w-11 flex items-center justify-center rounded-md hover:bg-gray-100 transition-colors"
        aria-label="Открыть меню"
      >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>
      <div class="flex items-center gap-2 text-sm min-w-0">
        <span class="font-semibold text-gray-900 truncate">{{ currentPageTitle }}</span>
      </div>
    </div>
    <div class="flex items-center gap-2 sm:gap-3">
      <div class="h-9 w-9 sm:h-10 sm:w-10 rounded-full bg-blue-600 border border-blue-500 flex items-center justify-center text-sm font-bold text-white flex-shrink-0">
        {{ userInitials }}
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, inject } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';

const route = useRoute();
const authStore = useAuthStore();
const mobileMenu = inject('mobileMenu', null);

const user = computed(() => authStore.user);

const userInitials = computed(() => {
  if (!user.value?.name) return 'U';
  const names = user.value.name.split(' ');
  return names.map(n => n[0]).join('').toUpperCase().substring(0, 2);
});

const currentPageTitle = computed(() => {
  return route.meta?.title || 'Панель управления';
});

const toggleMobileMenu = () => {
  if (mobileMenu) {
    mobileMenu.toggle();
  }
};
</script>
