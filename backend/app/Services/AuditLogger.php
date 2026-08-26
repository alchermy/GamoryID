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
        ActivityLog::create([
            'shop_id' => $shop->id,
            'user_id' => $request->user()?->id,
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
