<?php

namespace Database\Seeders;

use App\Models\AiModel;
use Illuminate\Database\Seeder;

class AiModelsSeeder extends Seeder
{
    /**
     * Рекомендуемые модели для анализа договоров и вызова через API.
     * Ключи API задаются в админке (Ключи API). Здесь только список моделей.
     */
    public function run(): void
    {
        $models = [
            // OpenAI — для анализа договоров (нужен ключ в Ключи API)
            ['provider' => 'openai', 'name' => 'GPT-4o', 'model_id' => 'gpt-4o', 'sort_order' => 10],
            ['provider' => 'openai', 'name' => 'GPT-4o mini', 'model_id' => 'gpt-4o-mini', 'sort_order' => 20],
            ['provider' => 'openai', 'name' => 'GPT-4 Turbo', 'model_id' => 'gpt-4-turbo', 'sort_order' => 30],
            // Gemini
            ['provider' => 'gemini', 'name' => 'Gemini 1.5 Flash', 'model_id' => 'gemini-1.5-flash', 'sort_order' => 40],
            ['provider' => 'gemini', 'name' => 'Gemini 1.5 Pro', 'model_id' => 'gemini-1.5-pro', 'sort_order' => 50],
        ];

        foreach ($models as $data) {
            AiModel::updateOrCreate(
                [
                    'provider' => $data['provider'],
                    'model_id' => $data['model_id'],
                ],
                [
                    'name' => $data['name'],
                    'is_active' => true,
                    'sort_order' => $data['sort_order'],
                ]
            );
        }
    }
}
