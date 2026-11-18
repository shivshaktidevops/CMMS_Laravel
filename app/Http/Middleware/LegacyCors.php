<?php
namespace App\Http\Middleware;
use Closure;use Illuminate\Http\Request;use Symfony\Component\HttpFoundation\Response;
class LegacyCors
{
 public function handle(Request $request,Closure $next):Response{$origin=$request->headers->get('Origin');$allowed=array_values(array_filter(array_map('trim',explode(',',(string)env('ALLOWED_ORIGINS','')))));if($request->isMethod('OPTIONS'))$response=response('',204);elseif($origin&&$allowed&& !in_array($origin,$allowed,true))return response()->json(['success'=>false,'message'=>'Not allowed by CORS'],500);else $response=$next($request);if($origin&&(!$allowed||in_array($origin,$allowed,true))){$response->headers->set('Access-Control-Allow-Origin',$origin);$response->headers->set('Vary','Origin');}$response->headers->set('Access-Control-Allow-Methods','GET, POST, PUT, PATCH, DELETE, OPTIONS');$response->headers->set('Access-Control-Allow-Headers','Content-Type, Authorization');return $response;}
}
