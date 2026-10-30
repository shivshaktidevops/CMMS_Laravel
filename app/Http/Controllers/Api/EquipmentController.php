<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Support\LegacyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class EquipmentController extends Controller
{
    public function __construct(private AuditService $audit) {}
    private function user(Request $r){return $r->attributes->get('jwt_user');}

    public function index(Request $r)
    {
        try {
            $q=DB::table('equipment')->where('active',true);
            if($r->filled('plant'))$q->where('plant',$r->input('plant'));
            $rows=$q->orderBy('department')->orderBy('name')->get();
            $shift=$r->input('shift','A'); $out=[];
            foreach($rows as $e){
                $x=(array)$e;
                $ob=DB::table('breakdowns')->select('bd_number','start_time')->where('equipment_id',$e->id)->where('status','OPEN')->orderByDesc('start_time')->first();
                $x['open_bd_number']=$ob->bd_number??null; $x['breakdown_start']=$ob->start_time??null;
                $x['current_downtime_min']=$ob?round((time()-strtotime($ob->start_time))/60):null;
                $rpm=DB::table('equipment_monitoring_types')->where('equipment_id',$e->id)->whereRaw("LOWER(monitoring_name) LIKE '%rpm%'")->where('is_active',true)->orderBy('id')->first();
                $load=DB::table('equipment_monitoring_types')->where('equipment_id',$e->id)->whereRaw("LOWER(monitoring_name) LIKE '%load%'")->where('is_active',true)->orderBy('id')->first();
                $x['standard_rpm']=$this->firstReferenceValue($rpm->reference_values??null);
                $x['actual_rpm']=$this->firstReadingValue($rpm?DB::table('eq_monitoring_logs')->where('equipment_id',$e->id)->where('monitoring_id',$rpm->id)->where('shift',$shift)->orderByDesc('logged_at')->value('readings'):null);
                $x['standard_load']=$this->firstReferenceValue($load->reference_values??null);
                $x['actual_load']=$this->readingByLabel($load?DB::table('eq_monitoring_logs')->where('equipment_id',$e->id)->where('monitoring_id',$load->id)->where('shift',$shift)->orderByDesc('logged_at')->value('readings'):null,'red');
                $lj=DB::table('jobs as j')->leftJoin('users as u','u.id','=','j.assigned_to')->select('j.title','j.closed_at','u.full_name as last_job_closed_by')->where('j.equipment_id',$e->id)->where('j.status','CLOSED')->orderByDesc('j.closed_at')->first();
                $x['last_job_title']=$lj->title??null;$x['last_job_closed_at']=$lj->closed_at??null;$x['last_job_closed_by']=$lj->last_job_closed_by??null;
                $aj=DB::table('jobs')->select('job_number','title','status')->where('equipment_id',$e->id)->where('status','!=','CLOSED')->orderByDesc('created_at')->first();
                $x['active_job_number']=$aj->job_number??null;$x['active_job_title']=$aj->title??null;$x['active_job_status']=$aj->status??null;
                $out[]=LegacyResponse::normalize($x);
            }
            return response()->json(['success'=>true,'data'=>$out]);
        }catch(Throwable $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}
    }
    public function show($id){$x=DB::table('equipment')->where('id',$id)->first();if(!$x)return response()->json(['success'=>false,'message'=>'Not found'],404);return response()->json(['success'=>true,'data'=>LegacyResponse::normalize($x)]);}
    public function store(Request $r){
        if(!$r->input('code')||!$r->input('name')||!$r->input('department'))return response()->json(['success'=>false,'message'=>'code, name, department required'],400);
        try{$id=DB::table('equipment')->insertGetId(['code'=>strtoupper($r->input('code')),'name'=>$r->input('name'),'location'=>$r->input('location'),'department'=>$r->input('department'),'type'=>$r->input('type'),'plant'=>$r->input('plant','100TPD'),'hourly_entry'=>$r->boolean('hourly_entry'),'kw_capacity'=>$r->input('kw_capacity')?:null,'load_100pct'=>$r->input('load_100pct')?:null,'critical_load'=>$r->input('critical_load')?:null,'load_monitoring'=>$r->boolean('load_monitoring'),'active'=>true,'current_status'=>'RUNNING','created_at'=>now(),'updated_at'=>now()]);$row=DB::table('equipment')->where('id',$id)->first();$this->audit->log($this->user($r)->id,'CREATE_EQUIPMENT','equipment',$id,$r->all(),$r->ip());return response()->json(['success'=>true,'data'=>LegacyResponse::normalize($row)],201);}catch(Throwable $e){if(str_contains(strtolower($e->getMessage()),'duplicate'))return response()->json(['success'=>false,'message'=>'Equipment code already exists'],409);return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}
    }
    public function update(Request $r,$id){try{$data=['name'=>$r->input('name'),'location'=>$r->input('location')?:null,'department'=>$r->input('department'),'type'=>$r->input('type')?:null,'plant'=>$r->input('plant','100TPD'),'hourly_entry'=>$r->boolean('hourly_entry'),'kw_capacity'=>$r->filled('kw_capacity')?(float)$r->input('kw_capacity'):null,'load_100pct'=>$r->filled('load_100pct')?(float)$r->input('load_100pct'):null,'critical_load'=>$r->filled('critical_load')?(float)$r->input('critical_load'):null,'load_monitoring'=>$r->boolean('load_monitoring'),'updated_at'=>now()];if($r->filled('code'))$data['code']=strtoupper($r->input('code'));DB::table('equipment')->where('id',$id)->update($data);$row=DB::table('equipment')->where('id',$id)->first();if(!$row)return response()->json(['success'=>false,'message'=>'Not found'],404);$this->audit->log($this->user($r)->id,'UPDATE_EQUIPMENT','equipment',$id,$r->all(),$r->ip());return response()->json(['success'=>true,'data'=>LegacyResponse::normalize($row)]);}catch(Throwable $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}}
    public function destroy(Request $r,$id){try{$eq=DB::table('equipment')->where('id',$id)->first();if(!$eq)return response()->json(['success'=>false,'message'=>'Equipment not found'],404);DB::transaction(function()use($id){DB::table('job_history')->whereIn('job_id',DB::table('jobs')->select('id')->where('equipment_id',$id))->delete();DB::table('job_assignments')->whereIn('job_id',DB::table('jobs')->select('id')->where('equipment_id',$id))->delete();DB::table('jobs')->where('equipment_id',$id)->delete();DB::table('breakdowns')->where('equipment_id',$id)->delete();DB::table('shift_logs')->where('equipment_id',$id)->delete();DB::table('eq_monitoring_logs')->where('equipment_id',$id)->delete();DB::table('monitoring_logs')->where('equipment_id',$id)->delete();DB::table('shift_monitoring_logs')->where('equipment_id',$id)->delete();DB::table('load_logs')->where('equipment_id',$id)->delete();DB::table('equipment_monitoring_types')->where('equipment_id',$id)->delete();DB::table('equipment_monitoring')->where('equipment_id',$id)->delete();DB::table('equipment_monitoring_params')->where('equipment_id',$id)->delete();DB::table('equipment')->where('id',$id)->delete();});$this->audit->log($this->user($r)->id,'DELETE_EQUIPMENT','equipment',$id,['deleted_equipment'=>(array)$eq],$r->ip());return response()->json(['success'=>true,'message'=>'Equipment deleted successfully']);}catch(Throwable $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],500);}}
    private function decode($v){if(is_string($v)){ $d=json_decode($v,true); return is_array($d)?$d:[];}return is_array($v)?$v:[];}
    private function firstReferenceValue($json){$a=$this->decode($json);return $a[0]['value']??null;}
    private function firstReadingValue($json){$a=$this->decode($json);return $a[0]['value']??null;}
    private function readingByLabel($json,$label){foreach($this->decode($json) as $v)if(strtolower((string)($v['label']??''))===strtolower($label))return $v['value']??null;return null;}
}
