<?php

namespace App\Console\Commands;

use App\Models\ContractAnalysis;
use App\Models\ContractSetting;
use Illuminate\Console\Command;

class ContractCleanupCommand extends Command
{
    protected $signature = 'contract:cleanup
                            {--months= : Срок хранения в месяцах (по умолчанию из настроек или config)}';

    protected $description = 'Удалить записи анализов договоров старше срока хранения (ТЗ п.9, по умолчанию 6 месяцев)';

    public function handle(): int
    {
        $months = $this->option('months') !== null
            ? (int) $this->option('months')
            : (int) (ContractSetting::get('analysis_retention_months') ?? config('contract.analysis_retention_months', 6));

        $before = now()->subMonths($months);
        $count = ContractAnalysis::where('created_at', '<', $before)->delete();

        $this->info("Удалено записей анализов старше {$months} мес.: {$count}.");
        return self::SUCCESS;
    }
}
