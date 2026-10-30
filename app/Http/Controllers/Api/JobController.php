<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Support\LegacyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class JobController extends Controller
{
    public function __construct(private AuditService $audit) {}
    private function user(Request $r){return $r->attributes->get('jwt_user');}
    private function jobNumber(string $plant): string { $prefix=strtoupper(preg_replace('/\s+/','',$plant)); $seq=DB::table('jobs')->where('plant',$plant)->count()+1; return $prefix.'-JOB-'.str_pad((string)$seq,4,'0',STR_PAD_LEFT); }
    private function enrich($row){ if(!$row)return null; $x=(array)$row; $x['assignments']=DB::table('job_assignments')->select('department','status','assigned_at','completed_at','remarks')->where('job_id',$row->id)->orderBy('department')->get()->map(fn($a)=>(array)$a)->all(); return LegacyResponse::normalize($x); }

    public function store(Request $r)
    {
        $title=$r->input('title');$plant=$r->input('plant');$shift=$r->input('shift');
        if(!$title)return response()->json(['success'=>false,'message'=>'Title is required'],400);
        if(!$plant)return response()->json(['success'=>false,'message'=>'Plant is required'],400);
        if(!$shift)return response()->json(['success'=>false,'message'=>'Shift is required'],400);
        try{
            $no=$this->jobNumber($plant);$u=$this->user($r);
            $id=DB::table('jobs')->insertGetId(['job_number'=>$no,'title'=>$title,'description'=>$r->input('description')?:null,'equipment_id'=>$r->input('equipment_id')?:null,'remarks'=>$r->input('remarks')?:null,'plant'=>$plant,'shift'=>$shift,'raised_by'=>$u->id,'raised_by_dept'=>$r->input('raised_by_dept')?:null,'status'=>'OPEN','is_multi_assign'=>false,'created_at'=>now()]);
            $row=DB::table('jobs')->where('id',$id)->first();
            $this->audit->log($u->id,'CREATE_JOB','jobs',$id,['job_number'=>$no,'title'=>$title,'plant'=>$plant,'shift'=>$shift],$r->ip());
            try{DB::table('job_history')->insert(['job_id'=>$id,'action'=>'OPEN','from_dept'=>null,'to_dept'=>$r->input('raised_by_dept')?:null,'done_by'=>$u->id,'done_by_name'=>$u->full_name??null,'remarks'=>$r->input('remarks')?:null,'created_at'=>now()]);}catch(Throwable $e){}
            return response()->json(['success'=>true,'data'=>LegacyResponse::normalize($row),'message'=>'Job raised successfully'],201);
        }catch(Throwable $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}
    }

    public function index(Request $r)
    {
        try{
            $u=$this->user($r);
            $q=DB::table('jobs as j')->leftJoin('equipment as e','e.id','=','j.equipment_id')->leftJoin('users as u1','u1.id','=','j.raised_by')->leftJoin('users as u2','u2.id','=','j.assigned_to')->select('j.*','e.name as equipment_name','e.code as equipment_code','e.department as equipment_dept','u1.full_name as raised_by_name','u2.full_name as assigned_to_name');
            if($u->role==='operator'){$dept=$u->department??'';$q->where(function($z)use($u,$dept){$z->where('j.raised_by',$u->id)->orWhere('j.assigned_dept',$dept)->orWhere('j.raised_by_dept',$dept)->orWhereExists(function($s)use($dept){$s->select(DB::raw(1))->from('job_assignments as ja')->whereColumn('ja.job_id','j.id')->where('ja.department',$dept);});});}
            if($u->role==='maintenance'){$dept=$u->department??'';$q->where(function($z)use($dept){$z->where('j.assigned_dept',$dept)->orWhereExists(function($s)use($dept){$s->select(DB::raw(1))->from('job_assignments as ja')->whereColumn('ja.job_id','j.id')->where('ja.department',$dept);});});}
            if($r->filled('plant'))$q->where('j.plant',$r->input('plant'));if($r->filled('status'))$q->where('j.status',$r->input('status'));if($r->filled('shift'))$q->where('j.shift',$r->input('shift'));
            $rows=$q->orderByDesc('j.created_at')->limit(500)->get()->map(fn($x)=>$this->enrich($x))->all();return response()->json(['success'=>true,'data'=>$rows]);
        }catch(Throwable $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}
    }

    public function show($id)
    {
        try{$row=DB::table('jobs as j')->leftJoin('equipment as e','e.id','=','j.equipment_id')->leftJoin('users as u1','u1.id','=','j.raised_by')->leftJoin('users as u2','u2.id','=','j.assigned_to')->select('j.*','e.name as equipment_name','e.code as equipment_code','e.department as equipment_dept','u1.full_name as raised_by_name','u2.full_name as assigned_to_name')->where('j.id',$id)->first();if(!$row)return response()->json(['success'=>false,'message'=>'Job not found'],404);return response()->json(['success'=>true,'data'=>$this->enrich($row)]);}catch(Throwable $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}
    }

    public function assign(Request $r,$id)
    {
        try{$list=$r->input('departments');if(!is_array($list)||count($list)===0)$list=$r->input('assigned_dept')?[$r->input('assigned_dept')]:null;if(!$list)return response()->json(['success'=>false,'message'=>'At least one department is required'],400);$job=DB::table('jobs')->where('id',$id)->first();if(!$job)return response()->json(['success'=>false,'message'=>'Job not found'],404);if(!in_array($job->status,['OPEN','REASSIGNED'],true))return response()->json(['success'=>false,'message'=>'Job is not in OPEN or REASSIGNED status'],400);$isMulti=count($list)>1;$primary=$list[0];DB::transaction(function()use($r,$id,$list,$isMulti,$primary){DB::table('jobs')->where('id',$id)->update(['assigned_dept'=>$primary,'assigned_to'=>$r->input('assigned_to')?:null,'assigned_at'=>now(),'status'=>'ASSIGNED','is_multi_assign'=>$isMulti,'remarks'=>$r->input('remarks')?:DB::table('jobs')->where('id',$id)->value('remarks')]);DB::table('job_assignments')->where('job_id',$id)->delete();foreach($list as $dept)DB::table('job_assignments')->insert(['job_id'=>$id,'department'=>$dept,'status'=>'ASSIGNED','assigned_at'=>now(),'assigned_by'=>$this->user($r)->id,'remarks'=>$r->input('remarks')?:null]);try{DB::table('job_history')->insert(['job_id'=>$id,'action'=>'ASSIGNED','from_dept'=>null,'to_dept'=>implode(', ',$list),'done_by'=>$this->user($r)->id,'done_by_name'=>$this->user($r)->full_name??null,'remarks'=>$r->input('remarks')?:null,'created_at'=>now()]);}catch(Throwable $e){}});$row=DB::table('jobs')->where('id',$id)->first();$this->audit->log($this->user($r)->id,'ASSIGN_JOB','jobs',$id,['departments'=>$list,'isMulti'=>$isMulti],$r->ip());return response()->json(['success'=>true,'data'=>LegacyResponse::normalize($row),'message'=>$isMulti?'Job assigned to '.count($list).' departments: '.implode(', ',$list):'Job assigned to '.$primary]);}catch(Throwable $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}
    }

    public function deptStatus(Request $r,$id)
    {
        try{$dept=$r->input('department');$status=$r->input('status');if(!$dept)return response()->json(['success'=>false,'message'=>'department is required'],400);if(!in_array($status,['IN PROGRESS','COMPLETED'],true))return response()->json(['success'=>false,'message'=>'Status must be IN PROGRESS or COMPLETED'],400);$u=$this->user($r);if($u->role!=='admin'&&($u->department??null)!==$dept)return response()->json(['success'=>false,'message'=>'You can only update your own department ('.($u->department??'').'), not '.$dept],403);$assignment=DB::table('job_assignments')->where('job_id',$id)->where('department',$dept)->first();if(!$assignment)return response()->json(['success'=>false,'message'=>'Assignment not found for this department'],404);$data=['status'=>$status,'remarks'=>$r->input('remarks')?:$assignment->remarks];if($status==='IN PROGRESS')$data['started_at']=now();else{$data['completed_at']=now();$data['completed_by']=$u->id;}DB::table('job_assignments')->where('id',$assignment->id)->update($data);$all=DB::table('job_assignments')->where('job_id',$id)->pluck('status')->all();$allDone=count($all)>0&&count(array_filter($all,fn($s)=>$s!=='COMPLETED'))===0;$new=null;if($allDone){DB::table('jobs')->where('id',$id)->update(['status'=>'COMPLETED','completed_at'=>now()]);$new='COMPLETED';}elseif($status==='IN PROGRESS'){DB::table('jobs')->where('id',$id)->where('status','ASSIGNED')->update(['status'=>'IN PROGRESS']);$new='IN PROGRESS';}try{DB::table('job_history')->insert(['job_id'=>$id,'action'=>$status,'from_dept'=>$dept,'to_dept'=>$dept,'done_by'=>$u->id,'done_by_name'=>$u->full_name??null,'remarks'=>$r->input('remarks')?:null,'created_at'=>now()]);}catch(Throwable $e){}return response()->json(['success'=>true,'message'=>$allDone?'All departments completed — job marked COMPLETED':"$dept updated to $status",'job_status'=>$new,'all_completed'=>$allDone]);}catch(Throwable $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}
    }

    public function status(Request $r,$id)
    {
        try{$status=$r->input('status');$valid=['IN PROGRESS','COMPLETED','CLOSED'];if(!in_array($status,$valid,true))return response()->json(['success'=>false,'message'=>'Status must be one of: '.implode(', ',$valid)],400);$job=DB::table('jobs')->where('id',$id)->first();if(!$job)return response()->json(['success'=>false,'message'=>'Job not found'],404);if($job->is_multi_assign&&$status!=='CLOSED')return response()->json(['success'=>false,'message'=>'For multi-department jobs, use /dept-status to update individual department progress'],400);if($job->is_multi_assign&&$status==='CLOSED'){$pending=DB::table('job_assignments')->where('job_id',$id)->where('status','!=','COMPLETED')->pluck('department')->all();if($pending)return response()->json(['success'=>false,'message'=>'Cannot close — these departments have not completed yet: '.implode(', ',$pending)],400);}$data=['status'=>$status,'remarks'=>$r->input('remarks')?:$job->remarks];if($status==='COMPLETED')$data['completed_at']=now();elseif($status==='CLOSED')$data['closed_at']=now();DB::table('jobs')->where('id',$id)->update($data);try{DB::table('job_history')->insert(['job_id'=>$id,'action'=>$status,'from_dept'=>$job->assigned_dept,'to_dept'=>$job->assigned_dept,'done_by'=>$this->user($r)->id,'done_by_name'=>$this->user($r)->full_name??null,'remarks'=>$r->input('remarks')?:null,'created_at'=>now()]);}catch(Throwable $e){}$this->audit->log($this->user($r)->id,'JOB_'.str_replace(' ','_',$status),'jobs',$id,['status'=>$status],$r->ip());return response()->json(['success'=>true,'data'=>LegacyResponse::normalize(DB::table('jobs')->where('id',$id)->first()),'message'=>'Job status updated to '.$status]);}catch(Throwable $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}
    }

    public function reassign(Request $r,$id)
    {
        try{$to=$r->input('to_dept');if(!$to)return response()->json(['success'=>false,'message'=>'to_dept is required'],400);$job=DB::table('jobs')->where('id',$id)->first();if(!$job)return response()->json(['success'=>false,'message'=>'Job not found'],404);DB::transaction(function()use($r,$id,$to,$job){DB::table('jobs')->where('id',$id)->update(['status'=>'REASSIGNED','assigned_dept'=>$to,'is_multi_assign'=>false,'reassigned_at'=>now(),'reassigned_from_dept'=>$job->assigned_dept,'remarks'=>$r->input('remarks')?:$job->remarks]);DB::table('job_assignments')->where('job_id',$id)->delete();DB::table('job_history')->insert(['job_id'=>$id,'action'=>'REASSIGNED','from_dept'=>$job->assigned_dept,'to_dept'=>$to,'done_by'=>$this->user($r)->id,'done_by_name'=>$this->user($r)->full_name??null,'remarks'=>$r->input('remarks')?:null,'created_at'=>now()]);});$this->audit->log($this->user($r)->id,'JOB_REASSIGNED','jobs',$id,['from'=>$job->assigned_dept,'to'=>$to],$r->ip());return response()->json(['success'=>true,'data'=>LegacyResponse::normalize(DB::table('jobs')->where('id',$id)->first()),'message'=>'Job reassigned to '.$to]);}catch(Throwable $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}
    }

    public function history($id){try{$rows=DB::table('job_history as jh')->leftJoin('job_assignments as ja',function($j){$j->on('ja.job_id','=','jh.job_id')->on('ja.department','=','jh.to_dept');})->select('jh.*','ja.department as assignment_dept','ja.status as assignment_status')->where('jh.job_id',$id)->orderBy('jh.created_at')->get()->map(fn($x)=>LegacyResponse::normalize($x))->all();return response()->json(['success'=>true,'data'=>$rows]);}catch(Throwable $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}}
    public function assignments($id){try{$rows=DB::table('job_assignments as ja')->leftJoin('users as u1','u1.id','=','ja.assigned_by')->leftJoin('users as u2','u2.id','=','ja.completed_by')->select('ja.*','u1.full_name as assigned_by_name','u2.full_name as completed_by_name')->where('ja.job_id',$id)->orderBy('ja.department')->get()->map(fn($x)=>LegacyResponse::normalize($x))->all();return response()->json(['success'=>true,'data'=>$rows]);}catch(Throwable $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}}
}
