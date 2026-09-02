<?php

namespace App\Services;

use App\Enums\InventoryStatus;
use App\Jobs\SendDiscordShopNotification;
use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReservationLifecycle
{
    public function run(): void
    {
        $log = Log::channel('scheduler');
        $released = 0;
        $failed = 0;

        Reservation::query()->expired()->with('item')->orderBy('id')
            ->chunkById(100, function ($reservations) use ($log, &$released, &$failed) {
                foreach ($reservations as $reservation) {
                    try {
                        if ($this->expireOne($reservation)) {
                            $released++;
                        }
                    } catch (Throwable $exception) {
                        $failed++;
                        $log->error('ปลดล็อกการจองที่หมดเวลาไม่สำเร็จ', [
                            'reservation_id' => $reservation->id,
                            'inventory_item_id' => $reservation->inventory_item_id,
                            'shop_id' => $reservation->shop_id,
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        if ($released > 0 || $failed > 0) {
            $log->info('รอบตรวจการจองหมดเวลา (reservations.lifecycle) เสร็จสิ้น', [
                'released' => $released,
                'failed' => $failed,
            ]);
        }
    }

    private function expireOne(Reservation $reservation): bool
    {
        $result = DB::transaction(function () use ($reservation) {
            $item = InventoryItem::whereKey($reservation->inventory_item_id)->lockForUpdate()->first();
            if (! $item) {
                Reservation::whereKey($reservation->id)->whereNull('released_at')->update(['released_at' => now()]);

                return null;
            }

            Reservation::where('inventory_item_id', $item->id)->whereNull('released_at')->update(['released_at' => now()]);

            if ($item->status !== InventoryStatus::Reserved) {
                return null;
            }

            $item->update(['status' => InventoryStatus::Available, 'lock_version' => $item->lock_version + 1]);
            ActivityLog::create([
                'shop_id' => $item->shop_id,
                'user_id' => null,
                'event' => 'inventory.reservation_expired',
                'subject_type' => $item->getMorphClass(),
                'subject_id' => $item->id,
                'metadata' => ['tag' => '#'.$item->tag, 'source' => 'auto'],
                'ip_address' => null,
                'user_agent' => null,
                'created_at' => now(),
            ]);

            return $item;
        }, 3);

        if (! $result) {
            return false;
        }

        Log::channel('scheduler')->info('การจองหมดเวลา: คืนไอดีเป็นสถานะพร้อมขายอัตโนมัติ', [
            'shop_id' => $result->shop_id,
            'inventory_item_id' => $result->id,
            'tag' => '#'.$result->tag,
        ]);

        SendDiscordShopNotification::dispatch(
            $result->shop_id,
            'reservations',
            'การจองหมดเวลาแล้ว',
            "**#{$result->tag}** · {$result->riot_id}\nรายการกลับเป็นสถานะพร้อมขายอัตโนมัติ",
        );

        return true;
    }
}
