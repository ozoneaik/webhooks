<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatRooms extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'chat_rooms';
    protected $fillable =['roomId','roomName','unRead'];
}
