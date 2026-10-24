<?php

/**
 * 役割: 一般ユーザーの会員登録と認証メール送信を処理する。
 */

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * PG01: 会員登録画面の表示
     *
     * @return View 表示する画面とそのデータ。
     */
    public function create(): View
    {
        // 💡こちらも実際の配置に合わせてView名を正しく指定します
        // 例: 'user.register' または 'register'
        return view('user.register');
    }

    /**
     * FN001〜FN005: 会員登録の実行 ＆ 指定文言エラー
     *
     * @param  RegisterRequest  $request  登録フォームの入力と検証済みデータ。
     * @return RedirectResponse 処理後の遷移先。
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user', // 初期値は一般ユーザー
        ]);

        // Keep the pending account in the session so the verification page can be shown without login.
        session(['verification.pending_user_id' => $user->id]);
        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice');
    }
}
