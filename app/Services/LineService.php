<?php

namespace App\Services;

use App\Models\ActiveConversations;
use App\Models\botMenu;
use App\Models\ChatRooms;
use App\Models\Customers;
use App\Models\Rates;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LineService
{
    public function handleMedia($mediaId, $token): string
    {
        Log::channel('lineEvent')->info('มีการส่ง media');
        if (!$mediaId) {
            return 'No image ID provided';
        }

        $url = "https://api-data.line.me/v2/bot/message/$mediaId/content";
        $client = new Client();
        $response = $client->request('GET', $url, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

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
        $fullPath = $IMAGE_URL.$imagePath;
        return $fullPath;
//        return asset('storage/' . $imagePath);
    }

    public function sendMenu($custId, $token): array
    {
        try {
            $customer = Customers::query()->where('custId', $custId)->first();
            $botMenus = botMenu::select('bot_menus.menuName')
                ->join('platform_access_tokens', 'bot_menus.botTokenId', '=', 'platform_access_tokens.id')
                ->join('customers', 'platform_access_tokens.id', '=', 'customers.platformRef')
                ->where('customers.custId', $custId)
                ->get();
            $actions = [];
            if (count($botMenus) > 0) {
                foreach ($botMenus as $botMenu) {
                    $actions[] = [
                        'type' => 'message',
                        'text' => $botMenu->menuName,
                        'label' => $botMenu->menuName,
                    ];
                }
            }else{
                $actions[] = [
                    'type' => 'message',
                    'text' => 'อื่นๆ',
                    'label' => 'อื่นๆ'
                ];
            }
            $body = [
                "to" => $custId,
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => "สวัสดีคุณ ".$customer['custName']." เพื่อให้การบริการที่รวดเร็ว กรุณาเลือกหัวด้านล่างเพื่อส่งต่อให้เจ้าหน้าที่เพื่อมาบริการท่านต่อไป  ขอบคุณครับ/ค่ะ",
                    ],
                    [
                        'type' => 'template',
                        'altText' => 'this is a buttons template',
                        'template' => [
                            'type' => 'buttons',
                            'title' => 'ยินดีต้อนรับ! 🙏',
                            'text' => 'กรุณาเลือกเมนูที่ท่านต้องการสอบถาม',
                            'actions' => $actions
                        ]
                    ]
                ]
            ];
            $res = $this->linePushMessage($token, $body);
            if ($res['status']) {
                $data['status'] = true;
                $data['message'] = $res['message'];
            } else throw new \Exception($res['message']);
        } catch (\Exception $e) {
            $data['status'] = false;
            $data['message'] = $e->getMessage();
        } finally {
            return $data;
        }
    }

    public function handleChangeRoom($content, $rate, $token,$TOKEN_DESCRIPTION): array
    {
        try {
            $custId = $rate['custId'];
            $updateRate = Rates::query()->where('id', $rate['id'])->first();

            $active = ActiveConversations::query()->where('custId', $custId)
                ->where('rateRef',$rate['id'])
                ->where('roomId', $rate['latestRoomId'])
                ->first();
            $active['endTime'] = Carbon::now();
            $startTime = Carbon::parse($active['startTime']);
            $endTime = Carbon::parse($active['endTime']);
            $diffInSeconds = $startTime->diffInSeconds($endTime);
            $hours = floor($diffInSeconds / 3600);
            $minutes = floor(($diffInSeconds % 3600) / 60);
            $seconds = $diffInSeconds % 60;
            $active['totalTime'] =  "{$hours} ชั่วโมง {$minutes} นาที {$seconds} วินาที";
            $active->save();

            DB::beginTransaction();
            $chatRooms = ChatRooms::query()->select('roomId', 'roomName')->get();
            $text = 'พนักงานที่รับผิดชอบ';
            foreach ($chatRooms as $key => $chatRoom) {
                $check = botMenu::query()->where('menuName', $content)->first();
                Log::info("บอทเปลี่ยนห้อง RateId >> $rate->id");
                if ($check) { //$content === $prefix
                    Log::info('อยู่ใน menu');
                    $text = $content;
                    // ทำการ update ห้องในตาราง rate
                    $updateRate->latestRoomId = $check->roomId;
                    $updateRate->status = 'pending';
                    $updateRate->save();
                    // ทำการสร้าง active
                    $AC = new ActiveConversations();
                    $AC['custId'] = $custId;
                    $AC['roomId'] = $check->roomId;
                    $AC['from_empCode'] = 'BOT';
                    $AC['from_roomId'] = 'ROOM00';
                    $AC['rateRef'] = $rate['id'];
                    $AC->save();
                    break;
                } else {
                    if ($key === count($chatRooms) - 1) {
                        Log::info('ไม่อยู่ใน menu');
                        if($TOKEN_DESCRIPTION === 'ศูนย์ซ่อม Pumpkin'){
                            $updateRate->latestRoomId = 'ROOM02';
                        }else{
                            $updateRate->latestRoomId = 'ROOM01';
                        }
                        // $updateRate->latestRoomId = 'ROOM01';
                        $updateRate->status = 'pending';
                        $updateRate->save();
                        // ทำการสร้าง active
                        $AC = new ActiveConversations();
                        $AC['custId'] = $custId;
                        if($TOKEN_DESCRIPTION === 'ศูนย์ซ่อม Pumpkin'){
                            $AC['roomId'] = 'ROOM02';
                        }else{
                            $AC['roomId'] = 'ROOM01';
                        }
                        // $AC['roomId'] = 'ROOM01';
                        $AC['from_empCode'] = 'BOT';
                        $AC['from_roomId'] = 'ROOM00';
                        $AC['rateRef'] = $rate['id'];
                        $AC->save();
                    }
                }
            }
            $body = [
                "to" => $custId,
                'messages' => [[
                    'type' => 'text',
                    'text' => "ระบบกำลังส่งต่อให้เจ้าหน้าที่ที่รับผิดชอบเพื่อเร่งดำเนินการเข้ามาสนทนา กรุณารอสักครู่",
                ]]
            ];

            $res = $this->linePushMessage($token, $body);
            if ($res['status']) {
                $data['status'] = true;
                $data['message'] = $res['message'];
            } else throw new \Exception($res['message']);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $data['status'] = true;
            $data['message'] = $e->getMessage();
        } finally {
            return $data;
        }
    }

    public function linePushMessage($token, $body): array
    {
        try {
            $UrlPush = 'https://api.line.me/v2/bot/message/push';
            $res = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->asJson()->post($UrlPush, $body);
            if ($res->status() == 200) {
                $data['status'] = true;
                $data['message'] = 'successful';
            } else {
                Log::info($res->json());
                throw new \Exception('not successful');
            }
        } catch (\Exception $e) {
            Log::info('error medthod line push message ใน line services');
            Log::error($e->getMessage());
            $data['status'] = false;
            $data['message'] = $e->getMessage();
        } finally {
            return $data;
        }
    }

}
