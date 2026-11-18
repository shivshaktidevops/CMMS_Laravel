<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $username = trim((string) $r->input('username'));
        $password = (string) $r->input('password');
        if ($username === '' || $password === '') {
            return response()->json(['success' => false, 'message' => 'Username and password required'], 400);
        }
        $u = User::where('username', $username)->where('active', true)->first();
        if (! $u) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        $ok = false;
        try {
            $ok = Hash::check($password, $u->password);
        } catch (\Throwable) {
        }
        if (! $ok && hash_equals((string) $u->password, $password)) {
            $ok = true;
        } // legacy emergency compatibility
        if (! $ok) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        $now = time();
        $ttl = (int) env('JWT_TTL', 720) * 60;
        $payload = ['id' => $u->id, 'username' => $u->username, 'role' => $u->role, 'full_name' => $u->full_name, 'department' => $u->department, 'plant' => $u->plant, 'iat' => $now, 'exp' => $now + $ttl];
        $token = JWT::encode($payload, (string) env('JWT_SECRET', 'cmms_secret_change_in_production'), 'HS256');
        AuditService::log($u->id, 'LOGIN', 'users', $u->id, ['username' => $username], $r->ip());

        return response()->json(['success' => true, 'token' => $token, 'user' => ['id' => $u->id, 'username' => $u->username, 'role' => $u->role, 'full_name' => $u->full_name, 'shift' => $u->shift, 'department' => $u->department, 'plant' => $u->plant]]);
    }

    public function me(Request $r)
    {
        $u = $r->user();

        return response()->json(['success' => true, 'user' => ['id' => $u->id, 'username' => $u->username, 'full_name' => $u->full_name, 'role' => $u->role, 'department' => $u->department, 'plant' => $u->plant, 'shift' => $u->shift]]);
    }
}
