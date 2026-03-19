<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'partner_id',
        'amount',
        'type',
        'description',
        'reference_type',
        'reference_id',
        'balance_after',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
