<?php

/**
 * 役割: 管理者向け修正申請一覧と承認処理を担当する。
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceCorrectionController extends Controller
{
    /**
     * 💡 【US014 / PG12】管理者用：修正申請一覧画面の表示処理
     * 要件FN047・FN048に基づき、「承認待ち」と「承認済み」のタブ切り替えに対応します
     */
    /** 管理者向けに全ユーザーの修正申請一覧を表示する。
     * @return View 表示する画面とそのデータ。
     */
    public function index(): View
    {
        // Bladeの2つのタブで表示を分けるため、承認待ちと承認済みの両方を取得する。
        $requests = AttendanceCorrection::with(['user', 'attendance'])
            ->latest()
            ->get()
            ->each(function (AttendanceCorrection $correction): void {
                $correction->approval_status = (int) $correction->status === 0 ? '承認待ち' : '承認済み';
                $correction->application_date = $correction->created_at?->format('Y/m/d');
                // 既存の一覧Bladeが参照する名称に合わせる。
                $correction->AttendanceRecord = $correction->attendance;
            });

        return view('admin.admin-application-list', [
            'applications' => $requests,
        ]);

    }

    /**
     * 💡 【US015 / PG13】管理者用：修正申請承認画面の表示処理
     * 送られてきた申請内容を、以前提示していただいた「承認画面用Blade」に流し込みます
     */
    /** 選択された修正申請と申請内容を表示する。
     * @param  int  $attendance_correct_request_id  対象レコードの識別子。
     * @return View 表示する画面とそのデータ。
     */
    public function showApprove(int $attendance_correct_request_id): View
    {
        // 申請、申請者、対象勤怠を取得して承認画面用に整形する。
        $application = AttendanceCorrection::with(['user', 'attendance'])->findOrFail($attendance_correct_request_id);
        $user = $application->user;
        $application->approval_status = (int) $application->status === 0 ? '承認待ち' : '承認済み';
        $application->new_date = Carbon::parse($application->original_date ?? $application->attendance->date);
        $application->new_clock_in = Carbon::parse($application->new_clock_in)->format('H:i');
        $application->new_clock_out = Carbon::parse($application->new_clock_out)->format('H:i');
        $application->proposalBreaks = collect($application->new_breaks ?? [])
            ->map(fn (array $break): object => (object) $break);

        return view('admin.admin-application-detail', compact('application', 'user'));
    }

    /**
     * 💡 【US015 / FN051】👑 修正申請の承認・本番データ一斉反映ロジック
     * 管理者が「承認」ボタンを押した瞬間、1つのトランザクションで確実にDBを書き換えます
     */
    /** 修正申請を承認し、申請内容を勤怠と休憩記録に反映する。
     * @param  int  $attendance_correct_request_id  対象レコードの識別子。
     * @return RedirectResponse 処理後の遷移先。
     */
    public function approve(int $attendance_correct_request_id): RedirectResponse
    {
        $alreadyApproved = false;

        DB::transaction(function () use ($attendance_correct_request_id, &$alreadyApproved): void {
            $correctionRequest = AttendanceCorrection::with('attendance')
                ->lockForUpdate()
                ->findOrFail($attendance_correct_request_id);

            if ((int) $correctionRequest->status !== 0) {
                $alreadyApproved = true;

                return;
            }

            $attendance = Attendance::lockForUpdate()->findOrFail($correctionRequest->attendance_id);
            $attendance->update([
                'clock_in' => $correctionRequest->new_clock_in,
                'clock_out' => $correctionRequest->new_clock_out,
                'comment' => $correctionRequest->comment,
            ]);

            $attendance->breakLogs()->delete();
            collect($correctionRequest->new_breaks ?? [])->each(
                fn (array $break) => $attendance->breakLogs()->create([
                    'break_in' => $break['break_in'],
                    'break_out' => $break['break_out'] ?? null,
                ])
            );

            $correctionRequest->update([
                'status' => 1,
                'approved_at' => now(),
            ]);
        });

        if ($alreadyApproved) {
            return redirect()->route('admin.correction.list')
                ->with('success', 'この申請はすでに承認済みです。');
        }

        return redirect()->route('admin.correction.list')
            ->with('success', '修正申請を承認し、勤怠情報を更新しました。');
    }
}
