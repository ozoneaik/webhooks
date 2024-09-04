<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $custId
 * @property mixed $textMessage
 * @property mixed $typeMessage
 * @property mixed $sender
 * @method static select(Expression $raw)
 * @method static where(string $string, mixed $custId)
 */
class chatHistory extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'chat_histories';
    protected $fillable = ['custId','content','contentType','attachment','sender','usersReply','platform'];
}
