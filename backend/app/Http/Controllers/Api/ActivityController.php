<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ShopMember;
use App\Services\CurrentShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ActivityController extends Controller
{
    public function index(Request $request, CurrentShop $currentShop): JsonResponse
    {
        $shop = $currentShop->from($request);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'event' => ['nullable', 'string', 'max:60'],
            'actor' => ['nullable', 'string', 'max:20'], // user id, or "system" for unattributed
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);

        $query = ActivityLog::query()
            ->where('shop_id', $shop->id)
            ->with('user:id,name')
            ->latest('created_at');

        if ($event = $validated['event'] ?? null) {
            $query->where('event', $event);
        }
        if (($actor = $validated['actor'] ?? null) !== null) {
            $actor === 'system'
                ? $query->whereNull('user_id')
                : $query->where('user_id', (int) $actor);
        }
        if ($from = $validated['from'] ?? null) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to = $validated['to'] ?? null) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }
        if ($q = trim((string) ($validated['q'] ?? ''))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('event', 'like', "%{$q}%")
                    ->orWhere('metadata', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$q}%"));
            });
        }

        $activities = $query->paginate($validated['per_page'] ?? 50)->withQueryString();
        $activities->through(fn (ActivityLog $log) => [
            'id' => $log->id,
            'event' => $log->event,
            'actor' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
            'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
            'subject_id' => $log->subject_id,
            'metadata' => $log->metadata,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => $activities->items(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
            'filters' => [
                'events' => ActivityLog::where('shop_id', $shop->id)
                    ->distinct()->orderBy('event')->pluck('event'),
                'actors' => ShopMember::where('shop_id', $shop->id)
                    ->with('user:id,name')->get()
                    ->map(fn (ShopMember $m) => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name, 'role' => $m->role] : null)
                    ->filter()->values(),
            ],
        ]);
    }
}
