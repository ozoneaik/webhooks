<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CallCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class lineController extends Controller
{
    public function lineWebHook(Request $request){
        $res = $request->all();
        $events = $res["events"];
        if (count($events) > 0) {
            $chatHistory = new CallCenter();
            $chatHistory->custId = $events[0]['source']['userId'];
            $chatHistory->textMessage = $events[0]['message']['text'];
            $chatHistory->save();
        }
        Log::info('Showing request', ['request' => json_encode($res, JSON_PRETTY_PRINT)]);
        return response()->json([
            'response' => $request->all()
        ]);
    }
}
