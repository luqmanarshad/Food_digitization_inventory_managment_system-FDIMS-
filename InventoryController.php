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
use App\Models\Site\BardanaReturnedFarmer;
use Validator;
use Auth;
use Session;
use App\Models\Site\Division;
use App\Models\Site\District;
use App\Models\Site\Tehsil;
use DB;
use Yajra\Datatables\Datatables;
use Response;
use App\Services\BardanaInventoryService;
use App\Datatables\BardanaInventoryDataTable;
class InventoryController extends Controller
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
    public function index(Request $request,$from_date = false, $to_date = false,$district_id = false, BardanaInventoryService $bardanaInventoryService){
        try{
            $data = $bardanaInventoryService->loadBardanaInventoryindex($request,$from_date,$to_date,$district_id);
        } catch(\Exception $exception){
            return view('admin.unauthorized');
        }
        
        return view('bardanainventory.index', $data);
    }
   
    public function total_bags(Request $request,BardanaInventoryService $bardanaInventoryService){

        try {
            $return_data = $bardanaInventoryService->load_total_bags($request); 
        } catch(\Exception $exception){
            return view('admin.unauthorized');
        }

        return Response::json($return_data,200); 
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request){
        if(!hasPrivilege('bardana_inventory','can_add')){
            return view('admin.unauthorized');
        }

        $chapter = $request->segment(1);

        $title = 'Add Bardana';    
        $godown_name = Godown::Active()->where(['center_idFk'=>Session::get('user_info.center_id')])->where('g_chapter','>',3)->select('g_title','g_id')->get();
        $addInventory_module = true;
        return view('bardanainventory.addnew', compact('title','godown_name','addInventory_module','chapter'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request){

        if(!hasPrivilege('bardana_inventory','can_add')){
            return view('admin.unauthorized');
        }

        $rules = BardanaInventory::RULES + ["godown_idFk" => 'required'];
        $validator = Validator::make($request->all(), $rules, BardanaInventory::MESSAGES);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]); exit;
        }
        $godown_idFk     = $request->godown_idFk;
        $fifty_kg_bags   = $request->fifty_kg_bags;
        $hundred_kg_bags = $request->hundred_kg_bags;
        $saveBardana = array();
        $saveBardana['godown_idFk'] = $godown_idFk;
        $saveBardana['division_idFk'] = $request->division_idFk;
        $saveBardana['district_idFk'] = $request->district_idFk;
        $saveBardana['tehsil_idFk'] = $request->tehsil_idFk;
        $saveBardana['center_idFk'] = $request->center_idFk;
        $saveBardana['bi_50kg_bags'] = $fifty_kg_bags;
        $saveBardana['bi_100kg_bags'] = $hundred_kg_bags;
        $saveBardana['created_by'] = Auth::id();
        $saveBardana['created_at'] = date('Y-m-d H:i:s'); 
        $inventory_date = BardanaInventory::Active()->whereDate('created_at', '=', date('Y-m-d'))->where('godown_idFk',$godown_idFk)->get();
        if (!$inventory_date->isEmpty()) {
            $saveBardana['updated_by']= Auth::id();
            $saveBardana['updated_at'] = date('Y-m-d H:i:s'); 
            $saveBardana['bi_50kg_bags'] += $inventory_date[0]->bi_50kg_bags;
            $saveBardana['bi_100kg_bags'] += $inventory_date[0]->bi_100kg_bags;
            BardanaInventory::find($inventory_date[0]->bi_id)->update($saveBardana);
        }
        else{
          BardanaInventory::create($saveBardana);
        }
        return response()->json(['success'=>'Data saved successfully!']);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id){
        $user_role = Session::get('user_info.user_role'); 
        $user_center = Session::get('user_info.center_id');
        $user_district = Session::get('user_info.district_id'); 
        $g_title = '';
        $chapter = $request->segment(1);
        $bardana_inventory = BardanaInventory::BardanaInventoryEditData($id,$user_role,$user_center);     
        if(in_array($user_role, array('Center User')) && $bardana_inventory[0]->center_id != $user_center){ 
            // $center = ($user_center == '' ?  $bardana_inventory[0]->center_id : $user_center);
            return view('admin.unauthorized');
        }
        if(in_array($user_role, array('District User')) && $bardana_inventory[0]->district_id != $user_district){

            return view('admin.unauthorized');
        }
        if(!$bardana_inventory->isEmpty()){
            $g_title = $bardana_inventory[0]->g_title;
        }else{
            return view('admin.unauthorized');
        }    
        $godown_name = Godown::Active()->where(['center_idFk'=>Session::get('user_info.center_id')])->where('g_chapter','>',3)->select('g_title','g_id')->get();
        $title = 'Edit Inventory';
        $bardanaEdit_edit_module = true;
        return view('bardanainventory.edit', compact('chapter','title','id','godown_name','bardana_inventory', 'g_title','bardanaEdit_edit_module'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,$id){
        if(!hasPrivilege('bardana_inventory','can_edit')){
            return view('admin.unauthorized');
        }

        $chapter_dates = get_chapter_dates($request->segment(1));
        if(!$chapter_dates){
            return view('admin.unauthorized');
        }
        $rules = [
                'fifty_kg_bags'     => 'required',
                'hundred_kg_bags'   => 'required',
        ];
        $messages = 
        [
            'fifty_kg_bags'   => 'This field is required',
            'hundred_kg_bags' => 'This field is required',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]); exit;
        }
        // $rules = BardanaInventory::RULES + ["godown_idFk" => ''];
        // $validator = Validator::make($request->all(), $rules, BardanaInventory::MESSAGES);

        // if ($validator->fails()) {
        //     return response()->json(['error'=>$validator->errors()]); exit;
        // }

        $old_data = BardanaInventory::find($id);

        $inventory_id    = $id;
        $godown_idFk     = $old_data->godown_idFk;  //godown id should not be editable
        $fifty_kg_bags   = $request->fifty_kg_bags;
        $hundred_kg_bags = $request->hundred_kg_bags;

        $godowns = array($godown_idFk);
        $issued = get_farmer_bardana_issuance_details_godown_wise(false, $godowns, date('Y-m-d H:i:s'), false, $chapter_dates);

        $total_issued_50 = ($issued[$godown_idFk]['bags_issued_50'] == null ? 0 : $issued[$godown_idFk]['bags_issued_50']);
        $total_issued_100 = ($issued[$godown_idFk]['bags_issued_100'] == null ? 0 : $issued[$godown_idFk]['bags_issued_100']);

        $bardana_inventory_50kg = $bardana_inventory_100kg = 0;

        $BardanaInventory =BardanaInventory::Active()->select(DB::raw('SUM(bi_50kg_bags) as total_50kg'),DB::raw('SUM(bi_100kg_bags) as total_100kg'))->where('godown_idFk',$godown_idFk)->get();
        if(!$BardanaInventory->isEmpty()){
            $bardana_inventory_50kg = ($BardanaInventory[0]->total_50kg == null ? 0 : $BardanaInventory[0]->total_50kg) - $old_data->bi_50kg_bags + $fifty_kg_bags;
            $bardana_inventory_100kg = ($BardanaInventory[0]->total_100kg == null ? 0 : $BardanaInventory[0]->total_100kg) - $old_data->bi_100kg_bags + $hundred_kg_bags;
        }
        $returned_bardana_transfer_details    = get_bardana_transfer_details_godown_wise($godowns);
        $get_bardana_receive_details_godown_wise     = get_bardana_receive_details_godown_wise($godowns);
        $BardanaReturned = BardanaReturnedFarmer::Active()->select(DB::raw('SUM( brf_50kg_bags) as total_50kg'),DB::raw('SUM(brf_100kg_bags) as total_100kg'))->where('godown_idFk',$godown_idFk)->get();
        if(!$BardanaReturned->isEmpty()){
            $bardana_returned_50kg = ($BardanaReturned[0]->total_50kg == null ? 0 : $BardanaReturned[0]->total_50kg);
            $bardana_returned_100kg = ($BardanaReturned[0]->total_100kg == null ? 0 : $BardanaReturned[0]->total_100kg);
        }
        if(($bardana_inventory_50kg + $bardana_returned_50kg - ($returned_bardana_transfer_details[$godown_idFk]['bags_returned_50']) + ($get_bardana_receive_details_godown_wise[$godown_idFk]['bags_returned_50'])) < $total_issued_50){
            return response()->json(['error'=>array('fifty_kg_bags'=>'50Kg Bags cannot be less than issued')]); exit;
        }

        if(($bardana_inventory_100kg + $bardana_returned_100kg - ($returned_bardana_transfer_details[$godown_idFk]['bags_returned_100']) + ($get_bardana_receive_details_godown_wise[$godown_idFk]['bags_returned_100'])) < $total_issued_100){
            return response()->json(['error'=>array('hundred_kg_bags'=>'100Kg Bags cannot be less than issued')]); exit;
        }
        
        $saveBardana = array();
        //$saveBardana['godown_idFk'] = $godown_idFk;
        //$saveBardana['division_idFk'] = $request->division_idFk;
        //$saveBardana['district_idFk'] = $request->district_idFk;
        //$saveBardana['tehsil_idFk'] = $request->tehsil_idFk;
        //$saveBardana['center_idFk'] = $request->center_idFk;
        $saveBardana['bi_50kg_bags'] = $fifty_kg_bags;
        $saveBardana['bi_100kg_bags'] = $hundred_kg_bags;
        $saveBardana['updated_by'] = Auth::id();
        $saveBardana['updated_at'] = date('Y-m-d H:i:s');
        $old_data->update($saveBardana);
        return response()->json(['success'=>'Data updated successfully!']);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request){
        if(!hasPrivilege('bardana_inventory','can_delete')){
            return view('admin.unauthorized');
        }
        $chapter_dates = get_chapter_dates($request->segment(1));
        if(!$chapter_dates){
            return view('admin.unauthorized');
        }

        $id              = $request->inventory_id;
        $old_data        = BardanaInventory::find($id);
        $fifty_kg_bags   = $old_data->bi_50kg_bags; 
        $hundred_kg_bags = $old_data->bi_100kg_bags;
        $godown_idFk     = $old_data->godown_idFk;  //godown id should not be editable

        $godowns = array($godown_idFk);
        $issued = get_farmer_bardana_issuance_details_godown_wise(false, $godowns, date('Y-m-d H:i:s'), false, $chapter_dates);
        $returned_bardana_transfer_details    = get_bardana_transfer_details_godown_wise($godowns);
        $get_bardana_receive_details_godown_wise     = get_bardana_receive_details_godown_wise($godowns);

        $total_issued_50 = ($issued[$godown_idFk]['bags_issued_50'] == null ? 0 : $issued[$godown_idFk]['bags_issued_50']);
        $total_issued_100 = ($issued[$godown_idFk]['bags_issued_100'] == null ? 0 : $issued[$godown_idFk]['bags_issued_100']);

        $bardana_inventory_50kg = $bardana_inventory_100kg = 0;

        $BardanaInventory =BardanaInventory::Active()->select(DB::raw('SUM(bi_50kg_bags) as total_50kg'),DB::raw('SUM(bi_100kg_bags) as total_100kg'))->where('godown_idFk',$godown_idFk)->get();
        if(!$BardanaInventory->isEmpty()){
            $bardana_inventory_50kg = ($BardanaInventory[0]->total_50kg == null ? 0 : $BardanaInventory[0]->total_50kg) - $fifty_kg_bags;
            $bardana_inventory_100kg = ($BardanaInventory[0]->total_100kg == null ? 0 : $BardanaInventory[0]->total_100kg) - $hundred_kg_bags;
        }
        $BardanaReturned = BardanaReturnedFarmer::Active()->select(DB::raw('SUM( brf_50kg_bags) as total_50kg'),DB::raw('SUM(brf_100kg_bags) as total_100kg'))->where('godown_idFk',$godown_idFk)->get();
        if(!$BardanaReturned->isEmpty()){
            $bardana_returned_50kg = ($BardanaReturned[0]->total_50kg == null ? 0 : $BardanaReturned[0]->total_50kg);
            $bardana_returned_100kg = ($BardanaReturned[0]->total_100kg == null ? 0 : $BardanaReturned[0]->total_100kg);
        }
      

         $totalBardana_50kg     = $bardana_inventory_50kg+$bardana_returned_50kg- ($returned_bardana_transfer_details[$godown_idFk]['bags_returned_50'])+($get_bardana_receive_details_godown_wise[$godown_idFk]['bags_returned_50']);
         $totalBardana_100kg    = $bardana_inventory_100kg+$bardana_returned_100kg- ($returned_bardana_transfer_details[$godown_idFk]['bags_returned_100'])+($get_bardana_receive_details_godown_wise[$godown_idFk]['bags_returned_100']);
         
        if(($total_issued_50 <= $totalBardana_50kg) && ($total_issued_100 <= $totalBardana_100kg)){
            $where = array('bi_id' =>$id ,'bi_status'=>1);
            BardanaInventory::where($where)->update(['bi_status'=>0,'updated_by'=>Auth::id()]);
            print_r(json_encode(array('msg'=>'success')));
        }
        else
        {
            print_r(json_encode(array('msg'=>'Error....Bardana issued is getting more than inventory. (Inventory: 50KG: '.$totalBardana_50kg.', 100KG: '.$totalBardana_100kg.')')));
        }
    }

    public function inventoryMessage($message){
        if($message == 'success'){
            $msg = 'Bardana added successfully!';
            return redirect(route('bardana'))->with('success', $msg);
        }
        if($message == 'editsuccess'){
            $msg = 'Bardana updated successfully!';
            return redirect(route('bardana'))->with('success', $msg);
        }
        
    }

    public function InventoryListing(Request $request){
        try {
            return BardanaInventoryDataTable::dataTable($request);   
        } catch (\Exception $exception) {
            return view('admin.unauthorized');
        }  
    }
    public function bardanaAudit(){
        if(!hasPrivilege('godown_audit','can_view')){
            return view('admin.unauthorized');
        }
        $title = 'Bardana Inventory Audit';
        return view('bardanainventory.auditbardana', compact('title'));
    }
    public function InventoryAuditListing(Request $request){
        if(!hasPrivilege('godown_audit','can_view')){
            return view('admin.unauthorized');
        }
        $chapter_dates = get_chapter_dates($request->segment(1));
        if(!$chapter_dates){
            return view('admin.unauthorized');
        }
        $division_id = $request->division_id;
        $district_id = $request->district_id;
        $tehsil_id = $request->tehsil_id;
        $center_id = $request->center_id;
        $godown_id = $request->godown_id;
        $user_role = Session::get('user_info.user_role');
        $user_district = Session::get('user_info.district_id');  
        $user_center = Session::get('user_info.center_id');
        $user_division = Session::get('user_info.division_id'); 
        $from_date=$request->input('from_date');
        $to_date=$request->input('to_date'); 
        if($from_date){ 
            $from_date = date('d-m-Y', strtotime($from_date));
        }
        if($to_date){
            $to_date = date('d-m-Y', strtotime($to_date));
        }
        $query = BardanaInventory::BardanaInventoryAuditData($division_id,$district_id,$tehsil_id,$center_id,$godown_id,$user_role,$user_center,$user_district,$user_division); 

        $data =  Datatables::of($query)

        ->setRowId(function ($query) {
            return 'farmer_'.$query->bi_id;
        })
        ->editColumn('divisions.name',function($query){
            return $query->division_name;
        }) 
        ->editColumn('districts.district_name',function($query){
            return $query->district_name;
        }) 
        ->editColumn('tehsils.tehsil_name',function($query){
            return $query->tehsil_name;
        })
        ->editColumn('centers.center_name',function($query){
            return $query->center_name;
        })
        ->editColumn('godowns.g_title',function($query){
            return $query->g_title;
        })
        ->editColumn('bardana_inventory.bi_50kg_bags',function($query){
            $value = $query->bi_50kg_bags;
            return number_format($value);
        })
        ->editColumn('bardana_inventory.bi_100kg_bags',function($query){
            return number_format($query->bi_100kg_bags);
        })
        
        ->editColumn('old_values',function($query){
        
                   $old_values =  json_decode(json_encode($query->old_values),true);
                    $transform_values="";
                    
                   if (is_array($old_values)) {
                    
                    foreach ($old_values as $key => $value) {
                        if (!empty($transform_values)) {

                             $transform_values.=", ".PHP_EOL;
                         }
                        $transform_values .=  transform_values($key , $value);
                    }   
                         
                   }

                   return  $transform_values; 
 
                })
        ->editColumn('new_values',function($query){

        
                   $new_values =  json_decode(json_encode($query->new_values),true);
                   $transform_new ="";
                   if (is_array($new_values)) { 
                    foreach ($new_values as $key => $value) {
                        if (!empty($transform_new)) {

                             $transform_new.=", ";

                         }    
                        $transform_new .=  transform_values($key , $value);

                    }   
                         
                   }

                   return  $transform_new; 
                    
 
                })
        ->editColumn('acitivity_datetime',function($query){
            return date('d-m-Y', strtotime($query->acitivity_datetime));
        }); 
        

        return $data->addIndexColumn()->make(true);     
    }

}
