<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActiveConversations extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'active_conversations';
    protected $fillable = [
        'custId',
        'start_time',
        'end_time',
        'user_code',
        'count_chat',
    ];
}
