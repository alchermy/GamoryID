<?php

namespace App\Jobs;

use App\Models\ImportError;
use App\Models\ImportJob;
use App\Models\InventoryCredential;
use App\Models\InventoryItem;
use App\Services\AuditLogger;
use App\Services\CredentialCipher;
use App\Services\InventoryImportReader;
use App\Services\TagGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessInventoryImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public int $importJobId) {}

    public function handle(TagGenerator $tags, CredentialCipher $cipher, InventoryImportReader $reader): void
    {
        $import = ImportJob::findOrFail($this->importJobId);
        $log = Log::channel('imports')->withContext([
            'import_job_id' => $import->id,
            'shop_id' => $import->shop_id,
            'user_id' => $import->user_id,
        ]);
        $log->info('เริ่มประมวลผลไฟล์นำเข้า', ['file' => $import->path, 'total_rows' => $import->total_rows]);
        $import->update(['status' => 'processing']);
        $sheet = $reader->read($import->disk, $import->path);
        $records = [];
        $errors = [];
        $usernames = [];
        $rowNumber = 1;
        foreach ($sheet['rows'] as $data) {
            $rowNumber++;
            $mapped = $this->mapped($import, $data);
            $message = $this->validationMessage($mapped);
            $username = mb_strtolower(trim((string) ($mapped['username'] ?? '')));
            if (! $message && $username !== '') {
                if (isset($usernames[$username])) {
                    $message = "พบ Username ซ้ำกับแถว {$usernames[$username]}";
                }
                $usernames[$username] = $rowNumber;
            }
            if ($message) {
                $errors[] = [
                    'row_number' => $rowNumber,
                    'message' => $message,
                    'row_data' => $this->redactRowData($import, $data),
                ];

                continue;
            }
            $records[] = $mapped;
        }

        if ($errors !== []) {
            $now = now();
            ImportError::insert(array_map(fn (array $error) => [
                ...$error,
                'row_data' => json_encode($error['row_data'], JSON_THROW_ON_ERROR),
                'import_job_id' => $import->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $errors));
            $import->update([
                'status' => 'failed', 'processed_rows' => count($records) + count($errors),
                'imported_rows' => 0, 'failed_rows' => count($errors), 'completed_at' => $now,
            ]);
            Storage::disk($import->disk)->delete($import->path);
            $log->warning('ยกเลิกการนำเข้าทั้งชุดเพราะข้อมูลบางแถวไม่ผ่านการตรวจสอบ', [
                'invalid_rows' => count($errors),
                'valid_rows' => count($records),
                'first_errors' => array_map(
                    fn (array $error) => "แถวที่ {$error['row_number']}: {$error['message']}",
                    array_slice($errors, 0, 5),
                ),
            ]);
            $this->audit($import, 'import.failed', ['invalid_rows' => count($errors), 'reason' => 'validation']);

            return;
        }

        try {
            DB::transaction(function () use ($records, $import, $tags, $cipher) {
                foreach ($records as $mapped) {
                    $item = InventoryItem::create([
                        'shop_id' => $import->shop_id,
                        'created_by' => $import->user_id,
                        'tag' => $tags->generate(),
                        'title' => trim((string) ($mapped['title'] ?? $mapped['riot_id'])),
                        'riot_id' => $this->blankToNull($mapped['riot_id'] ?? null),
                        'username' => $this->blankToNull($mapped['username'] ?? null),
                        'email' => $this->blankToNull($mapped['email'] ?? null),
                        'region' => 'TH',
                        'rank' => $this->blankToNull($mapped['rank'] ?? null),
                        'level' => filled($mapped['level'] ?? null) ? (int) $mapped['level'] : null,
                        'skin_count' => filled($mapped['skin_count'] ?? null) ? (int) $mapped['skin_count'] : 0,
                        'cost' => (float) ($mapped['cost'] ?? 0),
                        'list_price' => (float) ($mapped['list_price'] ?? 0),
                        'description' => $this->blankToNull($mapped['description'] ?? null),
                        'notes' => $this->blankToNull($mapped['notes'] ?? null),
                    ]);
                    if (filled($mapped['username'] ?? null) || filled($mapped['password'] ?? null)) {
                        $encrypted = $cipher->encrypt([
                            'username' => $mapped['username'] ?? '',
                            'password' => $mapped['password'] ?? '',
                            'recovery_email' => $mapped['recovery_email'] ?? '',
                        ]);
                        InventoryCredential::create([
                            'inventory_item_id' => $item->id,
                            'encrypted_payload' => $encrypted['payload'],
                            'key_version' => $encrypted['key_version'],
                        ]);
                    }
                }
            }, 3);
            $import->update([
                'status' => 'completed', 'processed_rows' => count($records),
                'imported_rows' => count($records), 'failed_rows' => 0, 'completed_at' => now(),
            ]);
            $log->info('นำเข้าสำเร็จ', ['imported_rows' => count($records)]);
            $this->audit($import, 'import.completed', ['imported_rows' => count($records)]);
        } catch (Throwable $exception) {
            ImportError::create([
                'import_job_id' => $import->id, 'row_number' => 0,
                'message' => 'นำเข้าทั้งชุดไม่สำเร็จ: '.$exception->getMessage(), 'row_data' => [],
            ]);
            $import->update([
                'status' => 'failed', 'processed_rows' => count($records),
                'imported_rows' => 0, 'failed_rows' => 1, 'completed_at' => now(),
            ]);
            $log->error('นำเข้าทั้งชุดไม่สำเร็จระหว่างบันทึกลงฐานข้อมูล (rollback แล้ว)', [
                'valid_rows' => count($records),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'at' => $exception->getFile().':'.$exception->getLine(),
            ]);
            $this->audit($import, 'import.failed', ['reason' => 'database']);
        } finally {
            Storage::disk($import->disk)->delete($import->path);
        }
    }

    private function audit(ImportJob $import, string $event, array $metadata): void
    {
        app(AuditLogger::class)->recordSystem($import->shop_id, $event, $import, $metadata, $import->user_id);
    }

    public function failed(Throwable $exception): void
    {
        ImportJob::whereKey($this->importJobId)->update(['status' => 'failed']);
        Log::channel('imports')->error('งานนำเข้าล้มเหลวและไม่สามารถลองใหม่ได้', [
            'import_job_id' => $this->importJobId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    /** @return array<string, mixed> */
    private function mapped(ImportJob $import, array $data): array
    {
        $mapped = [];
        foreach ($import->mapping as $target => $source) {
            $mapped[$target] = $data[$source] ?? null;
        }

        return $mapped;
    }

    private function validationMessage(array $mapped): ?string
    {
        if (blank($mapped['title'] ?? null) && blank($mapped['riot_id'] ?? null)) {
            return 'ต้องมี Riot ID หรือชื่อรายการ';
        }
        if (! isset($mapped['list_price']) || ! is_numeric($mapped['list_price']) || (float) $mapped['list_price'] < 0) {
            return 'ราคาตั้งขายต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป';
        }
        if (filled($mapped['cost'] ?? null) && (! is_numeric($mapped['cost']) || (float) $mapped['cost'] < 0)) {
            return 'ต้นทุนต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป';
        }
        if (filled($mapped['level'] ?? null) && (filter_var($mapped['level'], FILTER_VALIDATE_INT) === false || (int) $mapped['level'] < 0)) {
            return 'เลเวลต้องเป็นจำนวนเต็มตั้งแต่ 0 ขึ้นไป';
        }
        if (filled($mapped['skin_count'] ?? null) && (filter_var($mapped['skin_count'], FILTER_VALIDATE_INT) === false || (int) $mapped['skin_count'] < 0)) {
            return 'จำนวนสกินต้องเป็นจำนวนเต็มตั้งแต่ 0 ขึ้นไป';
        }

        return null;
    }

    private function blankToNull(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }

    private function redactRowData(ImportJob $import, array $data): array
    {
        foreach (['password', 'recovery_email'] as $target) {
            $source = $import->mapping[$target] ?? null;
            if ($source && array_key_exists($source, $data)) {
                $data[$source] = '[REDACTED]';
            }
        }

        return $data;
    }
}
