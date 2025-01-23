<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Customers;
use App\Models\PlatformAccessTokens;
use App\Models\Rates;
use App\Services\CustomerService;
use App\Services\RateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class testController extends Controller
{
    protected RateService $rateService;
    protected CustomerService $customerService;
    public function __construct(RateService $rateService, CustomerService $customerService)
    {
        $this->rateService = $rateService;
        $this->customerService = $customerService;
    }

    public function test(Request $request)
    {
        $customer = [];
        $events = $request->events;
        $message = '';
        $status = 200;
        $TOKEN = '';
        Log::channel('lineEvent')->info($request);
        try {

            //ตรวจสอบว่า events type เป็นอะไร
            if (count($events) > 0){
                foreach ($events as $event){
                    if($event['type'] === 'postback'){
                        $postbackData = $event['postback']['data'];
                        $dataParts = explode(',', $postbackData);
                        $feedback = $dataParts[0] ?? null;
                        $rateIdPostback = $dataParts[1] ?? null;
                        $this->rateService->updateFeedback($rateIdPostback, $feedback);
                        return response()->json([
                            'message' => 'update feedback success',
                        ]);
                    }elseif ($event['type'] === 'message'){
                        $customer = Customers::where('custId',$event['source']['userId'])->first();
                        if(!$customer) { // ตรวจสอบว่า เคยบันทึกข้อมูลลูกค้าคนนี้หรือไม่ ถ้าไม่
                            $tokens = PlatformAccessTokens::all();
                            foreach ($tokens as $token) {
                                $response = Http::withHeaders([
                                    'Authorization' => 'Bearer ' . $token->accessToken,
                                ])->get('https://api.line.me/v2/bot/profile/' . $event['source']['userId']);
                                Log::channel('lineEvent')->info($response);
                                if ($response->status() === 200){
                                    $customer = $this->customerService->store(
                                        $event['source']['userId'],
                                        $response['displayName'],
                                        "ทักมากไลน์ $token->descrtiption",
                                        $response['pictureUrl'],
                                        $token->id
                                    );
                                    $TOKEN = $token->accessToken;
                                    break;
                                }else Log::info('token นี้ไม่พบข้อมูลลูกค้า');
                            }
                            !$customer ?? throw new \Exception('ไม่พบข้อมูลลูกค้า');
                        }else{
                            $TOKEN = PlatformAccessTokens::where('id',$customer->platformRef)->first()->accessToken;
                        }
                        // เช็คว่ามี rates เป็นสถานะ success หรือไม่
                        $RATE = Rates::where('custId',$customer->custId)->orderBy('id','desc')->first();
                        if ($RATE['status'] === 'success'){
                            $message = $event['message'];
                            if ($message['type'] === 'sticker'){
                                // ทำต่อตรงนี้
                            }
                        }elseif(($RATE['status'] === 'pending') || ($RATE['status'] === 'progress')){

                        }else{

                        }
                    }else{
                        throw new \Exception('events ไม่ทราบ type');
                    }
                }
            }else{
                throw new \Exception('events is empty');
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ]);
        }
    }
}
