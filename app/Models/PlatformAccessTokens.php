<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformAccessTokens extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'platform_access_tokens';
    protected $fillable = ['accessTokenId','accessToken','description','platform'];
}
