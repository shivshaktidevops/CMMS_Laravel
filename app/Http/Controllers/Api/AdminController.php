<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Support\LegacyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdminController extends Controller
{
    public function __construct(private AuditService $audit) {}
    private function user(Request $r){ return $r->attributes->get('jwt_user'); }

    public function users()
    {
        $rows = DB::table('users')->select('id','username','full_name','role','department','plant','active','created_at')->orderBy('role')->orderBy('full_name')->get()->map(fn($x)=>LegacyResponse::normalize($x))->all();
        return response()->json(['success'=>true,'data'=>$rows]);
    }
    public function createUser(Request $r)
    {
        $v=$r->only('username','password','full_name','role','department','plant');
        if (!$v['username'] || !$v['password'] || !$v['full_name'] || !$v['role']) return response()->json(['success'=>false,'message'=>'username, password, full_name, role required'],400);
        if (!in_array($v['role'],['operator','maintenance','management','admin'],true)) return response()->json(['success'=>false,'message'=>'Invalid role'],400);
        try {
            $id=DB::table('users')->insertGetId(['username'=>$v['username'],'password'=>password_hash($v['password'],PASSWORD_BCRYPT,['cost'=>10]),'full_name'=>$v['full_name'],'role'=>$v['role'],'department'=>$v['department']?:null,'plant'=>$v['plant']?:null,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
            $row=(array)DB::table('users')->select('id','username','full_name','role','department','plant')->where('id',$id)->first();
            $this->audit->log($this->user($r)->id,'CREATE_USER','users',$id,['username'=>$v['username'],'role'=>$v['role']],$r->ip());
            return response()->json(['success'=>true,'data'=>$row,'message'=>'User created'],201);
        } catch(Throwable $e){ if(str_contains(strtolower($e->getMessage()),'duplicate')) return response()->json(['success'=>false,'message'=>'Username already exists'],409); return response()->json(['success'=>false,'message'=>$e->getMessage()],500); }
    }
    public function updateUser(Request $r,$id)
    {
        try {
            if ($r->has('active') && !$r->filled('full_name')) {
                DB::table('users')->where('id',$id)->update(['active'=>(bool)$r->input('active'),'updated_at'=>now()]);
                $row=DB::table('users')->select('id','username','full_name','role','active')->where('id',$id)->first();
                if(!$row) return response()->json(['success'=>false,'message'=>'User not found'],404);
                return response()->json(['success'=>true,'data'=>LegacyResponse::normalize($row),'message'=>'User '.($r->boolean('active')?'activated':'deactivated')]);
            }
            $data=['full_name'=>$r->input('full_name'),'role'=>$r->input('role'),'department'=>$r->input('department')?:null,'plant'=>$r->input('plant')?:null,'updated_at'=>now()];
            if($r->filled('password')) $data['password']=password_hash($r->input('password'),PASSWORD_BCRYPT,['cost'=>10]);
            $affected=DB::table('users')->where('id',$id)->update($data);
            $row=DB::table('users')->select('id','username','full_name','role','department','plant')->where('id',$id)->first();
            if(!$row) return response()->json(['success'=>false,'message'=>'User not found'],404);
            return response()->json(['success'=>true,'data'=>(array)$row,'message'=>'User updated successfully']);
        }catch(Throwable $e){ return response()->json(['success'=>false,'message'=>$e->getMessage()],500); }
    }
    public function resetPassword(Request $r,$id)
    {
        $p=$r->input('new_password'); if(!$p || strlen($p)<6) return response()->json(['success'=>false,'message'=>'Password must be 6+ characters'],400);
        DB::table('users')->where('id',$id)->update(['password'=>password_hash($p,PASSWORD_BCRYPT,['cost'=>10]),'updated_at'=>now()]);
        $this->audit->log($this->user($r)->id,'RESET_PASSWORD','users',$id,[],$r->ip());
        return response()->json(['success'=>true,'message'=>'Password updated']);
    }
    public function deleteUser($id){ try{ DB::table('users')->where('id',$id)->delete(); return response()->json(['success'=>true,'message'=>'User deleted']); }catch(Throwable $e){ return response()->json(['success'=>false,'message'=>$e->getMessage()],500); } }
    public function stopReasons(){ $rows=DB::table('stop_reasons')->orderBy('category')->orderBy('description')->get()->map(fn($x)=>LegacyResponse::normalize($x))->all(); return response()->json(['success'=>true,'data'=>$rows]); }
    public function createStopReason(Request $r){ if(!$r->input('code')||!$r->input('description')) return response()->json(['success'=>false,'message'=>'code and description required'],400); try{$id=DB::table('stop_reasons')->insertGetId(['code'=>strtoupper($r->input('code')),'description'=>$r->input('description'),'category'=>$r->input('category'),'active'=>true,'created_at'=>now()]); return response()->json(['success'=>true,'data'=>LegacyResponse::normalize(DB::table('stop_reasons')->where('id',$id)->first())],201);}catch(Throwable $e){if(str_contains(strtolower($e->getMessage()),'duplicate'))return response()->json(['success'=>false,'message'=>'Code already exists'],409);return response()->json(['success'=>false,'message'=>$e->getMessage()],500);} }
    public function updateStopReason(Request $r,$id){ DB::table('stop_reasons')->where('id',$id)->update(['description'=>$r->input('description'),'category'=>$r->input('category'),'active'=>$r->boolean('active')]); $row=DB::table('stop_reasons')->where('id',$id)->first(); if(!$row)return response()->json(['success'=>false,'message'=>'Not found'],404); return response()->json(['success'=>true,'data'=>LegacyResponse::normalize($row)]); }
    public function auditLog(){ $rows=DB::table('audit_log as al')->leftJoin('users as u','al.user_id','=','u.id')->select('al.*','u.username','u.full_name')->orderByDesc('al.logged_at')->limit(200)->get()->map(fn($x)=>LegacyResponse::normalize($x))->all(); return response()->json(['success'=>true,'data'=>$rows]); }
}
