<?php
namespace App\Services;

use App\Models\Customers;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

class PusherService{

    protected ResponseService  $response;

    public function newMessage($message,$emp = false,$title) : array{
        Log::info('Message In Pusher');
        Log::info($message['custId']);
        try {
            $AppCluster = env('PUSHER_APP_CLUSTER');
            $AppKey = env('PUSHER_APP_KEY');
            $AppSecret = env('PUSHER_APP_SECRET');
            $AppID = env('PUSHER_APP_ID');

            if (!empty($message)){
                $customer = Customers::query()->where('custId',$message['custId'])->first();
                Log::info($customer);
                $message['custName'] = $customer['custName'];
                $message['avatar'] = $customer['avatar'];
                $message['empSend'] = $emp;
                $message['title'] = $title;
                $options = ['cluster' => $AppCluster, 'useTLS' => true];
                $pusher = new Pusher($AppKey, $AppSecret, $AppID, $options);
                $pusher->trigger('notifications', 'my-event', $message);
                Log::info($message);
            }else{
                $message['title'] = $title;
            }
            // $data = $this->response->Res(true,'การแจ้งเตือนสำเร็จ','ไม่พบข้อผิดพลาด');
            $data['status'] = true;
        }catch (\Exception|GuzzleException $e){
            // $data = $this->response->Res(false,'การแจ้งเตือนผิดพลาด',$e->getMessage());
            $data['status'] = false;
        }
        return $data;
    }

}
