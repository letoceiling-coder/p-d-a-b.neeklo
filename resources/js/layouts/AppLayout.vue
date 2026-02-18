<template>
  <div class="flex h-screen w-full overflow-hidden bg-gray-50">
    <AppSidebar />
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-gray-50">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import AppSidebar from '../components/app/AppSidebar.vue';

defineOptions({
  name: 'AppLayout',
});
</script>
