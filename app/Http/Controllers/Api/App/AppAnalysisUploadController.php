<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\ContractAnalysis;
use App\Services\Contract\ContractFileHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Загрузка документов в анализ (Фаза 3). Multipart, валидация, сообщения по ТЗ.
 */
class AppAnalysisUploadController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $maxSizeKb = (int) ceil((config('contract.max_file_size_bytes', 20 * 1024 * 1024)) / 1024);
        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:' . $maxSizeKb],
        ], [], [
            'files' => 'файлы',
            'files.*' => 'файл',
        ]);

        $analysis = ContractAnalysis::whereNotNull('user_id')->findOrFail($id);

        if (! $request->user()->isAdmin() && ! $analysis->isOwnedBy($request->user()->id)) {
            abort(403, 'Доступ запрещён.');
        }

        $uploadedFiles = $request->file('files');
        $maxFiles = (int) config('contract.max_upload_files', 40);
        if (count($uploadedFiles) > $maxFiles) {
            return response()->json([
                'message' => 'Файл отклонён — неподдерживаемый формат',
                'messages' => array_fill(0, count($uploadedFiles), 'Файл отклонён — неподдерживаемый формат'),
            ], 422);
        }

        $handler = new ContractFileHandler();
        $result = $handler->processUploadedFiles($uploadedFiles);

        $fileInfo = array_map(fn ($name) => ['name' => $name], $result['file_names']);

        $analysis->update([
            'file_info' => array_merge($analysis->file_info ?? [], $fileInfo),
            'temp_upload_path' => $result['temp_dir'],
        ]);

        return response()->json([
            'message' => count($result['paths']) > 0 ? 'Файл загружен' : 'Файл отклонён — неподдерживаемый формат',
            'messages' => $result['messages'],
            'file_info' => $analysis->file_info,
        ]);
    }
}
