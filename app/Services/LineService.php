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
    public function handleImage($imageId, $token): string
    {
        if (!$imageId) {
            return 'No image ID provided';
        }

        $url = "https://api-data.line.me/v2/bot/message/$imageId/content";
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
            default => '.bin',
        };

        $imagePath = 'line-images/' . $imageId . $extension;
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
                    'text' => 'เมนู->'.$botMenu->roomId,
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
        Log::info('handleChangeRoom');
        try {
            $custId = $rate['custId'];
            $update = Rates::where('id', $rate['id'])->first();
            DB::beginTransaction();
            $chatRooms = ChatRooms::select('roomId','roomName')->get();
            foreach ($chatRooms as $key=>$chatRoom) {
                $prefix = 'เมนู->'.$chatRoom->roomId;
                if ($content === $prefix) {
                    $text = $chatRoom->roomName;
                    // ทำการ update ห้องในตาราง rate
                    $update->latestRoomId = $chatRoom->roomId;
                    $update->status = 'pending';
                    $update->save();
                    // ทำการสร้าง active
                    $AC = new ActiveConversations();
                    $AC['custId'] = $custId;
                    $AC['roomId'] = $chatRoom->roomId;
                    $AC['from_empCode'] = 'BOT';
                    $AC['from_roomId'] = 'ROOM00';
                    $AC['rateRef'] = $rate['id'];
                    $AC->save();
                    break;
                }else{
                    if ($key === count($chatRooms)-1) {
                        $update->latestRoomId = 'ROOM01';
                        $update->status = 'pending';
                        $update->save();
                        // ทำการสร้าง active
                        $AC = new ActiveConversations();
                        $AC['custId'] = $custId;
                        $AC['roomId'] = 'ROOM01';
                        $AC['from_empCode'] = 'BOT';
                        $AC['from_roomId'] = 'ROOM00';
                        $AC['rateRef'] = $rate['id'];
                        $AC->save();
                    }
                    $text = 'พนักงานที่รับผิดชอบ';
                }
            }
            $body = [
                "to" => $custId,
                'messages' => [[
                    'type' => 'text',
                    'text' => "ระบบกำลังส่งแชทของท่านไปยัง $text กรุณารอพนักงานรับเรื่องและตอบกลับครับ/ค่ะ",
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
