<?php

namespace App\Services;

use App\Models\Rates;

class RateService
{
    public function store(){
        $rate = new Rates();
        return '';
    }

    public function updateFeedback($rateId, $feedback){
        $rate = Rates::where('id', $rateId)->first();
        $rate->rate = $feedback === 'like' ? 5 : 1;
        $rate->save();
        return $rate;
    }
}
