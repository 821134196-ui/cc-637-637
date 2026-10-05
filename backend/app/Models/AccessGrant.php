<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'access_request_id', 'user_id', 'dataset_version_id', 'granted_fields',
    'period_start', 'period_end', 'revoked_at', 'revoke_reason',
])]
class AccessGrant extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accessRequest()
    {
        return $this->belongsTo(AccessRequest::class);
    }

    public function version()
    {
        return $this->belongsTo(DatasetVersion::class, 'dataset_version_id');
    }

    public function downloadTokens()
    {
        return $this->hasMany(DownloadToken::class);
    }

    public function citations()
    {
        return $this->hasMany(Citation::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->period_end->endOfDay()->isPast();
    }

    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function status(): string
    {
        if ($this->isRevoked()) {
            return 'revoked';
        }

        return $this->isExpired() ? 'expired' : 'active';
    }

    protected function casts(): array
    {
        return [
            'granted_fields' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'revoked_at' => 'datetime',
        ];
    }
}
