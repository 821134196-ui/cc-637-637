<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['access_grant_id', 'token_hash', 'label', 'expires_at'])]
class DownloadToken extends Model
{
    public function grant()
    {
        return $this->belongsTo(AccessGrant::class, 'access_grant_id');
    }

    /**
     * Create a token pair: the plain token is returned to the user once,
     * only its hash is stored.
     *
     * @return array{0: DownloadToken, 1: string}
     */
    public static function issue(AccessGrant $grant, ?string $label = null): array
    {
        $plain = Str::random(48);

        $token = static::create([
            'access_grant_id' => $grant->id,
            'token_hash' => static::hashToken($plain),
            'label' => $label,
        ]);

        return [$token, $plain];
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
