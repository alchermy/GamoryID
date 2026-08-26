<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SellInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['required_without:customer_id', 'string', 'max:190'],
            'customer.phone' => ['nullable', 'string', 'max:32'],
            'customer.line_id' => ['nullable', 'string', 'max:190'],
            'customer.facebook_url' => ['nullable', 'url', 'max:500'],
            'sold_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
