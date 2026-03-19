<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'rpm_limit',
        'daily_limit',
        'monthly_limit',
        'max_concurrent',
        'max_categories',
        'sandbox',
        'ip_whitelist',
        'webhook',
        'sla',
        'price_monthly',
        'status',
    ];

    protected $casts = [
        'rpm_limit' => 'integer',
        'daily_limit' => 'integer',
        'monthly_limit' => 'integer',
        'max_concurrent' => 'integer',
        'max_categories' => 'integer',
        'sandbox' => 'boolean',
        'ip_whitelist' => 'boolean',
        'webhook' => 'boolean',
        'price_monthly' => 'decimal:2',
    ];
}
