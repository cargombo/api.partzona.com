<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'rate_date',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'rate_date' => 'datetime',
    ];

    /**
     * Pair adı ilə tap (məs: "CNY-USD")
     */
    public static function getRate(string $from, string $to): ?float
    {
        $currency = static::where('from_currency', strtoupper($from))
            ->where('to_currency', strtoupper($to))
            ->first();

        return $currency ? (float) $currency->rate : null;
    }
}
