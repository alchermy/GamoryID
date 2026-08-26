<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomFieldDefinition;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomFieldController extends Controller
{
    public function index(Request $request, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);

        return response()->json(['data' => CustomFieldDefinition::where('shop_id', $shop->id)->orderBy('sort_order')->get()]);
    }

    public function store(Request $request, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $data = $this->validated($request, $shop->id);
        $field = CustomFieldDefinition::create([...$data, 'shop_id' => $shop->id]);
        $audit->record($request, $shop, 'custom_field.created', $field);

        return response()->json(['data' => $field], 201);
    }

    public function update(Request $request, int $field, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $record = CustomFieldDefinition::where('shop_id', $shop->id)->findOrFail($field);
        $record->update($this->validated($request, $shop->id, $record->id));
        $audit->record($request, $shop, 'custom_field.updated', $record);

        return response()->json(['data' => $record]);
    }

    public function destroy(Request $request, int $field, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $record = CustomFieldDefinition::where('shop_id', $shop->id)->findOrFail($field);
        $audit->record($request, $shop, 'custom_field.deleted', $record, ['key' => $record->key]);
        $record->delete();

        return response()->json(['message' => 'ลบฟิลด์แล้ว']);
    }

    private function validated(Request $request, int $shopId, ?int $except = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'key' => ['required', 'alpha_dash', 'max:100', Rule::unique('custom_field_definitions')->where('shop_id', $shopId)->ignore($except)],
            'type' => ['required', 'in:text,number,boolean,date,select'],
            'options' => ['nullable', 'array', 'required_if:type,select'],
            'options.*' => ['string', 'max:100'],
            'is_required' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:1000'],
        ]);
    }
}
