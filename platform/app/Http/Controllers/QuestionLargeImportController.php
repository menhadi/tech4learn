<?php

namespace App\Http\Controllers;

use App\Models\QuestionImportRun;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuestionLargeImportController extends Controller
{
    private const MAX_BYTES = 1073741824;

    public function initialize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'original_name' => ['required', 'string', 'max:255', 'regex:/\.csv$/i'],
            'total_bytes' => ['required', 'integer', 'min:1', 'max:'.self::MAX_BYTES],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:2048'],
            'import_mode' => ['required', 'in:create,update,upsert,source_patch,patch'],
        ]);

        $run = QuestionImportRun::create([
            'upload_id' => (string) Str::uuid(),
            'organization_id' => (int) Tenant::id(),
            'created_by' => $request->user()?->id,
            'original_name' => basename($data['original_name']),
            'status' => 'uploading',
            'total_bytes' => (int) $data['total_bytes'],
            'total_chunks' => (int) $data['total_chunks'],
            'options' => ['import_mode' => $data['import_mode']],
        ]);

        return response()->json(['upload_id' => $run->upload_id]);
    }

    public function uploadChunk(Request $request, string $uploadId): JsonResponse
    {
        $run = $this->ownedRun($request, $uploadId);
        abort_unless($run->status === 'uploading', 409, 'This upload is no longer accepting chunks.');
        $data = $request->validate([
            'chunk_index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:1024'],
        ]);
        $index = (int) $data['chunk_index'];
        abort_if($index >= $run->total_chunks, 422, 'Invalid chunk number.');

        $directory = $this->directory($run).'/chunks';
        $name = str_pad((string) $index, 6, '0', STR_PAD_LEFT).'.part';
        Storage::disk('local')->putFileAs($directory, $data['chunk'], $name);
        $received = count(Storage::disk('local')->files($directory));
        $run->update(['received_chunks' => min($received, $run->total_chunks)]);

        return response()->json(['received_chunks' => $run->received_chunks, 'total_chunks' => $run->total_chunks]);
    }

    public function finalize(Request $request, string $uploadId): JsonResponse
    {
        $run = $this->ownedRun($request, $uploadId);
        abort_unless($run->status === 'uploading', 409, 'This upload was already finalized.');
        $disk = Storage::disk('local');
        $directory = $this->directory($run);
        $sourcePath = $directory.'/source.csv';
        $absoluteSource = $disk->path($sourcePath);
        if (! is_dir(dirname($absoluteSource))) mkdir(dirname($absoluteSource), 0750, true);
        $assembling = $absoluteSource.'.assembling';
        $output = fopen($assembling, 'wb');
        if (! $output) throw ValidationException::withMessages(['excel_file' => 'The server could not prepare the uploaded file.']);

        try {
            for ($index = 0; $index < $run->total_chunks; $index++) {
                $chunkPath = $directory.'/chunks/'.str_pad((string) $index, 6, '0', STR_PAD_LEFT).'.part';
                if (! $disk->exists($chunkPath)) {
                    throw ValidationException::withMessages(['excel_file' => 'Upload chunk '.($index + 1).' is missing. Please retry.']);
                }
                $input = fopen($disk->path($chunkPath), 'rb');
                stream_copy_to_stream($input, $output);
                fclose($input);
            }
        } finally {
            fclose($output);
        }

        if (filesize($assembling) !== (int) $run->total_bytes) {
            @unlink($assembling);
            throw ValidationException::withMessages(['excel_file' => 'The assembled file size does not match the selected CSV. Please retry.']);
        }
        rename($assembling, $absoluteSource);
        $disk->deleteDirectory($directory.'/chunks');
        $run->update(['stored_path' => $sourcePath, 'received_chunks' => $run->total_chunks, 'status' => 'queued', 'failure_message' => null]);

        return response()->json(['status' => 'queued']);
    }

    public function status(Request $request, string $uploadId): JsonResponse
    {
        $run = $this->ownedRun($request, $uploadId);
        return response()->json([
            'status' => $run->status, 'original_name' => $run->original_name,
            'received_chunks' => $run->received_chunks, 'total_chunks' => $run->total_chunks,
            'total_rows' => $run->total_rows, 'processed_rows' => $run->processed_rows,
            'imported_rows' => $run->imported_rows, 'updated_rows' => $run->updated_rows,
            'duplicate_rows' => $run->duplicate_rows, 'created_records' => $run->created_records,
            'failed_rows' => $run->failed_rows, 'message' => $run->failure_message,
            'error_report_url' => $run->error_report_path ? route('questions.large-import.errors', $run->upload_id) : null,
        ]);
    }

    public function errors(Request $request, string $uploadId): BinaryFileResponse
    {
        $run = $this->ownedRun($request, $uploadId);
        abort_unless($run->error_report_path && Storage::disk('local')->exists($run->error_report_path), 404);
        return response()->download(Storage::disk('local')->path($run->error_report_path), pathinfo($run->original_name, PATHINFO_FILENAME).'-import-errors.csv', ['Content-Type' => 'text/csv']);
    }

    private function ownedRun(Request $request, string $uploadId): QuestionImportRun
    {
        return QuestionImportRun::query()->where('upload_id', $uploadId)
            ->where('organization_id', Tenant::id())->where('created_by', $request->user()?->id)->firstOrFail();
    }

    private function directory(QuestionImportRun $run): string
    {
        return 'question-imports/'.$run->organization_id.'/'.$run->upload_id;
    }
}
