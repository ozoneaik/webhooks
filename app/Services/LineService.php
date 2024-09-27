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
    public function handleImage($imageId): string
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
