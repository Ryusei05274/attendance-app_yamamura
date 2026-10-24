<?php

/**
 * 役割: 管理者以外のアクセスを拒否するミドルウェア。
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureAdmin
{
    /** Allow only administrator requests to continue through the pipeline.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Closure(Request): SymfonyResponse  $next  The next middleware callback.
     * @return SymfonyResponse The downstream response.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        abort_unless($request->user() && $request->user()->role === 'admin', 403);

        return $next($request);
    }
}
