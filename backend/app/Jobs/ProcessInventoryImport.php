<?php

namespace App\Jobs;

use App\Enums\InventoryStatus;
use App\Models\ImportError;
use App\Models\ImportJob;
use App\Models\InventoryCredential;
use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
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
        $shop = Shop::findOrFail($import->shop_id);
        // Shop status label (raw cell value, lower-cased) => system status.
        // Empty when no status column was mapped — every row is then "available".
        $statusLookup = collect($import->status_map ?? [])
            ->mapWithKeys(fn ($system, $raw) => [mb_strtolower(trim((string) $raw)) => $system])
            ->all();
        $records = [];
        $errors = [];   // hard problems — these abort the whole batch
        $skipped = [];  // item code already exists — skip the row, import the rest
        $usernames = [];
        $batchTags = [];   // shop-supplied item codes claimed earlier in this file
        $existingTags = InventoryItem::withTrashed()
            ->where('shop_id', $import->shop_id)
            ->pluck('tag')
            ->flip();
        $rowNumber = 1;
        foreach ($sheet['rows'] as $data) {
            $rowNumber++;
            $mapped = $this->mapped($import, $data);
            $message = $this->validationMessage($mapped);
            if ($message) {
                $errors[] = [
                    'row_number' => $rowNumber,
                    'message' => $message,
                    'kind' => 'error',
                    'row_data' => $this->redactRowData($import, $data),
                ];

                continue;
            }

            // A username that already exists in this shop is allowed on import —
            // an ID sold earlier can come back and be re-stocked. Only the exact
            // same username appearing twice in one file is skipped (data error).
            $username = mb_strtolower(trim((string) ($mapped['username'] ?? '')));
            if ($username !== '') {
                if (isset($usernames[$username])) {
                    $skipped[] = [
                        'row_number' => $rowNumber,
                        'message' => "Username \"{$username}\" ซ้ำกับแถว {$usernames[$username]} ในไฟล์ — ข้ามรายการนี้",
                        'kind' => 'duplicate',
                        'row_data' => $this->redactRowData($import, $data),
                    ];

                    continue;
                }
                $usernames[$username] = $rowNumber;
            }

            // Resolve a shop-supplied item-code number now so a clash skips just
            // this row instead of aborting the batch inside the transaction.
            $provided = trim((string) ($mapped['tag_number'] ?? ''));
            if ($provided !== '') {
                $tag = $shop->effective_tag_prefix.'-'.(ctype_digit($provided) ? str_pad($provided, 4, '0', STR_PAD_LEFT) : mb_strtoupper($provided));
                $clash = isset($batchTags[$tag])
                    ? "ซ้ำกับแถว {$batchTags[$tag]} ในไฟล์"
                    : ($existingTags->has($tag) ? 'มีอยู่ในคลังแล้ว' : null);
                if ($clash !== null) {
                    $skipped[] = [
                        'row_number' => $rowNumber,
                        'message' => "รหัสไอดี {$tag} {$clash} — ข้ามรายการนี้",
                        'kind' => 'duplicate',
                        'row_data' => $this->redactRowData($import, $data),
                    ];

                    continue;
                }
                $batchTags[$tag] = $rowNumber;
                $mapped['_tag'] = $tag;
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
                'status' => 'failed', 'processed_rows' => count($records) + count($errors) + count($skipped),
                'imported_rows' => 0, 'failed_rows' => count($errors), 'skipped_rows' => 0, 'completed_at' => $now,
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
            $this->notifyDiscord(
                $import,
                'นำเข้าข้อมูลไอดีไม่สำเร็จ',
                'ไม่ผ่านการตรวจสอบ '.count($errors).' แถว จึงยกเลิกทั้งชุด'
                    ."\nแถวที่ {$errors[0]['row_number']}: {$errors[0]['message']}",
            );

            return;
        }

        $byStatus = [];
        try {
            DB::transaction(function () use ($records, $import, $shop, $tags, $cipher, $statusLookup, &$byStatus) {
                foreach ($records as $mapped) {
                    $status = $this->resolveStatus($mapped, $statusLookup);
                    $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
                    $item = InventoryItem::create([
                        'shop_id' => $import->shop_id,
                        'created_by' => $import->user_id,
                        'tag' => $mapped['_tag'] ?? $tags->generate($shop),
                        // Title (ชื่อรายการ) is optional on import. When it's not
                        // mapped the item has no name — views fall back to the tag.
                        'title' => trim((string) ($mapped['title'] ?? '')),
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
                        'status' => $status,
                        'archived_at' => $status === InventoryStatus::Archived->value ? now() : null,
                    ]);
                    // A "sold" / "reserved" import keeps the same invariants the
                    // rest of the app assumes: sold ⇒ one Sale row, reserved ⇒ an
                    // open Reservation. No customer is attached.
                    if ($status === InventoryStatus::Sold->value) {
                        Sale::create([
                            'shop_id' => $import->shop_id,
                            'inventory_item_id' => $item->id,
                            'customer_id' => null,
                            'created_by' => $import->user_id,
                            'sold_price' => (float) $item->list_price,
                            'cost_snapshot' => (float) $item->cost,
                            'profit' => (float) $item->list_price - (float) $item->cost,
                            'notes' => 'นำเข้าจากไฟล์',
                            'sold_at' => now(),
                        ]);
                    } elseif ($status === InventoryStatus::Reserved->value) {
                        Reservation::create([
                            'shop_id' => $import->shop_id,
                            'inventory_item_id' => $item->id,
                            'customer_id' => null,
                            'created_by' => $import->user_id,
                            'notes' => 'นำเข้าจากไฟล์',
                            'expires_at' => now()->addDays(30),
                        ]);
                    }
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
            if ($skipped !== []) {
                $now = now();
                ImportError::insert(array_map(fn (array $row) => [
                    ...$row,
                    'row_data' => json_encode($row['row_data'], JSON_THROW_ON_ERROR),
                    'import_job_id' => $import->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $skipped));
            }
            $import->update([
                'status' => 'completed',
                'processed_rows' => count($records) + count($skipped),
                'imported_rows' => count($records),
                'failed_rows' => 0,
                'skipped_rows' => count($skipped),
                'completed_at' => now(),
            ]);
            $log->info('นำเข้าสำเร็จ', ['imported_rows' => count($records), 'skipped_rows' => count($skipped), 'by_status' => $byStatus]);
            $this->audit($import, 'import.completed', [
                'imported_rows' => count($records),
                'skipped_rows' => count($skipped),
                'by_status' => $byStatus,
            ]);
            $this->notifyDiscord(
                $import,
                'นำเข้าข้อมูลไอดีสำเร็จ',
                'เพิ่มเข้าคลัง '.count($records).' รายการ'
                    .$this->statusBreakdown($byStatus)
                    .($skipped !== [] ? ' · ข้ามรายการซ้ำ '.count($skipped).' รายการ' : ''),
            );
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
            $this->notifyDiscord(
                $import,
                'นำเข้าข้อมูลไอดีไม่สำเร็จ',
                'เกิดข้อผิดพลาดระหว่างบันทึกลงฐานข้อมูล จึงยกเลิกทั้งชุด',
            );
        } finally {
            Storage::disk($import->disk)->delete($import->path);
        }
    }

    private function audit(ImportJob $import, string $event, array $metadata): void
    {
        app(AuditLogger::class)->recordSystem($import->shop_id, $event, $import, $metadata, $import->user_id);
    }

    private function notifyDiscord(ImportJob $import, string $title, string $description): void
    {
        SendDiscordShopNotification::dispatch(
            $import->shop_id,
            'inventory',
            $title,
            $description,
            actor: $import->user_id ? User::find($import->user_id)?->name : null,
        );
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

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, string>  $statusLookup
     */
    private function resolveStatus(array $mapped, array $statusLookup): string
    {
        $raw = trim((string) ($mapped['status'] ?? ''));
        if ($raw === '') {
            return InventoryStatus::Available->value;
        }

        return $statusLookup[mb_strtolower($raw)] ?? InventoryStatus::Available->value;
    }

    /** @param  array<string, int>  $byStatus */
    private function statusBreakdown(array $byStatus): string
    {
        $parts = [];
        foreach ([
            InventoryStatus::Sold->value => 'ขายแล้ว',
            InventoryStatus::Reserved->value => 'ถูกจอง',
            InventoryStatus::Draft->value => 'ยังไม่เปิดขาย',
            InventoryStatus::Archived->value => 'เก็บถาวร',
        ] as $value => $label) {
            if (($byStatus[$value] ?? 0) > 0) {
                $parts[] = $label.' '.$byStatus[$value];
            }
        }

        return $parts === [] ? '' : ' ('.implode(' · ', $parts).')';
    }

    private function validationMessage(array $mapped): ?string
    {
        if (blank($mapped['username'] ?? null)) {
            return 'ต้องมี Username';
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
