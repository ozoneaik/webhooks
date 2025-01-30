<?php

use App\Http\Controllers\API\lineController;
use App\Http\Controllers\API\testController;
use App\Http\Controllers\fileUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/',function (){
    return response()->json([
        'message' => 'response success 200'
    ],200);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/line/webhook' ,[testController::class,'test']);
Route::post('/line/test', [testController::class, 'test']);
Route::post('/file-upload' ,[fileUploadController::class,'fileUpload']);
