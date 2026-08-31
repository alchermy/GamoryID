<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CurrentShop;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request, CurrentShop $currentShop)
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
}
