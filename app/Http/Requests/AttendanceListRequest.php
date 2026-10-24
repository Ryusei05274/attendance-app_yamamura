<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'nullable', 'date_format:Y-m'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format' => '年月は YYYY-MM 形式で指定してください',
        ];
    }
}
