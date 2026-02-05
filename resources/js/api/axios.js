import axios from 'axios';

const apiClient = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { response } = error;
    if (response?.status === 401) {
      localStorage.removeItem('auth_token');
    }
    if (response?.status === 419) {
      try {
        await axios.get('/sanctum/csrf-cookie');
        return apiClient.request(error.config);
      } catch (csrfError) {
        console.error('CSRF token refresh failed:', csrfError);
      }
    }
    return Promise.reject(error);
  }
);

export default apiClient;
