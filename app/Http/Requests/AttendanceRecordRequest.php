<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:clock_in,clock_out,break_in,break_out'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => '打刻内容を選択してください',
            'action.in' => '不正な打刻操作です',
        ];
    }
}
