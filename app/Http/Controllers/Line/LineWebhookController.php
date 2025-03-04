<?php

namespace App\Http\Controllers\Line;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineWebhookController extends Controller
{
    protected LineCustomerController $LineCustomerController;
    protected LineRateController $LineRateController;
    protected LinePendingController $LinePendingController;
    protected LineProgressController $LineProgressController;
    protected LineSuccessController $LineSuccessController;
    protected LineNewStatusController $LineNewStatusController;
    protected LineContentController $LineContentController;

    public function __construct(
        LineCustomerController  $LineCustomerController,
        LineRateController      $LineRateController,
        LinePendingController   $LinePendingController,
        LineProgressController  $LineProgressController,
        LineSuccessController   $LineSuccessController,
        LineNewStatusController $LineNewStatusController,
        LineContentController   $LineContentController
    )
    {
        $this->LineCustomerController = $LineCustomerController;
        $this->LineRateController = $LineRateController;
        $this->LinePendingController = $LinePendingController;
        $this->LineProgressController = $LineProgressController;
        $this->LineSuccessController = $LineSuccessController;
        $this->LineNewStatusController = $LineNewStatusController;
        $this->LineContentController = $LineContentController;
    }

    public function webhook(Request $request): JsonResponse
    {
        try {
            $events = $request['events'];
            if (count($events) > 0) { // เจอ Event และต้องเป็น event type === message
                foreach ($events as $event) {
                    if ($event['type'] !== 'message') {
                        throw new \Exception('event นี้ไม่ใช่ message');
                    }
                    Log::channel('lineEvent')->info($event);
                    /** ***************************************** หาลูกค้าในฐานข้อมูล ***************************************************** **/
                    $custId = $event['source']['userId'];
                    $findCustomer = $this->LineCustomerController->find($custId);
                    if ($findCustomer) {
                        /** เจอลูกค้าในฐานข้อมูล **/
                        $customer = $findCustomer->toArray();
                        $token = $customer['accessToken'];
                    } else {
                        /** ไม่เจอลูกค้าในฐานข้อมูล **/
                        /** ทำการสร้างลูกค้าใหม่โดยการวน token ไลน์เพื่อดึง profile **/
                        $new_customer = $this->LineCustomerController->store($custId)->toArray();
                        if ($new_customer) {
                            $customer = $new_customer;
                            $token = $new_customer['accessToken'];
                        } else {
                            throw new \Exception('ทำการหาหรือดึงข้อมูลลูกค้าจากทุก method แล้วไม่พบ');
                        }
                    }
                    /** ************************************************************************************************************** **/

                    /** ********************************* เก็บ content เอาไว้ก่อน ********************************* **/
                    $message = $this->LineContentController->getMessage($event['message'], $token);
                    /** ************************************************************************************************************** **/

                    /** ********************************* ตรวจสอบว่า rate->status === success หรือไม่ ********************************* **/
                    $Rate = $this->LineRateController->check($custId);
                    if ($Rate) {
                        if ($Rate->status === 'success') {
                            Log::channel('lineEvent')->info('เจอ Rate ที่เป็น Success');
                        } else if ($Rate->status === 'pending') {
                            Log::channel('lineEvent')->info('เจอ Rate ที่เป็น Pending');
                        } else {
                            Log::channel('lineEvent')->info('เจอ Rate ที่เป็น Progress');
                        }
                    } else {
                        Log::channel('lineEvent')->info('ไม่พบ Rate ใน Database');
                        $newRate = $this->LineNewStatusController->store($custId,$message);
                    }
                }
            } else { // ไม่เจอ Event
                throw new \Exception('ไม่พบ Event ใดๆ');
            }
        } catch (\Exception $exception) {
            Log::channel('lineEvent')->error(sprintf(
                "[Error] %s in %s on line %d",
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
        } finally {
            return response()->json([
                'message' => 'webhook received'
            ]);
        }
    }
}
