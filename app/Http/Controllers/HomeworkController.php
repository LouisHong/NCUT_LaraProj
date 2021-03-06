<?php

namespace App\Http\Controllers;

use Auth;
use DB;
use Storage;
use Illuminate\Http\Request;

class HomeworkController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    public $hwName=["請選擇...","作業一","作業二","作業三","作業四","作業五","作業六","期末作業"];
    public $hwType = ["一般","補交"];

    public function index(){

        if(Auth::user()->type == "正式生"){
            return redirect('home');
        }else{
        	$homeworks = DB::table('homeworks')->where('type','0')
        	->select(
    			'id', 
    			'weight',
    			'start_at',
    			'finish_at'
    		)->get();

            $homeworks1 = DB::table('homeworks')->where('type','1')
            ->select(
                'id', 
                'weight',
                'start_at',
                'finish_at'
            )->get();

           return view('ta.hw',[
                'homeworks' => $homeworks,
                'homeworks1' => $homeworks1,
                'hwName' => $this->hwName,
            ]);
        }
        //return view('home');
    }

    public function create(){
        if(Auth::user()->type == "正式生") return redirect('home');

        return view('ta.hwForm',[
            'title' => '新增作業項目',
            'FormType' => 'Create',
            'action' => route('homework.create'),
            'hwName' => $this->hwName,
            'hwType' => $this->hwType,
        ]);
    }

    public function store(Request $request){

        DB::table('homeworks')->insert([
            'type' => $request->hwType, 
            'id' => ($request->hwNo)+($request->hwType)*10,
            'weight' => $request->weight,
            'contect' => $request->hwText,
            'start_at' => $request->startTime,
            'finish_at' => $request->endTime,
        ]);

        return redirect('/homework');
    }

    public function edit($id){
        if(Auth::user()->type == "正式生") return redirect('home');

        $homeworks = DB::table('homeworks')->select(
            'type as hwType', 'id as hwNo','weight',
            'contect as hwText','start_at as startTime','finish_at as endTime'
        )->where('id',$id)->first();

        return view('ta.hwForm',[
            'title' => '修改作業項目',
            'FormType' => 'Edit',
            'action' => $id,
            'hwName' => $this->hwName,
            'hwType' => $this->hwType,
            'homeworks' => $homeworks,
        ]);
    }

    public function update(Request $request, $id){

        DB::table('homeworks')->where('id', $id)->update([
            'type' => $request->hwType, 
            'id' => ($request->hwNo)+($request->hwType)*10,
            'weight' => $request->weight,
            'contect' => $request->hwText,
            'start_at' => $request->startTime,
            'finish_at' => $request->endTime,
        ]);

        return redirect('/homework');
    }

    public function destroy(Request $request, $id){
        DB::table('homeworks')->where('id', $id)->delete();

        return redirect('/homework');
    }

    public function show($id){
        $hw = DB::table('homeworks')->select('id','contect')->where('id',$id)->first();
        return view('std.hwShow',[
            'title' => $this->hwName[$id%10],
            'backUrl' => url('homework'),
            'hw' => $hw,
        ]);
    }
	public function hwScore($id){
        $hw = DB::table('homeworks')->select('id','contect')->where('id',$id)->first();
		 $score = DB::table('scores')->where('userId',Auth::user()->uid)->select(
                    'hwId as hw',
                    'hwScore as Score'
                )->pluck('Score', 'hw');
		$comment = DB::table('scores')->where('userId',Auth::user()->uid)->select(
                    'hwId as hw',
                    'hwComment as Comment'
                )->pluck('Comment', 'hw');		
        return view('std.hwScore',[
            'title' => $this->hwName[$id%10],
            'backUrl' => url('homework'),
            'hw' => $hw,
			'hws' => $score,
			'hwc' => $comment,
        ]);
    }
	public function practice($id){
        $hw = DB::table('homeworks')->select('id','contect','start_at','finish_at')->where('id',$id)->first();
		$submit = DB::table('submits')->where('userId',Auth::user()->uid)->select(
                    'hwId as hw',
                    'practice as Practice'
                )->pluck('Practice', 'hw');
		 	
        return view('std.hwPractice',[
            'title' => $this->hwName[$id%10],
            'backUrl' => url('homework'),
			'action' => $id,
            'hw' => $hw,
			'submit'=> $submit,
        ]);
    }
	public function upload(Request $request, $id){
		
		$submits = DB::table('submits')->select('id')->where('userId',Auth::user()->uid)->where('hwId',$id)->first();

        try{
            $destinationPath = public_path().'/hw/'.$id.'/'.Auth::user()->uid.'/';
            $filetype = $request->stdFile->getMimeType();
            /*
            $filename = $request->stdFile->getclientoriginalname();
            */
            
            if($filetype == 'application/x-rar') $fType = ".rar";
            else if($filetype == 'application/zip') $fType = ".zip";
            else return "檔案格式錯誤";
            
            //return $filetype;
            $unique_name = Auth::user()->uid.$fType;
            if($request->stdFile){
                $request->file('stdFile')->move($destinationPath,$unique_name);
                $request->stdFile = 1;
            }else{
                $request->stdFile = 1;
            }
			
			date_default_timezone_set("Asia/Shanghai");
		    $date = date("Y-m-d h:i:s");
			if($submits){
            DB::table('submits')->where('userId',Auth::user()->uid)->where('hwId',$id)->update([
            'updated_at' => $date,
            'practice' => '1', 
            ]);
		    }else{
		    DB::table('submits')->where('userId',Auth::user()->uid)->where('hwId',$id)->insert([
		    'userId' => Auth::user()->uid,
			'hwId' =>$id,
			'created_at' => $date,
			'updated_at' => $date,
            'practice' => '1', 
            ]);	
		    }
            
			$request->session()->flash(
            'status', 
            "作業上傳成功!!"
             );
			 
            return redirect('/home');
			
        }catch (Exception $e){
            return "發生錯誤";
        }
    }

    public function mark($id,$uid="null"){
        $users = DB::table('users')->where('type','正式生')->select('name', 'uid', 'path')->Paginate(6);

        $submits = DB::table('submits')->where('hwId',$id)
        ->select('userId', 'choice', 'practice', 'created_at', 'updated_at')->get()->keyBy('userId');

        $HW = DB::table('scores')->where('hwId',$id)->select(
            'userId', 
            'hwScore as Score',
            'hwComment as Comment' 
        )->get()->keyBy('userId');

        $directory = public_path().'/hw/'.$id.'/'.$uid.'/';
        if(is_dir($directory)){
            $dir = scandir($directory);
        }else{
            $dir = [];
        }
        //$files = Storage::disk('public')->files($directory);
        

        if($uid != "null"){
            $idt = DB::table('users')->where('uid',$uid)->select('name', 'uid', 'path')->first();
        }else{
            $idt = [];
        }


        return view("ta.hwMark",[
            'title' => $this->hwName[$id%10]."批改",
            'id' => $id,
            'idt' => $idt,
            'submits' => $submits,
            'HW' => $HW,
            'dir' => $dir,
            //'files' => $files,
            'users' => $users,
        ]);
    }

    public function correct(Request $request, $id, $uid){
        if($request->mode){
            DB::table('scores')->where('userId', $uid)
            ->Where('hwId', $id%10)->update([
                'userId' => $uid, 
                'hwId' => $id%10,
                'hwScore' => $request->score,
                'hwComment' => $request->comment,
            ]);
        }else{
            DB::table('scores')->insert([
                'userId' => $uid, 
                'hwId' => $id%10,
                'hwScore' => $request->score,
                'hwComment' => $request->comment,
            ]);
        }
        $request->session()->flash(
            'status', 
            '學號 <b>'.$uid.'</b> 於 <b>'.$this->hwName[$id%10].'</b> 的成績已更新!!'
        );
        return redirect('/homework/mark/'.$id.'/'.$uid.'/');
    }
}
