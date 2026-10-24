<?php

/**
 * 役割: 勤怠レポートのゲスト制御、集計、勤怠0件のFeatureテスト。
 */

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** 指定ユーザーの勤怠レコードをテスト用データとして作成する。 */
    private function attendance(User $user, string $date, string $clockIn, string $clockOut): Attendance
    {
        return Attendance::query()->create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);
    }

    public function test_guest_is_redirected_to_login_from_the_report(): void
    {
        // 未ログインの利用者が勤怠レポートを開くと、ログイン画面へ移動することを確認する。
        $this->get('/attendance/report')->assertRedirect(route('login'));
    }

    public function test_report_calculates_six_month_summary_trend_and_current_month_anomalies(): void
    {
        // 集計対象の月を固定し、通常勤務・長時間勤務・遅刻と早退を含む勤怠を用意する。
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00'));
        $user = User::factory()->create();

        $may = $this->attendance($user, '2026-05-01', '09:00:00', '18:00:00');
        BreakLog::query()->create([
            'attendance_id' => $may->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $longDay = $this->attendance($user, '2026-10-02', '08:00:00', '21:00:00');
        BreakLog::query()->create([
            'attendance_id' => $longDay->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $lateAndEarly = $this->attendance($user, '2026-10-03', '09:30:00', '17:00:00');
        BreakLog::query()->create([
            'attendance_id' => $lateAndEarly->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $this->attendance($user, '2026-04-30', '09:00:00', '18:00:00');

        // 基本サマリー、6ヶ月の月次推移、当月の異常検知回数が期待値になることを確認する。
        $this->actingAs($user)
            ->get('/attendance/report')
            ->assertOk()
            ->assertViewHas('summary', [
                'total_work_minutes' => 1590,
                'total_overtime_minutes' => 240,
                'avg_work_minutes' => 530,
            ])
            ->assertViewHas('monthlyTrend', function ($trend): bool {
                return $trend->count() === 6
                    && $trend[0]['work_minutes'] === 480
                    && $trend[1]['work_minutes'] === 0
                    && $trend[5]['work_minutes'] === 1110
                    && $trend[5]['overtime_minutes'] === 240;
            })
            ->assertViewHas('anomalies', [
                'late_count' => 1,
                'early_leave_count' => 1,
                'long_work_count' => 1,
            ]);

        $this->travelBack();
    }

    public function test_report_for_user_without_attendances_returns_zero_values_and_six_empty_months(): void
    {
        // 勤怠記録がないユーザーでも、レポート表示でエラーにならないよう集計月を固定する。
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00'));
        $user = User::factory()->create();

        // 集計値と異常検知回数が0で、6ヶ月分の月次推移も0になることを確認する。
        $this->actingAs($user)
            ->get('/attendance/report')
            ->assertOk()
            ->assertViewHas('summary', [
                'total_work_minutes' => 0,
                'total_overtime_minutes' => 0,
                'avg_work_minutes' => 0,
            ])
            ->assertViewHas('monthlyTrend', function ($trend): bool {
                return $trend->count() === 6
                    && $trend->every(fn (array $month): bool => $month['work_minutes'] === 0 && $month['overtime_minutes'] === 0
                    );
            })
            ->assertViewHas('anomalies', [
                'late_count' => 0,
                'early_leave_count' => 0,
                'long_work_count' => 0,
            ]);

        $this->travelBack();
    }
}
