<?php

namespace App\Http\Controllers\Line;

use App\Http\Controllers\Controller;
use App\Models\Customers;
use App\Models\PlatformAccessTokens;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineCustomerController extends Controller
{
    public function find($custId)
    {
        $customer = Customers::query()->where('custId', $custId)->first();
        if ($customer) {
            $token = PlatformAccessTokens::query()
                ->select('accessToken')
                ->where('id', $customer->platformRef)
                ->first();
            $customer->accessToken = $token->accessToken;
        }
        return $customer ? $customer : null;
    }

    public function store($custId)
    {
        $customer = null;
        $platFormAccessToken = PlatformAccessTokens::query()
            ->select('accessToken','id','description')
            ->get();
        foreach ($platFormAccessToken as $key=>$accessToken) {
            try {
                DB::beginTransaction();
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken->accessToken,
                ])->get("https://api.line.me/v2/bot/profile/$custId");
                Log::channel('lineEvent')->info("วนหาข้อมูลลูกค้ารอบที่ ".$key+1);
                if ($response->successful()) {

                    Log::channel('lineEvent')->info("เจอข้อมูลลูกค้าจากการวนหาแล้ว");
                    $resJson = $response->json();
                    $customer = Customers::query()->create([
                        'custId' => $custId,
                        'custName' => $resJson['displayName'],
                        'avatar' => $resJson['pictureUrl'] ?? null,
                        'description' => 'ติดต่อมาจาก '.$accessToken['description'],
                        'platformRef' => $accessToken['id'],
                    ]);
                    $customer->accessToken = $accessToken['accessToken'];
                    DB::commit();
                    return $customer;
                }
            } catch (ConnectionException $e) {
                DB::rollBack();
                Log::channel('lineEvent')->error($e->getMessage());
            }
        }
        return null;

    }
}
