<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportError extends Model
{
    protected $fillable = ['import_job_id', 'row_number', 'field', 'message', 'row_data'];

    protected function casts(): array
    {
        return ['row_data' => 'array'];
    }
}
