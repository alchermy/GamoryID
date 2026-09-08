<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInventoryImport;
use App\Models\ImportError;
use App\Models\ImportJob;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use App\Services\InventoryImportReader;
use App\Services\PlanEntitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ImportController extends Controller
{
    private const MAPPABLE_FIELDS = [
        'title', 'tag_number', 'username', 'email', 'rank', 'level', 'skin_count', 'cost', 'list_price',
        'description', 'notes', 'password', 'recovery_email', 'status',
    ];

    public function template(): BinaryFileResponse
    {
        $path = resource_path('templates/gamoryid-inventory-import-template.xlsx');
        abort_unless(is_file($path), 404, 'ไม่พบไฟล์ Excel ตัวอย่าง');

        return response()->download($path, 'GamoryID-inventory-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            // Revalidate every time — the template changes rarely, but when it
            // does a stale hour-long browser cache is worse than a tiny refetch.
            'Cache-Control' => 'private, no-cache, max-age=0, must-revalidate',
        ]);
    }

    public function preview(Request $request, CurrentShop $currentShop, InventoryImportReader $reader)
    {
        $shop = $currentShop->from($request);
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:5120',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $extension = strtolower($value->getClientOriginalExtension());
                    if (! in_array($extension, ['csv', 'xlsx'], true)) {
                        $fail('รองรับเฉพาะไฟล์ Excel (.xlsx) หรือ CSV (.csv)');
                    }
                },
            ],
        ]);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs("imports/{$shop->id}", Str::uuid().'.'.$extension, 'private');
        try {
            // Return enough rows for the merchant to actually review the file
            // before committing; the UI paginates them.
            $sheet = $reader->read('private', $path, 500);
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }
        if ($sheet['total_rows'] === 0) {
            Storage::disk('private')->delete($path);
            throw ValidationException::withMessages(['file' => 'ไฟล์ต้องมีข้อมูลอย่างน้อย 1 แถว']);
        }
        if ($sheet['total_rows'] > 10000) {
            Storage::disk('private')->delete($path);
            throw ValidationException::withMessages(['file' => 'นำเข้าได้สูงสุด 10,000 แถวต่อไฟล์']);
        }
        $import = ImportJob::create([
            'shop_id' => $shop->id,
            'user_id' => $request->user()->id,
            'status' => 'preview',
            'disk' => 'private',
            'path' => $path,
            'mapping' => [],
            'total_rows' => $sheet['total_rows'],
        ]);

        return response()->json(['data' => [
            'id' => $import->id,
            'headers' => $sheet['headers'],
            'rows' => $this->maskSensitivePreview($sheet['rows']),
            'total_rows' => $sheet['total_rows'],
            // Distinct values per low-cardinality column — the UI uses these to
            // let the merchant map their own status labels onto system statuses.
            'distinct_values' => $sheet['distinct_values'],
        ]], 201);
    }

    public function confirm(Request $request, int $import, CurrentShop $currentShop, AuditLogger $audit, PlanEntitlements $planGate, InventoryImportReader $reader)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.title' => ['nullable', 'string'],
            'mapping.tag_number' => ['nullable', 'string'],
            'mapping.username' => ['required', 'string'],
            'mapping.email' => ['nullable', 'string'],
            'mapping.password' => ['nullable', 'string'],
            'mapping.description' => ['nullable', 'string'],
            'mapping.rank' => ['nullable', 'string'],
            'mapping.level' => ['nullable', 'string'],
            'mapping.skin_count' => ['nullable', 'string'],
            'mapping.cost' => ['nullable', 'string'],
            'mapping.list_price' => ['required', 'string'],
            'mapping.notes' => ['nullable', 'string'],
            'mapping.recovery_email' => ['nullable', 'string'],
            'mapping.status' => ['nullable', 'string'],
            // Maps a shop's own status label (the raw cell value) to a system status.
            'status_map' => ['nullable', 'array'],
            'status_map.*' => ['string', Rule::in(['available', 'reserved', 'sold', 'archived', 'draft'])],
        ]);
        $job = ImportJob::where('shop_id', $shop->id)->where('status', 'preview')->findOrFail($import);
        $headers = $reader->read($job->disk, $job->path, 0)['headers'];
        foreach ($data['mapping'] as $target => $source) {
            if (! in_array($target, self::MAPPABLE_FIELDS, true) || ! in_array($source, $headers, true)) {
                return response()->json(['message' => 'การจับคู่คอลัมน์ไม่ถูกต้อง'], 422);
            }
        }
        $planGate->ensureInventoryCapacity($shop, $job->total_rows);
        $job->update([
            'mapping' => $data['mapping'],
            'status_map' => isset($data['mapping']['status']) ? ($data['status_map'] ?? []) : null,
            'status' => 'queued',
        ]);
        ProcessInventoryImport::dispatch($job->id);
        $audit->record($request, $shop, 'import.queued', $job, ['total_rows' => $job->total_rows]);

        return response()->json(['data' => $job->fresh()], 202);
    }

    public function show(Request $request, int $import, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);
        $job = ImportJob::where('shop_id', $shop->id)->findOrFail($import);

        return response()->json(['data' => $job, 'errors' => ImportError::where('import_job_id', $job->id)->limit(100)->get()]);
    }

    private function maskSensitivePreview(array $rows): array
    {
        return array_map(static function (array $row): array {
            foreach ($row as $header => $value) {
                if (preg_match('/password|passcode|รหัสผ่าน/i', (string) $header)) {
                    $row[$header] = filled($value) ? '••••••••' : null;
                }
            }

            return $row;
        }, $rows);
    }
}
