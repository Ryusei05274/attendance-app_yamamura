<?php

/**
 * 役割: 勤怠レポート集計の品質と関連データ取得を検証するFeatureテスト。
 */

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceQualityTest extends TestCase
{
    use RefreshDatabase;

    /** The report must keep its query count constant as attendance rows increase. */
    public function test_report_eager_loads_breaks_and_ignores_missing_clock_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withoutVite();
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->startOfMonth()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('attendance.report'))->assertOk()
            ->assertViewHas('anomalies', fn (array $value): bool => $value['early_leave_count'] === 0);
        $firstCount = count(DB::getQueryLog());
        collect(range(2, 15))->each(function (int $day) use ($user): void {
            $record = Attendance::create([
                'user_id' => $user->id,
                'date' => now()->startOfMonth()->addDays($day - 1)->toDateString(),
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);
            $record->breakLogs()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
        });
        DB::flushQueryLog();
        $this->get(route('attendance.report'))->assertOk()
            ->assertViewHas('summary', fn (array $value): bool => $value['total_work_minutes'] === 14 * 480);
        $this->assertSame($firstCount, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
