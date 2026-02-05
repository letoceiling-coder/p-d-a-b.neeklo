<template>
  <aside
    class="relative flex flex-col bg-gray-900 text-white transition-all duration-300 border-r border-gray-800"
    :class="[
      'lg:flex',
      isMobileMenuOpen ? 'flex' : 'hidden',
      'lg:relative fixed lg:inset-auto inset-y-0 left-0 z-50 lg:z-auto',
      isCollapsed ? 'lg:w-16 w-72' : 'lg:w-72 w-72',
      'lg:translate-x-0 transition-transform duration-300 ease-in-out',
      isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
    ]"
  >
    <div class="flex h-16 items-center border-b border-gray-800 justify-between px-6">
      <h1 v-if="!isCollapsed" class="text-xl font-bold text-white">Admin</h1>
      <button
        @click="toggleCollapse"
        class="rounded-xl p-2 hover:bg-gray-800 transition-all"
        :title="isCollapsed ? 'Развернуть меню' : 'Свернуть меню'"
      >
        <svg
          class="h-5 w-5 transition-transform duration-300"
          :class="isCollapsed ? 'rotate-180' : ''"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
      </button>
    </div>
    <nav class="flex-1 overflow-y-auto space-y-1 p-4">
      <router-link
        to="/admin/dashboard"
        class="flex items-center rounded-xl text-sm font-medium transition-all px-4 py-3 gap-3"
        :class="[
          isCollapsed ? 'justify-center' : '',
          route.name === 'admin.dashboard'
            ? 'bg-gray-800 text-white'
            : 'text-gray-300 hover:bg-gray-800 hover:text-white'
        ]"
        @click="handleMobileMenuClick"
      >
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
        </svg>
        <span v-if="!isCollapsed">Панель управления</span>
      </router-link>
      <router-link
        to="/admin/bot"
        class="flex items-center rounded-xl text-sm font-medium transition-all px-4 py-3 gap-3"
        :class="[
          isCollapsed ? 'justify-center' : '',
          route.name === 'admin.bot'
            ? 'bg-gray-800 text-white'
            : 'text-gray-300 hover:bg-gray-800 hover:text-white'
        ]"
        @click="handleMobileMenuClick"
      >
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
        </svg>
        <span v-if="!isCollapsed">Бот</span>
      </router-link>
      <router-link
        to="/admin/access-requests"
        class="flex items-center rounded-xl text-sm font-medium transition-all px-4 py-3 gap-3"
        :class="[
          isCollapsed ? 'justify-center' : '',
          route.name === 'admin.access-requests'
            ? 'bg-gray-800 text-white'
            : 'text-gray-300 hover:bg-gray-800 hover:text-white'
        ]"
        @click="handleMobileMenuClick"
      >
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
        </svg>
        <span v-if="!isCollapsed">Запросы доступа</span>
      </router-link>
      <router-link
        to="/admin/ai-keys"
        class="flex items-center rounded-xl text-sm font-medium transition-all px-4 py-3 gap-3"
        :class="[
          isCollapsed ? 'justify-center' : '',
          route.name === 'admin.ai-keys'
            ? 'bg-gray-800 text-white'
            : 'text-gray-300 hover:bg-gray-800 hover:text-white'
        ]"
        @click="handleMobileMenuClick"
      >
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
        </svg>
        <span v-if="!isCollapsed">Ключи API</span>
      </router-link>
      <router-link
        to="/admin/contract-analyses"
        class="flex items-center rounded-xl text-sm font-medium transition-all px-4 py-3 gap-3"
        :class="[
          isCollapsed ? 'justify-center' : '',
          route.name === 'admin.contract-analyses'
            ? 'bg-gray-800 text-white'
            : 'text-gray-300 hover:bg-gray-800 hover:text-white'
        ]"
        @click="handleMobileMenuClick"
      >
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <span v-if="!isCollapsed">История анализов</span>
      </router-link>
      <router-link
        to="/admin/contract-settings"
        class="flex items-center rounded-xl text-sm font-medium transition-all px-4 py-3 gap-3"
        :class="[
          isCollapsed ? 'justify-center' : '',
          route.name === 'admin.contract-settings'
            ? 'bg-gray-800 text-white'
            : 'text-gray-300 hover:bg-gray-800 hover:text-white'
        ]"
        @click="handleMobileMenuClick"
      >
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
        <span v-if="!isCollapsed">Настройки анализа</span>
      </router-link>
      <router-link
        to="/admin/action-logs"
        class="flex items-center rounded-xl text-sm font-medium transition-all px-4 py-3 gap-3"
        :class="[
          isCollapsed ? 'justify-center' : '',
          route.name === 'admin.action-logs'
            ? 'bg-gray-800 text-white'
            : 'text-gray-300 hover:bg-gray-800 hover:text-white'
        ]"
        @click="handleMobileMenuClick"
      >
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
        </svg>
        <span v-if="!isCollapsed">Логи действий</span>
      </router-link>
      <router-link
        to="/admin/documentation"
        class="flex items-center rounded-xl text-sm font-medium transition-all px-4 py-3 gap-3"
        :class="[
          isCollapsed ? 'justify-center' : '',
          route.name === 'admin.documentation'
            ? 'bg-gray-800 text-white'
            : 'text-gray-300 hover:bg-gray-800 hover:text-white'
        ]"
        @click="handleMobileMenuClick"
      >
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
        </svg>
        <span v-if="!isCollapsed">Документация</span>
      </router-link>
    </nav>
    <div class="border-t border-gray-800 space-y-3 p-4">
      <div class="flex items-center gap-3 px-2" :class="isCollapsed ? 'justify-center' : ''">
        <div class="h-10 w-10 rounded-full bg-blue-600 border border-blue-500 flex items-center justify-center text-sm font-bold text-white shrink-0">
          {{ userInitials }}
        </div>
        <div v-if="!isCollapsed" class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-white">{{ user?.name || 'Пользователь' }}</p>
          <p class="text-xs text-gray-400 truncate">{{ user?.email || '' }}</p>
        </div>
      </div>
      <button
        @click="handleLogout"
        class="w-full flex justify-start gap-2 px-4 py-2 text-gray-400 hover:text-white rounded-md hover:bg-gray-800"
        :class="isCollapsed ? 'justify-center' : ''"
        :title="isCollapsed ? 'Выйти' : ''"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
        </svg>
        <span v-if="!isCollapsed">Выйти</span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { ref, computed, inject } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';

const route = useRoute();
const authStore = useAuthStore();
const mobileMenu = inject('mobileMenu', null);

const isCollapsed = ref(localStorage.getItem('sidebarCollapsed') === 'true');
const isMobileMenuOpen = computed(() => mobileMenu?.isOpen?.value ?? false);
const user = computed(() => authStore.user);

const userInitials = computed(() => {
  if (!user.value?.name) return 'U';
  const names = user.value.name.split(' ');
  return names.map(n => n[0]).join('').toUpperCase().substring(0, 2);
});

const toggleCollapse = () => {
  isCollapsed.value = !isCollapsed.value;
  localStorage.setItem('sidebarCollapsed', isCollapsed.value.toString());
};

const handleLogout = async () => {
  await authStore.logout();
};

const handleMobileMenuClick = () => {
  if (mobileMenu && window.innerWidth < 1024) {
    mobileMenu.close();
  }
};
</script>
