<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['shop_id', 'name', 'phone', 'line_id', 'facebook_url', 'notes'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
