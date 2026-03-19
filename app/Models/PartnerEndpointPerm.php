<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerEndpointPerm extends Model
{
    protected $table = 'partner_endpoint_perms';

    protected $fillable = [
        'partner_id',
        'endpoint',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
