<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
    // ตรวจสอบว่า user มีสิทธิ์เข้าถึงห้องแชทนี้
    return $user->rooms->contains($roomId);
});
