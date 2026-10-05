<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    protected $fillable = ['title', 'description', 'department', 'created_by'];

    public function versions(): HasMany
    {
        return $this->hasMany(DatasetVersion::class);
    }

    public function latestVersion(): ?DatasetVersion
    {
        return $this->versions()->orderByDesc('version')->first();
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DatasetField::class)->orderBy('position');
    }

    public function publicFields(): HasMany
    {
        return $this->fields()->where('is_restricted', false);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AccessApplication::class);
    }

    public function grants(): HasMany
    {
        return $this->hasMany(Grant::class);
    }
}
