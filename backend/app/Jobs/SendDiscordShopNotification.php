<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\Discord\DiscordNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDiscordShopNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(
        public readonly int $shopId,
        public readonly string $purpose,
        public readonly string $title,
        public readonly string $description,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(DiscordNotificationService $notifications): void
    {
        $shop = Shop::query()->find($this->shopId);
        if (! $shop) {
            return;
        }

        $notifications->send($shop, $this->purpose, $this->title, $this->description);
    }
}
