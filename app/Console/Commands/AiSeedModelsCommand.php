<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AiSeedModelsCommand extends Command
{
    protected $signature = 'ai:seed-models';

    protected $description = 'Добавить рекомендуемые модели AI (OpenAI, Gemini) для анализа договоров';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => \Database\Seeders\AiModelsSeeder::class]);
        $this->info('Модели созданы. Ключи API настройте в админке (Ключи API).');
        return self::SUCCESS;
    }
}
