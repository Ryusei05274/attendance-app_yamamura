<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminAttendanceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => '日付を指定してください',
            'date.date_format' => '日付は YYYY-MM-DD 形式で指定してください',
        ];
    }
}
