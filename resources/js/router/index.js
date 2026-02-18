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
    path: '/register',
    name: 'register',
    component: () => import('../pages/app/RegisterPage.vue'),
    meta: { requiresGuest: true },
  },
  {
    path: '/app',
    component: () => import('../layouts/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'app.home',
        component: () => import('../pages/app/AppHomePage.vue'),
        meta: { title: 'Анализ договора' },
      },
      {
        path: 'profile',
        name: 'app.profile',
        component: () => import('../pages/app/AppProfilePage.vue'),
        meta: { title: 'Профиль' },
      },
      {
        path: 'analysis/:id',
        name: 'app.analysis',
        component: () => import('../pages/app/AppAnalysisPage.vue'),
        meta: { title: 'Анализ' },
      },
    ],
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
        path: 'invite-codes',
        name: 'admin.invite-codes',
        component: () => import('../pages/admin/InviteCodesPage.vue'),
        meta: { title: 'Invite-коды' },
      },
      {
        path: 'users',
        name: 'admin.users',
        component: () => import('../pages/admin/UsersPage.vue'),
        meta: { title: 'Пользователи' },
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
    redirect: () => (localStorage.getItem('auth_token') ? '/app' : '/admin/login'),
  },
  {
    path: '/login',
    redirect: '/admin/login',
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/app',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();
  if (['admin.login', 'register'].includes(to.name) || ['admin.login', 'register'].includes(from?.name)) {
    authStore.clearError();
  }
  if (to.meta.requiresAuth) {
    if (!authStore.isAuthenticated) {
      const result = await authStore.fetchUser();
      if (!result.success) {
        return next({ name: 'admin.login', query: { redirect: to.fullPath } });
      }
    }
    // Доступ к /admin только для роли admin
    if (to.path.startsWith('/admin') && authStore.user?.role !== 'admin') {
      return next('/app');
    }
  }
  if (to.meta.requiresGuest) {
    if (authStore.isAuthenticated) {
      const redirect = to.query.redirect || '/app';
      return next(redirect);
    }
  }
  next();
});

export default router;
