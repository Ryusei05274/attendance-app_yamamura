<?php

/**
 * 役割: 認可ポリシーと認証関連の登録を行う。
 */

namespace App\Providers;

use App\Models\Attendance;
use App\Policies\AttendanceRecordPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [Attendance::class => AttendanceRecordPolicy::class];

    public function boot(): void
    {
        Gate::policy(Attendance::class, AttendanceRecordPolicy::class);
    }
}
