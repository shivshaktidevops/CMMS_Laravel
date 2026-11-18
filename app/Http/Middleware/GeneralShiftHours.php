<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class GeneralShiftHours
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('jwt_user');
        if (($user->role ?? null) === 'admin') return $next($request);
        $hour = (int) Carbon::now('Asia/Kolkata')->format('G');
        if ($hour < 9 || $hour >= 18) {
            return response()->json(['success'=>false,'message'=>'General Shift Jobs can only be accessed between 9:00 AM and 6:00 PM IST'], 403);
        }
        return $next($request);
    }
}
