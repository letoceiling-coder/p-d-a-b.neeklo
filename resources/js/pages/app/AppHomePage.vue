<template>
  <div class="flex flex-col items-center justify-center min-h-full text-center px-4 py-12">
    <div class="max-w-md space-y-4">
      <div class="w-16 h-16 mx-auto rounded-full bg-gray-100 flex items-center justify-center">
        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      </div>
      <h2 class="text-xl font-semibold text-gray-900">Анализ договора</h2>
      <p class="text-gray-500 text-sm">
        Нажмите «Новый анализ» в боковой панели или загрузите документы ниже, чтобы начать.
      </p>
      <div class="pt-4">
        <router-link
          :to="{ name: 'app.home' }"
          class="inline-flex items-center gap-2 rounded-lg bg-gray-900 text-white px-4 py-2.5 text-sm font-medium hover:bg-gray-800"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Новый анализ
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
defineOptions({
  name: 'AppHomePage',
});
</script>
