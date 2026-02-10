<?php

return [
    'bot_token' => env('LEXAUTO_BOT_TOKEN', ''),
    'reservation_minutes' => (int) env('LEXAUTO_RESERVATION_MINUTES', 30),
    'total_seats' => (int) env('LEXAUTO_TOTAL_SEATS', 100),
    'price' => (float) env('LEXAUTO_PRICE', 500),
    'google_sheet_url' => env('LEXAUTO_GOOGLE_SHEET_URL', ''),
];
