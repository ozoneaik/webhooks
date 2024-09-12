<?php
namespace App\Services;

use App\Models\chatHistory;
use App\Models\customers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pusher\Pusher;

class LineService{
    public function create($custId,$profile) : array{
        $data['status'] = false;
        $data['message'] = 'เกิดข้อผิดพลาด';
        try {
            $create = new customers();
            $create->custId = $custId;
            $create->name = $profile['displayName'] ?? 'Unknown';
            $create->avatar = $profile['pictureUrl'] ?? null;
            $create->platform = 'line';
            $create->description = $profile['statusMessage'] ?? '';
            $create->roomId = 0;
            $create->online = true;
            $create->userReply = 'admin';
            $create->save();
            $data['message'] = 'สำเร็จ';
            $data['status'] = true;
            $data['create'] = $create;
        }catch (\Exception $exception){
            $data['create'] = null;
            $data['message'] = $exception->getMessage();

        }
        return $data;
    }

    public function checkCust($custId) : array{
        $data['status'] = false;
        $data['message'] = 'เกิดข้อผิดพลาด';
        try {
            $checkCustomer = customers::where('custId', $custId)->where('platform', 'line')->first();
            if ($checkCustomer) {
                $data['message'] = 'สำเร็จ';
                $data['status'] = true;
                $data['customer'] = $checkCustomer;
               }
        }catch (\Exception $exception){
            $data['customer'] = null;
            $data['message'] = $exception->getMessage();
        }
        return $data;
    }

    public function storeChat($custId,$event,$customer): array
    {
        $data['status'] = false;
        $data['message'] = 'เกิดข้อผิดพลาด';
        try {
            $type = $event['message']['type'] ?? 'unknown';
            $chatHistory = new chatHistory();
            $chatHistory->custId = $custId;
            $chatHistory->contentType = $type;
            $chatHistory->sender = json_encode($customer);
            if ($type === 'text'){
                $text = $event['message']['text'] ?? '';
                $chatHistory->content = $text;
            }elseif ($type === 'image'){
                $imageId = $event['message']['id'] ?? '';
                $url = "https://api-data.line.me/v2/bot/message/$imageId/content";
                $client = new Client();$response = $client->request('GET', $url, [
                    'headers' => ['Authorization' => 'Bearer ' . env('CHANNEL_ACCESS_TOKEN')],
                ]);
                $imageContent = $response->getBody()->getContents();
                $contentType = $response->getHeader('Content-Type')[0];
                $extension = match ($contentType) {
                    'image/jpeg' => '.jpg',
                    'image/png' => '.png',
                    'image/gif' => '.gif',
                    default => '.bin',
                };
                $imagePath = 'line-images/' . $imageId . $extension ;
                Storage::disk('public')->put($imagePath, $imageContent);
                $chatHistory->content = asset('storage/'.$imagePath);
            }elseif ($type === 'sticker'){
                $stickerId = $event['message']['stickerId'] ?? '';
                $chatHistory->content = 'https://stickershop.line-scdn.net/stickershop/v1/sticker/'.$stickerId.'/iPhone/sticker.png';
            }else {
                $chatHistory->content = 'Unsupported message type';
            }
            $chatHistory->save();
            $options = [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true
            ];
            $pusher = new Pusher(env('PUSHER_APP_KEY'), env('PUSHER_APP_SECRET'), env('PUSHER_APP_ID'), $options);
            $chatId = 'chat.'.$custId;
            $pusher->trigger($chatId, 'my-event', [
                'message' => $event['message']['text'] ?? 'No message text'
            ]);
            $pusher->trigger('notifications', 'my-event', [
                'message' => 'new message'
            ]);
            $data['$chatHistory'] = $chatHistory;

        }catch (\Exception $e){
            $data['message'] = $e->getMessage();
            $data['$chatHistory'] = null;
            return $data;
        } catch (GuzzleException $e) {
            $data['message'] = $e->getMessage();
            $data['$chatHistory'] = null;
        }

        return $data;

    }
}
