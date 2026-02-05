<?php

namespace App\Services\Contract;

use App\Models\AiModel;
use App\Models\ContractSetting;
use App\Services\Ai\AiService;
use Illuminate\Support\Facades\Log;

/**
 * Анализ текста договора с помощью AI: извлечение структурированной выжимки по 16 пунктам (ТЗ п.6).
 * Не выполняет юридическую экспертизу и правовую оценку.
 */
class ContractAnalysisService
{
    /** Промпт по умолчанию, если в настройках пусто */
    private const DEFAULT_SYSTEM_PROMPT = <<<'TEXT'
Ты — помощник по извлечению информации из договоров. Твоя задача: по тексту договора составить структурированную выжимку.

Требования:
- Отвечай только на основе приведённого текста. Не давай юридических оценок, не проверяй соответствие законодательству.
- Выжимка — нумерованный список из 15–20 пунктов.
- Для каждого пункта, по которому в договоре есть информация, укажи краткое содержание. Если в тексте нет данных по пункту — не включай его в список или напиши «Не указано».

Извлекай сведения по следующим темам (если есть в тексте):
1. Предмет договора
2. Цена договора
3. Срок начала оказания услуг
4. Срок окончания оказания услуг
5. Срок предоставления заявки
6. Условия использования спецсчёта
7. Порядок оплаты (аванс, сроки, процедура)
8. Необходимые документы для оплаты
9. Ответственность сторон
10. Размер ответственности
11. Подсудность / порядок разрешения споров
12. Требования к транспорту
13. Требования к квалификации персонала
14. Условия расторжения
15. Специальные разрешения
16. Единичные расценки (если присутствуют)

Формат ответа: строго нумерованный список. Каждый пункт с новой строки. Без вступления и заключения.
TEXT;

    private const MERGE_SYSTEM_PROMPT = <<<'TEXT'
Ты объединяешь выжимки по фрагментам одного договора в одну итоговую выжимку.

На вход — несколько нумерованных списков (выжимки по частям документа). Нужно:
- Объединить в один нумерованный список по темам: предмет договора, цена, сроки, оплата, документы для оплаты, ответственность, размер ответственности, подсудность, расторжение и т.д.
- Убрать повторы: если одна и та же тема встречается в нескольких фрагментах — оставить одно объединённое или наиболее полное значение.
- Сохранить все уникальные сведения из всех фрагментов.
- Формат: строго нумерованный список. Без вступления и заключения.
TEXT;

    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * Проанализировать текст договора и вернуть выжимку.
     * Если текст длиннее порога — разбивается на части, каждая анализируется, результаты объединяются.
     *
     * @return array{summary_text: string, summary_json: array|null}
     * @throws ContractAnalysisException
     */
    public function analyze(string $documentText): array
    {
        $documentText = trim($documentText);
        if ($documentText === '') {
            throw new ContractAnalysisException('Текст договора пуст.');
        }

        $model = $this->resolveModel();
        if (!$model) {
            throw new ContractAnalysisException('Не настроена активная модель AI для анализа договоров.');
        }

        $maxChars = $this->getMaxCharsPerRequest();
        if (mb_strlen($documentText) <= $maxChars) {
            return $this->analyzeSingle($model->id, $documentText);
        }

        return $this->analyzeByChunks($model->id, $documentText);
    }

