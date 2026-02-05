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

    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * Проанализировать текст договора и вернуть выжимку.
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

        $systemPrompt = $this->getSystemPrompt();
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $this->truncateForModel($documentText)],
        ];

        $result = $this->aiService->chat($model->id, $messages);
        if ($result === null || trim($result['content'] ?? '') === '') {
            Log::warning('Contract analysis: AI returned empty or error');
            throw new ContractAnalysisException('Не удалось получить анализ от AI. Проверьте ключи и модель.');
        }

        $summaryText = trim($result['content']);
        $summaryJson = $this->parseSummaryToStructured($summaryText);

        return [
            'summary_text' => $summaryText,
            'summary_json' => $summaryJson,
        ];
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

    /**
     * Ограничить длину текста под лимит контекста (условно ~100k символов не передаём).
     */
    private function truncateForModel(string $text, int $maxChars = 120000): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }
        return mb_substr($text, 0, $maxChars) . "\n\n[Текст сокращён из-за ограничения длины.]";
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
