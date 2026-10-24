<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i'],
            'new_break_in' => ['sometimes', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i'],
            'new_break_out' => ['sometimes', 'array'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],
            'comment' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間を入力してください',
            'new_clock_in.date_format' => '出勤時間の形式が正しくありません',
            'new_clock_out.required' => '退勤時間を入力してください',
            'new_clock_out.date_format' => '退勤時間の形式が正しくありません',
            'new_break_in.*.date_format' => '休憩時間が不適切な値です',
            'new_break_out.*.date_format' => '休憩時間が不適切な値です',
            'comment.required' => '備考を記入してください',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $clockIn = Carbon::createFromFormat('H:i', $this->input('new_clock_in'));
            $clockOut = Carbon::createFromFormat('H:i', $this->input('new_clock_out'));
            if ($clockOut->lessThanOrEqualTo($clockIn)) {
                $validator->errors()->add('new_clock_out', '出勤時間もしくは退勤時間が不適切な値です');

                return;
            }

            $breaks = [];
            foreach ($this->input('new_break_in', []) as $index => $breakIn) {
                $breakOut = $this->input("new_break_out.$index");
                if (($breakIn === null || $breakIn === '') && ($breakOut === null || $breakOut === '')) {
                    continue;
                }
                if ($breakIn === null || $breakIn === '' || $breakOut === null || $breakOut === '') {
                    $validator->errors()->add("new_break_in.$index", '休憩時間が不適切な値です');

                    continue;
                }

                $start = Carbon::createFromFormat('H:i', $breakIn);
                $end = Carbon::createFromFormat('H:i', $breakOut);
                if ($start->lessThan($clockIn) || $start->greaterThan($clockOut)) {
                    $validator->errors()->add("new_break_in.$index", '休憩時間が不適切な値です');
                } elseif ($end->greaterThan($clockOut)) {
                    $validator->errors()->add("new_break_out.$index", '休憩時間もしくは退勤時間が不適切な値です');
                } elseif ($end->lessThanOrEqualTo($start)) {
                    $validator->errors()->add("new_break_out.$index", '休憩時間が不適切な値です');
                } else {
                    $breaks[] = ['start' => $start, 'end' => $end, 'index' => $index];
                }
            }

            usort($breaks, fn (array $left, array $right): int => $left['start']->compare($right['start']));
            for ($i = 1, $count = count($breaks); $i < $count; $i++) {
                if ($breaks[$i]['start']->lessThan($breaks[$i - 1]['end'])) {
                    $validator->errors()->add("new_break_in.{$breaks[$i]['index']}", '休憩時間が不適切な値です');
                }
            }
        });
    }
}
