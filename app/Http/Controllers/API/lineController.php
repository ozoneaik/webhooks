<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\LineService;
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
        if (empty($events) || empty($events[0]['source']['userId'])) {
            return response()->json(['error' => 'Invalid events or user ID not found'], 200);
        }
        $userId = $events[0]['source']['userId'];
        $accessToken = env('CHANNEL_ACCESS_TOKEN');
        if (!$accessToken) {
            return response()->json(['message' => 'Channel access token not set'], 500);
        }
        // ดึงโปรไฟล์ของลูกค้า
        $URL = "https://api.line.me/v2/bot/profile/".$userId;
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken
            ])->get($URL);
            if ($response->failed()) {
                Log::error('Failed to fetch profile for user: ' . $userId, ['response' => $response->body()]);
                return response()->json(['message' => 'Failed to fetch profile'], 500);
            }
            $profile = $response->json();
            $checkCustomer = $this->lineService->checkCust($userId); //ตรวจสอบว่าลูกค้าคนนี้เคยบันทึกหรือยัง
            if ($checkCustomer['status'] === false) {
                $customer = $this->lineService->create($userId, $profile);
                if ($customer['status'] === false) { // สร้างลูกค้า
                    throw new \Exception('Failed to create customer for user: ' . $userId);
                }
                $customer = $customer['create'];
            } else {
                $customer = $checkCustomer['customer'];
            }
            $chatHistory = $this->lineService->storeChat($customer->id,$userId, $events[0], $customer);
            if ($chatHistory['status'] === false) {
                throw new \Exception('Failed to store chat history for user: ' . $userId);
            }
        } catch (ConnectionException $e) {
            Log::error('Connection error: ' . $e->getMessage());
            return response()->json(['message' => 'Connection error'], 500);
        } catch (\Exception $e) {
            Log::error('General error: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred'], 500);
        }
        return response()->json(['response' => 'Webhook processed successfully']);
    }

}
