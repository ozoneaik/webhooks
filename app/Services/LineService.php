<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;

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
}
