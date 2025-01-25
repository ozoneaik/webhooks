<?php
namespace App\Services;

use App\Models\ActiveConversations;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class ActiveConversationService
{

    protected LineService $lineService;
    public function __construct(LineService $lineService){
        $this->lineService = $lineService;
    }
    public function updateEndTime($RATE,$TOKEN){
        $ac = ActiveConversations::query()->where('rateRef', $RATE->id)->orderBy('id', 'desc')->first();
        $ac->endTime = Carbon::now();
        $startTime = Carbon::parse($ac['startTime']);
        $endTime = Carbon::parse($ac['endTime']);
        $diffInSeconds = $startTime->diffInSeconds($endTime);
        $hours = floor($diffInSeconds / 3600);
        $minutes = floor(($diffInSeconds % 3600) / 60);
        $seconds = $diffInSeconds % 60;
        $ac['totalTime'] =  "{$hours} ชั่วโมง {$minutes} นาที {$seconds} วินาที";
        $ac->save();

        $body = [
            "to" => $RATE->custId,
            'messages' => [[
                'type' => 'text',
                'text' => "ระบบกำลังส่งต่อให้เจ้าหน้าที่ที่รับผิดชอบเพื่อเร่งดำเนินการเข้ามาสนทนา กรุณารอสักครู่",
            ]]
        ];
        $this->lineService->linePushMessage($TOKEN, $body);
        return $ac;
    }

}
