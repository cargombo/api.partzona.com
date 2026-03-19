<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $table = 'api_logs';

    protected $fillable = [
        'partner_id',
        'token_id',
        'endpoint',
        'method',
        'status_code',
        'response_time_ms',
        'ip',
        'user_agent',
        'request_params',
        'error_message',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
