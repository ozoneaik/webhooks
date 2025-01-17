<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ActiveConversations;
use App\Models\ChatHistory;
use App\Models\Customers;
use App\Models\Employee;
use App\Models\Keyword;
use App\Models\PlatformAccessTokens;
use App\Models\Rates;
use App\Services\LineService;
use App\Services\PusherService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class lineController extends Controller
{
    protected LineService $lineService;
    protected PusherService $pusherService;

    public function __construct(LineService $lineService, PusherService $pusherService)
    {
        $this->lineService = $lineService;
        $this->pusherService = $pusherService;
    }

    private function storeRate($custId,$status,$latestRoomId){
        $rate = new Rates();
        $rate['custId'] = $custId;
        $rate['rate'] = 0;
        $rate['status'] = 'progress';
        $rate['latestRoomId'] = 'ROOM00';
        $rate->save();
        return $rate;
    }

    private function storeAC ($rateId,$custId, $roomId){
        $activeConversation = new ActiveConversations();
        $activeConversation['custId'] = $custId;
        $activeConversation['roomId'] = $roomId;
        $activeConversation['rateRef'] = $rateId;
        $activeConversation->save();
        return $activeConversation;
    }

    private function storeChatHistory($custId,$content,$contentType,$sender,$conversationRef){
        $chatHistory = new ChatHistory();
        $chatHistory['custId'] = $custId;
        $chatHistory['content'] = $content;
        $chatHistory['contentType'] = $contentType;
        $chatHistory['sender'] = $sender;
        $chatHistory['conversationRef'] = $conversationRef;
        $chatHistory->save();
        return $chatHistory;
    }

    public function lineWebHook(Request $request): JsonResponse
    {
        $defalutRoom = 'ROOM00';
        Log::channel('lineEvent')->info($request);
        DB::beginTransaction();
        $checkSendMenu = false;
        $SEND_MENU = false;
        $status = 400;
        try {
            $TOKEN = '';
            $TOKEN_DESCRIPTION = '';
            /* เตรียมข้อมูล */
            if (count($request['events']) <= 0) throw new \Exception('event not data');
            if (($request['events'][0]['type'] !== 'message') && ($request['events'][0]['type'] !== 'postback')) {
                throw new \Exception('event type is not message');
            }
            $EVENTS = $request['events'];
            if ($request['events'][0]['type'] === 'postback') { // ส่งค้ากรอกแบบประเมินมา
                $postbackData = $request['events'][0]['postback']['data'];
                $dataParts = explode(',', $postbackData);
                // สร้างตัวแปร feedback และ rateId
                $feedback = $dataParts[0] ?? null; // ค่าก่อนเครื่องหมายคั่น ,
                $RATEID = $dataParts[1] ?? null;
                $rate = Rates::query()->where('id', $RATEID)->first();
                $rate->rate = $feedback === 'like' ? 5 : 1;
                $rate->save();
                $status = 200;
                $message = 'มีข้อความใหม่เข้ามา';
                $detail = 'ไม่มีข้อผิดพลาด';
            } else { // ส่งข้อความมาปรกติ
                $events = $request['events'][0];
                $custId = $events['source']['userId'];
                $customer = '';
                /* ---------------------------------------------------------------------------------------------------- */
                /* ดึง profile ลูกค้า และสร้างข้อมูลลูกค้า หากยังไม่มีในฐานข้อมูล */
                $URL = "https://api.line.me/v2/bot/profile/$custId";
                $channelAccessTokens = PlatformAccessTokens::all();
                $find = false;
                foreach ($channelAccessTokens as $token) {
                    $response = Http::withHeaders([
                        'Authorization' => "Bearer " . $token['accessToken'],
                    ])->get($URL);
                    if ($response->status() === 200) {
                        $TOKEN = $token['accessToken'];
                        $TOKEN_DESCRIPTION = $token['description'];
                        $find = true;
                        $checkCustomer = Customers::query()->where('custId', $custId)->first();
                        if (!$checkCustomer) {
                            $res = $response->json();
                            $createCustomer = new Customers();
                            $createCustomer['custId'] = $custId;
                            $createCustomer['custName'] = $res['displayName'];
                            $createCustomer['avatar'] = $res['pictureUrl'];
                            $createCustomer['description'] = "ทักมาจากไลน์ " . $token['description'];
                            $createCustomer['platformRef'] = $token['id'];
                            $createCustomer->save();
                            $customer = $createCustomer;
                        } else $customer = $checkCustomer;
                        break;
                    } else Log::info("AccessToken ไม่ตรง lineController ตรง foeach" . $response->status());
                }
                if (!$find) throw new \Exception('ไม่เจอ access token ที่เข้ากันได้ line controller');
                /* ---------------------------------------------------------------------------------------------------- */
                /* ตรวจสอบว่า custId คนนี้มี rate ที่สถานะเป็น pending หรือ progress หรือไม่ ถ้าไม่ */
                $checkRates = Rates::query()->where('custId', $custId)->where('status', '!=', 'success')->first();
                if (!$checkRates) { // กรณีที่ไม่มี rate ที่สถานะเป็น pending หรือ progress
                    $notCreateCase = false;
                    // foreach ($EVENTS as $key => $E) { //ตรวจก่อนว่าลูกค้าส่งอะไรเข้ามา
                    //     if ($E['message']['type'] === 'sticker') {
                    //         $latestAcId = ActiveConversations::query()->where('custId', $custId)->orderBy('created_at', 'desc')->first();
                    //         $conversationRef = $latestAcId['id'];
                    //         $notCreateCase = true;
                    //     } else if ($E['message']['type'] === 'text') { // กรณีที่ลูกค้าส่งข้อความมาเป็น text
                    //         $target = $E['message']['text'];
                    //         $keywords = Keyword::where('name', 'like', "%$target%")->first();
                    //         if ($keywords && $keywords->event === true) { // ลูกค้าส่งข้อความเขาแล้วมี keyword ตรงตามที่เรากำหนด และ event สำหรับเคสที่จบแล้ว
                    //             $latestAcId = ActiveConversations::query()->where('custId', $custId)->orderBy('created_at', 'desc')->first();
                    //             $conversationRef = $latestAcId['id'];
                    //             $notCreateCase = true;
                    //         } else if ($keywords && $keywords->event === false) { // ลูกค้าส่งข้อความเข้าแล้วมี keyword ตรงตามที่เรากำหนด และ event คือส่งไปยังห้องต่างๆ
                    //             $latestAcId = ActiveConversations::query()->where('custId', $custId)->orderBy('created_at', 'desc')->first();
                    //             $getRate = Rates::query()->where('custId', $custId)->orderBy('id', 'desc')->first();
                    //             if ($getRate['created_at'] >= Carbon::now()->subHour(12)) { // ทักเข้ามาภายใน 12 ชั่วโมง
                    //                 $cRate = new Rates();
                    //                 $cRate['custId'] = $custId;
                    //                 $cRate['rate'] = 0;
                    //                 $cRate['status'] = 'pending';
                    //                 $cRate['latestRoomId'] = $keywords->redirectTo;
                    //                 $cRate->save();
                    //                 $cAC = new ActiveConversations();
                    //                 $cAC['custId'] = $custId;
                    //                 $cAC['roomId'] = $keywords->redirectTo;
                    //                 $cAC['rateRef'] = $cRate->id;
                    //                 $cAC->save();
                    //                 $conversationRef = $cAC['id'];
                    //                 $notCreateCase = true;
                    //             } else $notCreateCase = false; // ทักเข้ามาเกิน 12 ชั่วโมง
                    //         } else { // ลูกค้าส่งข้อความเขาแล้วไม่มี keyword ตรงตามที่เรากำหนด
                    //             $getRate = Rates::query()->where('custId', $custId)->orderBy('id', 'desc')->first();
                    //             if ($getRate['created_at'] >= Carbon::now()->subHour(12)) { //ทักเข้ามาภายใน 12 ชั่วโมง
                    //                 $latestAcId = ActiveConversations::query()->where('custId', $custId)->orderBy('id', 'desc')->first();
                    //                 $createAC = new ActiveConversations();
                    //                 $createAC['custId'] = $custId;
                    //                 $createAC['roomId'] = $latestAcId->roomId;
                    //                 $createAC['empCode'] = $latestAcId->empCode;
                    //                 $createAC['receiveAt'] = Carbon::now();
                    //                 $createAC['startTime'] = Carbon::now();
                    //                 $createAC['rateRef'] = $getRate['id'];
                    //                 $createAC->from_roomId = $latestAcId->from_roomId;
                    //                 $createAC->from_empCode = $latestAcId->from_empCode;
                    //                 $getRate['status'] = 'progress';
                    //                 DB::connection('call_center_database')
                    //                     ->table('rates')
                    //                     ->where('id', $getRate->id)
                    //                     ->update(['status' => 'progress']);
                    //                 $createAC->save();
                    //                 $conversationRef = $createAC['id'];
                    //                 $notCreateCase = true;
                    //             } else $notCreateCase = false; // ทักเข้ามาเกิน 12 ชั่วโมง
                    //         }
                    //     }
                    // }
                    // if (!$notCreateCase) {
                        $rate = new Rates();
                        $rate['custId'] = $custId;
                        $rate['rate'] = 0;
                        $rate['status'] = 'progress';
                        $rate['latestRoomId'] = $defalutRoom;
                        $rate->save();
                        $activeConversation = new ActiveConversations();
                        $activeConversation['custId'] = $custId;
                        $activeConversation['roomId'] = $defalutRoom;
                        $activeConversation['empCode'] = 'BOT';
                        $activeConversation['receiveAt'] = Carbon::now();
                        $activeConversation['startTime'] = Carbon::now();
                        $activeConversation['rateRef'] = $rate['id'];
                        $activeConversation->save();
                        $conversationRef = $activeConversation['id'];
                        // ส่งเมนูตัวเลือกให้ลูกค้าเลือก
                        $sendMenu = $this->lineService->sendMenu($custId, $TOKEN);
                        $SEND_MENU = true;
                        if (!$sendMenu['status']) throw new \Exception($sendMenu['message']);
                        else $checkSendMenu = true;
                    // }
                } else {
                    Log::info('not create case is true');
                    $rateRef = $checkRates['id'];
                    $latestRoomId = $checkRates['latestRoomId'];
                    $checkActiveConversation = ActiveConversations::query()->where('rateRef', $rateRef)
                        ->where('roomId', $latestRoomId)
                        ->where('endTime', null)
                        ->first();
                    if ($checkActiveConversation) {
                        $conversationRef = $checkActiveConversation['id'];
                        // ถ้าเช็คแล้วว่า มีการรับเรื่อง (receiveAt) แล้วยังไม่มี startTime ให้ startTime = carbon::now()
                        if (!empty($checkActiveConversation['receiveAt'])) {
                            if (empty($checkActiveConversation['startTime'])) $checkActiveConversation['startTime'] = carbon::now();
                            if ($checkActiveConversation->save()) $status = 200;
                            else throw new \Exception('เจอปัญหา startTime ไม่ได้');
                        }
                    } else throw new \Exception('ไม่พบ conversationRef จากตาราง ActiveConversations');
                }
                /* ---------------------------------------------------------------------------------------------------- */
                /* สร้าง chatHistory */
                foreach ($EVENTS as $key => $E) {
                    $messages['contentType'] = $E['message']['type'];
                    switch ($E['message']['type']) {
                        case 'text':
                            $messages['content'] = $E['message']['text'];
                            break;
                        case 'image':
                            $imageId = $E['message']['id'];
                            $messages['content'] = $this->lineService->handleMedia($imageId, $TOKEN);
                            break;
                        case 'sticker':
                            $stickerId = $E['message']['stickerId'];
                            $pathStart = 'https://stickershop.line-scdn.net/stickershop/v1/sticker/';
                            $pathEnd = '/iPhone/sticker.png';
                            $newPath = $pathStart . $stickerId . $pathEnd;
                            $messages['content'] = $newPath;
                            break;
                        case 'video':
                            $videoId = $E['message']['id'];
                            $messages['content'] = $this->lineService->handleMedia($videoId, $TOKEN);
                            break;
                        case 'location':
                            $messages['content'] = $E['message']['address'];
                            break;
                        case 'audio':
                            $audioId = $E['message']['id'];
                            $messages['content'] = $this->lineService->handleMedia($audioId, $TOKEN);
                            break;
                        default:
                            $messages['content'] = 'ไม่สามารถตรวจสอบได้ว่าลูกค้าส่งอะไรเข้ามา';
                    }
                    $chatHistory = new ChatHistory();
                    $chatHistory['custId'] = $custId;
                    $chatHistory['content'] = $messages['content'];
                    $chatHistory['contentType'] = $messages['contentType'];
                    $chatHistory['sender'] = json_encode($customer);
                    $chatHistory['conversationRef'] = $conversationRef;
                    $chatHistory->save();
                    // ส่ง pusher
                    $notification = $this->pusherService->newMessage($chatHistory, false, 'มีข้อความใหม่เข้ามา');
                    if (!$notification['status']) throw new \Exception($notification['message']);
                }
                // ถ้ามีการส่งเมนู Bot ให้ลูกค้า
                Log::info('tes sendmenu', ['SEND_MENU' => $SEND_MENU]);
                if ($SEND_MENU) {
                    $bot = Employee::where('empCode', 'BOT')->first();
                    $chatHistory = new ChatHistory();
                    $chatHistory['custId'] = $custId;
                    $chatHistory['content'] = "สวัสดีคุณ " . $customer['custName'] . " เพื่อให้การบริการของเราดำเนินไปอย่างรวดเร็วและสะดวกยิ่งขึ้น กรุณาเลือกหัวข้อด้านล่าง เพื่อให้เจ้าหน้าที่สามารถให้ข้อมูลและบริการท่านได้อย่างถูกต้องและรวดเร็ว ขอบคุณค่ะ/ครับ";
                    $chatHistory['contentType'] = 'text';
                    $chatHistory['sender'] = json_encode($bot);
                    $chatHistory['conversationRef'] = $conversationRef;
                    $chatHistory->save();
                    $chatHistory = new ChatHistory();
                    $chatHistory['custId'] = $custId;
                    $chatHistory['content'] = 'เมนูของบอทแสดง';
                    $chatHistory['contentType'] = 'text';
                    $chatHistory['sender'] = json_encode($bot);
                    $chatHistory['conversationRef'] = $conversationRef;
                    $chatHistory->save();
                    // ส่ง pusher
                    $notification = $this->pusherService->newMessage($chatHistory, false, 'มีข้อความใหม่เข้ามา');
                    if (!$notification['status']) throw new \Exception($notification['message']);
                }
                // กรองการส่งต่อถ้า rate ยังอยู่ในห้อง Bot
                $R = $rate ?? $checkRates;
                if ($R['latestRoomId'] === 'ROOM00') {
                    if (!$checkSendMenu) {
                        $change = $this->lineService->handleChangeRoom($chatHistory['content'], $R, $TOKEN, $TOKEN_DESCRIPTION);
                        if ($change['status']) {
                            $bot = Employee::where('empCode', 'BOT')->first();
                            $chatHistory = new ChatHistory();
                            $chatHistory['custId'] = $custId;
                            $chatHistory['content'] = 'ระบบกำลังส่งต่อให้เจ้าหน้าที่ที่รับผิดชอบเพื่อเร่งดำเนินการเข้ามาสนทนา กรุณารอสักครู่';
                            $chatHistory['contentType'] = 'text';
                            $chatHistory['sender'] = json_encode($bot);
                            $chatHistory['conversationRef'] = $conversationRef;
                            $chatHistory->save();
                            $notification = $this->pusherService->newMessage($chatHistory, false, 'มีข้อความใหม่เข้ามา');
                            if (!$notification['status']) throw new \Exception($notification['message']);
                        } else throw new \Exception($change['message']);
                    } else Log::info('$checkSendMenu is true');
                } elseif (($R['latestRoomId'] !== 'ROOM00') && ($R['status'] === 'pending')) {
                    $queueChat = DB::connection('call_center_database')
                        ->table('active_conversations')
                        ->leftJoin('rates', 'active_conversations.rateRef', '=', 'rates.id')
                        ->where('active_conversations.roomId', $R['latestRoomId'])
                        ->where('rates.status', '=', 'pending') // เงื่อนไข where สำหรับ rates.status
                        ->orderBy('active_conversations.created_at', 'asc')
                        ->get();
                    $countProgress = DB::connection('call_center_database')
                        ->table('rates')
                        ->select('id')
                        ->where('status', 'progress')
                        ->where('latestRoomId', $R['latestRoomId'])
                        ->count();
                    $count = $countProgress + 1;
                    foreach ($queueChat as $key => $value) {
                        if ($value->custId === $custId) break;
                        else $count++;
                    }
                    $body = [
                        'to' => $custId,
                        'messages' => [[
                            'type' => 'text',
                            'text' => 'คิวของท่านคือ ' . $count . ' คิว กรุณารอสักครู่'
                        ]]
                    ];
                    $newChat = new ChatHistory();
                    $newChat['custId'] = $custId;
                    $newChat['content'] = 'คิวของท่านคือ ' . $count . ' คิว กรุณารอสักครู่';
                    $newChat['contentType'] = 'text';
                    $bot = DB::connection('call_center_database')->table('users')->where('empCode', 'BOT')->first();
                    $newChat['sender'] = json_encode($bot);
                    $newChat['conversationRef'] = $conversationRef;
                    $newChat->save();
                    $sendLine = $this->lineService->linePushMessage($TOKEN, $body);
                    if (!$sendLine['status']) throw new \Exception('error');
                }
                /* ---------------------------------------------------------------------------------------------------- */
                $message = 'มีข้อความใหม่เข้ามา';
                $detail = 'ไม่มีข้อผิดพลาด';
                $status = 200;
            }
            DB::commit();
        } catch (\Exception $e) {
            if ($e->getMessage() === 'event not data') {
                Log::info('test not event');
                $message = $e->getMessage();
                $detail = 'ไม่มีข้อผิดพลาด';
                $status = 200;
            } else {
                $message = 'เกิดข้อผิดพลาดในการรับข้อความ';
                $detail = $e->getMessage();
            }
            Log::info($e->getMessage());
            DB::rollBack();
        } finally {
            return response()->json([
                'message' => $message ?? 'เกิดข้อผิดพลาด',
                'detail' => $detail ?? 'ไม่พบรายละเอียด',
            ], $status);
        }
    }
}
