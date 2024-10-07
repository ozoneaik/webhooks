<?php

namespace App\Services;

use GuzzleHttp\Client;
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
            $UrlPush = 'https://api.line.me/v2/bot/message/push';
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
                            'actions' => [
                                [
                                    'type' => 'message',
                                    'label' => '🧰ติดต่อห้องช่าง',
                                    'text' => 'เมนู->ติดต่อห้องช่าง'
                                ],
                                [
                                    'type' => 'message',
                                    'label' => '💵ติดต่อห้องการขาย',
                                    'text' => 'เมนู->ติดต่อห้องการขาย'
                                ],
                                [
                                    'type' => 'message',
                                    'label' => '💼ติดต่อห้องประสานการขาย',
                                    'text' => 'เมนู->ติดต่อห้องประสานการขาย'
                                ],
                                [
                                    'type' => 'message',
                                    'label' => '🎃อื่นๆ',
                                    'text' => 'เมนู->อื่นๆ'
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->asJson()->post($UrlPush, $body);
            if ($response->status() == 200) {
                $data['status'] = true;
                $data['message'] = 'ส่งประเมินสำเร็จ';
            } else {
                Log::info($response->json());
                throw new \Exception('ส่งประเมินไม่ได้');
            }
        } catch (\Exception $e) {
            $data['status'] = false;
            $data['message'] = $e->getMessage();
        } finally {
            return $data;
        }

    }
}
