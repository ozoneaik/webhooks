<?php
namespace App\Services;

use App\Models\ChatHistory;

class ChatHistoryService
{
    protected LineService $lineService;
    public function __construct(LineService $lineService)
    {
        $this->lineService = $lineService;
    }

    public function store($custId, $message, $sender, $conversationRef,$TOKEN) : ChatHistory
    {
        switch ($message['type']) {
            case 'text':
                $content = $message['text'];
                break;
            case 'image':
                $imageId = $message['id'];
                $content = $this->lineService->handleMedia($imageId, $TOKEN);
                break;
            case 'sticker':
                $stickerId = $message['stickerId'];
                $pathStart = 'https://stickershop.line-scdn.net/stickershop/v1/sticker/';
                $pathEnd = '/iPhone/sticker.png';
                $newPath = $pathStart . $stickerId . $pathEnd;
                $content = $newPath;
                break;
            case 'video':
                $videoId = $message['id'];
                $content = $this->lineService->handleMedia($videoId, $TOKEN);
                break;
            case 'location':
                $lat = $message['latitude'];
                $long = $message['longitude'];
                $locationLink = 'พิกัดแผนที่ => https://www.google.com/maps?q=' . $lat . ',' . $long;
                $content = $message['address'].'🗺️'.$locationLink;
                break;
            case 'audio':
                $audioId = $message['id'];
                $content = $this->lineService->handleMedia($audioId, $TOKEN);
                break;
            default:
                $content = 'ไม่สามารถตรวจสอบได้ว่าลูกค้าส่งอะไรเข้ามา';
        }

        return ChatHistory::query()->create([
            'custId' => $custId,
            'content' => $content,
            'contentType' => $message['type'],
            'sender' => $sender,
            'conversationRef' => $conversationRef,
        ]);
    }
}
