<?php

namespace App\Services;

use App\Models\ActiveConversations;
use App\Models\chatHistory;
use App\Models\customers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Storage;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Pusher\PusherException;

class LineService
{
    public function create($custId, $profile): array
    {
        try {
            $customer = customers::create([
                'custId' => $custId,
                'name' => $profile['displayName'] ?? 'Unknown',
                'avatar' => $profile['pictureUrl'] ?? null,
                'platform' => 'line',
                'description' => $profile['statusMessage'] ?? '',
                'online' => true,
                'status' => 'pending',
                'roomId' => 0,
            ]);
            return [
                'status' => true,
                'message' => 'สำเร็จ',
                'create' => $customer
            ];
        } catch (\Exception $exception) {
            return [
                'status' => false,
                'message' => $exception->getMessage(),
                'create' => null
            ];
        }
    }

    public function checkCust($custId): array
    {
        try {
            $customer = customers::where('custId', $custId)->where('platform', 'line')->first();
            return [
                'status' => (bool)$customer,
                'message' => $customer ? 'สำเร็จ' : 'ไม่พบลูกค้า',
                'customer' => $customer
            ];
        } catch (\Exception $exception) {
            return [
                'status' => false,
                'message' => $exception->getMessage(),
                'customer' => null
            ];
        }
    }

    public function storeChat($id,$custId, $event, $customer): array
    {
        try {

            $type = $event['message']['type'] ?? 'unknown';
            $chatHistory = new chatHistory([
                'custId' => $custId,
                'contentType' => $type,
                'sender' => json_encode($customer)
            ]);

            $chatHistory->content = match ($type) {
                'text' => $event['message']['text'] ?? '',
                'image' => $this->handleImage($event['message']['id'] ?? ''),
                'sticker' => $this->getStickerUrl($event['message']['stickerId'] ?? ''),
                default => 'Unsupported message type',
            };

            $customer = customers::where('custId', $custId)->where('platform', 'line')->first();
            if ($customer->status === 'progress'){
                $active_conversation = ActiveConversations::where('custId', $custId)->where('end_time',null)->first();
                $chatHistory->conversationId = $active_conversation->id;
            }
            if($customer->status === 'success'){
                $customer->status = 'pending';
                $customer->save();
            }
            $chatHistory->save();
            $this->triggerPusher($id,$custId,$customer->name, $chatHistory->content,$type,$customer->avatar);

            return [
                'status' => true,
                'message' => 'Chat saved successfully',
                'chatHistory' => $chatHistory
            ];
        } catch (\Exception|GuzzleException $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'chatHistory' => null
            ];
        }
    }

    private function handleImage($imageId): string
    {
        if (!$imageId) {
            return 'No image ID provided';
        }

        $url = "https://api-data.line.me/v2/bot/message/$imageId/content";
        $client = new Client();
        $response = $client->request('GET', $url, [
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

        $imagePath = 'line-images/' . $imageId . $extension;
        Storage::disk('public')->put($imagePath, $imageContent);

        return asset('storage/' . $imagePath);
    }

    private function getStickerUrl($stickerId): string
    {
        return $stickerId ? 'https://stickershop.line-scdn.net/stickershop/v1/sticker/' . $stickerId . '/iPhone/sticker.png' : 'No sticker ID provided';
    }

    /**
     * @throws PusherException
     * @throws ApiErrorException
     * @throws GuzzleException
     */
    private function triggerPusher($id,$custId ,$custName, $message,$contentType,$avatar): void
    {
        $options = [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'useTLS' => true
        ];

        $pusher = new Pusher(env('PUSHER_APP_KEY'), env('PUSHER_APP_SECRET'), env('PUSHER_APP_ID'), $options);

        $pusher->trigger('chat.' . $custId, 'my-event', ['message' => $message]);
        $pusher->trigger('notifications', 'my-event', [
            'message' => 'มีข้อความใหม่เข้ามา',
            'id' => $id,
            'custId' => $custId,
            'avatar' => $avatar,
            'custName' => $custName,
            'content' => $message,
            'contentType' => $contentType
        ]);
    }
}
