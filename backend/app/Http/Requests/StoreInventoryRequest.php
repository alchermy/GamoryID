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
            'title' => ['required', 'string', 'max:190'],
            // Optional item-code number the shop supplies (from their old data);
            // blank → the system allocates the next one. Ignored on update.
            'tag_number' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z0-9]+$/'],
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
            // Only the two "plain" states are settable here; reserved/sold/archived
            // go through their own flows (reserve, sell, archive).
            'status' => ['nullable', 'in:available,draft'],
            'custom_values' => ['nullable', 'array'],
            'credentials' => ['nullable', 'array'],
            'credentials.username' => ['nullable', 'string', 'max:500'],
            'credentials.password' => ['nullable', 'string', 'max:1000'],
            'credentials.recovery_email' => ['nullable', 'string', 'max:500'],
            'credentials.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
