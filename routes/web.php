<?php

use App\Models\customers;
use App\Models\Rates;
use http\Client\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{custId}/{rateId}' ,function($custId,$rateId){
    $custName = customers::select('custName')->where('custId',$custId)->first();
    $custName = $custName->custName;
    $rateStatus = Rates::where('id',$rateId)->first();
    $status = $rateStatus['status'];
    $star = $rateStatus['rate'];
    return view('star',compact('custName','status','star','rateId','custId'));
});

Route::get('/rate/{star}/{rateId}',function($star,$rateId){
    $rates = Rates::where('id',$rateId)->first();
    $rates['rate'] = $star;
    $rates->save();
    return response()->json(['message' => 'success']);
});
