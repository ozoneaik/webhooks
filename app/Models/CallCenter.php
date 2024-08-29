<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallCenter extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'chat_histories';
    protected $fillable = ['custId','empReply','TextMessage','platform'];
}
