<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleAuthorize
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $role = $request->user()?->role;
        if (! $role || ! in_array($role, $roles, true)) {
            return response()->json(['success' => false, 'message' => 'Access denied'], 403);
        }

return $next($request);
    }
}
