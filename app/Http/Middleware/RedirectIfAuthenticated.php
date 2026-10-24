<?php

/**
 * 役割: HTTPリクエストに対する共通ミドルウェア設定を定義する。
 */

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // 💡 ログインしているユーザーの権限（role）を取得します
                $user = Auth::guard($guard)->user();

                // 👑 管理者（admin）であれば、管理者用の日次一覧画面へリダイレクトさせます
                if ($user && $user->role === 'admin') {
                    return redirect('/admin/attendance/list');
                }

                // 👤 一般ユーザーは、従来の標準ホームページ（/attendance）へ進ませます
                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
