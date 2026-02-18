<?php

namespace App\Jobs;

use App\Models\ContractAnalysis;
use App\Services\Contract\ContractAnalysisException;
use App\Services\Contract\ContractFileHandler;
use App\Services\Contract\ContractAnalysisService;
use App\Services\Contract\ContractPdfService;
use App\Services\Contract\CounterpartyCheckService;
use App\Services\Contract\DocumentTextExtractor;
use App\Services\Contract\DocumentTextException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Фаза 4: извлечение текста из загруженных файлов, AI-выжимка, сохранение результата.
 */
class ProcessContractAnalysisJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $contractAnalysisId
    ) {}

    public function handle(): void
    {
        $analysis = ContractAnalysis::whereNotNull('user_id')->find($this->contractAnalysisId);
        if (! $analysis || $analysis->temp_upload_path === null) {
            return;
        }

        $tempDir = $analysis->temp_upload_path;
        if (! is_dir($tempDir)) {
            $this->failAnalysis($analysis, 'Временные файлы не найдены.');
            return;
        }

        $allowedExt = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $paths = [];
        foreach (scandir($tempDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $tempDir . DIRECTORY_SEPARATOR . $entry;
            if (! is_file($path)) {
                continue;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExt, true)) {
                $paths[] = $path;
            }
        }

        if (empty($paths)) {
            $this->failAnalysis($analysis, 'Нет файлов для анализа.');
            return;
        }

        try {
            $analysis->update(['processing_step' => 'extracting']);
            $extractor = app(DocumentTextExtractor::class);
            $fullText = $extractor->extractFromPaths($paths);
            if (trim($fullText) === '') {
                $this->failAnalysis($analysis, 'Не удалось извлечь текст из документов.');
                return;
            }

            $analysis->update(['processing_step' => 'report']);
            $analysisService = app(ContractAnalysisService::class);
            $result = $analysisService->analyze($fullText);

            $inn = $this->extractInnFromSummary($result['summary_text'], $result['summary_json'] ?? []);
            $analysis->update(['processing_step' => 'counterparty']);
            $counterpartyCheck = app(CounterpartyCheckService::class)->check($inn);

            $title = $inn
                ? 'ИНН ' . $inn . ' · ' . $analysis->created_at->format('d.m.Y')
                : ('— ' . $analysis->created_at->format('d.m.Y H:i'));

            $analysis->update([
                'title' => $title,
                'summary_text' => $result['summary_text'],
                'summary_json' => $result['summary_json'],
                'counterparty_check' => $counterpartyCheck,
                'status' => ContractAnalysis::STATUS_READY,
                'processing_step' => null,
                'temp_upload_path' => null,
            ]);

            try {
                $pdfPath = app(ContractPdfService::class)->generate($analysis->fresh());
                $analysis->update(['pdf_path' => $pdfPath]);
            } catch (\Throwable $e) {
                Log::warning('Contract PDF generation failed: ' . $e->getMessage());
            }

            ContractFileHandler::cleanup($paths);
            $this->cleanupTempDir($tempDir);
        } catch (DocumentTextException|ContractAnalysisException $e) {
            Log::warning('ProcessContractAnalysisJob: ' . $e->getMessage());
            $this->failAnalysis($analysis, $e->getMessage());
            ContractFileHandler::cleanup($paths);
            $this->cleanupTempDir($tempDir);
        } catch (\Throwable $e) {
            Log::error('ProcessContractAnalysisJob error: ' . $e->getMessage());
            $this->failAnalysis($analysis, 'Ошибка обработки.');
            if (! empty($paths)) {
                try {
                    ContractFileHandler::cleanup($paths);
                } catch (\Throwable $x) {
                    // ignore
                }
            }
            $this->cleanupTempDir($tempDir);
        }
    }

    private function extractInnFromSummary(string $summaryText, array $summaryJson): ?string
    {
        if (preg_match('/ИНН\s*[:\s]*(\d{10}|\d{12})/u', $summaryText, $m)) {
            return $m[1];
        }
        foreach ($summaryJson as $item) {
            $value = $item['value'] ?? $item['text'] ?? '';
            if (is_string($value) && preg_match('/(\d{10}|\d{12})/u', $value, $m)) {
                return $m[1];
            }
        }
        if (preg_match('/\b(\d{10})\b/u', $summaryText, $m)) {
            return $m[1];
        }
        return null;
    }

    private function failAnalysis(ContractAnalysis $analysis, string $reason): void
    {
        $analysis->update([
            'status' => ContractAnalysis::STATUS_DRAFT,
            'processing_step' => null,
        ]);
    }

    private function cleanupTempDir(string $dir): void
    {
        if (! is_dir($dir) || ! str_contains(realpath($dir) ?: '', realpath(storage_path('app/temp')) ?: '')) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
