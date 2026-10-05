<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadToken extends Model
{
    protected $fillable = ['grant_id', 'token'];

    public function grant(): BelongsTo
    {
        return $this->belongsTo(Grant::class);
    }
}
