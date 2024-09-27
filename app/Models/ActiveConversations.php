<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $rateRef)
 */
class ActiveConversations extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'active_conversations';

    protected $fillable = [
        'custId',
        'roomId',
        'receiveAt',
        'startTime',
        'endTime',
        'totalTime',
        'from_empCode',
        'from_roomId',
        'empCode',
        'rateRef'
    ];
}
