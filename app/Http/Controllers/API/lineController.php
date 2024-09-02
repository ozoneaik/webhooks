<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\chatHistory;
use App\Models\customers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Pusher\Pusher;

class lineController extends Controller
{
    public function lineWebHook(Request $request) : JsonResponse{
        $res = $request->all();
        $events = $res["events"];
        Log::info('Webhooks', ['request' => $request]);
        // ตรวจสอบก่อนว่า มี message ส่งมามั้ย
        if (count($events) > 0) {
            //ตรวจสอบว่ามี userId หรือไม่
            if ($events[0]['source']['userId']){
                $userId = $events[0]['source']['userId'];
                $URL = "https://api.line.me/v2/bot/profile/".$userId;
                try {
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . env('CHANNEL_ACCESS_TOKEN')
                    ])->get($URL);

                    // ตรวจสอบสถานะการตอบสนอง
                    if ($response->successful()) {
                        $profile = $response->json(); // แปลง response เป็น array หรือ object
                        Log::info('PROFILE', ['request' => json_encode($profile, JSON_PRETTY_PRINT)]);
                        // เช็คว่าเคยบันทึก customer คนนี้หรือยัง
                        $checkCustomer = customers::where('custId',$userId)->where('platform','line')->first();
                        if (!$checkCustomer){
                            $customer = new customers();
                            $customer->custId = $userId;
                            $customer->name = $profile['displayName'];
                            $customer->imageUrl = $profile['pictureUrl'];
                            $customer->platform = 'line';
                            $customer->description = $profile['statusMessage'];
                            $customer->groupId = 1;
                            $customer->save();
                        }else{
                            //
                        }
                        //ทำการบันทึก chat
                        $type = $events[0]['message']['type'];
                        $chatHistory = new chatHistory();
                        $chatHistory->custId = $userId;
                        $chatHistory->typeMessage = $type;
                        if ($type == 'text'){
                            $text = $events[0]['message']['text'];
                            $chatHistory->textMessage = $text;
                        }elseif ($type == 'image'){
                            $imageId = $events[0]['message']['id'];
                            $chatHistory->textMessage = 'https://api-data.line.me/v2/bot/message/'.$imageId.'/content/preview';
                        }elseif ($type == 'sticker'){
                            $stickerId = $events[0]['message']['stickerId'];
                            $chatHistory->textMessage = 'https://stickershop.line-scdn.net/stickershop/v1/sticker/'.$stickerId.'/iPhone/sticker.png';
                        }else{
                            $stickerName = 'name';
                        }
                        $chatHistory->save();
                        if (!$chatHistory){
                            Log::info('$chatHistory Error');
                        }

                        //ส่ง event ไปยัง web socket
                        $options = [
                            'cluster' => env('PUSHER_APP_CLUSTER'),
                            'useTLS' => true
                        ];
                        $pusher = new Pusher(env('PUSHER_APP_KEY'), env('PUSHER_APP_SECRET'), env('PUSHER_APP_ID'), $options);
                        $chatId = 'chat.'.$userId;
                        $pusher->trigger($chatId, 'my-event', [
                            'message' => $events[0]['message']['text']
                        ]);

                    } else {
                        // จัดการกับกรณีที่การร้องขอไม่สำเร็จ
                        $error = $response->body(); // รับข้อความ error
                    }
                } catch (ConnectionException $e) {
                    Log::info('Error request', ['request' => json_encode($res, JSON_PRETTY_PRINT)]);
                } catch (\Exception $e){
                    return response()->json(['error' => $e->getMessage()], 500);
                }
            }
        }
//        Log::info('Showing request', ['request' => json_encode($request, JSON_PRETTY_PRINT)]);
        return response()->json([
            'response' => $request->all()
        ]);
    }
}
