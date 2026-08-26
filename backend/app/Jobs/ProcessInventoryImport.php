<?php

namespace App\Jobs;

use App\Models\ImportError;
use App\Models\ImportJob;
use App\Models\InventoryCredential;
use App\Models\InventoryItem;
use App\Services\CredentialCipher;
use App\Services\TagGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessInventoryImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public int $importJobId) {}

    public function handle(TagGenerator $tags, CredentialCipher $cipher): void
    {
        $import = ImportJob::findOrFail($this->importJobId);
        $import->update(['status' => 'processing']);
        $path = Storage::disk($import->disk)->path($import->path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('ไม่สามารถอ่านไฟล์นำเข้าได้');
        }

        $headers = fgetcsv($handle) ?: [];
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $data = array_combine($headers, array_pad($row, count($headers), null));
            if ($data === false) {
                $data = [];
            }
            try {
                DB::transaction(function () use ($import, $data, $tags, $cipher) {
                    $mapped = [];
                    foreach ($import->mapping as $target => $source) {
                        $mapped[$target] = $data[$source] ?? null;
                    }
                    if (blank($mapped['title'] ?? null)) {
                        throw new \InvalidArgumentException('ต้องมีชื่อหรือรายละเอียดสินค้า');
                    }
                    $item = InventoryItem::create([
                        'shop_id' => $import->shop_id,
                        'created_by' => $import->user_id,
                        'tag' => $tags->generate(),
                        'title' => $mapped['title'],
                        'region' => $mapped['region'] ?? null,
                        'rank' => $mapped['rank'] ?? null,
                        'level' => filled($mapped['level'] ?? null) ? (int) $mapped['level'] : null,
                        'skin_count' => filled($mapped['skin_count'] ?? null) ? (int) $mapped['skin_count'] : 0,
                        'cost' => (float) ($mapped['cost'] ?? 0),
                        'list_price' => (float) ($mapped['list_price'] ?? 0),
                        'description' => $mapped['description'] ?? null,
                    ]);
                    if (filled($mapped['username'] ?? null) || filled($mapped['password'] ?? null)) {
                        $encrypted = $cipher->encrypt(['username' => $mapped['username'] ?? '', 'password' => $mapped['password'] ?? '']);
                        InventoryCredential::create(['inventory_item_id' => $item->id, 'encrypted_payload' => $encrypted['payload'], 'key_version' => $encrypted['key_version']]);
                    }
                });
                $import->increment('imported_rows');
            } catch (Throwable $exception) {
                ImportError::create(['import_job_id' => $import->id, 'row_number' => $rowNumber, 'message' => $exception->getMessage(), 'row_data' => $data]);
                $import->increment('failed_rows');
            }
            $import->increment('processed_rows');
        }
        fclose($handle);
        Storage::disk($import->disk)->delete($import->path);
        $import->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        ImportJob::whereKey($this->importJobId)->update(['status' => 'failed']);
    }
}
