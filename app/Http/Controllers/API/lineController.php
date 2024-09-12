<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\LineService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class lineController extends Controller
{
    protected LineService $lineService;
    public function __construct(LineService $lineService){
        $this->lineService = $lineService;
    }

    public function lineWebHook(Request $request) : JsonResponse
    {
        $res = $request->all();
        $events = $res["events"] ?? [];
        if (empty($events)) {
            return response()->json(['message' => 'No events found'], 200);
        }
        if (empty($events[0]['source']['userId'])) {
            return response()->json(['error' => 'User ID not found'], 400);
        }
        $userId = $events[0]['source']['userId'];
        $URL = "https://api.line.me/v2/bot/profile/".$userId;
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('CHANNEL_ACCESS_TOKEN')
            ])->get($URL);
            if ($response->failed()) {
                Log::error('Failed to fetch profile', ['response' => $response->body()]);
                return response()->json(['error' => 'Failed to fetch profile'], 500);
            }
            $profile = $response->json();
            $checkCustomer = $this->lineService->checkCust($userId); // เช็คก่อนว่าเคยบันทึกผู้ใช้คนนี้หรือยัง
            if ($checkCustomer['status'] === false) {
                $customer = $this->lineService->create($userId, $profile); // ถ้าไม่เจอให้ทำการสร้าง
                if ($customer['status'] === false) {
                    throw new \Exception('Customer status is false');
                }
                $customer = $customer['create'];
            } else {
                $customer = $checkCustomer['customer'];
            }
            $chatHistory = $this->lineService->storeChat($userId, $events[0], $customer);
            if ($chatHistory['status'] === false) {
                throw new \Exception('ChatHistory status is false');
            }
        } catch (ConnectionException|\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
        return response()->json(['response' => 'Webhook processed successfully']);
    }
}
