<?php

/**
 * 役割: 会員登録後の認証メール送信と認証動線のFeatureテスト。
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_a_verification_email_and_shows_mailpit_link(): void
    {
        // メール送信を記録し、実際のSMTP接続なしで送信先と通知内容を確認できるようにする。
        Notification::fake();

        // 一般ユーザーを会員登録する。
        $response = $this->post('/register', [
            'name' => '検証ユーザー',
            'email' => 'verification@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $user = User::query()->where('email', 'verification@example.test')->firstOrFail();

        // 登録したメールアドレス宛てに認証メール通知が送られ、登録直後は未ログインであることを確認する。
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertGuest();

        // 認証案内画面の「認証はこちらから」リンクがMailpitを指していることを確認する。
        $this->withSession(['verification.pending_user_id' => $user->id])
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('href="http://localhost:8025"', false)
            ->assertSee('>認証はこちらから</a>', false);
    }

    public function test_signed_verification_link_verifies_and_redirects_to_attendance(): void
    {
        // メール未認証の一般ユーザーを用意し、期限付き署名URLを発行する。
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        // メール内の認証リンクを開くと認証が完了し、勤怠登録画面へ移動することを確認する。
        $this->withSession(['verification.pending_user_id' => $user->id])
            ->get($verificationUrl)
            ->assertRedirect(route('attendance.register'));

        // 認証済みになり、勤怠登録画面へアクセスできることを確認する。
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
        $this->get(route('attendance.register'))->assertOk();
    }

    public function test_pending_user_can_resend_the_verification_email(): void
    {
        // 認証案内画面からの再送を記録する。
        Notification::fake();
        $user = User::factory()->unverified()->create();

        // 未認証ユーザーの認証メールを再送できることを確認する。
        $this->withSession(['verification.pending_user_id' => $user->id])
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'));

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
