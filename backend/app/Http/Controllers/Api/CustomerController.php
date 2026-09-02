<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request, CurrentShop $currentShop): JsonResponse
    {
        $shop = $currentShop->from($request);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);

        $query = Customer::query()
            ->where('shop_id', $shop->id)
            ->withCount('sales')
            ->latest('updated_at');

        if ($q = trim((string) ($validated['q'] ?? ''))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('line_id', 'like', "%{$q}%");
            });
        }

        return response()->json($query->paginate($validated['per_page'] ?? 25)->withQueryString());
    }

    /**
     * Erase a customer's personal contact details on request (right to be
     * forgotten). The row and its sale history are kept — only the PII is removed.
     */
    public function destroy(Request $request, int $customer, CurrentShop $currentShop, AuditLogger $audit): JsonResponse
    {
        $shop = $currentShop->from($request);
        $record = Customer::where('shop_id', $shop->id)->findOrFail($customer);

        if (! $record->isAnonymized()) {
            $salesCount = $record->sales()->count();
            $record->anonymize();
            $audit->record($request, $shop, 'customer.anonymized', $record, ['sales_count' => $salesCount]);
        }

        return response()->json(['message' => 'ลบข้อมูลติดต่อของลูกค้าแล้ว ประวัติการขายยังคงอยู่']);
    }
}
