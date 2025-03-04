<?php

namespace App\Http\Controllers\Line;

use App\Http\Controllers\Controller;
use App\Models\Rates;
use Illuminate\Http\Request;

class LineRateController extends Controller
{
    public function store(Request $request)
    {

    }

    public function find($rateId)
    {

    }

    public function check($custId)
    {
        $rate =  Rates::query()
            ->where('custId', $custId)
            ->orderBy('id', 'desc')
            ->first();
        if ($rate){
            return $rate;
        }else{
            return null;
        }
    }
}
