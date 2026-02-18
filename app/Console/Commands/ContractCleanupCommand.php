<?php

namespace App\Console\Commands;

use App\Models\ContractAnalysis;
use App\Models\ContractSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ContractCleanupCommand extends Command
{
    protected $signature = 'contract:cleanup
                            {--months= : Срок хранения в месяцах (по умолчанию из настроек или config)}';

    protected $description = 'Удалить записи анализов договоров и PDF старше срока хранения (ТЗ п.9, Фаза 8)';

    public function handle(): int
    {
        $months = $this->option('months') !== null
            ? (int) $this->option('months')
            : (int) (ContractSetting::get('analysis_retention_months') ?? config('contract.analysis_retention_months', 6));

        $before = now()->subMonths($months);
        $toDelete = ContractAnalysis::where('created_at', '<', $before)->get();

        foreach ($toDelete as $analysis) {
            if ($analysis->pdf_path && Storage::disk('local')->exists($analysis->pdf_path)) {
                Storage::disk('local')->delete($analysis->pdf_path);
            }
        }

        $count = ContractAnalysis::where('created_at', '<', $before)->delete();

        $this->info("Удалено записей анализов старше {$months} мес.: {$count}.");
        return self::SUCCESS;
    }
}
