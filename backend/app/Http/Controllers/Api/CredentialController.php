<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Services\AuditLogger;
use App\Services\CredentialCipher;
use App\Services\CurrentShop;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
    public function reveal(Request $request, int $inventory, CurrentShop $currentShop, CredentialCipher $cipher, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $item = InventoryItem::forShop($shop)->with('credentials')->findOrFail($inventory);
        if (! $item->credentials) {
            abort(404, 'รายการนี้ไม่มีข้อมูลเข้าสู่ระบบ');
        }

        $item->credentials->update(['last_revealed_at' => now()]);
        $audit->record($request, $shop, 'credentials.revealed', $item, ['tag' => '#'.$item->tag]);

        return response()->json(['data' => $cipher->decrypt($item->credentials->encrypted_payload)]);
    }
}
