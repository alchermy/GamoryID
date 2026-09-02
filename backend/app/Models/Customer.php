<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['shop_id', 'name', 'phone', 'line_id', 'facebook_url', 'notes', 'anonymized_at'];

    protected function casts(): array
    {
        return ['anonymized_at' => 'datetime'];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    /**
     * Strip all personal contact details but keep the row so sale history stays intact.
     */
    public function anonymize(): void
    {
        $this->forceFill([
            'name' => 'ลูกค้าที่ลบข้อมูลแล้ว #'.$this->id,
            'phone' => null,
            'line_id' => null,
            'facebook_url' => null,
            'notes' => null,
            'anonymized_at' => now(),
        ])->save();
    }
}
