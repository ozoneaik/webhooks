<?php

namespace App\Services;

use App\Models\botMenu;
use App\Models\Customers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class newLineService
{
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
            Log::info('error method line push message ใน line services');
            Log::error($e->getMessage());
            $data['status'] = false;
            $data['message'] = $e->getMessage() ?? 'error';
        } finally {
            return $data;
        }
    }

    public function sendMenu($custId,$token,$bot,$customer): array
    {
        try {
            $botMenus = botMenu::query()->select('bot_menus.menuName')
                ->join('platform_access_tokens', 'bot_menus.botTokenId', '=', 'platform_access_tokens.id')
                ->join('customers', 'platform_access_tokens.id', '=', 'customers.platformRef')
                ->where('customers.custId', $customer['custId'])
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
}
