<?php
namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ImportBundledLegacy extends Command
{
    protected $signature='legacy:import-bundled {--truncate : Clear target tables first}';
    protected $description='Import the bundled PostgreSQL snapshot data into the MySQL Laravel schema without requiring PostgreSQL locally.';

    private array $jsonColumns=[
      'audit_log'=>['details'],'equipment_monitoring'=>['reference_values','columns'],'equipment_monitoring_types'=>['reference_values','columns'],
      'equipment_monitoring_params'=>['user_boxes','columns'],'shift_monitoring_logs'=>['readings'],'eq_monitoring_logs'=>['readings'],
      'monitoring_logs'=>['readings'],'general_shift_jobs'=>['photos']
    ];
    private array $booleans=['plants'=>['is_active'],'departments'=>['is_active'],'shifts'=>['is_active'],'users'=>['active'],'stop_reasons'=>['active'],'equipment'=>['active','hourly_entry','load_monitoring'],'equipment_monitoring_params'=>['is_active','is_dynamic'],'equipment_monitoring'=>['is_active','general_shift_only','is_unlocked'],'equipment_monitoring_types'=>['is_active','general_shift_only','is_unlocked'],'jobs'=>['is_multi_assign']];
    private array $dates=['plants'=>['created_at'],'departments'=>['created_at'],'shifts'=>['created_at'],'users'=>['created_at','updated_at'],'stop_reasons'=>['created_at'],'equipment'=>['created_at','updated_at'],'equipment_monitoring_params'=>['created_at'],'equipment_monitoring'=>['unlocked_at','created_at','updated_at'],'equipment_monitoring_types'=>['created_at','unlocked_at'],'shift_monitoring_logs'=>['logged_at'],'shift_logs'=>['logged_at'],'breakdowns'=>['start_time','end_time','closed_at','created_at','updated_at'],'audit_log'=>['logged_at'],'eq_monitoring_logs'=>['logged_at'],'monitoring_logs'=>['logged_at'],'jobs'=>['created_at','assigned_at','completed_at','closed_at','reassigned_at'],'job_assignments'=>['assigned_at','started_at','completed_at'],'job_history'=>['created_at'],'general_shift_jobs'=>['started_at','completed_at','closed_at','created_at','updated_at']];
    private array $order=['plants','departments','shifts','users','stop_reasons','equipment','equipment_monitoring_params','equipment_monitoring','equipment_monitoring_types','shift_monitoring_logs','shift_logs','breakdowns','audit_log','eq_monitoring_logs','monitoring_logs','jobs','job_assignments','job_history','general_shift_jobs'];

    public function handle():int
    {
      $path=database_path('legacy/cmms_temp_data.json');if(!is_file($path)){ $this->error('Bundled data file missing: '.$path);return self::FAILURE; }
      $all=json_decode(file_get_contents($path),true);if(!is_array($all))throw new RuntimeException('Invalid bundled data JSON.');
      DB::statement('SET FOREIGN_KEY_CHECKS=0');
      try{
        if($this->option('truncate'))foreach(array_reverse($this->order) as $t)if(Schema::hasTable($t))DB::table($t)->truncate();
        foreach($this->order as $table){if(!Schema::hasTable($table))throw new RuntimeException("Target table $table missing. Run migrations first.");$rows=$all[$table]??[];if(!$rows){$this->line("$table: 0 rows");continue;}foreach(array_chunk($rows,250) as $chunk){$payload=[];foreach($chunk as $row)$payload[]=$this->transform($table,$row);DB::table($table)->insert($payload);} $max=(int)DB::table($table)->max('id');DB::statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = '.max(1,$max+1));$this->info("$table: ".count($rows).' rows');}
      } finally {DB::statement('SET FOREIGN_KEY_CHECKS=1');}
      $ok=true;foreach($this->order as $t){$src=count($all[$t]??[]);$dst=Schema::hasTable($t)?DB::table($t)->count():-1;if($src!==$dst)$ok=false;$this->line(sprintf('%-30s source=%-6d target=%-6d %s',$t,$src,$dst,$src===$dst?'OK':'MISMATCH'));}
      return $ok?self::SUCCESS:self::FAILURE;
    }

    private function transform(string $table,array $row):array
    {
      foreach($this->jsonColumns[$table]??[] as $c){if(!array_key_exists($c,$row)||$row[$c]===null)continue;$d=is_string($row[$c])?json_decode($row[$c],true):$row[$c];if(is_string($row[$c])&&json_last_error()!==JSON_ERROR_NONE)throw new RuntimeException("Invalid JSON $table.$c id=".($row['id']??'?'));$row[$c]=json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
      if($table==='jobs'&&array_key_exists('photos',$row))$row['photos']=$this->arrayToJson($row['photos']);
      foreach($this->booleans[$table]??[] as $c)if(array_key_exists($c,$row)&&$row[$c]!==null)$row[$c]=in_array($row[$c],[true,1,'1','t','true'],true)?1:0;
      foreach($this->dates[$table]??[] as $c)if(!empty($row[$c]))$row[$c]=CarbonImmutable::parse($row[$c])->utc()->format('Y-m-d H:i:s.u');
      return $row;
    }
    private function arrayToJson($v):?string{if($v===null)return null;if(is_array($v))return json_encode(array_values($v));$v=trim((string)$v);if($v==='{}')return '[]';if(!str_starts_with($v,'{')||!str_ends_with($v,'}'))return json_encode([$v]);$inner=substr($v,1,-1);if($inner==='')return '[]';return json_encode(str_getcsv($inner,',','"','\\'),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
}
