<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ActiveConversations;
use App\Models\ChatHistory;
use App\Models\Customers;
use App\Models\PlatformAccessTokens;
use App\Models\Rates;
use App\Services\LineService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class lineController extends Controller
{
    protected LineService $lineService;

    public function __construct(LineService $lineService)
    {
        $this->lineService = $lineService;
    }

    public function lineWebHook(Request $request): JsonResponse
    {
        DB::beginTransaction();
        $status = 400;
        try {
            /* เตรียมข้อมูล */
            if (count($request['events']) <= 0) throw new \Exception('event not data');
            $events = $request['events'][0];
            $custId = $events['source']['userId'];
            $customer = '';
            /* ---------------------------------------------------------------------------------------------------- */
            /* ดึง profile ลูกค้า และสร้างข้อมูลลูกค้า หากยังไม่มีในฐานข้อมูล */
            $URL = "https://api.line.me/v2/bot/profile/$custId";
            $channelAccessTokens = PlatformAccessTokens::all();
            foreach ($channelAccessTokens as $token) {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer " . $token['accessToken'],
                ])->get($URL);
                if ($response->status() === 200) {
                    $checkCustomer = Customers::where('custId', $custId)->first();
                    if (!$checkCustomer) {
                        $res = $response->json();
                        $createCustomer = new Customers();
                        $createCustomer['custId'] = $custId;
                        $createCustomer['custName'] = $res['displayName'];
                        $createCustomer['avatar'] = $res['pictureUrl'];
                        $createCustomer['description'] = $res['statusMessage'];
                        $createCustomer['platformRef'] = $token['accessTokenId'];
                        $createCustomer->save();
                        $customer = $createCustomer;
                    } else  $customer = $checkCustomer;
                    break;
                } else Log::info("ไม่พบ" . $response->status());
            }
            /* ---------------------------------------------------------------------------------------------------- */
            /* ตรวจสอบว่า custId คนนี้มี rate ที่สถานะเป็น pending หรือ progress หรือไม่ ถ้าไม่ */
            $checkRates = Rates::where('custId', $custId)->where('status', '!=', 'success')->first();
            // ถ้าไม่เจอ Rates ที่ status เป็น pending หรือ progress ให้สร้าง Rates กับ activeConversations ใหม่
            if (!$checkRates) {
                $rate = new Rates();
                $rate['custId'] = $custId;
                $rate['rate'] = 0;
                $rate['status'] = 'pending';
                $rate['latestRoomId'] = 'ROOM00';
                $rate->save();
                $activeConversation = new ActiveConversations();
                $activeConversation['custId'] = $custId;
                $activeConversation['roomId'] = 'ROOM00';
                $activeConversation['receiveAt'] = Carbon::now();
                $activeConversation['empCode'] = 'BOT';
                $activeConversation['rateRef'] = $rate['id'];
                $activeConversation->save();
                $conversationRef = $activeConversation['id'];
            } else {
                $rateRef = $checkRates['id'];
                $latestRoomId = $checkRates['latestRoomId'];
                $checkActiveConversation = ActiveConversations::where('rateRef', $rateRef)
                    ->where('roomId', $latestRoomId)->first();
                if ($checkActiveConversation) $conversationRef = $checkActiveConversation['id'];
                else throw new \Exception('ไม่พบ conversationRef จากตาราง ActiveConversations');
            }
            /* ---------------------------------------------------------------------------------------------------- */
            /* สร้าง chatHistory */
            $messages['contentType'] = $events['message']['type'];
            switch ($events['message']['type']) {
                case 'text':
                    $messages['content'] = $events['message']['text'];
                    break;
                case 'image':
                    $imageId = $events['message']['id'];
                    $messages['content'] = $this->lineService->handleImage($imageId);
                    break;
                case 'sticker':
                    $stickerId = $events['message']['stickerId'];
                    $newPath = 'https://stickershop.line-scdn.net/stickershop/v1/sticker/' . $stickerId . '/iPhone/sticker.png';
                    $messages['content'] = $newPath;
                    break;
                default:
                    $messages['content'] = 'ไม่สามารถตรวจสอบได้ว่าลูกค้าส่งอะไรเข้ามา';
            }
            $chatHistory = new ChatHistory();
            $chatHistory['custId'] = $custId;
            $chatHistory['content'] = $messages['content'];
            $chatHistory['contentType'] = $messages['contentType'];
            $chatHistory['sender'] = json_encode($customer);
            $chatHistory['conversationRef'] = $conversationRef;
            $chatHistory->save();
            /* ---------------------------------------------------------------------------------------------------- */
            $message = 'มีข้อความใหม่เข้ามา';
            $detail = 'ไม่มีข้อผิดพลาด';
            DB::commit();
        } catch (\Exception $e) {
            if ($e->getMessage() === 'event not data') {
                Log::info('test not event');
                $message = $e->getMessage();
                $detail = 'ไม่มีข้อผิดพลาด';
                $status = 200;
            } else {
                $message = 'เกิดข้อผิดพลาดในการรับข้อความ';
                $detail = $e->getMessage();
            }
            Log::info($e->getMessage());
            DB::rollBack();
        } finally {
            return response()->json([
                'message' => $message,
                'detail' => $detail,
            ], $status);
        }
    }
}
