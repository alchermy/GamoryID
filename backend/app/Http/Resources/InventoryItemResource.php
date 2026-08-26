<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tag' => '#'.$this->tag,
            'title' => $this->title,
            'region' => $this->region,
            'rank' => $this->rank,
            'level' => $this->level,
            'skin_count' => $this->skin_count,
            'battlepass_level' => $this->battlepass_level,
            'description' => $this->description,
            'notes' => $this->notes,
            'cost' => $this->when($request->user()?->hasShopPermission($this->shop, 'profit.view'), $this->cost),
            'list_price' => $this->list_price,
            'status' => $this->status->value,
            'custom_values' => $this->custom_values ?? (object) [],
            'has_credentials' => $this->credentials_exists ?? $this->credentials()->exists(),
            'updated_at' => $this->updated_at,
        ];
    }
}
