<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;
    protected $connection = 'call_center_database';
    protected $table = 'users';
    protected $fillable = ['empCode','name','description','avatar'];
}
