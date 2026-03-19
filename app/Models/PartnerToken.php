<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PartnerToken extends Model
{
    protected $table = 'partner_tokens';

    protected $fillable = [
        'partner_id',
        'token_key',
        'token_hash',
        'token_type',
        'status',
        'expires_at',
        'last_used_at',
        'ip_whitelist',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'last_used_at' => 'datetime',
        'ip_whitelist' => 'array',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Yeni token generasiya et
     */
    public static function generate(string $partnerId, string $type = 'live', ?string $expiresAt = null, ?array $ipWhitelist = null): array
    {
        $prefix = $type === 'live' ? 'pz_live_' : 'pz_sandbox_';
        $rawToken = $prefix . Str::random(32);

        $token = self::create([
            'partner_id' => $partnerId,
            'token_key' => $rawToken,
            'token_hash' => hash('sha256', $rawToken),
            'token_type' => $type,
            'status' => 'active',
            'expires_at' => $expiresAt,
            'ip_whitelist' => $ipWhitelist ?? [],
        ]);

        return [
            'token' => $token,
            'plain_token' => $rawToken,
        ];
    }
}
