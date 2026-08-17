<?php

namespace App\Http\Middleware;

use App\Support\HistoryAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHistoryAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! HistoryAccess::userCanAccess($request->user())) {
            abort(403, 'You do not have access to History.');
        }

        return $next($request);
    }
}
