<?php
namespace App\Services;
class ResponseService{
    public function Res($status, $message, $detail) : array{
        $data['status'] = $status;
        $data['message'] = $message;
        $data['detail'] = $detail;
        return $data;
    }
}
