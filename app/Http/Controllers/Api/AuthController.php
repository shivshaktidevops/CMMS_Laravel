<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private JwtService $jwt, private AuditService $audit) {}

    public function login(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');
        if (!$username || !$password) return response()->json(['success'=>false,'message'=>'Username and password required'], 400);
        try {
            $user = DB::table('users')->where('username',$username)->where('active',true)->first();
            if (!$user) return response()->json(['success'=>false,'message'=>'Invalid credentials'], 401);
            $hash = $user->password;
            $match = str_starts_with($hash, '$2') ? password_verify($password, $hash) : hash_equals((string)$hash, (string)$password);
            if (!$match && str_starts_with($hash, '$2b$')) $match = password_verify($password, '$2y$'.substr($hash,4));
            if (!$match) return response()->json(['success'=>false,'message'=>'Invalid credentials'], 401);

            $claims = [
                'id'=>(int)$user->id,'username'=>$user->username,'role'=>$user->role,
                'full_name'=>$user->full_name,'department'=>$user->department,'plant'=>$user->plant,
            ];
            $token = $this->jwt->encode($claims);
            $this->audit->log((int)$user->id, 'LOGIN', 'users', (int)$user->id, ['username'=>$username], $request->ip());
            return response()->json([
                'success'=>true,'token'=>$token,
                'user'=>[
                    'id'=>(int)$user->id,'username'=>$user->username,'role'=>$user->role,
                    'full_name'=>$user->full_name,'shift'=>$user->shift,'department'=>$user->department,'plant'=>$user->plant,
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()], 500);
        }
    }

    public function me(Request $request)
    {
        $jwt = $request->attributes->get('jwt_user');
        $user = DB::table('users')->select('id','username','full_name','role','department','plant','shift')->where('id',$jwt->id)->first();
        return response()->json(['success'=>true,'user'=>$user ? (array)$user : null]);
    }
}
