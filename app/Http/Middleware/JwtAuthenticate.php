<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['success' => false, 'message' => 'No token provided'], 401);
        }
        try {
            $token = substr($header, 7);
            $decoded = (array) JWT::decode($token, new Key((string) env('JWT_SECRET', 'cmms_secret_change_in_production'), 'HS256'));
            $user = User::query()->whereKey($decoded['id'] ?? 0)->where('active', true)->first();
            if (! $user) {
                return response()->json(['success' => false, 'message' => 'Invalid or expired token'], 401);
            }
            $request->attributes->set('jwt_user', $decoded);
            $request->setUserResolver(fn () => $user);

            return $next($request);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired token'], 401);
        }
    }
}
