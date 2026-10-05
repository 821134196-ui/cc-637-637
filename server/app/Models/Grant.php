<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Grant extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'application_id', 'user_id', 'dataset_id', 'dataset_version_id', 'fields',
        'valid_from', 'valid_until', 'status', 'revoked_by', 'revoked_at',
    ];

    protected $casts = [
        'fields' => 'array',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AccessApplication::class, 'application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DatasetVersion::class, 'dataset_version_id');
    }

    public function downloadTokens(): HasMany
    {
        return $this->hasMany(DownloadToken::class);
    }

    public function citations(): HasMany
    {
        return $this->hasMany(Citation::class);
    }

    /**
     * A grant only serves data while active and inside its validity window.
     * Revocation and expiry take effect immediately for every download token.
     */
    public function isUsable(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $now = now();

        return $now->gte($this->valid_from) && $now->lte($this->valid_until);
    }

    public function revoke(User $by): void
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
            'revoked_by' => $by->id,
            'revoked_at' => now(),
        ]);
    }

    public function issueDownloadToken(): DownloadToken
    {
        return $this->downloadTokens()->create(['token' => Str::random(48)]);
    }
}
