<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShortChats extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'short_chats';
    protected $fillable = ['content'];
}
