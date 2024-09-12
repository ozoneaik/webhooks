<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $custId
 * @property mixed $name
 * @property mixed $imageUrl
 * @property mixed|string $platform
 * @property mixed $description
 * @property int|mixed $groupId
 * @property mixed|null $avatar
 * @property int|mixed $roomId
 * @property mixed|true $online
 * @property mixed|string $userReply
 * @method static where(string $string, mixed $userId)
 * @method static create(array $array)
 */

class customers extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'customers';
    protected $fillable = ['custId','name','platform','description','avatar','online','roomId','userReply'];
}
