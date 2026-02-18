<?php

namespace App\Services\Contract;

use Carbon\Carbon;

/**
 * Проверка контрагента (Фаза 5). ТЗ: ФССП, ФНС (ГИР БО), судебные дела, массовый адрес,
 * массовый директор, задолженность, нулевая отчётность, признаки ненадёжности.
 * По каждому: статус (OK / Risk / Warning), источник, дата проверки.
 * Реализация: заглушка с мок-данными; интеграция с внешними API — позже.
 */
class CounterpartyCheckService
{
    public const STATUS_OK = 'OK';
    public const STATUS_RISK = 'Risk';
    public const STATUS_WARNING = 'Warning';

    /** Ключи пунктов проверки по ТЗ */
    private const ITEM_KEYS = [
        'fssp' => 'ФССП',
        'fns_gir_bo' => 'ФНС (ГИР БО)',
        'court_cases' => 'Судебные дела',
        'mass_address' => 'Массовый адрес',
        'mass_director' => 'Массовый директор',
        'debt' => 'Наличие задолженности',
        'zero_reporting' => 'Нулевая отчётность',
        'unreliability' => 'Признаки ненадёжности',
    ];

    /**
     * Выполнить проверку по ИНН. Заглушка: возвращает мок-данные по всем пунктам.
     *
     * @return array<int, array{name: string, status: string, source: string, checked_at: string}>
     */
    public function check(?string $inn): array
    {
        $checkedAt = Carbon::now()->toIso8601String();
        $items = [];

        foreach (self::ITEM_KEYS as $key => $label) {
            $items[] = [
                'name' => $label,
                'status' => $this->stubStatus($key, $inn),
                'source' => $this->stubSource($key),
                'checked_at' => $checkedAt,
            ];
        }

        return $items;
    }

    private function stubStatus(string $key, ?string $inn): string
    {
        if ($inn === null || $inn === '') {
            return self::STATUS_WARNING;
        }
        $hash = abs(crc32($key . substr($inn, -4)));
        if ($hash % 5 === 0) {
            return self::STATUS_RISK;
        }
        if ($hash % 7 === 0) {
            return self::STATUS_WARNING;
        }
        return self::STATUS_OK;
    }

    private function stubSource(string $key): string
    {
        return match ($key) {
            'fssp' => 'ФССП (заглушка)',
            'fns_gir_bo' => 'ФНС ГИР БО (заглушка)',
            'court_cases' => 'Судебные дела (заглушка)',
            'mass_address' => 'ЕГРЮЛ (заглушка)',
            'mass_director' => 'ЕГРЮЛ (заглушка)',
            'debt' => 'Налоговая (заглушка)',
            'zero_reporting' => 'ФНС (заглушка)',
            'unreliability' => 'Сводный отчёт (заглушка)',
            default => '—',
        };
    }
}
