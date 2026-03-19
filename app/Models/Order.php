<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'partner_id',
        'order_id',
        'out_order_id',
        'status',
        'products',
        'total_amount',
        'post_fee',
        'flow',
        'address',
        'message',
    ];

    protected $casts = [
        'products' => 'array',
        'address' => 'array',
        'total_amount' => 'decimal:2',
        'post_fee' => 'decimal:2',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
