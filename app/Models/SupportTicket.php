<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'partner_id',
        'subject',
        'category',
        'priority',
        'message',
        'status',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id')->orderBy('created_at', 'asc');
    }
}
