<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class fileUploadController extends Controller
{
    public function fileUpload(Request $request): JsonResponse
    {
        Log::info(request()->all());
        $request->validate(['file' => 'required|file|mimes:jpeg,jpg,png,gif,mp4,mkv,mov,pdf'], ['file.required' => 'กรุณาอัปโหลดไฟล์']);
        // รับไฟล์จาก request
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();

        // สร้าง path สำหรับการบันทึกรูปภาพ
        $imagePath = 'line-images/' . time() . '.' . $extension;

        // บันทึกไฟล์ใน disk 'public'
        Storage::disk('public')->put($imagePath, file_get_contents($file));

        // สร้าง URL สำหรับการเข้าถึงรูปภาพ
        $fullImagePath = env('IMAGE_URL') . '/' . $imagePath;

        // ส่งข้อมูลกลับในรูปแบบ JSON
        return response()->json([
            'fileName' => $fileName,
            'extension' => $extension,
            'imagePath' => $fullImagePath
        ]);
    }

}
