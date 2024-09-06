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
 */

class customers extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'customers';
    protected $fillable = ['custId','name','platform','description','avatar','online','roomId','userReply'];
}
