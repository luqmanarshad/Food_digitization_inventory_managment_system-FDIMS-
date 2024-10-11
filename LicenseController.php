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
use App\Datatables\FoodGrainLicenseDataTable;
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


class LicenseController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index(Request $request,$district_id = false,StoreService $storeService){
 
        try{    
            $data = $storeService->loadLicenseIndex($request,$district_id);
        } catch(\Exception $exception){  
            return view('admin.unauthorized');
        }

        return view('foodgrainlicense.index', $data);
            	 
    }

    public function FoodLicenseListing (Request $request){
        try {
            return FoodGrainLicenseDataTable::dataTable($request);   
        } catch (\Exception $exception) {  
            return view('admin.unauthorized');
        }  
    }
     
    public function addStoreLicenseForm(Request $request,$mill_id=false,$type=false){  
        if(!hasPrivilege('food_grain_license','can_add')){
            return view('admin.unauthorized');
        } 
        $title = 'Store License Form';
        $max_date_license='';
        $type = request()->segment(4);
        $flour_mills = FlourMill::Active();
        $flour_mills = $flour_mills->where('user_idFk', Auth::id());
        $flour_mills = $flour_mills->get();
 
        if (!$flour_mills->isEmpty()) {
            $max_date_license = FoodGrainLicense::check_latest_expiry($flour_mills[0]->fm_id);
        }  
        $nature_Food = NatureOfFood::Active()->get();
        $previous_experience = PreviousExperience::Active()->get();
        $user_role = Session::get('user_info.user_role');
        $business_type  =  array (
                                  array('type_id' => 0,'type_name' => 'Mill'),
                                  array('type_id' => 1,'type_name' => 'Store'),
                                  array('type_id' => 2,'type_name' => 'Chakki')
                                );   
        return view('foodgrainlicense.addnewlicense', compact('title','mill_id','max_date_license','type','flour_mills','nature_Food','previous_experience','business_type','user_role'));
    }
    public function saveStoreLicense(Request $request,$type=false)
    {
        if(!hasPrivilege('food_grain_license','can_add')){
            return view('admin.unauthorized');
        }
        $type =  2; 
        $rules = FoodGrainLicense::RULES;
        $validator = Validator::make($request->all(), $rules, FoodGrainLicense::MESSAGES);
       
        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]); exit;
        }
        // if($request->input('place_of_business') != ''){
        //     $address = FlourMill::Active()->where(['fm_id'=>$request->store_name,'mill_type'=>1])->whereRaw('
        //     (fm_place_bussiness = "'.$request->input('place_of_business').'")')->get();
        //     if(!$address->isEmpty()){           
        //         return response()->json(['error' => array('place_of_business'=>"Adress already exists!")]);exit;    
        //     } 
        // }
        $user_role = Session::get('user_info.user_role'); //$user->getUserRole->role->name; 
        $user_district = Session::get('user_info.district_id');
        $user_id   = Auth::id();
        // if (in_array($user_role, array('Mill User'))) { 
        //     $flourMill = FlourMill::Active()->where('fm_id',$request->store_name)->first();
        //     $mill_name = $flourMill->fm_name; 
        // }else{
        //    $flourMill = FlourMill::Active()->Store()->where('user_idFk',Auth::id())->first();
        //    $mill_name = $request->store_name; 
        // }
        $flourMill = FlourMill::Active()->where('fm_id',$request->store_name)->first();
        $mill_name = $flourMill->fm_name;  
        
        $date = date('Y-m-d H:i:s'); 
        // $flour_array = array( 
        //     'fm_owner_name'                 => $request->owner_name,
        //     'fm_name'                       => $request->store_name,
        //     'user_idFk'                     => Auth::id(),
        //     'mill_type'                     => 1,
        //     'created_at'                    => $date,
        //     'created_by'                    => Auth::id()
        //     );
        $msg = "";
        $appUser = User::find(Auth::id());
        $role_name = $appUser->getUserRole->role->name;  
        // $foodGrain = FoodGrainLicense::license_store_info($user_id,$appUser,$role_name);
        // $flourMill = FlourMill::Active()->where('user_idFk',$user_id)->first();
        if ($flourMill) { 
            $foodGrain = FoodGrainLicense::check_latest_expiry($flourMill->fm_id);
        }
        else{  
            // dd('$flourMill');
            $foodGrain = FoodGrainLicense::license_store_info($user_id);
        } 
        if (!empty($foodGrain)) {    
            $before_one_month_date= date('Y-m-d', strtotime('-30 days',strtotime($foodGrain->fgl_expiry_date)));  
            if (date('Y-m-d') > $before_one_month_date) {  
                if ($foodGrain->fgl_verify_license == 1) {
                    $type = 1;
                }else{
                    $type = 2;
                }
            }     
            else{
                ($msg != '' ? $msg.=', ':'');
                $msg.="License already exist, expiry: ".date('d-m-Y',strtotime($foodGrain->fgl_expiry_date));
            }  
           
        }else{
            $type = 1;
        }
        if($msg != ''){
                return response()->json(['error'=>array('general'=>$msg)]);
                exit;
        }
        DB::beginTransaction();
            try {
                // if (empty($flourMill)) {  
                //     $flourMill = FlourMill::create($flour_array);
                // }
                $flour_mill_id = $flourMill->fm_id;
                $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.$flour_mill_id;

                // generate a pin based on 2 * 7 digits + a random character
                $pin = mt_rand(1000000, 9999999)
                    . mt_rand(1000000, 9999999)
                    . $characters[rand(0, strlen($characters) - 1)];

                // shuffle the result 
                $string = str_shuffle($pin);
                
                 
                $qr_code = $this->generateQrCode(1);

                // $qr_code = '33434'; 
                $picture =  $this->uploadFile($request->file('picture'), 'food_grain_license');
                $registration_deed_document =  $this->uploadFile($request->file('registration_deed_document'), 'food_grain_license'); 
                $foodGrainLicense = array();
                $foodGrainLicense['mill_idFk'] = $flour_mill_id;
                $foodGrainLicense['fgl_license_number'] = 'LII'.$string;
                $foodGrainLicense['fgl_business_type'] = $request->business_type;
                $foodGrainLicense['fgl_owner_name'] = $request->owner_name;
                $foodGrainLicense['fgl_father_name'] = $request->father_name;
                $foodGrainLicense['fgl_cnic'] = $request->cnic;
                $foodGrainLicense['fgl_store_name'] = $flourMill->fm_name;
                $foodGrainLicense['fgl_place_of_business'] = $request->place_of_business;
                // $foodGrainLicense['fgl_storage_place'] = $request->storage_place;
                $foodGrainLicense['fgl_nature_of_food'] = $request->fgl_nature_of_food;
                $foodGrainLicense['fgl_previous_experience'] = $request->previous_experience;
                $foodGrainLicense['fgl_place_of_storage'] = $request->place_of_storage;
                    if($picture){
                        $foodGrainLicense['fgl_agreement_attached'] = $picture;
                        $foodGrainLicense['fgl_agreement_original_name'] = $request->file('picture')->getClientOriginalName();
                    }
                $foodGrainLicense['fgl_registration_deed'] = $request->fgl_registration_deed;
                    if($registration_deed_document){
                        $foodGrainLicense['fgl_deed_attachment'] = $registration_deed_document;
                        $foodGrainLicense['fgl_deed_original_name'] = $request->file('registration_deed_document')->getClientOriginalName();
                    }
                $foodGrainLicense['fgl_nation_income_tax_no'] = $request->nation_income_tax_no;
                $foodGrainLicense['fgl_tax_paid_lass'] = $request->tax_paid_lass;
                $foodGrainLicense['fgl_challan_qrcode'] = $qr_code;
                $foodGrainLicense['fgl_expiry_date'] = date('Y-m-d', strtotime('+1 years'));
                $foodGrainLicense['fgl_license_type'] = $type;
                $foodGrainLicense['fgl_verify_license'] = 1;
                $foodGrainLicense['created_by'] = Auth::id();
                $foodGrainLicense['created_at'] = date('Y-m-d H:i:s');  
                $License = FoodGrainLicense::create($foodGrainLicense);
                DB::commit();
                return response()->json(['success'=>'License added successfully!']);
            }
            catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error'=>array('general'=>'something went wrong! Try Again.')]);
            }

    }
    public function viewLicenseInfo(Request $request,FoodGrainLicense $foodGrainLicense){    
        if(!hasPrivilege('food_grain_license','can_view')){
            return view('admin.unauthorized');
        }  
        $title = 'View License Form';  
        $copies = array('Bank Copy', 'DFC Office Copy', 'Center Copy', 'Treasury Office Copy'); 
  
        $user_info = User::Active()->where('id',$foodGrainLicense->created_by)->first();     
        $foodGrainAmount = FoodGrainAmount::all(); 
        return view('foodgrainlicense.challan', compact('title','copies','foodGrainAmount','user_info','foodGrainLicense'));
    }
    public function millTypeInformation(Request $request){
        if(!hasPrivilege('food_grain_license','can_view')){
            return view('admin.unauthorized');
        }  
        $business_type = $request->business_type;
        $user_role = Session::get('user_info.user_role'); //$user->getUserRole->role->name; 
        $user_district = Session::get('user_info.district_id');  
        $flourMill =FlourMill::Active()->select('fm_id','fm_name');  
        if ($business_type == 2) {
            $flourMill = $flourMill->where(['mill_type'=>0,'flour_mills.fm_license_aata'=>1]);
        }elseif($business_type == 0 && $user_role != 'Mill User')
        {  
            $flourMill = $flourMill->where(['mill_type'=>0,'flour_mills.fm_license_aata'=>0]);
        }
        elseif(in_array($user_role, array('Mill User'))){ 
            $flourMill = $flourMill->where('user_idFk',Auth::id());
            $flourMill = $flourMill->where(['mill_type'=>0,'flour_mills.fm_license_aata'=>0]);
        }
        else{ 
            $flourMill = $flourMill->where('mill_type',$business_type);
        }
        
        if(in_array($user_role, array('District User', 'DC User'))){
            $flourMill = $flourMill->where('district_idFk','=',$user_district);
        }
        $flourMill = $flourMill->orderBy('fm_name','ASC')->get();   
         return response()->json($flourMill);
    }
    public function millUserInformation(Request $request){
        if(!hasPrivilege('food_grain_license','can_view')){
            return view('admin.unauthorized');
        } 
        $mill_id = $request->store_id;
        $flourMill =FlourMill::Active()->select('name','cnic','fm_place_bussiness')->where('fm_id',$mill_id)->leftjoin('users','user_idFk','id')->first();   
         return response()->json($flourMill);
    }
    public function verifyStoreLicense(Request $request,FoodGrainLicense $foodGrainLicense){  
        if(!hasPrivilege('food_grain_verify_license','can_add')){
            return view('admin.unauthorized');
        }  
        $title = 'Verify License Form'; 
         
        $nature_Food = NatureOfFood::Active()->get();
        $previous_experience = PreviousExperience::Active()->get();    
        return view('foodgrainlicense.addverifylicense', compact('title','nature_Food','previous_experience','foodGrainLicense'));
    }
    public function varifyLicense(Request $request,$license_id){
        if(!hasPrivilege('food_grain_verify_license','can_add')){
            return view('admin.unauthorized');
        }
        $foodGrainLicense = FoodGrainLicense::Active()->where('fgl_id',$license_id)->first();
        $foodGrainAmount = FoodGrainAmount::first(); 
        if ($foodGrainLicense->fgl_license_type == 1) {
            if ($request->varify_amount > $foodGrainAmount->new || $request->varify_amount < $foodGrainAmount->new) {
                return response()->json(['error' => array('varify_amount'=>"This amount should be equal to ".$foodGrainAmount->new."!")]);exit;
            }

            
        }
        if ($foodGrainLicense->fgl_license_type == 2) {
            if ($request->varify_amount < $foodGrainAmount->renew || $request->varify_amount > $foodGrainAmount->renew) {
                return response()->json(['error' => array('varify_amount'=>"This amount should be equal to ".$foodGrainAmount->renew."!")]);exit;
            }
            
        }
        $qr_code = $this->generateQrCode(2);
        $foodGrainLicense->fgl_license_qrcode = $qr_code;  
        $foodGrainLicense->fgl_verify_license = 2;
        $foodGrainLicense->updated_by = Auth::id();
        $foodGrainLicense->verify_at = date('Y-m-d H:i:s');
        $foodGrainLicense->save();
        return response()->json(['success'=>'Varify License successfully!']);
    }
    
    public function LicenseThirtyTwo(Request $request,FoodGrainLicense $foodGrainLicense){    
        if(!hasPrivilege('food_grain_license','can_view')){
            return view('admin.unauthorized');
        }    
        $flourMill = FlourMill::Active()->where('fm_id',$foodGrainLicense->mill_idFk)->first(); 
        $title = 'View License Form';  
        $copies = array('Bank Copy', 'DFC Office Copy', 'Center Copy', 'Treasury Office Copy'); 
        $date = date('Y-m-d H:i:s');
        $user_info = User::Active()->where('id',$foodGrainLicense->created_by)->first();     
        $foodGrainAmount = FoodGrainAmount::all(); 
        return view('foodgrainlicense.form_thirty_two', compact('title','copies','foodGrainAmount','user_info','foodGrainLicense','date','flourMill'));
    }
    public function licenseMessage($message){  
        if($message == 'success'){
            $msg = 'License added successfully!';
            return redirect(route('foodGrainLicenseReport'))->with('success', $msg);
        }
        if($message == 'editsuccess'){
            $msg = 'Varify License successfully!';
            return redirect(route('foodGrainLicenseReport'))->with('success', $msg);
        }
        
    }

    public function generateQrCode($type){

        $random = $this->incrementalHash();
        //$random = uniqid();
        $id=Auth::id().$type.$random.time();
        $name = $id.".png";
        $image_path = "qrcode/foodgrain/".$name;
        if($type == 1){
            $qrverifier_url = 'challanLicenseCode';
        }
        if($type == 2){
            $qrverifier_url = 'verifyLicenseCode';
        }
        
        $redirect_url = route($qrverifier_url,[$id]);
        // $redirect_url = route('verifyLicenseCode',[$type,$id]);
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