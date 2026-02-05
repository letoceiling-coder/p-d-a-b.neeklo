<template>
  <div class="documentation-page">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Документация</h1>
      <p class="text-gray-600 mt-1">Работа с ботом и админ-панелью: настройки, функционал и подсказки.</p>
    </div>

    <div class="flex flex-col lg:flex-row gap-8">
      <!-- Навигация по разделам -->
      <nav
        class="lg:w-56 shrink-0 lg:sticky lg:top-6 h-fit rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
        aria-label="Содержание документации"
      >
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Содержание</h2>
        <ul class="space-y-1 text-sm">
          <li v-for="item in navItems" :key="item.id">
            <a
              :href="`#${item.id}`"
              class="block py-1.5 px-2 rounded-md transition-colors"
              :class="activeId === item.id ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
              @click.prevent="scrollTo(item.id)"
            >
              {{ item.title }}
            </a>
          </li>
        </ul>
      </nav>

      <!-- Контент -->
      <div class="flex-1 min-w-0 space-y-8">
        <section id="intro" class="doc-section bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xl font-semibold text-gray-900 mb-3">Введение</h2>
          <p class="text-gray-600 mb-3">
            Система состоит из Telegram-бота для анализа договоров и веб-админки для настройки бота, ключей AI, одобрения доступа и просмотра истории. Бот принимает фото/документы договоров и возвращает текстовую выжимку с ключевыми условиями (AI).
          </p>
          <p class="text-gray-600">
            Доступ к админ-панели имеют только пользователи с одобренным запросом на роль администратора. Запрос отправляется из бота командой <code class="px-1.5 py-0.5 bg-gray-100 rounded text-sm">/admin</code>.
          </p>
        </section>

        <section id="bot-usage" class="doc-section bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xl font-semibold text-gray-900 mb-3">Работа с ботом</h2>
          <ul class="list-disc list-inside text-gray-600 space-y-2 mb-3">
            <li><strong>/start</strong> — запуск бота, приветственное сообщение (настраивается в админке).</li>
            <li><strong>Отправка договора</strong> — пользователь отправляет фото или документ (изображение) договора; бот обрабатывает через выбранную AI-модель и присылает выжимку в соответствии с настройками (полная/краткая/обе).</li>
            <li><strong>/admin</strong> — запрос доступа к админ-панели. Появляется в разделе «Запросы доступа»; администратор может одобрить или отклонить.</li>
          </ul>
          <p class="text-gray-600">
            Лимиты: максимальное количество фото в одном запросе и срок хранения анализов задаются в разделе «Настройки анализа».
          </p>
        </section>

        <section id="admin-overview" class="doc-section bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xl font-semibold text-gray-900 mb-3">Админ-панель — обзор</h2>
          <p class="text-gray-600 mb-3">
            В боковом меню доступны: Панель управления, Бот, Запросы доступа, Ключи API, История анализов, Настройки анализа, Логи действий и Документация. Ниже — краткое описание каждого раздела и основных настроек.
          </p>
        </section>

        <section id="dashboard" class="doc-section bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xl font-semibold text-gray-900 mb-3">Панель управления</h2>
          <p class="text-gray-600">
            Главная страница после входа. Здесь отображается общая информация и статистика по системе. Используйте её как стартовую точку для перехода в нужный раздел.
          </p>
        </section>

        <section id="bot-settings" class="doc-section bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xl font-semibold text-gray-900 mb-3">Настройки бота</h2>
          <p class="text-gray-600 mb-3">
            <strong>Токен бота</strong> — получается у @BotFather в Telegram. После сохранения токена автоматически настраивается webhook для приёма обновлений. Кнопка «Тест Webhook» проверяет доступность сервера для Telegram.
          </p>
          <p class="text-gray-600 mb-3">
            <strong>Приветственное сообщение</strong> — текст, который пользователь видит при команде /start. Можно оставить пустым для значения по умолчанию.
          </p>
          <p class="text-gray-600">
            <strong>Описание бота</strong> — краткое описание, отображаемое в карточке бота в Telegram (по желанию).
          </p>
        </section>

        <section id="access-requests" class="doc-section bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xl font-semibold text-gray-900 mb-3">Запросы доступа</h2>
          <p class="text-gray-600 mb-3">
            Пользователи отправляют в боте команду <code class="px-1.5 py-0.5 bg-gray-100 rounded text-sm">/admin</code> — здесь отображаются все запросы. Можно фильтровать: Все, Ожидают, Одобрены, Отклонены.
          </p>
          <p class="text-gray-600">
            Одобрение даёт пользователю роль администратора и доступ к админ-панели. Отклонённые запросы не получают доступ; при необходимости пользователь может отправить запрос снова.
          </p>
        </section>

        <section id="ai-keys" class="doc-section bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xl font-semibold text-gray-900 mb-3">Ключи API</h2>
          <p class="text-gray-600 mb-3">
            Здесь настраиваются провайдеры AI для анализа договоров:
          </p>
          <ul class="list-disc list-inside text-gray-600 space-y-1 mb-3">
            <li><strong>Gemini</strong> — API ключ Google; ссылки на получение ключа и на страницу баланса/квот (Google AI Studio).</li>
            <li><strong>OpenAI</strong> — API ключ OpenAI; ссылка на создание ключа и на использование/баланс в личном кабинете.</li>
          </ul>
          <p class="text-gray-600 mb-3">
            Для каждого провайдера можно нажать «Проверить ключ» — система проверит доступ и отобразит результат. Ключи хранятся в замаскированном виде; для смены введите новый ключ и сохраните.
          </p>
          <p class="text-gray-600">
            <strong>Модели</strong> — список моделей (Gemini, OpenAI), доступных для анализа. Можно включать/отключать модели и задавать порядок выбора. Модель по умолчанию для анализа выбирается в разделе «Настройки анализа» (или «Первая активная»).
          </p>
        </section>

        <section id="ai-models-capabilities" class="doc-section bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xl font-semibold text-gray-900 mb-3">Возможности моделей AI</h2>
          <p class="text-gray-600 mb-4">
            Ниже — рекомендуемые модели для анализа договоров. В разделе «Ключи API» при добавлении модели укажите <strong>ID модели (API)</strong> из столбца «ID в API». Название можно задать любое для удобства.
          </p>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left font-medium text-gray-700">Провайдер</th>
                  <th class="px-4 py-2 text-left font-medium text-gray-700">Название</th>
                  <th class="px-4 py-2 text-left font-medium text-gray-700">ID в API</th>
                  <th class="px-4 py-2 text-left font-medium text-gray-700">Возможности</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 text-gray-600">
                <tr>
                  <td class="px-4 py-3 font-medium text-gray-800">OpenAI</td>
                  <td>GPT-4o</td>
                  <td><code class="bg-gray-100 px-1.5 py-0.5 rounded">gpt-4o</code></td>
                  <td>Флагманская мультимодальная модель: высокое качество ответов, поддержка текста и изображений, быстрые ответы. Подходит для сложных договоров и больших объёмов текста.</td>
                </tr>
                <tr>
                  <td class="px-4 py-3 font-medium text-gray-800">OpenAI</td>
                  <td>GPT-4o mini</td>
                  <td><code class="bg-gray-100 px-1.5 py-0.5 rounded">gpt-4o-mini</code></td>
                  <td>Облегчённая версия GPT-4o: дешевле и быстрее, хорошее качество для типовых выжимок. Удобна при большом числе запросов.</td>
                </tr>
                <tr>
                  <td class="px-4 py-3 font-medium text-gray-800">OpenAI</td>
                  <td>GPT-4 Turbo</td>
                  <td><code class="bg-gray-100 px-1.5 py-0.5 rounded">gpt-4-turbo</code></td>
                  <td>Мощная модель с большим контекстом, отличное качество анализа длинных документов. Выше стоимость запроса, чем у mini.</td>
                </tr>
                <tr>
                  <td class="px-4 py-3 font-medium text-gray-800">Gemini</td>
                  <td>Gemini 1.5 Flash</td>
                  <td><code class="bg-gray-100 px-1.5 py-0.5 rounded">gemini-1.5-flash</code></td>
                  <td>Быстрая и экономичная модель Google: низкая задержка, большой контекст, подходит для массового анализа и простых выжимок.</td>
                </tr>
                <tr>
                  <td class="px-4 py-3 font-medium text-gray-800">Gemini</td>
                  <td>Gemini 1.5 Pro</td>
                  <td><code class="bg-gray-100 px-1.5 py-0.5 rounded">gemini-1.5-pro</code></td>
                  <td>Продвинутая модель с очень большим контекстом: детальный анализ длинных договоров, сложные инструкции. Баланс качества и стоимости.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="text-gray-500 text-sm mt-3">
            Итог: для экономии и скорости — <strong>GPT-4o mini</strong> или <strong>Gemini 1.5 Flash</strong>; для максимального качества — <strong>GPT-4o</strong> или <strong>Gemini 1.5 Pro</strong>; для очень длинных документов — <strong>GPT-4 Turbo</strong> или <strong>Gemini 1.5 Pro</strong>.
          </p>
        </section>

        <section id="contract-analyses" class="doc-section bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xl font-semibold text-gray-900 mb-3">История анализов</h2>
          <p class="text-gray-600">
            Список всех выполненных анализов договоров: пользователь (Telegram), дата, использованная модель, превью результата. Удобно для контроля использования и разбора обращений. Срок хранения записей задаётся в «Настройках анализа».
          </p>
        </section>

        <section id="contract-settings" class="doc-section bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xl font-semibold text-gray-900 mb-3">Настройки анализа</h2>
          <p class="text-gray-600 mb-3">
            Основные параметры выдачи и лимиты:
          </p>
          <ul class="list-disc list-inside text-gray-600 space-y-1 mb-3">
            <li><strong>Формат выдачи в Telegram</strong> — полная выжимка (одно сообщение), краткая (одно сообщение) или сначала краткая, затем полная (два сообщения).</li>
            <li><strong>Макс. символов в сообщении</strong> — для полной выжимки (ограничение длины сообщения в Telegram).</li>
            <li><strong>Длина краткой выжимки</strong> — количество символов для краткого варианта.</li>
            <li><strong>Макс. фото в одном запросе</strong> — сколько изображений можно отправить за раз.</li>
            <li><strong>Срок хранения анализов</strong> — сколько месяцев хранить записи в истории.</li>
            <li><strong>Модель AI по умолчанию</strong> — какая модель используется для анализа договоров; вариант «Первая активная» берёт первую включённую модель из списка в «Ключах API».</li>
          </ul>
          <p class="text-gray-600">
            После изменения нажмите «Сохранить».
          </p>
        </section>

        <section id="action-logs" class="doc-section bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xl font-semibold text-gray-900 mb-3">Логи действий</h2>
          <p class="text-gray-600">
            Журнал действий администраторов в панели: кто и когда выполнял действия (вход, смена настроек, одобрение/отклонение запросов и т.д.). Помогает аудиту и разбору инцидентов.
          </p>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const navItems = [
  { id: 'intro', title: 'Введение' },
  { id: 'bot-usage', title: 'Работа с ботом' },
  { id: 'admin-overview', title: 'Админ-панель — обзор' },
  { id: 'dashboard', title: 'Панель управления' },
  { id: 'bot-settings', title: 'Настройки бота' },
  { id: 'access-requests', title: 'Запросы доступа' },
  { id: 'ai-keys', title: 'Ключи API' },
  { id: 'ai-models-capabilities', title: 'Возможности моделей AI' },
  { id: 'contract-analyses', title: 'История анализов' },
  { id: 'contract-settings', title: 'Настройки анализа' },
  { id: 'action-logs', title: 'Логи действий' },
];

const activeId = ref('intro');

function scrollTo(id) {
  const el = document.getElementById(id);
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

let observer = null;

onMounted(() => {
  const sections = navItems.map((i) => ({ id: i.id, el: document.getElementById(i.id) })).filter((s) => s.el);
  if (sections.length === 0) return;
  observer = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        const id = entry.target.id;
        if (navItems.some((i) => i.id === id)) activeId.value = id;
      }
    },
    { root: null, rootMargin: '-80px 0px -60% 0px', threshold: 0 }
  );
  sections.forEach(({ el }) => observer.observe(el));
});

onUnmounted(() => {
  if (observer) {
    navItems.forEach((i) => {
      const el = document.getElementById(i.id);
      if (el) observer.unobserve(el);
    });
  }
});
</script>

<style scoped>
.doc-section {
  scroll-margin-top: 1.5rem;
}
</style>
