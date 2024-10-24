<?php

namespace App\Http\Controllers;

use App\Http\Requests\Line\fileUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class fileUploadController extends Controller
{
    public function fileUpload(fileUploadRequest $request) : JsonResponse
    {
        // รับไฟล์จาก request
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();

        // สร้าง path สำหรับการบันทึกรูปภาพ
        $imagePath = 'line-images/' . time() . '.' . $extension;

        // บันทึกไฟล์ใน disk 'public'
        Storage::disk('public')->put($imagePath, file_get_contents($file));

        // สร้าง URL สำหรับการเข้าถึงรูปภาพ
        $fullImagePath = env('IMAGE_URL') . '/storage/' . $imagePath;

        // ส่งข้อมูลกลับในรูปแบบ JSON
        return response()->json([
            'fileName' => $fileName,
            'extension' => $extension,
            'imagePath' => $fullImagePath
        ]);
    }

}
