<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(Request $request, Shop $shop, string $event, ?Model $subject = null, array $metadata = []): void
    {
        $this->write($shop->id, $request->user()?->id, $event, $subject, $metadata, $request->ip(), $request->userAgent());
    }

    /**
     * Log an account-level action (login, logout, session revoke, re-auth) against
     * the actor's current shop so it shows up in that shop's activity log.
     */
    public function recordAuth(Request $request, string $event, array $metadata = []): void
    {
        $user = $request->user();
        if (! $user?->current_shop_id) {
            return;
        }

        $this->write($user->current_shop_id, $user->id, $event, null, $metadata, $request->ip(), $request->userAgent());
    }

    /**
     * Log a background action (queue jobs, scheduled sweeps) that has no HTTP request.
     */
    public function recordSystem(int $shopId, string $event, ?Model $subject = null, array $metadata = [], ?int $userId = null): void
    {
        $this->write($shopId, $userId, $event, $subject, $metadata, null, null);
    }

    private function write(int $shopId, ?int $userId, string $event, ?Model $subject, array $metadata, ?string $ip, ?string $userAgent): void
    {
        ActivityLog::create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
            'created_at' => now(),
        ]);
    }
}
