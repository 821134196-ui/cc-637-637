<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetField extends Model
{
    protected $fillable = ['dataset_id', 'name', 'label', 'description', 'is_restricted', 'position'];

    protected $casts = ['is_restricted' => 'boolean'];

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
