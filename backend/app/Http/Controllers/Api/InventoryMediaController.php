<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryMediaResource;
use App\Models\InventoryItem;
use App\Models\InventoryMedia;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InventoryMediaController extends Controller
{
    public function index(Request $request, int $inventory, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);
        $item = InventoryItem::forShop($shop)->findOrFail($inventory);
        $media = InventoryMedia::where('inventory_item_id', $item->id)
            ->orderByRaw("case when role = 'display' then 0 else 1 end")
            ->orderBy('sort_order')
            ->get();

        return InventoryMediaResource::collection($media);
    }

    public function store(Request $request, int $inventory, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $item = InventoryItem::forShop($shop)->findOrFail($inventory);
        $validated = $request->validate([
            'role' => ['required', 'in:display,detail'],
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ], [
            'role.required' => 'กรุณาเลือกประเภทของรูปภาพ',
            'role.in' => 'ประเภทของรูปภาพไม่ถูกต้อง',
            'image.required' => 'กรุณาเลือกรูปภาพ',
            'image.image' => 'ไฟล์ที่เลือกต้องเป็นรูปภาพ',
            'image.mimes' => 'รองรับเฉพาะไฟล์ JPEG, PNG หรือ WebP',
            'image.max' => 'รูปภาพต้องมีขนาดไม่เกิน 5 MB',
        ]);
        $role = $validated['role'];
        $upload = $request->file('image');
        $path = null;
        try {
            $media = DB::transaction(function () use ($item, $role, $shop, $upload, &$path) {
                $lockedItem = InventoryItem::forShop($shop)->lockForUpdate()->findOrFail($item->id);

                if ($role === InventoryMedia::DETAIL && $lockedItem->media()->where('role', InventoryMedia::DETAIL)->count() >= 4) {
                    throw ValidationException::withMessages([
                        'image' => ['แนบรูปภาพรายละเอียดได้สูงสุด 4 รูปต่อไอดี'],
                    ]);
                }

                $path = $upload->store("inventory/{$shop->id}/{$lockedItem->id}", 'private');
                if (! $path) {
                    throw ValidationException::withMessages([
                        'image' => ['ไม่สามารถจัดเก็บรูปภาพได้ กรุณาลองใหม่'],
                    ]);
                }

                $existingDisplays = $role === InventoryMedia::DISPLAY
                    ? $lockedItem->media()->where('role', InventoryMedia::DISPLAY)->get()
                    : collect();

                $media = InventoryMedia::create([
                    'inventory_item_id' => $lockedItem->id,
                    'role' => $role,
                    'disk' => 'private',
                    'path' => $path,
                    'original_name' => $upload->getClientOriginalName(),
                    'mime_type' => $upload->getMimeType(),
                    'size_bytes' => $upload->getSize(),
                    'sort_order' => $role === InventoryMedia::DISPLAY
                        ? 0
                        : ((int) $lockedItem->media()->where('role', InventoryMedia::DETAIL)->max('sort_order')) + 1,
                ]);

                foreach ($existingDisplays as $existingDisplay) {
                    $existingDisplay->delete();
                    DB::afterCommit(fn () => Storage::disk($existingDisplay->disk)->delete($existingDisplay->path));
                }

                return $media;
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('private')->delete($path);
            }
            throw $error;
        }
        $audit->record($request, $shop, 'inventory.media_added', $item, [
            'media_id' => $media->id,
            'role' => $role,
        ]);

        return (new InventoryMediaResource($media))->response()->setStatusCode(201);
    }

    public function show(Request $request, InventoryMedia $media)
    {
        $item = $media->inventoryItem()->with('shop')->firstOrFail();
        $fileName = $media->original_name ?: basename($media->path);
        abort_unless(
            $request->user()->shops()->where('shops.id', $item->shop_id)->exists(),
            404,
        );

        return Storage::disk($media->disk)->response(
            $media->path,
            $fileName,
            [
                'Content-Type' => $media->mime_type,
                'Cache-Control' => 'private, max-age=600',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function destroy(Request $request, int $inventory, InventoryMedia $media, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $item = InventoryItem::forShop($shop)->findOrFail($inventory);
        abort_unless($media->inventory_item_id === $item->id, 404);
        DB::transaction(function () use ($request, $shop, $item, $media, $audit) {
            $disk = $media->disk;
            $path = $media->path;
            $audit->record($request, $shop, 'inventory.media_deleted', $item, [
                'media_id' => $media->id,
                'role' => $media->role,
            ]);
            $media->delete();
            DB::afterCommit(fn () => Storage::disk($disk)->delete($path));
        });

        return response()->json(['message' => 'ลบรูปแล้ว']);
    }
}
