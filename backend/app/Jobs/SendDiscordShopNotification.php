<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\Discord\DiscordNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDiscordShopNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    /**
     * @param  array{label: string, url: string}|null  $link  optional link-button appended to the message
     */
    public function __construct(
        public readonly int $shopId,
        public readonly string $purpose,
        public readonly string $title,
        public readonly string $description,
        public readonly ?array $link = null,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(DiscordNotificationService $notifications): void
    {
        $shop = Shop::query()->find($this->shopId);
        if (! $shop) {
            Log::channel('discord')->warning('ข้ามการแจ้งเตือน Discord: ไม่พบร้าน', [
                'shop_id' => $this->shopId,
                'purpose' => $this->purpose,
                'title' => $this->title,
            ]);

            return;
        }

        $notifications->send($shop, $this->purpose, $this->title, $this->description, $this->link);
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('discord')->error('งานแจ้งเตือน Discord ล้มเหลวหลังพยายามครบทุกครั้ง', [
            'shop_id' => $this->shopId,
            'purpose' => $this->purpose,
            'title' => $this->title,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
