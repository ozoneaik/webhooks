<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/line/webhook' ,function(){
    return response()->json([
        'message' => 'test'
    ]);
});
