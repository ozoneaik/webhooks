<?php

namespace App\Services;

use App\Models\ActiveConversations;
use App\Models\botMenu;
use App\Models\ChatRooms;
use App\Models\Rates;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LineService
{
    public function handleMedia($mediaId, $token): string
    {
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
            default => '.bin',
        };

        $imagePath = 'line-images/' . $mediaId . $extension;
        Storage::disk('public')->put($imagePath, $imageContent);

        return asset('storage/' . $imagePath);
    }

    public function sendMenu($custId, $token): array
    {
        try {
            $botMenus = botMenu::all();
            $actions = [];
            foreach ($botMenus as $key => $botMenu) {
                $actions[] = [
                    'type' => 'message',
//                    'text' => 'เมนู->'.$botMenu->roomId,
                    'text' => $botMenu->menuName,
                    'label' => $botMenu->menuName,
                ];
            }
            $body = [
                "to" => $custId,
                'messages' => [
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

    public function handleChangeRoom($content, $rate, $token): array
    {
        try {
            $custId = $rate['custId'];
            $updateRate = Rates::where('id', $rate['id'])->first();
            DB::beginTransaction();
            $chatRooms = ChatRooms::select('roomId', 'roomName')->get();
            $text = 'พนักงานที่รับผิดชอบ';
            foreach ($chatRooms as $key => $chatRoom) {
                $check = botMenu::where('menuName', $content)->first();
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
                        $updateRate->latestRoomId = 'ROOM01';
                        $updateRate->status = 'pending';
                        $updateRate->save();
                        // ทำการสร้าง active
                        $AC = new ActiveConversations();
                        $AC['custId'] = $custId;
                        $AC['roomId'] = 'ROOM01';
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
                    'text' => "ระบบกำลัง $text กรุณารอพนักงานรับเรื่องและตอบกลับครับ/ค่ะ🙏",
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

    private function linePushMessage($token, $body): array
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
            $data['status'] = false;
            $data['message'] = $e->getMessage();
        } finally {
            return $data;
        }
    }

}
