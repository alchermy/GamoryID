<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:190', 'required_without:riot_id'],
            'riot_id' => ['nullable', 'string', 'max:190', 'required_without:title'],
            'username' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'string', 'max:254'],
            'rank' => ['nullable', 'string', 'max:80'],
            'level' => ['nullable', 'integer', 'min:0'],
            'skin_count' => ['nullable', 'integer', 'min:0'],
            'battlepass_level' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'cost' => ['required', 'numeric', 'min:0'],
            'list_price' => ['required', 'numeric', 'min:0'],
            'custom_values' => ['nullable', 'array'],
            'credentials' => ['nullable', 'array'],
            'credentials.username' => ['nullable', 'string', 'max:500'],
            'credentials.password' => ['nullable', 'string', 'max:1000'],
            'credentials.recovery_email' => ['nullable', 'string', 'max:500'],
            'credentials.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
