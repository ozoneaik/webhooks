<?php

namespace App\Http\Controllers;

use App\Http\Requests\Line\fileUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class fileUploadController extends Controller
{
    public function fileUpload(fileUploadRequest $request) : JsonResponse{
        $file =  $request->file('file');
        $fileName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $imagePath = 'line-images/'.time().'.'.$extension;
        Storage::disk('public')->putFileAs('line-images', $file, time().'.'.$extension);


        // สร้างชื่อไฟล์สำหรับไฟล์ preview
        $previewImagePath = 'line-images/preview_' . time() . '.' . $extension;
        $fullImagePath = env('IMAGE_URL').'/storage/'. $imagePath;
        return response()->json([
            'fileName' => $fileName,
            'extension' => $extension,
            'imagePath' => $fullImagePath
        ]);
    }
}
