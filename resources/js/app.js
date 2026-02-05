import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router/index.js';
import App from './App.vue';
import '../css/app.css';
import { useAuthStore } from './stores/auth.js';

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);
app.use(router);

const authStore = useAuthStore();
const token = localStorage.getItem('auth_token');
if (token) {
  authStore.fetchUser().catch(() => {});
}

app.mount('#app');
