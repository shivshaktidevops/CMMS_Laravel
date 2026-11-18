<?php
namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateJwt
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return response()->json(['success'=>false,'message'=>'No token provided'], 401);
        }
        try {
            $claims = $this->jwt->decode(substr($header, 7));
            $request->attributes->set('jwt_user', (object)$claims);
            return $next($request);
        } catch (Throwable $e) {
            return response()->json(['success'=>false,'message'=>'Invalid or expired token'], 401);
        }
    }
}
