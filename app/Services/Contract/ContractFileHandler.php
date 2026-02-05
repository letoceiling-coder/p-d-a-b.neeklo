<?php

namespace App\Services\Contract;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Приём и валидация файлов договоров из Telegram (ТЗ п.4–7).
 * Скачивает файл во временную папку, проверяет тип и размер, для ZIP — распаковка и отбор файлов.
 * Вызывающий код обязан удалить возвращённые пути после обработки.
 */
class ContractFileHandler
{
    private string $tempDir;

    public function __construct()
    {
        $this->tempDir = storage_path('app/temp/contracts/' . uniqid('contract_', true));
    }

    /**
     * Получить file_id из сообщения (документ или самое большое фото).
     */
    public static function getFileIdFromMessage(array $message): ?string
    {
        if (!empty($message['document']['file_id'])) {
            return $message['document']['file_id'];
        }
        if (!empty($message['photo']) && is_array($message['photo'])) {
            $sizes = $message['photo'];
            $largest = end($sizes);
            return $largest['file_id'] ?? null;
        }
        return null;
    }

    /**
     * Скачать файл по file_id через Telegram Bot API, проверить тип/размер, при ZIP — распаковать и отфильтровать.
     * Возвращает список путей к файлам для анализа (первый — приоритетный при наличии «договор» в имени).
     *
     * @return string[] пути к локальным файлам
     * @throws ContractFileException при ошибке валидации или загрузки
     */
    public function downloadAndValidate(string $botToken, array $message): array
    {
        $fileId = self::getFileIdFromMessage($message);
        if (!$fileId) {
            throw new ContractFileException('Нет файла в сообщении.');
        }

        $fileInfo = $this->getFileFromTelegram($botToken, $fileId);
        if (!$fileInfo) {
            throw new ContractFileException('Не удалось получить файл от Telegram.');
        }

        $filePath = $fileInfo['file_path'] ?? null;
        $fileName = $message['document']['file_name'] ?? basename($filePath ?? '');
        $fileSize = $message['document']['file_size'] ?? $message['photo'][array_key_last($message['photo'] ?? [])]['file_size'] ?? null;

        if (!$filePath) {
            throw new ContractFileException('Путь к файлу не получен.');
        }

        $this->ensureTempDir();
        $localPath = $this->downloadFile($botToken, $filePath, $fileName);

        $ext = strtolower(pathinfo($fileName ?: $localPath, PATHINFO_EXTENSION));
        if ($fileSize !== null && $fileSize > (int) config('contract.max_file_size_bytes', 20 * 1024 * 1024)) {
            $this->deleteLocalFile($localPath);
            throw new ContractFileException('Файл слишком большой.');
        }

        $allowedExtensions = array_map('strtolower', config('contract.allowed_extensions', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip']));
        if (!in_array($ext, $allowedExtensions, true)) {
            $this->deleteLocalFile($localPath);
            throw new ContractFileException('Неподдерживаемый формат.');
        }

        if ($ext === 'zip') {
            $paths = $this->extractAndFilterZip($localPath);
            $this->deleteLocalFile($localPath);
            if (empty($paths)) {
                throw new ContractFileException('В архиве нет подходящих файлов.');
            }
            return $paths;
        }

        return [$localPath];
    }

    /**
     * Удалить временные файлы и каталог (если пустой).
     */
    public static function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
        $dir = dirname($paths[0] ?? '');
        if ($dir !== '' && is_dir($dir) && str_contains(realpath($dir) ?: '', realpath(storage_path('app/temp')) ?: '')) {
            @rmdir($dir);
        }
    }

    private function getFileFromTelegram(string $botToken, string $fileId): ?array
    {
        $result = $this->getFileFromTelegramOnce($botToken, $fileId);
        if ($result !== null && !empty($result['file_path'])) {
            return $result;
        }
        // Иногда Telegram не сразу возвращает file_path — повторный запрос через 1 сек
        if ($result !== null && empty($result['file_path'])) {
            Log::info('Telegram getFile: file_path пустой, повтор через 1 сек', ['file_id' => $fileId, 'result' => $result]);
            sleep(1);
            $result = $this->getFileFromTelegramOnce($botToken, $fileId);
        }
        if ($result !== null && !empty($result['file_path'])) {
            return $result;
        }
        if ($result !== null) {
            Log::warning('Telegram getFile: file_path так и не получен', ['file_id' => $fileId, 'result' => $result]);
        }
        return $result;
    }

    private function getFileFromTelegramOnce(string $botToken, string $fileId): ?array
    {
        try {
            $response = Http::timeout(15)->get("https://api.telegram.org/bot{$botToken}/getFile", [
                'file_id' => $fileId,
            ]);
            $data = $response->json();
            if (!$response->successful() || empty($data['ok']) || empty($data['result'])) {
                Log::warning('Telegram getFile: неверный ответ', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }
            return $data['result'];
        } catch (\Throwable $e) {
            Log::warning('Telegram getFile error: ' . $e->getMessage());
        }
        return null;
    }

    private function downloadFile(string $botToken, string $telegramFilePath, string $originalFileName): string
    {
        $url = "https://api.telegram.org/file/bot{$botToken}/" . ltrim($telegramFilePath, '/');
        $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION)) ?: pathinfo($telegramFilePath, PATHINFO_EXTENSION);
        $allowed = config('contract.allowed_extensions', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip']);
        if (!in_array(strtolower($ext), array_map('strtolower', $allowed), true)) {
            $ext = 'bin';
        }
        $localPath = $this->tempDir . '/' . 'file.' . $ext;

        $response = Http::timeout(30)->get($url);
        if (!$response->successful()) {
            throw new ContractFileException('Не удалось скачать файл.');
        }

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        file_put_contents($localPath, $response->body());
        return $localPath;
    }

    private function extractAndFilterZip(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new ContractFileException('Архив повреждён или нечитаем.');
        }

        $allAllowed = config('contract.allowed_extensions', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip']);
        $allowedExtensions = array_values(array_filter(
            array_map('strtolower', $allAllowed),
            fn ($e) => $e !== 'zip'
        ));
        if ($allowedExtensions === []) {
            $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        }
        $keyword = mb_strtolower(config('contract.priority_filename_keyword', 'договор'));

        $candidates = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            $name = $entry['name'];
            if (str_ends_with($name, '/')) {
                continue;
            }
            $baseName = basename($name);
            $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions, true)) {
                continue;
            }
            $candidates[] = [
                'name' => $name,
                'basename' => $baseName,
                'ext' => $ext,
                'has_keyword' => $keyword !== '' && mb_strpos(mb_strtolower($baseName), $keyword) !== false,
            ];
        }
        $zip->close();

        if (empty($candidates)) {
            return [];
        }

        usort($candidates, function ($a, $b) {
            if ($a['has_keyword'] !== $b['has_keyword']) {
                return $a['has_keyword'] ? -1 : 1;
            }
            return strcmp($a['basename'], $b['basename']);
        });

        $this->ensureTempDir();
        $zip = new ZipArchive();
        $zip->open($zipPath);
        $outPaths = [];
        foreach ($candidates as $c) {
            $targetPath = $this->tempDir . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $c['basename']);
            $contents = $zip->getFromName($c['name']);
            if ($contents !== false && file_put_contents($targetPath, $contents) !== false) {
                $outPaths[] = $targetPath;
            }
        }
        $zip->close();

        return $outPaths;
    }

    private function ensureTempDir(): void
    {
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    private function deleteLocalFile(string $path): void
    {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}
