<?php

namespace App\Services\Contract;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Извлечение текста из файлов договоров: PDF, DOC/DOCX, JPG/PNG (OCR).
 * При неудаче или нечитаемом документе выбрасывает DocumentTextException.
 */
class DocumentTextExtractor
{
    /**
     * Извлечь текст из одного файла по пути.
     *
     * @throws DocumentTextException
     */
    public function extract(string $path): string
    {
        if (!is_file($path)) {
            throw new DocumentTextException('Файл не найден.');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => $this->extractFromPdf($path),
            'doc', 'docx' => $this->extractFromWord($path, $ext),
            'jpg', 'jpeg', 'png' => $this->extractFromImage($path),
            default => throw new DocumentTextException('Неподдерживаемый тип файла для извлечения текста.'),
        };
    }

    /**
     * Извлечь текст из нескольких файлов и объединить (с разделителем).
     */
    public function extractFromPaths(array $paths): string
    {
        $parts = [];
        foreach ($paths as $path) {
            $parts[] = $this->extract($path);
        }
        return implode("\n\n---\n\n", array_filter($parts));
    }

    private function extractFromPdf(string $path): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();
            return is_string($text) ? trim($text) : '';
        } catch (\Throwable $e) {
            Log::warning('PDF text extraction failed: ' . $e->getMessage());
            throw new DocumentTextException('Не удалось извлечь текст из PDF (возможно, скан или повреждённый файл).');
        }
    }

    private function extractFromWord(string $path, string $ext): string
    {
        if ($ext === 'docx') {
            try {
                $phpWord = IOFactory::load($path, 'Word2007');
                $text = $this->getTextFromPhpWord($phpWord);
                $text = trim(preg_replace('/\s+/u', ' ', $text));
                if ($text !== '') {
                    return $text;
                }
            } catch (DocumentTextException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::info('Word (PhpWord) failed, trying ZIP fallback: ' . $e->getMessage());
            }
            return $this->extractFromDocxAsZip($path);
        }

        try {
            $phpWord = IOFactory::load($path, 'MsDoc');
            $text = $this->getTextFromPhpWord($phpWord);
            $text = trim(preg_replace('/\s+/u', ' ', $text));
            if ($text === '') {
                throw new DocumentTextException('Документ Word пуст или нечитаем.');
            }
            return $text;
        } catch (DocumentTextException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Word text extraction failed: ' . $e->getMessage());
            throw new DocumentTextException('Не удалось извлечь текст из документа Word.');
        }
    }

    /**
     * Извлечь текст из DOCX как из ZIP (word/document.xml), без обработки изображений.
     * Используется, когда PhpWord падает на встроенных EMF/картинках.
     */
    private function extractFromDocxAsZip(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new DocumentTextException('Не удалось открыть документ Word.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false || $xml === '') {
            throw new DocumentTextException('В документе Word не найден текст.');
        }
        if (preg_match_all('/<w:t(?:\s[^>]*)?>([^<]*)</w:t>/u', $xml, $matches) !== false && !empty($matches[1])) {
            $parts = array_map(function ($s) {
                return html_entity_decode($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            }, $matches[1]);
            $text = trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)));
            if ($text !== '') {
                return $text;
            }
        }
        throw new DocumentTextException('Не удалось извлечь текст из документа Word.');
    }

    private function getTextFromPhpWord($phpWord): string
    {
        $out = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $out .= $this->getElementText($element);
            }
        }
        return $out;
    }

    private function getElementText($element): string
    {
        if ($element instanceof Text) {
            return $element->getText();
        }
        if ($element instanceof TextRun) {
            $s = '';
            foreach ($element->getElements() as $sub) {
                $s .= $this->getElementText($sub);
            }
            return $s;
        }
        if (method_exists($element, 'getText')) {
            return (string) $element->getText();
        }
        if (method_exists($element, 'getElements')) {
            $s = '';
            foreach ($element->getElements() as $sub) {
                $s .= $this->getElementText($sub);
            }
            return $s . "\n";
        }
        return "\n";
    }

    private function extractFromImage(string $path): string
    {
        $apiUrl = rtrim((string) config('contract.tesseract_api_url'), '/');
        if ($apiUrl !== '') {
            return $this->extractFromImageViaApi($path, $apiUrl);
        }

        $tesseractPath = config('contract.tesseract_path');
        if (empty($tesseractPath)) {
            throw new DocumentTextException('OCR не настроен (tesseract_path или TESSERACT_API_URL).');
        }

        $lang = config('contract.tesseract_lang', 'rus+eng');
        $outBase = dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME) . '_ocr_' . uniqid();
        $outTxt = $outBase . '.txt';

        $cmd = sprintf(
            '%s %s %s -l %s 2>&1',
            escapeshellarg($tesseractPath),
            escapeshellarg($path),
            escapeshellarg($outBase),
            escapeshellarg($lang)
        );

        try {
            exec($cmd, $lines, $code);
            if ($code !== 0 || !is_file($outTxt)) {
                @unlink($outTxt);
                throw new DocumentTextException('OCR не смог распознать текст на изображении.');
            }
            $text = trim((string) file_get_contents($outTxt));
            @unlink($outTxt);
            if ($text === '') {
                throw new DocumentTextException('На изображении не распознан текст.');
            }
            return $text;
        } catch (DocumentTextException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('OCR extraction failed: ' . $e->getMessage());
            @unlink($outTxt);
            throw new DocumentTextException('Не удалось распознать текст на изображении.');
        }
    }

    /**
     * OCR через Tesseract-api: POST изображения на URL, ответ — JSON {"text": "..."} или plain text.
     */
    private function extractFromImageViaApi(string $path, string $apiUrl): string
    {
        try {
            $lang = config('contract.tesseract_lang', 'rus+eng');
            $field = config('contract.tesseract_api_field', 'image');
            $response = Http::timeout(60)
                ->attach($field, file_get_contents($path), basename($path))
                ->withHeaders(['X-Language' => $lang])
                ->post($apiUrl);

            if (!$response->successful()) {
                Log::warning('Tesseract API error: ' . $response->status());
                throw new DocumentTextException('Сервис OCR недоступен или вернул ошибку.');
            }

            $body = $response->body();
            $json = $response->json();
            if (is_array($json) && isset($json['text'])) {
                $text = trim((string) $json['text']);
            } elseif (is_array($json) && isset($json['data']['text'])) {
                $text = trim((string) $json['data']['text']);
            } else {
                $text = trim($body);
            }

            if ($text === '') {
                throw new DocumentTextException('На изображении не распознан текст.');
            }
            return $text;
        } catch (DocumentTextException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Tesseract API exception: ' . $e->getMessage());
            throw new DocumentTextException('Не удалось распознать текст на изображении.');
        }
    }
}
