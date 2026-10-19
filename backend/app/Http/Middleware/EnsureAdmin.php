<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) DB::table('users')->where('id', $request->user()?->getAuthIdentifier())->value('is_admin')) {
            abort(403, 'Administrator access required.');
        }

        return $next($request);
    }
}
