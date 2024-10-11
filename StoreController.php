<?php
/*
@author:Luqman Arshad
@email: luqmanarshad469@gmail.com
*/
namespace App\Http\Controllers\FoodGrain;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\UrlGenerator;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;
use Illuminate\Http\File;
use App\Models\Site\Division;
use App\Models\Site\District;
use App\Models\Site\Tehsil;
use App\Models\Site\Center;
use App\Models\Site\User;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Site\UserRoles;
use App\Models\Site\Roles;
use App\Models\Site\FoodGrain\FoodGrainLicense;
use App\Models\Site\FoodGrain\FoodGrainAmount;
use App\Models\Site\FoodGrain\NatureOfFood;
use App\Models\Site\FoodGrain\PreviousExperience;
use Hash;
use App\Services\StoreService;
use App\Datatables\FoodGrainStoreDataTable;
use App\Models\Site\FlourMill;
use QrCode;
use Illuminate\Support\Facades\Input;

use Illuminate\Support\Facades\Response;

use Validator;
use Auth;
use DB;
use URL;
use Session;
use Redirect;


class StoreController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index(Request $request,$district_id = false,StoreService $storeService){

        try{  
            $data = $storeService->loadStoreIndex($request,$district_id);
        } catch(\Exception $exception){
            return view('admin.unauthorized');
        }

        return view('foodgrain.index', $data);
            	 
    }

    public function storeUserListing (Request $request){
        try {
            return FoodGrainStoreDataTable::dataTable($request);   
        } catch (\Exception $exception) {  
            return view('admin.unauthorized');
        }  
    }
    public function addStore(){
        $title = 'Store Registration Form';  
        $user_role = Session::get('user_info.user_role');
        $user_district = Session::get('user_info.district_id'); 
      
        $user = User::Active()
        ->select('users.id','users.name')
        ->leftJoin('fms_user_roles','user_id','users.id')
        ->leftJoin('flour_mills','users.id','flour_mills.user_idFk')
        ->whereNull('flour_mills.user_idFk')
        ->where('role_id',40);
        if(in_array($user_role, array('District User', 'DC User')) ){
            $user = $user->where('users.district_idFk','=',$user_district);   
        }
        $user =$user->get();   
        return view('foodgrain.addnew', compact('title' ,'user'));
    }
    public function store(Request $request){
        if(!hasPrivilege('food_grain_store','can_add')){
            return view('admin.unauthorized');
        }
         
        
        $rule['fm_name'] = 'required';
        $rule['place_of_business'] = 'required';
        $rule['district_idFk'] ='required';
        $rule['tehsil_idFk'] ='required';
        $rule['owner_id'] ='required';
        $rules = FlourMill::RULES_Store + $rule;
        $validator = Validator::make($request->all(), $rules, FlourMill::MESSAGES);
       
        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]); exit;
        }
        if($request->input('place_of_business') != ''){
            $address = FlourMill::Active()->where(['fm_id'=>$request->store_name,'mill_type'=>1])->whereRaw('
            (fm_place_bussiness = "'.$request->input('place_of_business').'")')->get();
            if(!$address->isEmpty()){           
                return response()->json(['error' => array('place_of_business'=>"Adress already exists!")]);exit;    
            } 
        }

        $flour_mill = new FlourMill();
        $flour_mill->fm_name = $request->fm_name;
        $flour_mill->fm_place_bussiness = $request->place_of_business;
        $flour_mill->district_idFk = $request->district_idFk;
        $flour_mill->tehsil_idFk = $request->tehsil_idFk;
        $flour_mill->mill_type = 1;
        $flour_mill->user_idFk = $request->owner_id;
        $flour_mill->created_at = date('Y-m-d H:i:s');
        $flour_mill->created_by = Auth::id();
        $flour_mill->save();
        return response()->json(['success'=>'Store added successfully!']);
        
    }
   
    public function viewLicense(Request $request,$mill_id){   
        if(!hasPrivilege('user_reports','can_add')){
            return view('admin.unauthorized');
        }
        $title = 'View License Form';
        $copies = array('Bank Copy', 'DFC Office Copy', 'Center Copy', 'Treasury Office Copy'); 
        $max_date_license = FoodGrainLicense::check_latest_expiry($mill_id);
        $user_info = User::where('id',$max_date_license->user_id)->first();     
        $foodGrainAmount = FoodGrainAmount::all(); 
        return view('foodgrain.challan', compact('title','mill_id','max_date_license','copies','foodGrainAmount','user_info'));
    }
     
    public function storeMessage($message){
        if($message == 'success'){
            $msg = 'store added successfully!';
            return redirect(route('foodLicenseStore'))->with('success', $msg);
        }
        if($message == 'editsuccess'){
            $msg = 'store updated successfully!';
            return redirect(route('foodLicenseStore'))->with('success', $msg);
        }
        
    }
    public function generateQrCode($type){

        $random = $this->incrementalHash();
        //$random = uniqid();
        $id=Auth::id().$random.time();
        $name = $id.".png";
        $image_path = "qrcode/".$name;
        $redirect_url = route('verifyCode',[$type,$id]);
        QrCode::size(800)
            ->format('png')
            ->generate($redirect_url, public_path($image_path));
        return $id;
    }

    function incrementalHash($len = 5){
        $charset = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
        $base = strlen($charset);
        $result = '';

        $now = explode(' ', microtime())[1];
        while ($now >= $base){
            $i = $now % $base;
            $result = $charset[$i] . $result;
            $now /= $base;
        }
        return substr($result, -5);
    }
    private function uploadFile($requested_file, $folder_name = false){

            if(!$requested_file)
                return false;

            if(!$folder_name){
                $folder_name = 'foodgrain';
            }
            $custom_file_name   = date("YmdHis")."_".rand(11111, 99999).rand(100,999).".".$requested_file->getClientOriginalExtension();
            $path_destination   = 'public/'.$folder_name;

            $savedFile = Storage::putFileAs(
                            $path_destination, $requested_file, $custom_file_name
                );
            if($savedFile){
                
                return $custom_file_name;
            }
            return false;
    }
    
}