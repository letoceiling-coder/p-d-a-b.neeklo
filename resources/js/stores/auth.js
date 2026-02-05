import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import apiClient from '../api/axios.js';
import router from '../router/index.js';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const loading = ref(false);
  const error = ref(null);

  const isAuthenticated = computed(() => !!user.value);

  const extractValidationError = (err) => {
    const response = err.response;
    if (!response) return 'Произошла ошибка при выполнении запроса';
    const data = response.data;
    if (response.status === 422 && data.errors) {
      const first = Object.values(data.errors)[0];
      return Array.isArray(first) ? (first[0] || 'Ошибка валидации') : first;
    }
    if (data.message) return data.message;
    if (response.status === 401) return 'Неверный email или пароль';
    return 'Произошла ошибка при выполнении запроса';
  };

  const fetchCsrfCookie = async () => {
    try {
      await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
    } catch (err) {
      console.error('Failed to fetch CSRF cookie:', err);
    }
  };

  const login = async (credentials) => {
    loading.value = true;
    error.value = null;
    try {
      await fetchCsrfCookie();
      const response = await apiClient.post('/auth/login', credentials);
      user.value = response.data.user;
      if (response.data.token) {
        localStorage.setItem('auth_token', response.data.token);
      }
      return { success: true };
    } catch (err) {
      error.value = extractValidationError(err);
      return { success: false, error: error.value };
    } finally {
      loading.value = false;
    }
  };

  const logout = async () => {
    loading.value = true;
    error.value = null;
    try {
      await apiClient.post('/logout');
      user.value = null;
      localStorage.removeItem('auth_token');
      router.push('/admin/login');
    } catch (err) {
      console.error('Logout error:', err);
      user.value = null;
      localStorage.removeItem('auth_token');
      router.push('/admin/login');
    } finally {
      loading.value = false;
    }
  };

  const fetchUser = async () => {
    const token = localStorage.getItem('auth_token');
    if (!token) {
      user.value = null;
      return { success: false };
    }
    loading.value = true;
    error.value = null;
    try {
      const response = await apiClient.get('/user');
      user.value = response.data.user;
      return { success: true };
    } catch (err) {
      if (err.response?.status === 401) {
        localStorage.removeItem('auth_token');
      }
      user.value = null;
      return { success: false };
    } finally {
      loading.value = false;
    }
  };

  const clearError = () => {
    error.value = null;
  };

  return {
    user,
    loading,
    error,
    isAuthenticated,
    login,
    logout,
    fetchUser,
    clearError,
  };
});
