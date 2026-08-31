<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInventoryImport;
use App\Models\ImportError;
use App\Models\ImportJob;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use App\Services\PlanGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    private const MAPPABLE_FIELDS = [
        'title', 'riot_id', 'username', 'rank', 'level', 'skin_count', 'cost', 'list_price',
        'description', 'notes', 'username', 'password', 'recovery_email',
    ];

    public function preview(Request $request, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $path = $request->file('file')->store("imports/{$shop->id}", 'private');
        $handle = fopen(Storage::disk('private')->path($path), 'rb');
        $headers = fgetcsv($handle) ?: [];
        $rows = [];
        while (count($rows) < 10 && ($row = fgetcsv($handle)) !== false) {
            $normalized = array_slice(array_pad($row, count($headers), null), 0, count($headers));
            $rows[] = array_combine($headers, $normalized);
        }
        fclose($handle);
        $totalRows = max(0, count(file(Storage::disk('private')->path($path), FILE_SKIP_EMPTY_LINES)) - 1);
        $import = ImportJob::create([
            'shop_id' => $shop->id,
            'user_id' => $request->user()->id,
            'status' => 'preview',
            'disk' => 'private',
            'path' => $path,
            'mapping' => [],
            'total_rows' => $totalRows,
        ]);

        return response()->json(['data' => ['id' => $import->id, 'headers' => $headers, 'rows' => $rows, 'total_rows' => $totalRows]], 201);
    }

    public function confirm(Request $request, int $import, CurrentShop $currentShop, AuditLogger $audit, PlanGate $planGate)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.title' => ['nullable', 'string', 'required_without:mapping.riot_id'],
            'mapping.riot_id' => ['nullable', 'string', 'required_without:mapping.title'],
            'mapping.list_price' => ['required', 'string'],
        ]);
        $job = ImportJob::where('shop_id', $shop->id)->where('status', 'preview')->findOrFail($import);
        $headers = $this->headersFor($job);
        foreach ($data['mapping'] as $target => $source) {
            if (! in_array($target, self::MAPPABLE_FIELDS, true) || ! in_array($source, $headers, true)) {
                return response()->json(['message' => 'การจับคู่คอลัมน์ไม่ถูกต้อง'], 422);
            }
        }
        $planGate->ensureInventoryCapacity($shop, $job->total_rows);
        $job->update(['mapping' => $data['mapping'], 'status' => 'queued']);
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

    /** @return array<int, string> */
    private function headersFor(ImportJob $job): array
    {
        $handle = fopen(Storage::disk($job->disk)->path($job->path), 'rb');
        if ($handle === false) {
            return [];
        }
        $headers = fgetcsv($handle) ?: [];
        fclose($handle);

        return array_values(array_filter(array_map(static fn ($header) => trim((string) $header), $headers)));
    }
}
