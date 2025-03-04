<?php

namespace App\Http\Controllers\Line;

use App\Http\Controllers\Controller;
use App\Models\ActiveConversations;
use App\Models\ChatHistory;
use App\Models\Employee;
use App\Models\Keyword;
use App\Models\Rates;
use App\Services\newLineService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LineNewStatusController extends Controller
{
    protected newLineService $newLineService;

    public function __construct(newLineService $newLineService)
    {
        $this->newLineService = $newLineService;
    }


    public function store($custId, $message, $customer)
    {
        try {
            $bot = Employee::query()->where('empCode', 'BOT')->first();
            if ($message['contentType'] !== 'text') {
                $data = $this->messageNotText($custId, $message, $customer, $bot);
            } else {
                $data = $this->messageText($custId, $message, $customer, $bot);
            }
        } catch (\Exception $e) {
            $data = [];
            Log::channel('lineEvent')->error($e->getMessage());
        }
        return $data;
    }

    private function messageNotText($custId, $message, $customer, $bot): array
    {
        try {
            DB::beginTransaction();
            Log::channel('lineEvent')->info('มีการส่ง media messageNotText');
            $token = $customer['accessToken'];
            unset($customer['accessToken']);
            $rate = new Rates();
            $rate['custId'] = $custId;
            $rate['status'] = 'progress';
            $rate['latestRoomId'] = 'ROOM00';
            $rate['rate'] = 0;
            $rate->save();
            $active = new ActiveConversations();
            $active['custId'] = $custId;
            $active['roomId'] = 'ROOM00';
            $active['receiveAt'] = Carbon::now();
            $active['startTime'] = Carbon::now();
            $active['empCode'] = 'BOT';
            $active['rateRef'] = $rate['id'];
            $active->save();
            $chatHistory = new ChatHistory();
            $chatHistory['custId'] = $custId;
            $chatHistory['content'] = $message['content'];
            $chatHistory['contentType'] = $message['contentType'];
            $chatHistory['sender'] = json_encode($customer);
            $chatHistory['conversationRef'] = $active['id'];
            $chatHistory->save();
            $this->newLineService->sendMenu($custId, $token, $bot, $customer);
            $chatHistoryBot = new ChatHistory();
            $chatHistoryBot['custId'] = $custId;
            $chatHistoryBot['content'] = "สวัสดีคุณ " . $customer['custName'] . " เพื่อให้การบริการที่รวดเร็ว กรุณาเลือกหัวด้านล่างเพื่อส่งต่อให้เจ้าหน้าที่เพื่อมาบริการท่านต่อไป  ขอบคุณครับ/ค่ะ";
            $chatHistoryBot['contentType'] = "text";
            $chatHistoryBot['sender'] = json_encode($bot);
            $chatHistoryBot['conversationRef'] = $active['id'];
            $chatHistoryBot->save();
            DB::commit();
            return [
                'rate' => $rate->toArray(),
                'active' => $active->toArray(),
                'chatHistory' => $chatHistory->toArray(),
                'customer' => $customer
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::channel('lineEvent')->error(sprintf(
                "[Error] %s in %s on line %d",
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
            return [];
        }

    }

    private function messageText($custId, $message, $customer, $bot)
    {
        try {
            $findKeyword = Keyword::query()->where('name', 'LIKE', "%{$message['content']}%")->first();
            if ($findKeyword){
                if ($findKeyword['event']){// สำหรับปิดสนทนา
                    DB::beginTransaction();
                    unset($customer['accessToken']);
                    $rate = new Rates();
                    $rate['custId'] = $custId;
                    $rate['status'] = 'success';
                    $rate['latestRoomId'] = 'ROOM00';
                    $rate['rate'] = 0;
                    $rate->save();
                    $active = new ActiveConversations();
                    $active['custId'] = $custId;
                    $active['roomId'] = 'ROOM00';
                    $active['receiveAt'] = Carbon::now();
                    $active['startTime'] = Carbon::now();
                    $active['endTime'] = $active['startTime'];
                    $active['totalTime'] = '0 ชั่วโมง 0 นาที 0 วินาที';
                    $active['empCode'] = 'BOT';
                    $active['rateRef'] = $rate['id'];
                    $active->save();
                    $chatHistory = new ChatHistory();
                    $chatHistory['custId'] = $custId;
                    $chatHistory['content'] = $message['content'];
                    $chatHistory['contentType'] = $message['contentType'];
                    $chatHistory['sender'] = json_encode($customer);
                    $chatHistory['conversationRef'] = $active['id'];
                    $chatHistory->save();
                    DB::commit();
                    return [
                        'rate' => $rate->toArray(),
                        'active' => $active->toArray(),
                        'chatHistory' => $chatHistory->toArray(),
                        'customer' => $customer
                    ];
                }else{
                    unset($customer['accessToken']);
                    $rate = new Rates();
                    $rate['custId'] = $custId;
                    $rate['status'] = 'pending';
                    $rate['latestRoomId'] = $findKeyword['redirectTo'];
                    $rate['rate'] = 0;
                    $rate->save();
                    $active = new ActiveConversations();
                    $active['custId'] = $custId;
                    $active['roomId'] = $findKeyword['redirectTo'];
                    $active['rateRef'] = $rate['id'];
                    $active->save();
                    $chatHistory = new ChatHistory();
                    $chatHistory['custId'] = $custId;
                    $chatHistory['content'] = $message['content'];
                    $chatHistory['contentType'] = $message['contentType'];
                    $chatHistory['sender'] = json_encode($customer);
                    $chatHistory['conversationRef'] = $active['id'];
                    $chatHistory->save();
                    DB::commit();
                    return [
                        'rate' => $rate->toArray(),
                        'active' => $active->toArray(),
                        'chatHistory' => $chatHistory->toArray(),
                        'customer' => $customer
                    ];
                }
            }else{
                DB::beginTransaction();
                Log::channel('lineEvent')->info('มีการส่ง media messageNotText');
                $token = $customer['accessToken'];
                unset($customer['accessToken']);
                $rate = new Rates();
                $rate['custId'] = $custId;
                $rate['status'] = 'progress';
                $rate['latestRoomId'] = 'ROOM00';
                $rate['rate'] = 0;
                $rate->save();
                $active = new ActiveConversations();
                $active['custId'] = $custId;
                $active['roomId'] = 'ROOM00';
                $active['receiveAt'] = Carbon::now();
                $active['startTime'] = Carbon::now();
                $active['empCode'] = 'BOT';
                $active['rateRef'] = $rate['id'];
                $active->save();
                $chatHistory = new ChatHistory();
                $chatHistory['custId'] = $custId;
                $chatHistory['content'] = $message['content'];
                $chatHistory['contentType'] = $message['contentType'];
                $chatHistory['sender'] = json_encode($customer);
                $chatHistory['conversationRef'] = $active['id'];
                $chatHistory->save();
                $this->newLineService->sendMenu($custId, $token, $bot, $customer);
                $chatHistoryBot = new ChatHistory();
                $chatHistoryBot['custId'] = $custId;
                $chatHistoryBot['content'] = "สวัสดีคุณ " . $customer['custName'] . " เพื่อให้การบริการที่รวดเร็ว กรุณาเลือกหัวด้านล่างเพื่อส่งต่อให้เจ้าหน้าที่เพื่อมาบริการท่านต่อไป  ขอบคุณครับ/ค่ะ";
                $chatHistoryBot['contentType'] = "text";
                $chatHistoryBot['sender'] = json_encode($bot);
                $chatHistoryBot['conversationRef'] = $active['id'];
                $chatHistoryBot->save();
                DB::commit();
                return [
                    'rate' => $rate->toArray(),
                    'active' => $active->toArray(),
                    'chatHistory' => $chatHistory->toArray(),
                    'customer' => $customer
                ];
            }
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::channel('lineEvent')->error(sprintf(
                "[Error] %s in %s on line %d",
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
            return [];
        }
    }
}
