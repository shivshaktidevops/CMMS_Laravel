<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;use Illuminate\Http\Request;
class UploadController extends Controller
{
 public function store(Request $r){$files=$r->file('photos',[]);if(!is_array($files))$files=[$files];if(count($files)>5)return response()->json(['success'=>false,'message'=>'Too many files'],422);$out=[];$dir=public_path('uploads');if(!is_dir($dir))mkdir($dir,0775,true);foreach($files as $f){if(!$f)continue;if($f->getSize()>10*1024*1024)return response()->json(['success'=>false,'message'=>'File too large'],413);$name=(int)(microtime(true)*1000).'-'.preg_replace('/[^A-Za-z0-9._-]/','_', $f->getClientOriginalName());$f->move($dir,$name);$out[]='/uploads/'.$name;}return response()->json(['success'=>true,'files'=>$out]);}
}
