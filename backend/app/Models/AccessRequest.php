<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'dataset_version_id', 'purpose', 'requested_fields',
    'period_start', 'period_end', 'status', 'decided_by', 'decided_at', 'decision_reason',
])]
class AccessRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function version()
    {
        return $this->belongsTo(DatasetVersion::class, 'dataset_version_id');
    }

    public function grant()
    {
        return $this->hasOne(AccessGrant::class);
    }

    protected function casts(): array
    {
        return [
            'requested_fields' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'decided_at' => 'datetime',
        ];
    }
}
