<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\chatHistory;
use App\Models\customers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Pusher\Pusher;
use Illuminate\Support\Facades\Storage;

class lineController extends Controller
{
    public function lineWebHook(Request $request) : JsonResponse
    {
        $res = $request->all();
        $events = $res["events"] ?? [];

        if (empty($events)) {
            return response()->json(['error' => 'No events found'], 400);
        }

        if (empty($events[0]['source']['userId'])) {
            return response()->json(['error' => 'User ID not found'], 400);
        }

        $userId = $events[0]['source']['userId'];
        $URL = "https://api.line.me/v2/bot/profile/".$userId;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('CHANNEL_ACCESS_TOKEN')
            ])->get($URL);

            if ($response->failed()) {
                Log::error('Failed to fetch profile', ['response' => $response->body()]);
                return response()->json(['error' => 'Failed to fetch profile'], 500);
            }

            $profile = $response->json();

            $checkCustomer = customers::where('custId', $userId)
                ->where('platform', 'line')
                ->first();

            if (!$checkCustomer) {
                $customer = new customers();
                $customer->custId = $userId;
                $customer->name = $profile['displayName'] ?? 'Unknown';
                $customer->avatar = $profile['pictureUrl'] ?? null;
                $customer->platform = 'line';
                $customer->description = $profile['statusMessage'] ?? '';
                $customer->roomId = 0;
                $customer->online = true;
                $customer->save();
            } else {
                $customer = $checkCustomer;
            }

            $type = $events[0]['message']['type'] ?? 'unknown';
            $chatHistory = new chatHistory();
            $chatHistory->custId = $userId;
            $chatHistory->contentType = $type;
            $chatHistory->sender = json_encode($customer); // Encode $customer as JSON

            if ($type == 'text') {
                $text = $events[0]['message']['text'] ?? '';
                $chatHistory->content = $text;
            } elseif ($type == 'image') {
                $imageId = $events[0]['message']['id'] ?? '';
                $url = "https://api-data.line.me/v2/bot/message/$imageId/content";
                $client = new \GuzzleHttp\Client();
                $response = $client->request('GET', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . env('CHANNEL_ACCESS_TOKEN')
                    ],
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
                $chatHistory->content = 'http://localhost:8001/storage/'.$imagePath;

            } elseif ($type == 'sticker') {
                $stickerId = $events[0]['message']['stickerId'] ?? '';
                $chatHistory->content = 'https://stickershop.line-scdn.net/stickershop/v1/sticker/'.$stickerId.'/iPhone/sticker.png';
            } else {
                $chatHistory->content = 'Unsupported message type';
            }

            $chatHistory->save();

            $options = [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true
            ];
            $pusher = new Pusher(env('PUSHER_APP_KEY'), env('PUSHER_APP_SECRET'), env('PUSHER_APP_ID'), $options);
            $chatId = 'chat.'.$userId;
            $pusher->trigger($chatId, 'my-event', [
                'message' => $events[0]['message']['text'] ?? 'No message text'
            ]);

            $pusher->trigger('notifications', 'my-event', [
                'message' => 'new message'
            ]);

        } catch (ConnectionException $e) {
            Log::error('Connection error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Connection error'], 500);
        } catch (\Exception $e) {
            Log::error('Exception occurred', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Server error'], 500);
        }

        return response()->json(['response' => 'Webhook processed successfully']);
    }
}
