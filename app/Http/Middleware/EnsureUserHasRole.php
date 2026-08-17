<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || (! $user->hasAnyRole($roles) && ! $user->hasRole('admin'))) {
            abort(403, 'Unauthorized for this workspace.');
        }

        return $next($request);
    }
}
