<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatHistory extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'chat_histories';
    protected $fillable = ['custId','content','contentType','sender','conversationRef'];
}
