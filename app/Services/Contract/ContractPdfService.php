<?php

namespace App\Services\Contract;

use App\Models\ContractAnalysis;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

/**
 * Генерация PDF-отчёта по анализу договора (Фаза 7).
 * Содержит: реквизиты, выжимка, проверка контрагента, источники, дата формирования.
 */
class ContractPdfService
{
    private const STORAGE_DISK = 'local';
    private const PDF_DIR = 'contract-pdfs';

    /**
     * Сгенерировать PDF и сохранить в storage. Возвращает относительный путь (для pdf_path).
     */
    public function generate(ContractAnalysis $analysis): string
    {
        $html = $this->buildHtml($analysis);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'analysis-' . $analysis->id . '.pdf';
        $relativePath = self::PDF_DIR . '/' . $filename;
        Storage::disk(self::STORAGE_DISK)->put($relativePath, $dompdf->output());

        return $relativePath;
    }

    private function buildHtml(ContractAnalysis $analysis): string
    {
        $title = e($analysis->title ?? 'Анализ договора');
        $dateFormed = $analysis->updated_at?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i');
        $summary = e($analysis->summary_text ?? '—');
        $summaryHtml = nl2br($summary);

        $counterpartyRows = '';
        $sources = [];
        foreach ((array) ($analysis->counterparty_check ?? []) as $item) {
            $name = e($item['name'] ?? '');
            $status = e($item['status'] ?? '');
            $source = e($item['source'] ?? '');
            $checkedAt = isset($item['checked_at']) ? date('d.m.Y H:i', strtotime($item['checked_at'])) : '—';
            $counterpartyRows .= "<tr><td>{$name}</td><td>{$status}</td><td>{$source}</td><td>{$checkedAt}</td></tr>";
            if ($source !== '' && ! in_array($source, $sources, true)) {
                $sources[] = $source;
            }
        }
        $sourcesHtml = $sources ? implode(', ', array_map('e', $sources)) : '—';

        return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; padding: 20px; color: #333; }
h1 { font-size: 16px; margin-bottom: 8px; }
h2 { font-size: 13px; margin-top: 16px; margin-bottom: 8px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
th { background: #f5f5f5; }
p { margin: 4px 0; }
.footer { margin-top: 24px; font-size: 10px; color: #666; }
</style>
</head>
<body>
<h1>Отчёт по анализу договора</h1>
<p><strong>Реквизиты:</strong> {$title}</p>
<p><strong>Дата формирования:</strong> {$dateFormed}</p>

<h2>Выжимка договора</h2>
<div>{$summaryHtml}</div>

<h2>Проверка контрагента</h2>
<table>
<thead><tr><th>Пункт</th><th>Статус</th><th>Источник</th><th>Дата проверки</th></tr></thead>
<tbody>{$counterpartyRows}</tbody>
</table>

<h2>Источники</h2>
<p>{$sourcesHtml}</p>

<p class="footer">Сформировано: {$dateFormed}</p>
</body>
</html>
HTML;
    }
}
