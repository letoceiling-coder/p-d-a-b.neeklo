import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const routes = [
  {
    path: '/admin/login',
    name: 'admin.login',
    component: () => import('../pages/admin/auth/LoginPage.vue'),
    meta: { requiresGuest: true },
  },
  {
    path: '/admin',
    component: () => import('../layouts/AdminLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: { name: 'admin.dashboard' },
      },
      {
        path: 'dashboard',
        name: 'admin.dashboard',
        component: () => import('../pages/admin/DashboardPage.vue'),
        meta: { title: 'Панель управления' },
      },
      {
        path: 'bot',
        name: 'admin.bot',
        component: () => import('../pages/admin/BotSettingsPage.vue'),
        meta: { title: 'Настройки бота' },
      },
      {
        path: 'access-requests',
        name: 'admin.access-requests',
        component: () => import('../pages/admin/AccessRequestsPage.vue'),
        meta: { title: 'Запросы доступа' },
      },
      {
        path: 'ai-keys',
        name: 'admin.ai-keys',
        component: () => import('../pages/admin/AiKeysPage.vue'),
        meta: { title: 'Ключи API' },
      },
      {
        path: 'contract-analyses',
        name: 'admin.contract-analyses',
        component: () => import('../pages/admin/ContractAnalysesPage.vue'),
        meta: { title: 'История анализов' },
      },
      {
        path: 'contract-analyses/:id',
        name: 'admin.contract-analysis-detail',
        component: () => import('../pages/admin/ContractAnalysisDetailPage.vue'),
        meta: { title: 'Просмотр анализа' },
      },
      {
        path: 'contract-settings',
        name: 'admin.contract-settings',
        component: () => import('../pages/admin/ContractSettingsPage.vue'),
        meta: { title: 'Настройки анализа' },
      },
      {
        path: 'action-logs',
        name: 'admin.action-logs',
        component: () => import('../pages/admin/ActionLogsPage.vue'),
        meta: { title: 'Логи действий' },
      },
      {
        path: 'lexauto/orders',
        name: 'admin.lexauto.orders',
        component: () => import('../pages/admin/LexautoOrdersPage.vue'),
        meta: { title: 'LEXAUTO — Заявки' },
      },
      {
        path: 'documentation',
        name: 'admin.documentation',
        component: () => import('../pages/admin/DocumentationPage.vue'),
        meta: { title: 'Документация' },
      },
    ],
  },
  {
    path: '/',
    redirect: '/admin/dashboard',
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/admin/dashboard',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();
  if (['admin.login'].includes(to.name) || ['admin.login'].includes(from?.name)) {
    authStore.clearError();
  }
  if (to.meta.requiresAuth) {
    if (!authStore.isAuthenticated) {
      const result = await authStore.fetchUser();
      if (!result.success) {
        return next({ name: 'admin.login', query: { redirect: to.fullPath } });
      }
    }
  }
  if (to.meta.requiresGuest) {
    if (authStore.isAuthenticated) {
      const redirect = to.query.redirect || '/admin/dashboard';
      return next(redirect);
    }
  }
  next();
});

export default router;
