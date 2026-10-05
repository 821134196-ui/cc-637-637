<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['dataset_version_id', 'name', 'label', 'data_type', 'is_restricted', 'description', 'sort_order'])]
class DatasetField extends Model
{
    public function version()
    {
        return $this->belongsTo(DatasetVersion::class, 'dataset_version_id');
    }

    protected function casts(): array
    {
        return [
            'is_restricted' => 'boolean',
        ];
    }
}
