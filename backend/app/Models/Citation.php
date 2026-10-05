<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'citation_number', 'user_id', 'access_grant_id', 'dataset_version_id',
    'title', 'venue', 'url', 'published_at',
])]
class Citation extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grant()
    {
        return $this->belongsTo(AccessGrant::class, 'access_grant_id');
    }

    public function version()
    {
        return $this->belongsTo(DatasetVersion::class, 'dataset_version_id');
    }

    public static function nextNumber(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;

        return sprintf('CIT-%d-%04d', $year, $count);
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
        ];
    }
}
