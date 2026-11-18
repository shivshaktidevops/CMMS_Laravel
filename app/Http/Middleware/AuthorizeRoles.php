<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeRoles
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->attributes->get('jwt_user');
        if (!$user || !in_array($user->role ?? null, $roles, true)) {
            return response()->json(['success'=>false,'message'=>'Access denied'], 403);
        }
        return $next($request);
    }
}
