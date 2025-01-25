<?php
namespace App\Services;

use App\Models\Customers;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Pusher\PusherException;

class PusherService{

    protected ResponseService  $response;

    public function newMessage($message,$emp = false,$title) : array{

        Log::info('Message In Pusher');
        try {


            $AppCluster = env('PUSHER_APP_CLUSTER');
            $AppKey = env('PUSHER_APP_KEY');
            $AppSecret = env('PUSHER_APP_SECRET');
            $AppID = env('PUSHER_APP_ID');

            if (!empty($message)){
                $customer = Customers::query()->where('custId',$message['custId'])->first();
                $message['custName'] = $customer['custName'];
                $message['avatar'] = $customer['avatar'];
                $message['empSend'] = $emp;
                $message['title'] = $title;
                $options = ['cluster' => $AppCluster, 'useTLS' => true];
                $pusher = new Pusher($AppKey, $AppSecret, $AppID, $options);
                if ($pusher) {
                    Log::info('Pusher Connected');
                }else{
                    Log::error('Pusher Not Connected');
                }
                $pusher->trigger('notifications', 'my-event', $message);
                Log::info($message);

            }else{
                $message['title'] = $title;
            }
            // $data = $this->response->Res(true,'การแจ้งเตือนสำเร็จ','ไม่พบข้อผิดพลาด');
            $data['status'] = true;
            $data['message'] = 'test';
        }catch (\Exception|GuzzleException $e){
            // $data = $this->response->Res(false,'การแจ้งเตือนผิดพลาด',$e->getMessage());
            $data['status'] = false;
            Log::error('เกิดข้อผิดพลาด pusher ใน method newMessage ใน PusherService');
            $data['message'] = $e->getMessage();

        }
        return $data;
    }

    public function sendNotification ($Rate, $activeConversation, $message,$customer): void
    {
        $Json['Rate'] = $Rate;
        $Json['activeConversation'] = $activeConversation;
        $Json['message'] = $message;
        $Json['customer'] = $customer;
        $AppCluster = env('PUSHER_APP_CLUSTER');
        $AppKey = env('PUSHER_APP_KEY');
        $AppSecret = env('PUSHER_APP_SECRET');
        $AppID = env('PUSHER_APP_ID');
        $options = ['cluster' => $AppCluster, 'useTLS' => true];
        try {
            $pusher = new Pusher($AppKey, $AppSecret, $AppID, $options);
            $pusher->trigger('notifications', 'my-event', $Json);
        } catch (PusherException|GuzzleException $e) {
            Log::error('Pusher Error');
            Log::channel('lineEvent')->error(sprintf(
                'Error: %s in %s on line %d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }
    }

}
