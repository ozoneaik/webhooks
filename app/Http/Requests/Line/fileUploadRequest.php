<?php

namespace App\Http\Requests\Line;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class fileUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
//    public function authorize(): bool
//    {
//        return false;
//    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:jpeg,jpg,png,gif'
        ];
    }

    public function messages(): array{
        return [
            'file' => 'pls upload File'
        ];
    }
}
