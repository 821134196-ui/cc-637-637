<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'description'])]
class Dataset extends Model
{
    public function versions()
    {
        return $this->hasMany(DatasetVersion::class);
    }

    public function latestVersion()
    {
        return $this->hasOne(DatasetVersion::class)->latestOfMany();
    }
}