    /**
     * Один запрос к AI на весь текст (в пределах лимита).
     */
    private function analyzeSingle(int $modelId, string $text): array
    {
        $systemPrompt = $this->getSystemPrompt();
        $content = $this->truncateToMaxChars($text, $this->getMaxCharsPerRequest());
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $content],
        ];

        $result = $this->aiService->chat($modelId, $messages);
        if ($result === null || trim($result['content'] ?? '') === '') {
            Log::warning('Contract analysis: AI returned empty or error');
            throw new ContractAnalysisException('Не удалось получить анализ от AI. Проверьте ключи и модель.');
        }

        $summaryText = trim($result['content']);
        return [
            'summary_text' => $summaryText,
            'summary_json' => $this->parseSummaryToStructured($summaryText),
        ];
    }

    /**
     * Разбить длинный документ на части, проанализировать каждую, объединить выжимки.
     */
    private function analyzeByChunks(int $modelId, string $documentText): array
    {
        $chunkSize = (int) config('contract.chunk_size', 32000);
        $overlap = (int) config('contract.chunk_overlap', 2000);
        if ($chunkSize <= 0) {
            $chunkSize = 32000;
        }
        $overlap = max(0, min($overlap, $chunkSize - 1000));

        $chunks = $this->splitIntoChunks($documentText, $chunkSize, $overlap);
        if (empty($chunks)) {
            throw new ContractAnalysisException('Не удалось разбить документ на части.');
        }

        $systemPrompt = $this->getSystemPrompt();
        $partialSummaries = [];

        foreach ($chunks as $i => $chunk) {
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $chunk],
            ];
            $result = $this->aiService->chat($modelId, $messages);
            if ($result !== null && trim($result['content'] ?? '') !== '') {
                $partialSummaries[] = trim($result['content']);
            } else {
                Log::warning("Contract analysis: chunk " . ($i + 1) . " returned empty");
            }
        }

        if (empty($partialSummaries)) {
            throw new ContractAnalysisException('Не удалось получить анализ от AI по частям документа.');
        }

        if (count($partialSummaries) === 1) {
            $summaryText = $partialSummaries[0];
            return [
                'summary_text' => $summaryText,
                'summary_json' => $this->parseSummaryToStructured($summaryText),
            ];
        }

        $summaryText = $this->mergePartialSummaries($modelId, $partialSummaries);
        return [
            'summary_text' => $summaryText,
            'summary_json' => $this->parseSummaryToStructured($summaryText),
        ];
    }

    /**
     * Разбить текст на фрагменты с перекрытием (по границам абзацев, где возможно).
     */
    private function splitIntoChunks(string $text, int $chunkSize, int $overlap): array
    {
        $chunks = [];
        $len = mb_strlen($text);
        $pos = 0;

        while ($pos < $len) {
            $take = min($chunkSize, $len - $pos);
            $slice = mb_substr($text, $pos, $take);

            if ($pos + $take < $len) {
                $lastNewline = mb_strrpos($slice, "\n");
                if ($lastNewline !== false && $lastNewline > (int) ($chunkSize * 0.5)) {
                    $slice = mb_substr($slice, 0, $lastNewline + 1);
                }
            }

            $chunks[] = $slice;
            $pos += mb_strlen($slice);
            if ($pos < $len && $overlap > 0) {
                $pos -= $overlap;
            }
        }

        return $chunks;
    }

    /**
     * Объединить выжимки по фрагментам в одну через отдельный запрос к AI.
     */
    private function mergePartialSummaries(int $modelId, array $partialSummaries): string
    {
        $combined = '';
        foreach ($partialSummaries as $i => $s) {
            $combined .= "--- Фрагмент " . ($i + 1) . " ---\n\n" . $s . "\n\n";
        }
        $combined = trim($combined);

        $maxMergeInput = 30000;
        if (mb_strlen($combined) > $maxMergeInput) {
            $combined = mb_substr($combined, 0, $maxMergeInput) . "\n\n[Часть текста опущена при объединении.]";
        }

        $messages = [
            ['role' => 'system', 'content' => self::MERGE_SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $combined],
        ];

        $result = $this->aiService->chat($modelId, $messages);
        if ($result !== null && trim($result['content'] ?? '') !== '') {
            return trim($result['content']);
        }

        return implode("\n\n---\n\n", $partialSummaries);
    }

    private function getMaxCharsPerRequest(): int
    {
        $maxChars = (int) (ContractSetting::get('max_document_chars_for_ai') ?? config('contract.max_document_chars_for_ai', 35000));
        return $maxChars <= 0 ? 35000 : $maxChars;
    }

    /**
     * Системный промпт: из настроек (админка) или конфиг/константа по умолчанию.
     */
    private function getSystemPrompt(): string
    {
        $prompt = ContractSetting::get('ai_system_prompt');
        $prompt = is_string($prompt) ? trim($prompt) : '';
        return $prompt !== '' ? $prompt : self::DEFAULT_SYSTEM_PROMPT;
    }

    /**
     * Модель для анализа: первая активная или id из конфига.
     */
    private function resolveModel(): ?AiModel
    {
        $id = (int) (ContractSetting::get('default_ai_model_id') ?? config('contract.default_ai_model_id', 0));
        if ($id > 0) {
            $model = AiModel::where('id', $id)->active()->first();
            if ($model) {
                return $model;
            }
        }
        return AiModel::active()->ordered()->first();
    }

    private function truncateToMaxChars(string $text, int $maxChars): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }
        return mb_substr($text, 0, $maxChars) . "\n\n[Текст сокращён из-за ограничения длины для API.]";
    }

    /**
     * Преобразовать текстовую выжимку в массив пунктов для JSON (админка, хранение).
     */
    private function parseSummaryToStructured(string $summaryText): array
    {
        $lines = preg_split('/\r?\n/', $summaryText, -1, PREG_SPLIT_NO_EMPTY);
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\d+[.)]\s*(.+)$/u', $line, $m)) {
                $items[] = ['title' => '', 'value' => trim($m[1])];
            } else {
                $items[] = ['title' => '', 'value' => $line];
            }
        }
        return $items;
    }
}
