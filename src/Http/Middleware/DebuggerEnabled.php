<?php

namespace MohamedHathout\Debugger\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DebuggerEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('debugger.is_enabled', true)) {
            abort(404);
        }

        return $next($request);
    }
}
