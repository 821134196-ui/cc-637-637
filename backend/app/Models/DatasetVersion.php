<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['dataset_id', 'version', 'file_path', 'changelog'])]
class DatasetVersion extends Model
{
    public function dataset()
    {
        return $this->belongsTo(Dataset::class);
    }

    public function fields()
    {
        return $this->hasMany(DatasetField::class)->orderBy('sort_order');
    }

    public function publicFields()
    {
        return $this->fields()->where('is_restricted', false);
    }

    public function restrictedFields()
    {
        return $this->fields()->where('is_restricted', true);
    }

    public function fieldNames(): array
    {
        return $this->fields()->pluck('name')->all();
    }
}
