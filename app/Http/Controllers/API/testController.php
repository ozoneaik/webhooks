<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ActiveConversations;
use App\Models\ChatHistory;
use App\Models\Customers;
use App\Models\Employee;
use App\Models\PlatformAccessTokens;
use App\Models\Rates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class testController extends Controller
{
    public function lineWebhooks(Request $request)
    {
        try {
            Log::info('lineWebhooks');
            $events = $request->events;
            Log::channel('lineEvent')->info($events);
            if (empty($events)) { // ถ้าไม่เจอ events
                Log::channel('lineEvent')->info($events);
                return response()->json([
                    'status' => 'success'
                ]);
            } else { // ถ้าเจอ events
                $BOT = Employee::query()->where('empCode', 'BOT')->first();
                foreach ($events as $key => $event) {
                    $custId = $event['source']['userId'];





                    // ดึงข้อมูลลูกค้าจากฐานข้อมูล
                    $customer = $this->storeOrFoundCustomer($custId);
                    if (empty($customer['customer']) || empty($customer['token'])) {
                        Log::channel('lineEvent')->info('ไม่พบข้อมูลลูกค้า');
                        continue;
                    } else {
                        //
                    }



                    // ตรวจสอบว่า มี rate ที่ status === success หรือไม่
                    $rate = Rates::query()->where('status','!=','success')->where('custId', $customer['customer']['custId'])->orderBy('id','DESC')->first();
                    if (!empty($rate)) {
                        //
                    } else {
                        $activeConversation = ActiveConversations::query()->where('custId', $customer['customer']['custId'])
                            ->where('roomId', $rate['latestRoomId'])
                            ->orderBy('id', 'desc')->first();
                        if (empty($activeConversation)) {
                            //
                        } else {
                            if ($activeConversation['roomId'] === 'ROOM00') {
                                // ดูว่า content ที่ส่งมามีใน listKeyWord หรือไม่
                            } else {
                                // สร้าง chatHistory
                                $chathistory = $this->storeChatHistory(
                                    $custId = $customer['customer']['custId'],
                                    $content = $event['message'],
                                    $contentType = $event['message']['type'],
                                    $sender = $customer['customer'],
                                    $token = $customer['token'],
                                    $conversationRef = 1
                                );

                                if ($rate['status'] === 'pending') {
                                    // ดูว่า อยู่คิวที่เท่าไหร่
                                    $C['message']['text'] = 'ขณะนี้คิวของท่านเหลือ 1 คิว กรุณารอสักครู่';
                                    $chathistory = $this->storeChatHistory(
                                        $custId = $customer['customer']['custId'],
                                        $content = $C['message'],
                                        $contentType = 'text',
                                        $sender = $BOT,
                                        $token = $customer['token'],
                                        $conversationRef = 1
                                    );
                                    $sendMsgLine = $this->lineMessageSend(
                                        $custId = $customer['customer']['custId'],
                                        $content = 'ขณะนี้คิวของท่านเหลือ 1 คิว กรุณารอสักครู่',
                                        $contentType = 'text',
                                        $token = $customer['token']
                                    );
                                } else {
                                    // trigger ไปที่ pusher
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::info('Exception caught', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    // function ในการส่วง events ไปที่ pusher
    private function pusherSend($events)
    {
        Log::channel('lineEvent')->info($events);
        return response()->json([
            'status' => 'success'
        ]);
    }

    // function ในการส่ง message ไปที่ line
    private function lineMessageSend($custId, $token, $content, $contentType)
    {
        $res = Http::withHeader([
            'Authorization' => 'Bearer ' . $token,
        ])->post('https://api.line.me/v2/bot/message/push', [
            'to' => $custId,
            'messages' => [[
                'type' => $contentType,
                'text' => $content,
            ]]
        ]);
    }

    // function ในการสร้างหรือค้นหาข้อมูลลูกค้า
    private function storeOrFoundCustomer($custId)
    {
        $TOKEN = null;
        $foundCust = Customers::query()->where('custId', $custId)->first();
        $URL = "https://api.line.me/v2/bot/profile/$custId";
        if (empty($foundCust)) {
            $tokens = PlatformAccessTokens::query()->select('id', 'accessToken', 'description')->get();
            foreach ($tokens as $key => $token) {
                $res = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token->accessToken,
                ])->get($URL);
                if ($res->successful()) {
                    $res = $res->json();
                    $newCust = Customers::query()->create([
                        'custId' => $custId,
                        'custName' => $res['displayName'],
                        'avatar' => $res['pictureUrl'],
                        'description' => "ทักมาจากไลน์ " . $token['description'],
                        'platformRef' => $token['id'],
                    ]);
                    $customer = $newCust;
                    $TOKEN = $token->accessToken;
                    break;
                } else {
                    $customer = null;
                    $TOKEN = null;
                }
            }
        } else {
            $customer = $foundCust;
            $TOKEN = PlatformAccessTokens::query()->where('id', $foundCust->platformRef)->first()->accessToken;
        }
        return [
            'customer' => $customer,
            'token' => $TOKEN
        ];
    }

    // function ในการสร้าง chatHistory
    private function storeChatHistory($custId, $content, $contentType, $sender, $conversationRef, $token)
    {

        if ($contentType === 'text') {
            $content = $content['text'];
        } else if (($contentType === 'image') || $contentType === 'video' || $contentType === 'audio') {
            $content = $this->getMediaLine($mediaId = $content['id'], $token = $token);
        } else if ($contentType === 'location') {
            $content = 'location';
        } else {
            $contentType = 'text';
            $content = 'ไม่สามารถตรวจสอบได้ว่าลูกค้าส่งอะไรเข้ามา';
        }

        $chathistory = ChatHistory::query()->create([
            'custId' => $custId,
            'content' => $content,
            'contentType' => $contentType,
            'sender' => json_encode($sender),
            'conversationRef' => $conversationRef,
        ]);
        return $chathistory ? 'success' : 'error';
    }

    // function ในการดึง media จาก line
    private function getMediaLine($mediaId, $token)
    {
        $response = Http::withHeader([
            'Authorization' => 'Bearer ' . $token,
        ])->get("https://api-data.line.me/v2/bot/message/$mediaId/content");
        if ($response->successful()) {
            $imageContent = $response->getBody()->getContents();
            $contentType = $response->getHeader('Content-Type')[0];
            $extension = match ($contentType) {
                'image/jpeg' => '.jpg',
                'image/png' => '.png',
                'image/gif' => '.gif',
                'video/mp4' => '.mp4',
                'video/webm' => '.webm',
                'video/ogg' => '.ogg',
                'video/avi' => '.avi',
                'video/mov' => '.mov',
                'audio/x-m4a' => '.m4a',
                default => '.bin',
            };
            $imagePath = '/line-images/' . $mediaId . $extension;
            Storage::disk('public')->put($imagePath, $imageContent);
            $IMAGE_URL = env('IMAGE_URL');
            if ($IMAGE_URL === null) {
                $IMAGE_URL = 'https://ass.pumpkin.co.th/laravel-webhooks/storage/app/public/';
            }
            $fullPath = $IMAGE_URL . $imagePath;
            return $fullPath;
        } else {
            return 'not found Media';
        }
    }
}
