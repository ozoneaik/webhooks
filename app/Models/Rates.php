<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $custId)
 * @method static select(string $string)
 * @method static leftJoin(string $string, string $string1, string $string2, string $string3)
 */
class Rates extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'rates';
    protected $fillable = ['custId','rate','latestRoomId','status'];
}
