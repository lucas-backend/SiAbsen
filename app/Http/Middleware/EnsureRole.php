<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        foreach ($roles as $role) {
            $roleUpper = strtoupper($role);
            if (($roleUpper === 'TEACHER' && $user->isTeacher()) ||
                ($roleUpper === 'STUDENT' && $user->isStudent())) {
                return $next($request);
            }
        }

        abort(403);
    }
}
