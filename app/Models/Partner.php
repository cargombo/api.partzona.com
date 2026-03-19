<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class Partner extends Authenticatable
{
    use HasApiTokens, Notifiable;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_name',
        'contact_name',
        'email',
        'password',
        'plain_password',
        'phone',
        'country',
        'industry',
        'website',
        'status',
        'plan_id',
        'payment_model',
        'deposit_balance',
        'debit_limit',
        'debit_used',
        'outstanding_balance',
        'rpm_limit',
        'daily_limit',
        'monthly_limit',
        'max_concurrent',
        'ip_whitelist',
        'allow_negative',
        'limits_reset_at',
        'notes',
        'approved_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $with = ['plan'];

    protected $casts = [
        'approved_at' => 'datetime',
        'deposit_balance' => 'decimal:2',
        'debit_limit' => 'decimal:2',
        'debit_used' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'rpm_limit' => 'integer',
        'daily_limit' => 'integer',
        'monthly_limit' => 'integer',
        'max_concurrent' => 'integer',
        'ip_whitelist' => 'array',
        'allow_negative' => 'boolean',
        'limits_reset_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Partnerin sifariş verə bilib-bilməyəcəyini yoxlayır (USD məbləği ilə)
     */
    public function canPlaceOrder(float $amountUsd = 0): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->allow_negative) {
            return true;
        }

        if ($this->payment_model === 'deposit') {
            return $this->deposit_balance >= $amountUsd;
        }

        // debit model
        return ($this->debit_limit - $this->debit_used) >= $amountUsd;
    }

    /**
     * Mövcud balansı (USD) qaytarır
     */
    public function availableBalance(): float
    {
        if ($this->payment_model === 'deposit') {
            return (float) $this->deposit_balance;
        }

        return (float) ($this->debit_limit - $this->debit_used);
    }

    /**
     * Sifariş məbləğini balansdan düşür (USD)
     */
    public function chargeForOrder(float $amountUsd): bool
    {
        if ($this->payment_model === 'deposit') {
            $this->deposit_balance -= $amountUsd;
            $this->outstanding_balance += $amountUsd;
        } else {
            $this->debit_used += $amountUsd;
            $this->outstanding_balance += $amountUsd;
        }

        return $this->save();
    }

    /**
     * IP ünvanının icazəli olub-olmadığını yoxlayır
     */
    public function isIpAllowed(string $ip): bool
    {
        // Plan IP whitelist dəstəkləmirsə — hamısına icazə
        if (!$this->plan || !$this->plan->ip_whitelist) {
            return true;
        }

        // Whitelist boşdursa — hamısına icazə
        if (empty($this->ip_whitelist)) {
            return true;
        }

        return in_array($ip, $this->ip_whitelist);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Partnerin tranzaksiyaları
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Partnerin API tokenləri
     */
    public function partnerTokens()
    {
        return $this->hasMany(PartnerToken::class);
    }

    /**
     * Partnerin icazəli kateqoriyaları (1688 category_id-ləri)
     */
    public function allowedCategories()
    {
        return $this->belongsToMany(
            Category1688::class,
            'partner_categories',
            'partner_id',
            'category_id',
            'id',
            'category_id'
        );
    }

    /**
     * Partnerin icazəli endpoint-ləri
     */
    public function allowedEndpoints()
    {
        return $this->hasMany(\App\Models\PartnerEndpointPerm::class);
    }
}
