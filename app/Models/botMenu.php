<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where($content, string $string)
 */
class botMenu extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'bot_menus';
    protected $fillable = ['botTokenId','menuName','roomId'];
}
