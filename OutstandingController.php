<?php
/*
@author:Luqman Arshad
@email: luqmanarshad469@gmail.com
*/
namespace App\Http\Controllers\Bardana;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Site\Godown;
use App\Models\Site\BardanaInventory;
use App\Models\Site\Farmer;
use App\Models\Site\Division;
use App\Models\Site\District;
use App\Models\Site\Tehsil;
use App\Models\Site\Center;
use App\Models\Site\User;
use App\Models\Site\BardanaIssuance;
use App\Models\Site\WheatProcurement;
use App\Models\Site\BardanaReturnedFarmer;
use Validator;
use Auth;
use Session;
use DB;
use Yajra\Datatables\Datatables;
use Response;
class OutstandingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){
        if(!hasPrivilege('outstanding_bardana','can_view')){
            return view('admin.unauthorized');
        }

        $chapter_dates = get_chapter_dates($request->segment(1));
        if(!$chapter_dates){
            return view('admin.unauthorized');
        } 
        $user_center = Session::get('user_info.center_id'); 
        $godown = Godown::Where('center_idFk',$user_center);
        if($chapter_dates['general_chapter'] > 3)
            $godown = $godown->where('g_chapter','>',3);
        else
            $godown = $godown->where('g_chapter','=',3);
        $godown = $godown->pluck('g_title','g_id');
        
        $title = 'Outstanding Bardana';
        $outstand_bardana = true;
        return view('outstandingbardana.index', compact('title', 'chapter_dates','godown','outstand_bardana'));
    }

    public function OutstandbardanaListing(Request $request){
        if(!hasPrivilege('outstanding_bardana','can_view')){
            return view('admin.unauthorized');
        }
        $chapter_dates = get_chapter_dates($request->segment(1));
        if(!$chapter_dates){
            return view('admin.unauthorized');
        }

        $chapter_number = $request->segment(1); 
        $division_id = $request->division_id;
        $district_id = $request->district_id;
        $tehsil_id = $request->tehsil_id;
        $center_id = $request->center_id;

       // $user = User::find(Auth::id());
        $user_role = Session::get('user_info.user_role'); //$user->getUserRole->role->name;
        
        $user_division = Session::get('user_info.division_id'); 
        $user_district = Session::get('user_info.district_id'); 
        $user_tehsil = Session::get('user_info.tehsil_id'); 
        $user_center = Session::get('user_info.center_id'); 

       $query = BardanaIssuance::OutstandingBardanaIssuanceData($chapter_dates,$chapter_number,$division_id,$district_id,$tehsil_id,$center_id,$user_role,$user_division,$user_district,$user_tehsil,$user_center);  

        $data =  Datatables::of($query)
 
                ->setRowId(function ($query)  {
                    return 'farmer_'.$query->f_id;
                })
                ->editColumn('farmers.f_name',function($query){
                    return $query->f_name;
                })
                
                ->editColumn('farmers.f_cnic',function($query){
                    return $query->f_cnic;
                })
                ->editColumn('farmers.f_phone_number',function($query){
                    return $query->f_phone_number;
                })
                ->editColumn('bi_50kg_bags',function($query) {
                    return number_format($query->total_50_issued-$query->total_50_procured-$query->total_50_returned);
                }) 
                ->addColumn('bi_100kg_bags',function($query) {
                    return number_format($query->total_100_issued-$query->total_100_procured-$query->total_100_returned);
                })
                ->editColumn('max_issued_date',function($query){
                    return date('d M, Y h:i:s A', strtotime($query->max_issued_date));
                });
                // ->addColumn('f_picture',function($query){
                //     return '<a href="javascript:void(0)" onclick="show_modal(\''.$query->picture_path.'\')"><img src="'.$query->picture_path.' "width="40px" height="40px"></a>';
                // })
                
               
                if(hasPrivilege('outstanding_bardana','can_add') && hasCRUDRight($chapter_dates['general_start_date'], $chapter_dates['general_end_date'])){
                    
                    $data->addColumn('manage',function($query){
 
                        return '
                        <a href="javascript:void(0)" onclick="view_modal(0, '.$query->f_id.')"><i class="fa fa-plus" style="color:blue"; aria-hidden="true"></i></a>';

                        
                    });
                    $data->rawColumns(['manage']);
                }
                
                


                return $data->addIndexColumn()->make(true);
    }

    public function store(Request $request){

        $rules = ["godown_idFk" => 'required' , "fifty_kg_bags"     => 'required',
                "hundred_kg_bags"   => 'required'];
        $validator = Validator::make($request->all(), $rules, BardanaInventory::MESSAGES);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]); exit;
        }

        $farmer_id =$request->farmer_id;
        $chapter_dates = get_active_chapter_dates();
        if(!$chapter_dates){
            return view('admin.unauthorized');
        }

        $chapter_number = $chapter_dates['general_chapter'];
        $query = BardanaIssuance::BardanaStoreIssuanceDataView($chapter_dates,$chapter_number,$farmer_id);
       
        $total_50_Outstanding = $query->total_50_issued - $query->total_50_procured - $query->total_50_returned;  
        $total_100_Outstanding = $query->total_100_issued - $query->total_100_procured - $query->total_100_returned;
       
        $godown_idFk     = $request->godown_idFk;
        $fifty_kg_bags   = $request->fifty_kg_bags;
        $hundred_kg_bags = $request->hundred_kg_bags;
        if ($fifty_kg_bags > $total_50_Outstanding) {
            return response()->json(['error'=>array('general'=>'You are issuing more than limit : '.$total_50_Outstanding.' (50kg)')]);
            exit;
        }
        if ($hundred_kg_bags > $total_100_Outstanding) {
            return response()->json(['error'=>array('general'=>'You are issuing more than limit : '.$total_100_Outstanding.' (100kg)')]);
            exit;
        }
        
        $saveReturnedBardana = array();
        $saveReturnedBardana['farmer_idFk'] = $farmer_id;
        $saveReturnedBardana['godown_idFk'] = $godown_idFk;
        $saveReturnedBardana['brf_50kg_bags'] = $fifty_kg_bags;
        $saveReturnedBardana['brf_100kg_bags'] = $hundred_kg_bags;
        $saveReturnedBardana['brf_chapter'] = $chapter_number;
        $saveReturnedBardana['created_by'] = Auth::id();
        $saveReturnedBardana['created_at'] = date('Y-m-d H:i:s');
        
        BardanaReturnedFarmer::create($saveReturnedBardana);

        return response()->json(['success'=>'Data saved successfully!']);
    }

     public function OutstandinventoryMessage($message){
        if($message == 'success'){
            $msg = 'Outstanding Bardana added successfully!';
            return redirect(route('outstandingBardana'))->with('success', $msg);
        }
        if($message == 'editsuccess'){
            $msg = 'Outstanding Bardana updated successfully!';
            return redirect(route('outstandingBardana'))->with('success', $msg);
        }
        
    }
}