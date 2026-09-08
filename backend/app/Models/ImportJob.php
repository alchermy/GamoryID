<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    protected $fillable = ['shop_id', 'user_id', 'status', 'disk', 'path', 'mapping', 'status_map', 'total_rows', 'processed_rows', 'imported_rows', 'failed_rows', 'skipped_rows', 'completed_at'];

    protected function casts(): array
    {
        return ['mapping' => 'array', 'status_map' => 'array', 'completed_at' => 'datetime'];
    }
}
