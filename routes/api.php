<?php

use App\Http\Controllers\API\lineController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/line/webhook' ,[lineController::class,'lineWebHook']);

Route::get('/test/{custId}/{rateId}' ,function($custId){
    $custName = \App\Models\customers::select('name')->where('custId',$custId)->first();
    $custName = $custName->name;
    return view('star',compact('custName'));
});
