<?php

/**
 * 役割: 開発・確認用のユーザー、勤怠、休憩データを作成する。
 */

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\BreakLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // サンプル用の一般ユーザー2名と管理者1名を作成する。
        // 全員のパスワードは「password」、メール認証済みとして登録する。
        $users = collect([
            ['name' => 'ユーザー1', 'email' => 'user1@example.com', 'role' => 'user', 'is_admin' => false],
            ['name' => 'ユーザー2', 'email' => 'user2@example.com', 'role' => 'user', 'is_admin' => false],
            ['name' => 'ユーザー3', 'email' => 'user3@example.com', 'role' => 'admin', 'is_admin' => true],
        ])->map(function (array $attributes) {
            $user = User::firstOrNew(['email' => $attributes['email']]);
            $user->forceFill(array_merge($attributes, [
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'admin_status' => $attributes['is_admin'],
            ]));
            $user->save();

            return $user;
        });

        // 再実行時に同じユーザーの勤怠が重複しないよう、既存の勤怠を削除して作り直す。
        Attendance::whereIn('user_id', $users->pluck('id'))->delete();

        // 過去5か月は各月15日、当月は17日分の勤怠データを作成する。
        $months = [];
        for ($monthsAgo = 5; $monthsAgo >= 1; $monthsAgo--) {
            $months[] = [
                'month' => now()->startOfMonth()->subMonths($monthsAgo),
                'days' => 15,
            ];
        }
        $months[] = ['month' => now()->startOfMonth(), 'days' => 17];

        // 各ユーザー・各月の平日を選び、勤怠と休憩を登録する。
        foreach ($users as $userIndex => $user) {
            foreach ($months as $monthIndex => $monthData) {
                $workDates = $this->firstWeekdays($monthData['month'], $monthData['days']);

                foreach ($workDates as $dayIndex => $date) {
                    [$clockIn, $clockOut] = $this->timesFor($userIndex, $monthIndex, $dayIndex);
                    $attendance = Attendance::create([
                        'user_id' => $user->id,
                        'date' => $date->toDateString(),
                        'clock_in' => $clockIn,
                        'clock_out' => $clockOut,
                    ]);

                    // 全勤怠に共通の昼休憩（12:00〜13:00）を1件登録する。
                    BreakLog::create([
                        'attendance_id' => $attendance->id,
                        'break_in' => '12:00:00',
                        'break_out' => '13:00:00',
                    ]);
                }
            }
        }
    }

    /** @return array<int, Carbon> */
    private function firstWeekdays(Carbon $month, int $count): array
    {
        // 指定月の初日から平日だけを数え、必要な件数の日付を返す。
        $dates = [];
        $date = $month->copy()->startOfMonth();

        while ($date->month === $month->month && count($dates) < $count) {
            if ($date->isWeekday()) {
                $dates[] = $date->copy();
            }
            $date->addDay();
        }

        return $dates;
    }

    /** @return array{string, string} */
    private function timesFor(int $userIndex, int $monthIndex, int $dayIndex): array
    {
        // ユーザー1の当月17件は、レポート確認用に勤務パターンを意図的に分ける。
        if ($userIndex === 0 && $monthIndex === 5) {
            return match (true) {
                $dayIndex < 10 => ['09:00:00', '18:00:00'],
                $dayIndex < 13 => ['09:00:00', '20:00:00'],
                $dayIndex < 15 => ['09:30:00', '18:00:00'],
                $dayIndex === 15 => ['09:00:00', '17:00:00'],
                default => ['08:00:00', '21:00:00'],
            };
        }

        return match ($userIndex) {
            1 => ['09:05:00', '18:05:00'],
            default => ['08:45:00', '17:45:00'],
        };
    }
}
