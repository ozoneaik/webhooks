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
        $chatHistory = new ChatHistory();
        $chatHistory->custId = $custId;
        switch ($message['type']) {
            case 'text':
                $messages['content'] = $content;
                break;
            case 'image':
                $imageId = $content;
                $messages['content'] = $this->lineService->handleMedia($imageId, $TOKEN);
                break;
            case 'sticker':
                $stickerId = $content;
                $pathStart = 'https://stickershop.line-scdn.net/stickershop/v1/sticker/';
                $pathEnd = '/iPhone/sticker.png';
                $newPath = $pathStart . $stickerId . $pathEnd;
                $messages['content'] = $newPath;
                break;
            case 'video':
                $videoId = $content;
//                $messages['content'] = $this->lineService->handleMedia($videoId, $TOKEN);
                break;
            case 'location':
                $messages['content'] = $E['message']['address'];
                break;
            case 'audio':
                $audioId = $content;
//                $messages['content'] = $this->lineService->handleMedia($audioId, $TOKEN);
                break;
            default:
                $messages['content'] = 'ไม่สามารถตรวจสอบได้ว่าลูกค้าส่งอะไรเข้ามา';
        }

        if ($contentType == 'image') {
            $chatHistory->content = $content;
        }elseif ($contentType == 'sticker') {
            $chatHistory->content = $content;
        }
        else {
            $chatHistory->content = $content;
        }
        $chatHistory->contentType = $contentType;
        $chatHistory->sender = $sender;
        $chatHistory->conversationRef = $conversationRef;
        $chatHistory->save();
        return $chatHistory;
    }
}
