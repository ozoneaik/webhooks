<?php

namespace App\Http\Controllers\Line;

use App\Http\Controllers\Controller;
use App\Services\LineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineContentController extends Controller
{
    protected LineService $lineService;
    public function __construct(LineService $lineService){
        $this->lineService = $lineService;
    }
    public function getMessage ($m,$token): array
    {
        Log::channel('lineEvent')->info($m);
        $message['contentType'] = $m['type'];
        if ($m['type'] === 'text'){
            $message['content'] = $m['text'];
        }else if ($m['type'] === 'sticker'){
            $stickerId = $m['stickerId'];
            $pathStart = 'https://stickershop.line-scdn.net/stickershop/v1/sticker/';
            $pathEnd = '/iPhone/sticker.png';
            $newPath = $pathStart . $stickerId . $pathEnd;
            $message['content'] = $newPath;
        }else if(($m['type'] === 'image') || ($m['type'] === 'video') || ($m['type'] === 'audio')){
            $mediaId = $m['id'];
            $message['content'] = $this->lineService->handleMedia($mediaId, $token);
        }else if($m['type'] === 'location'){
            $lat = $m['latitude'];
            $long = $m['longitude'];
            $address = $m['address'];
            $locationLink = 'พิกัดแผนที่ => https://www.google.com/maps?q=' . $lat . ',' . $long;
            $message['content'] = $address.'🗺️'.$locationLink;;
        }
        else{
            $message['content'] = "ลูกค้าส่งข้อความประเภท ".$message['contentType'];
        }
        return $message;
    }
}
