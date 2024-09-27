<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $custId)
 */
class Rates extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'rates';
    protected $fillable = ['custId','rate','latestRoomId','status'];
}
