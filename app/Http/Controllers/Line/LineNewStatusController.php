<?php

namespace App\Http\Controllers\Line;

use App\Http\Controllers\Controller;
use App\Models\ActiveConversations;
use App\Models\Rates;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LineNewStatusController extends Controller
{
    public function store($custId,$message){
        try {
            DB::beginTransaction();
            if ($message['contentType'] !== 'text'){
                // สร้าง rate ใหม่
                $rate = new Rates();
                $rate['cust_id'] = $custId;
                $rate['status'] = 'progress';
                $rate['latestRoomId'] = 'ROOM00';
                $rate->save();
                // สร้าง active ใหม่
                $active = new ActiveConversations();
                $active['cust_id'] = $custId;
                $active['roomId'] = 'ROOM00';
                $active['receiveAt'] = Carbon::now();
                $active['startTime'] = Carbon::now();
                $active['empCode'] = 'BOT';
                $active->save();
            }else{

            }
            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
        }
        return $custId;
    }
}
