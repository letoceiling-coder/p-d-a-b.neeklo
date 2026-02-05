<?php

return [
    /*
    | Сообщение при ошибке загрузки (ТЗ п.7)
    */
    'error_upload_message' => 'Пожалуйста, загрузите договор или выбранные страницы договора.',

    /*
    | Лимит количества фото в одном запросе (ТЗ п.5.2 — первый этап)
    */
    'max_photos_per_request' => 5,

    /*
    | Максимальный размер одного файла (байты). Telegram до 20 MB для бота.
    */
    'max_file_size_bytes' => 20 * 1024 * 1024,

    /*
    | Срок хранения результатов анализа (месяцы). ТЗ п.9 — по умолчанию 6 месяцев.
    */
    'analysis_retention_months' => 6,

    /*
    | ID модели AI для анализа договоров (таблица ai_models). 0 = первая активная по sort_order.
    */
    'default_ai_model_id' => (int) env('CONTRACT_DEFAULT_AI_MODEL_ID', 0),

    /*
    | Допустимые MIME-типы для загрузки
    */
    'allowed_mime_types' => [
        'application/pdf',
        'application/msword',                                                    // .doc
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'image/jpeg',
        'image/png',
        'application/zip',
    ],

    /*
    | Допустимые расширения (для имени файла)
    */
    'allowed_extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip'],

    /*
    | Ключевое слово в имени файла для приоритета при выборе из ZIP (ТЗ п.4.2)
    */
    'priority_filename_keyword' => 'договор',

    /*
    | OCR: по умолчанию Tesseract-api на том же сервере (порт 8080, путь /ocr, поле file).
    | Чтобы использовать локальный бинарник — задайте TESSERACT_API_URL= в .env (пусто).
    */
    'tesseract_api_url' => env('TESSERACT_API_URL', 'http://127.0.0.1:8080/ocr'),
    'tesseract_api_field' => env('TESSERACT_API_FIELD', 'file'),

    /*
    | Путь к исполняемому файлу Tesseract (используется, если tesseract_api_url пуст).
    */
    'tesseract_path' => env('TESSERACT_PATH', 'tesseract'),

    /*
    | Языки для Tesseract (binary или заголовок X-Language для API).
    */
    'tesseract_lang' => env('TESSERACT_LANG', 'rus+eng'),

    /*
    | Формат выдачи результата в Telegram (ТЗ п.8).
    | full — одно сообщение с полной выжимкой (до telegram_max_message_chars).
    | short — одно сообщение с краткой выжимкой (до telegram_short_summary_chars).
    | both — сначала краткая, затем полная выжимка двумя сообщениями.
    */
    'telegram_summary_mode' => env('CONTRACT_TELEGRAM_SUMMARY_MODE', 'full'),

    /*
    | Максимум символов в одном сообщении Telegram (лимит API 4096).
    */
    'telegram_max_message_chars' => (int) env('CONTRACT_TELEGRAM_MAX_CHARS', 4090),

    /*
    | Длина краткой выжимки (при mode short или first сообщение при mode both).
    */
    'telegram_short_summary_chars' => (int) env('CONTRACT_TELEGRAM_SHORT_CHARS', 600),

    /*
    | Шифровать ли персональные данные в bot_users (first_name, last_name, username). ТЗ п.13.
    | Включите после настройки APP_KEY. Существующие записи останутся в открытом виде до следующего обновления.
    */
    'encrypt_bot_user_pii' => env('CONTRACT_ENCRYPT_BOT_USER_PII', false),
];
