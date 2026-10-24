<?php

/**
 * 役割: 会員登録、ログイン、打刻、一覧、詳細、修正申請のFeatureテスト。
 */

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\BreakLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BasicFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'user', string $email = 'user@example.test'): User
    {
        $user = new User;
        $user->forceFill(['name' => ucfirst($role), 'email' => $email, 'password' => Hash::make('password123'), 'role' => $role, 'email_verified_at' => now()]);
        $user->save();

        return $user;
    }

    private function attendance(User $user, string $date = '2026-10-02', array $values = []): Attendance
    {
        return Attendance::create(array_merge(['user_id' => $user->id, 'date' => $date, 'clock_in' => '09:00', 'clock_out' => '18:00'], $values));
    }

    public function test_registration_validates_required_fields_password_rules_and_persists_a_user(): void
    {
        // 必須項目をすべて空にして送信し、入力エラーになることを確認する。
        $this->post('/register', [])->assertSessionHasErrors([
            'name',
            'email',
            'password',
            'password_confirmation',
        ]);

        // 8文字未満のパスワードは受け付けられないことを確認する。
        $shortPassword = [
            'name' => 'A',
            'email' => 'a@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];
        $this->post('/register', $shortPassword)->assertSessionHasErrors(['password']);

        // パスワードと確認用パスワードが異なる場合、確認用項目のエラーになることを確認する。
        $differentPasswords = [
            'name' => 'A',
            'email' => 'a@example.test',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ];
        $this->post('/register', $differentPasswords)->assertSessionHasErrors(['password_confirmation']);

        // 正しい情報を送信し、認証案内へ進むことを確認する。
        $validRegistration = [
            'name' => 'A',
            'email' => 'a@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
        $registrationResponse = $this->post('/register', $validRegistration);
        $registrationResponse
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification.pending_user_id');

        // 登録内容が保存され、メール認証前はログイン状態にならないことを確認する。
        $this->assertDatabaseHas('users', ['name' => 'A', 'email' => 'a@example.test', 'role' => 'user', 'email_verified_at' => null]);
        $this->assertGuest();
    }

    public function test_user_login_validates_and_authenticates(): void
    {
        // ログイン対象となる一般ユーザーを登録しておく。
        $this->user();

        // メールアドレス以外を入力した場合、指定の必須エラーが表示されることを確認する。
        $this->post('/login', [
            'email' => '',
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);

        // パスワード以外を入力した場合、指定の必須エラーが表示されることを確認する。
        $this->post('/login', [
            'email' => 'user@example.test',
            'password' => '',
        ])->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);

        // 登録内容と異なるメールアドレスではログインできず、指定のエラーが表示されることを確認する。
        $this->post('/login', [
            'email' => 'missing@example.test',
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        // 正しい登録情報ならログインでき、一般ユーザーの勤怠登録画面へ進むことを確認する。
        $this->post('/login', ['email' => 'user@example.test', 'password' => 'password123'])->assertRedirect(route('attendance.register'));
        $this->assertAuthenticated();
    }

    public function test_admin_login_validates_credentials_and_routes_admin(): void
    {
        // ログイン対象となる管理者ユーザーを登録しておく。
        $this->user('admin', 'admin@example.test');

        // メールアドレス以外を入力した場合、指定の必須エラーが表示されることを確認する。
        $this->post('/admin/login', [
            'email' => '',
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);

        // パスワード以外を入力した場合、指定の必須エラーが表示されることを確認する。
        $this->post('/admin/login', [
            'email' => 'admin@example.test',
            'password' => '',
        ])->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);

        // 登録内容と異なるメールアドレスではログインできず、指定のエラーが表示されることを確認する。
        $this->post('/admin/login', [
            'email' => 'missing@example.test',
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        // 正しい登録情報なら管理者勤怠一覧へ進めることを確認する。
        $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'password123'])->assertRedirect('/admin/attendance/list');
        $this->assertAuthenticated();
    }

    public function test_attendance_clock_shows_current_date_and_time(): void
    {
        // テスト中の「現在日時」を固定し、画面表示と同じ日付・時刻になるか確認する。
        $currentDateTime = now()->setDate(2026, 10, 2)->setTime(8, 5);
        $this->travelTo($currentDateTime);
        $user = $this->user();
        $this->actingAs($user)
            ->get('/attendance')
            ->assertOk()
            ->assertSee('2026年10月02日(金)')
            ->assertSee($currentDateTime->format('H:i'));
        $this->travelBack();
    }

    public function test_attendance_status_is_displayed_as_off_duty(): void
    {
        // 勤怠記録のないユーザーは「勤務外」と表示されることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(8, 5));
        $user = $this->user();

        $this->actingAs($user)->get('/attendance')->assertOk()->assertSee('勤務外');
        $this->travelBack();
    }

    public function test_attendance_status_is_displayed_as_working(): void
    {
        // 出勤済みで退勤していないユーザーは「出勤中」と表示されることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(10, 0));
        $user = $this->user();
        $this->attendance($user, '2026-10-02', ['clock_out' => null]);

        $this->actingAs($user)->get('/attendance')->assertOk()->assertSee('出勤中');
        $this->travelBack();
    }

    public function test_attendance_status_is_displayed_as_on_break(): void
    {
        // 休憩開始後、休憩終了前のユーザーは「休憩中」と表示されることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(12, 30));
        $user = $this->user();
        $attendance = $this->attendance($user, '2026-10-02', ['clock_out' => null]);
        BreakLog::create(['attendance_id' => $attendance->id, 'break_in' => '12:00', 'break_out' => null]);

        $this->actingAs($user)->get('/attendance')->assertOk()->assertSee('休憩中');
        $this->travelBack();
    }

    public function test_attendance_status_is_displayed_as_clocked_out(): void
    {
        // 退勤時刻が記録されたユーザーは「退勤済」と表示されることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(18, 0));
        $user = $this->user();
        $this->attendance($user, '2026-10-02', ['clock_out' => '18:00']);

        $this->actingAs($user)->get('/attendance')->assertOk()->assertSee('退勤済');
        $this->travelBack();
    }

    public function test_clock_in_button_starts_attendance_and_updates_status(): void
    {
        // 勤務外の画面に出勤ボタンがあり、押すと出勤中になることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(9, 0));
        $user = $this->user();

        $this->actingAs($user)
            ->get('/attendance')
            ->assertOk()
            ->assertSee('value="clock_in">出勤</button>', false);

        $this->post('/attendance', ['action' => 'clock_in'])
            ->assertRedirect(route('attendance.register'));
        $this->get('/attendance')
            ->assertSee('出勤中')
            ->assertDontSee('value="clock_in">出勤</button>', false);

        $this->travelBack();
    }

    public function test_clock_in_button_is_hidden_after_clocking_out_for_the_day(): void
    {
        // 退勤済みのユーザーには出勤ボタンが表示されず、同日の再出勤も拒否されることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(18, 0));
        $user = $this->user();
        $this->attendance($user, '2026-10-02', ['clock_out' => '18:00']);

        $this->actingAs($user)
            ->get('/attendance')
            ->assertOk()
            ->assertSee('退勤済')
            ->assertDontSee('value="clock_in">出勤</button>', false);
        $this->post('/attendance', ['action' => 'clock_in'])->assertStatus(422);

        $this->travelBack();
    }

    public function test_clock_in_time_is_shown_in_attendance_list(): void
    {
        // 出勤時刻が勤怠一覧に正確に記録されて表示されることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(9, 7));
        $user = $this->user();

        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);
        $this->get('/attendance/list?date=2026-10')
            ->assertOk()
            ->assertSee('09:07');

        $this->travelBack();
    }

    public function test_break_in_button_starts_a_break_and_updates_status(): void
    {
        // 出勤中には「休憩入」が表示され、押すと休憩中になることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(10, 0));
        $user = $this->user();
        $this->attendance($user, '2026-10-02', ['clock_out' => null]);

        $this->actingAs($user)
            ->get('/attendance')
            ->assertOk()
            ->assertSee('value="break_in">休憩入</button>', false);

        $this->post('/attendance', ['action' => 'break_in'])
            ->assertRedirect(route('attendance.register'));
        $this->get('/attendance')
            ->assertSee('休憩中')
            ->assertSee('value="break_out">休憩戻</button>', false);

        $this->travelBack();
    }

    public function test_break_out_button_returns_user_to_working_status(): void
    {
        // 休憩中には「休憩戻」が表示され、押すと出勤中へ戻ることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(12, 30));
        $user = $this->user();
        $attendance = $this->attendance($user, '2026-10-02', ['clock_out' => null]);
        BreakLog::create(['attendance_id' => $attendance->id, 'break_in' => '12:00', 'break_out' => null]);

        $this->actingAs($user)
            ->get('/attendance')
            ->assertOk()
            ->assertSee('value="break_out">休憩戻</button>', false);

        $this->post('/attendance', ['action' => 'break_out'])
            ->assertRedirect(route('attendance.register'));
        $this->get('/attendance')
            ->assertSee('出勤中')
            ->assertSee('value="break_in">休憩入</button>', false);

        $this->travelBack();
    }

    public function test_multiple_breaks_and_returns_are_allowed_in_one_day(): void
    {
        // 同じ日に休憩入・休憩戻を2回行え、各状態に応じて次のボタンが表示されることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(10, 0));
        $user = $this->user();
        $this->attendance($user, '2026-10-02', ['clock_out' => null]);
        $this->actingAs($user);

        $this->post('/attendance', ['action' => 'break_in']);
        $this->get('/attendance')->assertSee('休憩中')->assertSee('休憩戻');
        $this->travel(15)->minutes();
        $this->post('/attendance', ['action' => 'break_out']);
        $this->get('/attendance')->assertSee('出勤中')->assertSee('休憩入');

        $this->post('/attendance', ['action' => 'break_in']);
        $this->get('/attendance')->assertSee('休憩中')->assertSee('休憩戻');
        $this->travel(15)->minutes();
        $this->post('/attendance', ['action' => 'break_out']);
        $this->get('/attendance')->assertSee('出勤中')->assertSee('休憩入');

        $this->assertDatabaseCount('break_logs', 2);
        $this->travelBack();
    }

    public function test_break_duration_is_shown_in_attendance_list(): void
    {
        // 勤怠一覧の「休憩」欄に、休憩の合計時間が表示されることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(12, 0));
        $user = $this->user();
        $attendance = $this->attendance($user, '2026-10-02', ['clock_out' => null]);
        $this->actingAs($user);

        $this->post('/attendance', ['action' => 'break_in']);
        $this->travel(1)->hours();
        $this->post('/attendance', ['action' => 'break_out']);
        $this->travel(5)->hours();
        $this->post('/attendance', ['action' => 'clock_out']);

        $this->get('/attendance/list?date=2026-10')
            ->assertOk()
            ->assertSee('1:00');

        $this->assertDatabaseHas('break_logs', [
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $this->travelBack();
    }

    public function test_clock_out_button_ends_work_and_updates_status(): void
    {
        // 出勤中には「退勤」ボタンが表示され、押すと退勤済になることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(18, 0));
        $user = $this->user();
        $this->attendance($user, '2026-10-02', [
            'clock_in' => '09:00',
            'clock_out' => null,
        ]);

        $this->actingAs($user)
            ->get('/attendance')
            ->assertOk()
            ->assertSee('出勤中')
            ->assertSee('value="clock_out">退勤</button>', false);

        $this->post('/attendance', ['action' => 'clock_out'])
            ->assertRedirect(route('attendance.register'));
        $this->get('/attendance')
            ->assertSee('退勤済')
            ->assertDontSee('value="clock_out">退勤</button>', false);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-02',
            'clock_out' => '18:00:00',
        ]);
        $this->travelBack();
    }

    public function test_clock_out_time_is_shown_in_attendance_list(): void
    {
        // 出勤後に退勤した時刻が勤怠一覧に正確に表示されることを確認する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(9, 0));
        $user = $this->user();
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);

        $this->travel(9)->hours();
        $this->post('/attendance', ['action' => 'clock_out']);
        $this->get('/attendance/list?date=2026-10')
            ->assertOk()
            ->assertSee('18:00');

        $this->travelBack();
    }

    public function test_clock_in_breaks_and_clock_out_persist_once_and_appear_in_list(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(9, 0));
        $user = $this->user();
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in'])->assertRedirect(route('attendance.register'));
        $this->assertDatabaseHas('attendances', ['user_id' => $user->id, 'date' => '2026-10-02', 'clock_in' => '09:00:00']);
        $this->post('/attendance', ['action' => 'break_in']);
        $this->travel(30)->minutes();
        $this->post('/attendance', ['action' => 'break_out']);
        $this->post('/attendance', ['action' => 'break_in']);
        $this->travel(15)->minutes();
        $this->post('/attendance', ['action' => 'break_out']);
        $this->travel(8)->hours();
        $this->post('/attendance', ['action' => 'clock_out']);
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseCount('break_logs', 2);
        $this->assertDatabaseHas('attendances', ['user_id' => $user->id, 'clock_out' => '17:45:00']);
        $this->get('/attendance/list?date=2026-10')->assertOk()->assertSee('09:00')->assertSee('17:45');
        $this->travelBack();
    }

    public function test_clock_transitions_hide_unavailable_actions_and_reject_duplicate_clock_in(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(9, 0));
        $user = $this->user();
        $this->actingAs($user)->get('/attendance')->assertSee('value="clock_in">出勤</button>', false);
        $this->post('/attendance', ['action' => 'clock_in']);
        $this->post('/attendance', ['action' => 'clock_in'])->assertStatus(422);
        $this->get('/attendance')->assertSee('休憩入')->assertSee('退勤')->assertDontSee('休憩戻');
        $this->post('/attendance', ['action' => 'break_in']);
        $this->get('/attendance')->assertSee('休憩戻')->assertDontSee('休憩入');
        $this->post('/attendance', ['action' => 'break_out']);
        $this->post('/attendance', ['action' => 'break_in']);
        $this->post('/attendance', ['action' => 'break_out']);
        $this->post('/attendance', ['action' => 'clock_out']);
        $this->get('/attendance')->assertSee('退勤済')->assertDontSee('value="clock_in">出勤</button>', false);
        $this->travelBack();
    }

    public function test_user_attendance_list_shows_own_records_month_navigation_and_details(): void
    {
        // 現在月を固定し、自分と他人の勤怠を区別できるデータを用意する。
        $this->travelTo(now()->setDate(2026, 10, 2)->startOfDay());
        $user = $this->user();
        $other = $this->user('user', 'other-list@example.test');
        $octoberAttendance = $this->attendance($user, '2026-10-02', [
            'clock_in' => '09:15',
            'clock_out' => '18:10',
        ]);
        $this->attendance($user, '2026-10-03', [
            'clock_in' => '09:20',
            'clock_out' => '18:30',
        ]);
        $this->attendance($user, '2026-09-20', [
            'clock_in' => '08:30',
            'clock_out' => '17:30',
        ]);
        $this->attendance($other, '2026-10-02', [
            'clock_in' => '07:00',
            'clock_out' => '16:00',
        ]);

        // 初期表示は現在月で、自分の当月レコードはすべて表示し、他人のデータは含めない。
        $currentMonthResponse = $this->actingAs($user)->get('/attendance/list');
        $currentMonthResponse
            ->assertOk()
            ->assertSee('2026/10')
            ->assertSee('09:15')
            ->assertSee('09:20')
            ->assertDontSee('07:00')
            ->assertSee('href="?date=2026-09"', false)
            ->assertSee('href="?date=2026-11"', false)
            ->assertSee('href="'.url('/attendance/'.$octoberAttendance->id).'"', false);

        // 「前月」を開くと2026年9月の自分の勤怠が表示される。
        $this->get('/attendance/list?date=2026-09')
            ->assertOk()
            ->assertSee('2026/09')
            ->assertSee('08:30')
            ->assertDontSee('09:15');

        // 「翌月」を開くと2026年11月が表示される。
        $this->get('/attendance/list?date=2026-11')
            ->assertOk()
            ->assertSee('2026/11')
            ->assertDontSee('09:15');

        // 一覧の「詳細」リンク先で該当日の勤怠詳細が開く。
        $this->get('/attendance/'.$octoberAttendance->id)
            ->assertOk()
            ->assertSee('09:15')
            ->assertSee('18:10');

        $this->travelBack();
    }

    public function test_user_attendance_detail_shows_logged_in_users_date_and_punch_times(): void
    {
        // ログインユーザー本人の詳細画面に、名前・日付・出退勤・休憩が表示されることを確認する。
        $user = $this->user();
        $user->update(['name' => '勤怠確認ユーザー']);
        $attendance = $this->attendance($user, '2026-09-20', [
            'clock_in' => '08:47',
            'clock_out' => '17:36',
        ]);
        BreakLog::create([
            'attendance_id' => $attendance->id,
            'break_in' => '12:10',
            'break_out' => '12:55',
        ]);

        $this->actingAs($user)
            ->get('/attendance/'.$attendance->id)
            ->assertOk()
            ->assertSee('value="勤怠確認ユーザー"', false)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="9月20日"', false)
            ->assertSee('value="08:47"', false)
            ->assertSee('value="17:36"', false)
            ->assertSee('value="12:10"', false)
            ->assertSee('value="12:55"', false);
    }

    public function test_user_attendance_month_navigation_details_and_correction_requests(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->startOfDay());
        $user = $this->user();
        $other = $this->user('user', 'other@example.test');
        $attendance = $this->attendance($user);
        $otherAttendance = $this->attendance($user, '2026-09-20');
        $this->attendance($other);
        BreakLog::create(['attendance_id' => $attendance->id, 'break_in' => '12:00', 'break_out' => '13:00']);
        $this->actingAs($user)->get('/attendance/list')->assertOk()->assertSee('2026/10')->assertSee('09:00');
        $this->get('/attendance/list?date=2026-09')->assertSee('09:00');
        $this->get('/attendance/list?date=2026-11')->assertSee('2026/11');
        $this->get('/attendance/'.$attendance->id)->assertOk()->assertSee('09:00')->assertSee('18:00')->assertSee('12:00');
        $this->get('/attendance/'.$other->attendances()->first()->id)->assertNotFound();
        $bad = ['new_clock_in' => '19:00', 'new_clock_out' => '18:00', 'comment' => '訂正'];
        $this->post('/attendance/'.$attendance->id, $bad)
            ->assertSessionHasErrors(['new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です']);
        $this->post('/attendance/'.$attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['19:00'],
            'new_break_out' => ['19:30'],
            'comment' => '休憩開始が退勤後',
        ])->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
        $this->post('/attendance/'.$attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['17:30'],
            'new_break_out' => ['19:00'],
            'comment' => '休憩終了が退勤後',
        ])->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
        $this->post('/attendance/'.$attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '訂正',
        ])->assertRedirect('/attendance/'.$attendance->id);
        $this->post('/attendance/'.$otherAttendance->id, [
            'new_clock_in' => '08:30',
            'new_clock_out' => '17:30',
            'comment' => '別日の訂正',
        ])->assertRedirect('/attendance/'.$otherAttendance->id);
        $correction = AttendanceCorrection::firstOrFail();
        $pendingList = $this->get('/attendance_correction_request/list');
        $pendingList
            ->assertOk()
            ->assertSee('訂正')
            ->assertSee('別日の訂正')
            ->assertSee('承認待ち')
            ->assertSee('href="'.route('attendance.detail', $attendance->id).'"', false)
            ->assertSee('href="'.route('attendance.detail', $otherAttendance->id).'"', false);
        $this->get('/attendance/'.$attendance->id)->assertOk()->assertSee('09:00');
        $this->get('/attendance/'.$otherAttendance->id)->assertOk()->assertSee('08:30');
        $this->travelBack();
    }

    public function test_user_and_admin_correction_forms_validate_notes_and_time_order(): void
    {
        $user = $this->user();
        $attendance = $this->attendance($user);
        $this->actingAs($user)->post('/attendance/'.$attendance->id, [
            'new_clock_in' => '19:00',
            'new_clock_out' => '18:00',
            'comment' => '出退勤時刻の確認',
        ])->assertSessionHasErrors(['new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です']);

        $this->post('/attendance/'.$attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['19:00'],
            'new_break_out' => ['19:30'],
            'comment' => '休憩開始時刻の確認',
        ])->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);

        $this->post('/attendance/'.$attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['17:30'],
            'new_break_out' => ['19:00'],
            'comment' => '休憩終了時刻の確認',
        ])->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);

        $this->post('/attendance/'.$attendance->id, [
            'new_clock_in' => '09:00', 'new_clock_out' => '18:00', 'comment' => '',
        ])->assertSessionHasErrors(['comment' => '備考を記入してください']);

        $admin = $this->user('admin', 'admin@example.test');
        $this->actingAs($admin)->put('/admin/attendance/'.$attendance->id, [
            'new_clock_in' => '19:00', 'new_clock_out' => '18:00', 'comment' => '修正',
        ])->assertSessionHasErrors(['new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です']);
        $this->put('/admin/attendance/'.$attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['19:00'],
            'new_break_out' => ['19:30'],
            'comment' => '休憩開始時刻の確認',
        ])->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
        $this->put('/admin/attendance/'.$attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['17:30'],
            'new_break_out' => ['19:00'],
            'comment' => '休憩終了時刻の確認',
        ])->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
        $this->put('/admin/attendance/'.$attendance->id, [
            'new_clock_in' => '09:00', 'new_clock_out' => '18:00', 'comment' => '',
        ])->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }

    public function test_admin_can_view_all_general_users_and_selected_staff_monthly_attendance(): void
    {
        // 現在月を固定し、管理者・複数スタッフ・月をまたぐ勤怠を用意する。
        $this->travelTo(now()->setDate(2026, 10, 2)->startOfDay());
        $admin = $this->user('admin', 'admin@example.test');
        $staffOne = $this->user('user', 'staff-one@example.test');
        $staffOne->update(['name' => '勤怠スタッフ一']);
        $staffTwo = $this->user('user', 'staff-two@example.test');
        $staffTwo->update(['name' => '勤怠スタッフ二']);

        $selectedAttendance = $this->attendance($staffOne, '2026-10-02', [
            'clock_in' => '09:10',
            'clock_out' => '18:20',
        ]);
        BreakLog::create([
            'attendance_id' => $selectedAttendance->id,
            'break_in' => '12:00',
            'break_out' => '13:00',
        ]);
        $this->attendance($staffOne, '2026-10-03', [
            'clock_in' => '10:05',
            'clock_out' => '19:00',
        ]);
        $this->attendance($staffOne, '2026-09-20', [
            'clock_in' => '08:30',
            'clock_out' => '17:30',
        ]);
        $this->attendance($staffTwo, '2026-10-02', [
            'clock_in' => '07:45',
            'clock_out' => '16:15',
        ]);

        // スタッフ一覧には全一般ユーザーの氏名・メールがあり、管理者はスタッフとして含まれない。
        $this->actingAs($admin)
            ->get('/admin/staff/list')
            ->assertOk()
            ->assertSee('勤怠スタッフ一')
            ->assertSee('staff-one@example.test')
            ->assertSee('勤怠スタッフ二')
            ->assertSee('staff-two@example.test')
            ->assertDontSee('admin@example.test');

        // 選択したスタッフの当月勤怠のみが表示され、前月・翌月リンクも正しい。
        $currentMonth = $this->get('/admin/attendance/staff/'.$staffOne->id);
        $currentMonth
            ->assertOk()
            ->assertSee('勤怠スタッフ一さんの勤怠')
            ->assertSee('2026/10')
            ->assertSee('10/02')
            ->assertSee('09:10')
            ->assertSee('18:20')
            ->assertSee('10:05')
            ->assertDontSee('07:45')
            ->assertSee('href="?date=2026-09"', false)
            ->assertSee('href="?date=2026-11"', false)
            ->assertSee('href="'.route('admin.attendance.detail', $selectedAttendance->id).'"', false);

        // 前月を選ぶと対象スタッフの9月の勤怠に切り替わる。
        $this->get('/admin/attendance/staff/'.$staffOne->id.'?date=2026-09')
            ->assertOk()
            ->assertSee('2026/09')
            ->assertSee('08:30')
            ->assertDontSee('09:10');

        // 翌月を選ぶと対象スタッフの11月の勤怠に切り替わる。
        $this->get('/admin/attendance/staff/'.$staffOne->id.'?date=2026-11')
            ->assertOk()
            ->assertSee('2026/11')
            ->assertDontSee('09:10');

        // 勤怠一覧の詳細リンクから、選択した日の勤怠詳細へ移動できる。
        $this->get('/admin/attendance/'.$selectedAttendance->id)
            ->assertOk()
            ->assertSee('value="勤怠スタッフ一"', false)
            ->assertSee('value="10月2日"', false)
            ->assertSee('value="09:10"', false)
            ->assertSee('value="18:20"', false)
            ->assertSee('value="12:00"', false)
            ->assertSee('value="13:00"', false);

        $this->travelBack();
    }

    public function test_admin_can_review_and_approve_corrections_and_list_staff_attendance(): void
    {
        // 日付を固定し、当日・前日・翌日の勤怠と複数スタッフを用意する。
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(12, 0));
        $admin = $this->user('admin', 'admin@example.test');
        $staff = $this->user('user', 'staff@example.test');
        $staff->update(['name' => 'スタッフ1']);
        $attendance = $this->attendance($staff);
        BreakLog::create([
            'attendance_id' => $attendance->id,
            'break_in' => '12:00',
            'break_out' => '13:00',
        ]);
        $secondStaff = $this->user('user', 'staff2@example.test');
        $secondStaff->update(['name' => 'スタッフ2']);
        $secondStaffAttendance = $this->attendance($secondStaff, '2026-10-02', [
            'clock_in' => '08:45',
            'clock_out' => '17:15',
        ]);
        $this->attendance($staff, '2026-10-01', [
            'clock_in' => '07:50',
            'clock_out' => '16:20',
        ]);
        $this->attendance($staff, '2026-10-03', [
            'clock_in' => '08:10',
            'clock_out' => '17:00',
        ]);
        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id, 'user_id' => $staff->id, 'original_date' => '2026-10-02',
            'new_clock_in' => '08:30', 'new_clock_out' => '17:30', 'new_breaks' => [['break_in' => '12:00', 'break_out' => '12:45']],
            'comment' => '時刻訂正', 'status' => 0,
        ]);
        $secondPendingCorrection = AttendanceCorrection::create([
            'attendance_id' => $secondStaffAttendance->id,
            'user_id' => $secondStaff->id,
            'original_date' => '2026-10-02',
            'new_clock_in' => '08:40',
            'new_clock_out' => '17:10',
            'new_breaks' => [],
            'comment' => 'スタッフ2の承認待ち申請',
            'status' => 0,
        ]);
        $approvedAttendance = $this->attendance($secondStaff, '2026-10-03', [
            'clock_in' => '08:10',
            'clock_out' => '17:00',
        ]);
        $approvedCorrection = AttendanceCorrection::create([
            'attendance_id' => $approvedAttendance->id,
            'user_id' => $secondStaff->id,
            'original_date' => '2026-10-03',
            'new_clock_in' => '08:00',
            'new_clock_out' => '16:50',
            'new_breaks' => [],
            'comment' => 'スタッフ2の承認済み申請',
            'status' => 1,
        ]);
        // 初期表示は現在日で、その日に出勤した全スタッフの正確な勤怠が表示される。
        $this->actingAs($admin)
            ->get('/admin/attendance/list')
            ->assertOk()
            ->assertSee('2026/10/02')
            ->assertSee('スタッフ1')
            ->assertSee('09:00')
            ->assertSee('スタッフ2')
            ->assertSee('08:45')
            ->assertSee('17:15')
            ->assertDontSee('admin@example.test')
            ->assertSee('href="?date=2026-10-01"', false)
            ->assertSee('href="?date=2026-10-03"', false);

        // 「前日」では前日の日付と、その日の勤怠時刻が表示される。
        $this->get('/admin/attendance/list?date=2026-10-01')
            ->assertOk()
            ->assertSee('2026/10/01')
            ->assertSee('07:50')
            ->assertSee('16:20')
            ->assertDontSee('08:45');

        // 「翌日」では翌日の日付と、その日の勤怠時刻が表示される。
        $this->get('/admin/attendance/list?date=2026-10-03')
            ->assertOk()
            ->assertSee('2026/10/03')
            ->assertSee('08:10')
            ->assertSee('17:00')
            ->assertDontSee('08:45');
        // 詳細画面が選択したスタッフ・日付・打刻・休憩の内容を表示する。
        $this->get('/admin/attendance/'.$attendance->id)
            ->assertOk()
            ->assertSee('value="スタッフ1"', false)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="10月2日"', false)
            ->assertSee('value="09:00"', false)
            ->assertSee('value="18:00"', false)
            ->assertSee('value="12:00"', false)
            ->assertSee('value="13:00"', false);
        $this->get('/admin/staff/list')->assertOk()->assertSee('staff@example.test')->assertDontSee('admin@example.test');
        $this->get('/admin/attendance/staff/'.$staff->id.'?date=2026-10')->assertOk()->assertSee('10/02');
        // 承認待ち一覧には全ユーザーの未承認申請が表示され、承認済み申請は混ざらない。
        // 管理者画面は承認待ち・承認済みの両タブを同じHTMLに出すため、各タブ内を分けて確認する。
        $applicationList = $this->get('/admin/attendance/correction-request/list')->assertOk()->getContent();
        $pendingTab = explode('<div class="tab__content" id="content1">', $applicationList, 2)[1];
        $pendingTab = explode('<div class="tab__content" id="content2">', $pendingTab, 2)[0];
        $approvedTab = explode('<div class="tab__content" id="content2">', $applicationList, 2)[1];

        $this->assertStringContainsString('スタッフ1', $pendingTab);
        $this->assertStringContainsString('時刻訂正', $pendingTab);
        $this->assertStringContainsString('スタッフ2の承認待ち申請', $pendingTab);
        $this->assertStringNotContainsString('スタッフ2の承認済み申請', $pendingTab);
        $this->assertStringContainsString('href="'.route('admin.correction.approve', $correction->id).'"', $pendingTab);
        $this->assertStringContainsString('href="'.route('admin.correction.approve', $secondPendingCorrection->id).'"', $pendingTab);
        $this->assertStringContainsString('スタッフ2の承認済み申請', $approvedTab);
        $this->assertStringNotContainsString('スタッフ2の承認待ち申請', $approvedTab);
        $this->assertStringContainsString('href="'.route('admin.correction.approve', $approvedCorrection->id).'"', $approvedTab);

        // 修正申請の詳細に申請者・日付・出退勤・休憩・理由が正しく表示される。
        $this->get('/admin/attendance_correction_request/approve/'.$correction->id)
            ->assertOk()
            ->assertSee('value="スタッフ1"', false)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="10月2日"', false)
            ->assertSee('value="08:30"', false)
            ->assertSee('value="17:30"', false)
            ->assertSee('value="12:00"', false)
            ->assertSee('value="12:45"', false)
            ->assertSee('時刻訂正')
            ->assertSee('>承認</button>', false);
        $this->post('/admin/attendance_correction_request/approve/'.$correction->id)->assertRedirect(route('admin.correction.list'));
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'clock_in' => '08:30', 'clock_out' => '17:30']);
        $this->assertDatabaseHas('attendance_corrections', ['id' => $correction->id, 'status' => 1]);
        $this->assertDatabaseHas('break_logs', ['attendance_id' => $attendance->id, 'break_in' => '12:00', 'break_out' => '12:45']);
        // 承認後は承認済み一覧へ移り、承認待ち一覧からは該当申請が消える。
        $updatedApplicationList = $this->get('/admin/attendance/correction-request/list')->assertOk()->getContent();
        $pendingTab = explode('<div class="tab__content" id="content1">', $updatedApplicationList, 2)[1];
        $pendingTab = explode('<div class="tab__content" id="content2">', $pendingTab, 2)[0];
        $approvedTab = explode('<div class="tab__content" id="content2">', $updatedApplicationList, 2)[1];
        $this->assertStringContainsString('スタッフ2の承認待ち申請', $pendingTab);
        $this->assertStringNotContainsString('時刻訂正', $pendingTab);
        $this->assertStringContainsString('時刻訂正', $approvedTab);
        $this->assertStringContainsString('スタッフ2の承認済み申請', $approvedTab);
        $this->assertStringNotContainsString('スタッフ2の承認待ち申請', $approvedTab);

        // 承認後、本人の申請一覧に承認済み申請と詳細リンクが表示されることを確認する。
        $this->actingAs($staff)
            ->get('/attendance_correction_request/list')
            ->assertOk()
            ->assertSee('承認済み')
            ->assertSee('時刻訂正')
            ->assertSee('href="'.route('attendance.detail', $attendance->id).'"', false);
        $this->get('/attendance/'.$attendance->id)->assertOk()->assertSee('08:30')->assertSee('17:30');
        $this->travelBack();
    }
}
