<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $custId
 * @property mixed $textMessage
 * @property mixed $typeMessage
 */
class chatHistory extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'chat_histories';
    protected $fillable = ['custId','typeMessage','textMessage','platform'];
}
