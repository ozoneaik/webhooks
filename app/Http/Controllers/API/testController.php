<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ActiveConversations;
use App\Models\botMenu;
use App\Models\ChatHistory;
use App\Models\Customers;
use App\Models\Employee;
use App\Models\Keyword;
use App\Models\PlatformAccessTokens;
use App\Models\Rates;
use App\Services\ActiveConversationService;
use App\Services\ChatHistoryService;
use App\Services\CustomerService;
use App\Services\LineService;
use App\Services\PusherService;
use App\Services\RateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class testController extends Controller
{
    protected RateService $rateService;
    protected CustomerService $customerService;
    protected LineService $lineService;
    protected ChatHistoryService $chatHistoryService;
    protected ActiveConversationService $activeConversationService;
    protected PusherService $pusherService;

    public function __construct(
        RateService               $rateService,
        CustomerService           $customerService,
        LineService               $lineService,
        ChatHistoryService        $chatHistoryService,
        ActiveConversationService $activeConversationService,
        PusherService             $pusherService
    )
    {
        $this->rateService = $rateService;
        $this->customerService = $customerService;
        $this->lineService = $lineService;
        $this->chatHistoryService = $chatHistoryService;
        $this->activeConversationService = $activeConversationService;
        $this->pusherService = $pusherService;
    }

    public function test(Request $request)
    {
        $customer = [];
        $events = $request->events;
        $TOKEN = '';
        try {

            //ตรวจสอบว่า events type เป็นอะไร
            if (count($events) > 0) {
                foreach ($events as $event) {
                    if ($event['type'] === 'postback') {
                        $postbackData = $event['postback']['data'];
                        $dataParts = explode(',', $postbackData);
                        $feedback = $dataParts[0] ?? null;
                        $rateIdPostback = $dataParts[1] ?? null;
                        $this->rateService->updateFeedback($rateIdPostback, $feedback);
                        return response()->json([
                            'message' => 'update feedback success',
                        ]);
                    } elseif ($event['type'] === 'message') {
                        $customer = Customers::where('custId', $event['source']['userId'])->first();
                        if (!$customer) { // ตรวจสอบว่า เคยบันทึกข้อมูลลูกค้าคนนี้หรือไม่ ถ้าไม่
                            $tokens = PlatformAccessTokens::all();
                            foreach ($tokens as $token) {
                                $response = Http::withHeaders([
                                    'Authorization' => 'Bearer ' . $token->accessToken,
                                ])->get('https://api.line.me/v2/bot/profile/' . $event['source']['userId']);
                                if ($response->status() === 200) {
                                    $customer = $this->customerService->store(
                                        $event['source']['userId'],
                                        $response['displayName'],
                                        "ทักมากไลน์ $token->description",
                                        $response['pictureUrl'] ?? ' ',
                                        $token->id
                                    );
                                    $TOKEN = $token->accessToken;
                                    break;
                                } else Log::info('token นี้ไม่พบข้อมูลลูกค้า');
                            }
                                !$customer ?? throw new \Exception('ไม่พบข้อมูลลูกค้า');
                        } else {
                            $TOKEN = PlatformAccessTokens::query()->where('id', $customer->platformRef)->first()->accessToken;
                        }
                        // เช็คว่ามี rates เป็นสถานะ success หรือไม่
                        $RATE = Rates::where('custId', $customer->custId)->orderBy('id', 'desc')->first();
                        $message = $event['message'];
                        $keyword = [];
                        if ($RATE) {
                            if ($RATE['status'] === 'success') {
                                if ($message['type'] === 'sticker') { // หากข้อความที่ทักเข้ามาเป็น sticker
                                    // ให้เก้บแค่ chat พร้อมอ้างอิง AcId ก่อนหน้า
                                    $acId = ActiveConversations::where('rateRef', $RATE->id)->orderBy('id', 'desc')->first();
                                    $this->chatHistoryService->store($customer->custId, $message, $customer->toJson(), $acId->id, $TOKEN);
                                } elseif ($message['type'] === 'text') { // หากข้อความที่ทักเข้ามาเป็น text
                                    $keyword = Keyword::where('name', 'LIKE', '%' . $message['text'] . '%')->first();
                                    if ($keyword) {
                                        if ($keyword->event === true) {
                                            // ให้เก็บแค่ chat พร้อมอ้างอิง AcId ก่อนหน้า
                                            $acId = ActiveConversations::where('rateRef', $RATE->id)->orderBy('id', 'desc')->first();
                                            $this->chatHistoryService->store($customer->custId, $message, $customer->toJson(), $acId->id, $TOKEN);
                                        } else {
                                            // สร้าง Rate ใหม่ โดย อ้างอิงจาก roomId
                                            $newRate = Rates::query()->create([
                                                'custId' => $customer->custId,
                                                'rate' => 0,
                                                'status' => 'pending',
                                                'latestRoomId' => $keyword->redirectTo
                                            ]);
                                            $newAc = ActiveConversations::query()->create([
                                                'custId' => $customer->custId,
                                                'roomId' => $keyword->redirectTo,
                                                'rateRef' => $newRate->id
                                            ]);
                                            // สร้าง chat
                                            $this->chatHistoryService->store($customer->custId, $message, $customer->toJson(), $newAc->id, $TOKEN);
                                        }
                                    } else {
                                        // สร้าง Rate ใหม่ โดย copy จาก Rate ก่อนหน้า แล้ว สร้าง Ac ใหม่โดย copy จาก Ac ก่อนหน้า
                                        $newRate = Rates::query()->create([
                                            'custId' => $customer->custId,
                                            'rate' => 0,
                                            'latestRoomId' => $RATE->latestRoomId,
                                            'status' => 'pending'
                                        ]);
                                        $newAc = ActiveConversations::query()->create([
                                            'custId' => $customer->custId,
                                            'roomId' => $RATE->latestRoomId,
                                            'rateRef' => $newRate->id
                                        ]);
                                        // สร้าง chat
                                        $this->chatHistoryService->store($customer->custId, $message, $customer->toJson(), $newAc->id, $TOKEN);

                                    }
                                } else {
                                    // สร้าง Rate ใหม่ โดย copy จาก Rate ก่อนหน้า แล้ว สร้าง Ac ใหม่โดย copy จาก Ac ก่อนหน้า
                                    $newRate = Rates::query()->create([
                                        'custId' => $customer->custId,
                                        'latestRoomId' => $RATE->latestRoomId,
                                        'status' => 'pending'
                                    ]);
                                    $newAc = ActiveConversations::query()->create([
                                        'custId' => $customer->custId,
                                        'roomId' => $newRate->latestRoomId,
                                        'rateRef' => $newRate->id
                                    ]);
                                    // สร้าง chat
                                    $this->chatHistoryService->store($customer->custId, $message, $customer->toJson(), $newAc->id, $TOKEN);
                                }


                            } else {
                                if ($RATE['status'] === 'pending') {
                                    $acId = ActiveConversations::query()->where('rateRef', $RATE->id)->orderBy('id', 'desc')->first();
                                    $this->chatHistoryService->store($customer->custId, $message, $customer->toJson(), $acId->id, $TOKEN);
                                    $queueChat = DB::connection('call_center_database')
                                        ->table('active_conversations')
                                        ->leftJoin('rates', 'active_conversations.rateRef', '=', 'rates.id')
                                        ->where('active_conversations.roomId', $RATE->latestRoomId)
                                        ->where('rates.status', '=', 'pending') // เงื่อนไข where สำหรับ rates.status
                                        ->orderBy('active_conversations.created_at', 'asc')
                                        ->get();
                                    $countProgress = DB::connection('call_center_database')
                                        ->table('rates')
                                        ->select('id')
                                        ->where('status', 'progress')
                                        ->where('latestRoomId', $RATE->latestRoomId)
                                        ->count();
                                    $count = $countProgress + 1;
                                    foreach ($queueChat as $key => $value) {
                                        if ($value->custId === $customer->custId) break;
                                        else $count++;
                                    }
                                    $body = [
                                        'to' => $customer->custId,
                                        'messages' => [[
                                            'type' => 'text',
                                            'text' => 'คิวของท่านคือ ' . $count . ' คิว กรุณารอสักครู่'
                                        ]]
                                    ];
                                    $Bot = Employee::query()->where('empCode', 'BOT')->first();
                                    $botSendMenuContent['type'] = 'text';
                                    $botSendMenuContent['text'] = 'คิวของท่านคือ ' . $count . ' คิว กรุณารอสักครู่';
                                    $this->chatHistoryService->store($customer->custId, $botSendMenuContent, $Bot->toJson(), $acId->id, $TOKEN);
                                    $this->lineService->linePushMessage($TOKEN, $body);
                                } else {
                                    $matchMenu = false;
                                    if ($RATE->latestRoomId === 'ROOM00') {
                                        // อัพ AC ตรง endTime และ TotalTime
                                        $oldAc = $this->activeConversationService->updateEndTime($RATE, $TOKEN);
                                        $platform = PlatformAccessTokens::query()->where('id', $customer->platformRef)->first();
                                        $botMenu = botMenu::where('botTokenId', $platform->id)->get();
                                        if ($message['type'] === 'text') {
                                            foreach ($botMenu as $key => $menu) {
                                                if ($menu->menuName === $message['text']) {
                                                    $RATE->latestRoomId = $menu->roomId;
                                                    $RATE->status = 'pending';
                                                    ActiveConversations::query()->create([
                                                        'custId' => $customer->custId,
                                                        'from_empCode' => 'BOT',
                                                        'from_roomId' => 'ROOM00',
                                                        'roomId' => $menu->roomId,
                                                        'rateRef' => $RATE->id
                                                    ]);
                                                    $this->chatHistoryService->store($customer->custId, $message, $customer->toJson(), $oldAc->id, $TOKEN);
                                                    $RATE->save();
                                                    $matchMenu = true;
                                                    break;
                                                }
                                            }
                                        }
                                        if ($matchMenu === false) {
                                            if ($platform->description === 'ศูนย์ซ่อม Pumpkin') {
                                                $RATE->latestRoomId = 'ROOM02';
                                            } elseif ($platform->description === 'pumpkintools') {
                                                $RATE->latestRoomId = 'ROOM06';
                                            } else {
                                                $RATE->latestRoomId = 'ROOM01';
                                            }
                                            $RATE->status = 'pending';
                                            $RATE->save();
                                            $newAc = ActiveConversations::query()->create([
                                                'custId' => $customer->custId,
                                                'from_empCode' => 'BOT',
                                                'from_roomId' => 'ROOM00',
                                                'roomId' => $RATE->latestRoomId,
                                                'rateRef' => $RATE->id
                                            ]);
                                            $this->chatHistoryService->store($customer->custId, $message, $customer->toJson(), $oldAc->id, $TOKEN);
                                        }
                                        $bot = Employee::query()->where('empCode', 'BOT')->first();
                                        $botSendMenuContent['type'] = 'text';
                                        $botSendMenuContent['text'] = 'ระบบกำลังส่งต่อให้เจ้าหน้าที่ที่รับผิดชอบเพื่อเร่งดำเนินการเข้ามาสนทนา กรุณารอสักครู่';
                                        $this->chatHistoryService->store($customer->custId, $botSendMenuContent, $bot->toJson(), $oldAc->id, $TOKEN);
                                    } else {
                                        $acId = ActiveConversations::query()->where('rateRef', $RATE->id)->orderBy('id', 'desc')->first();
                                        $this->chatHistoryService->store($customer->custId, $message, $customer->toJson(), $acId->id, $TOKEN);
                                    }
                                }


                            }
                        } else {
                            $bot = Employee::query()->where('empCode', 'BOT')->first();
                            // สร้าง Rate ใหม่ พร้อมส่งเมนู บอท
                            $RATE = Rates::query()->create([
                                'custId' => $customer->custId,
                                'status' => 'progress',
                                'rate' => 0,
                                'latestRoomId' => 'ROOM00'
                            ]);
                            $newAc = ActiveConversations::query()->create([
                                'custId' => $customer->custId,
                                'receiveAt' => Carbon::now(),
                                'startTime' => Carbon::now(),
                                'empCode' => 'BOT',
                                'roomId' => 'ROOM00',
                                'rateRef' => $RATE->id
                            ]);
                            $this->chatHistoryService->store($customer->custId, $message, $customer->toJson(), $newAc->id, $TOKEN);
                            $botSendMenuContent['type'] = 'text';
                            $text = "สวัสดีคุณ $customer->custName เพื่อให้การบริการที่รวดเร็ว กรุณาเลือกหัวด้านล่างเพื่อส่งต่อให้เจ้าหน้าที่เพื่อมาบริการท่านต่อไป  ขอบคุณครับ/ค่ะ
BOT ทำการส่งเมนู 📃";
                            $botSendMenuContent['text'] = $text;
                            $this->chatHistoryService->store($customer->custId, $botSendMenuContent, $bot->toJson(), $newAc->id, $TOKEN);
                            $this->lineService->sendMenu($customer->custId, $TOKEN);
                        }
                        // ส่ง event ไปยัง pusher
                        $ac = ActiveConversations::query()->where('rateRef', $RATE->id)->orderBy('id', 'desc')->first();
                        $chat = ChatHistory::query()
                            ->select(['id','content', 'contentType', 'sender', 'created_at'])
                            ->where('custId', $customer->custId)
                            ->orderBy('id', 'desc')->first();
                        $chat->sender = json_decode($chat->sender);
                        $this->pusherService->sendNotification($RATE, $ac, $chat, $customer);

                    } else {
                        throw new \Exception('events ไม่ทราบ type');
                    }
                } // สิ้นสุด loop events
            } else {
                throw new \Exception('events is empty');
            }
            return response()->json([
                'message' => 'success',
            ]);
        } catch (\Exception $e) {
            Log::channel('lineEvent')->info(sprintf(
                'Error: %s in %s on line %d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
            return response()->json([
                'message' => $e->getMessage(),
            ]);
        }
    }
}
