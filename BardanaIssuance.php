<?php

namespace App\Models\Site;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use App\Models\Site\User;
use App\Models\Site\Farmer;
use App\Models\Site\WheatProcurement;
use App\Models\Site\BardanaReturnedFarmer;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Models\Audit;
use DB;
use Session;

class BardanaIssuance extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable; 
    protected $primaryKey = 'bi_id';
    protected $table = 'bardana_issuance';
    // protected $guarded = ['bi_id'];

    public const RULES = [
        "bi_cash_deposit_receipt_number"  => 'required|max:30',
        // "picture"  => 'mimes:jpeg,png,jpg,pdf|max:2048',
    ];

    public const MESSAGES = [
                "required" => 'This field is required',
                "bi_cash_deposit_receipt_number.max" => 'This field may not be greater than 30 characters.',
                "picture.max" => 'The picture may not be greater than 2 MB.',
    ];

        public static function BardanaIssuanceData($chapter_dates,$division_id=false,$district_id=false,$tehsil_id=false,$center_id=false,$user_role=false,$user_division=false,$user_district=false,$user_tehsil=false,$user_center=false)
        {  
             $query = BardanaIssuance::Active()
             ->selectRaw('bi_id,f_name, f_father_name,districts.district_name,tehsils.tehsil_name,centers.center_name,f_cnic,f_phone_number,bi_50kg_bags,bi_100kg_bags,bi_cash_deposit_receipt_number,bi_mobile,bi_activity_datetime')
             ->leftJoin('farmers','f_id','=','farmer_idFk')
             ->leftJoin('districts','district_id','=','farmers.district_idFk')
             ->leftJoin('tehsils','tehsil_id','=','farmers.tehsil_idFk')
             ->leftJoin('centers','center_id','=','farmers.center_idFk')
             ->whereBetween('bi_activity_datetime', [$chapter_dates['general_start_date'], $chapter_dates['general_end_date']]);
             //->with(['getFarmer','getFarmer.getDistrict','getFarmer.getTehsil','getFarmer.getCenter']); 

        if(in_array($user_role, array('Division User','Deputy Director', 'Sugar Commissioner')) || $division_id != ''){
            $division = ($user_division == '' ? $division_id : $user_division);
            // $query = $query->whereHas('getFarmer', function($q) use ($division){
            //     $q->where('division_idFk', '=', $division);
            // });
            $query = $query->where('division_idFk', '=', $division);
        }

        if(in_array($user_role, array('District User', 'DC User')) || $district_id != ''){
            $district = ($user_district == '' ? $district_id : $user_district);
            // $query = $query->whereHas('getFarmer', function($q) use ($district){
            //     $q->where('district_idFk', '=', $district);
            // });
            $query = $query->where('district_idFk', '=', $district);
        }

        if(in_array($user_role, array('Tehsil User','PLRA User')) || $tehsil_id != ''){
            $tehsil = ($user_tehsil == '' ? $tehsil_id : $user_tehsil);
            // $query = $query->whereHas('getFarmer', function($q) use ($tehsil){
            //     $q->where('tehsil_idFk', '=', $tehsil);
            // });
            $query = $query->where('tehsil_idFk', '=', $tehsil);
        }

        if(in_array($user_role, array('Center User')) || $center_id != ''){
            $center = ($user_center == '' ? $center_id : $user_center);  
            // $query = $query->whereHas('getFarmer', function($q) use ($center){
            //     $q->where('center_idFk', '=', $center);
            // });
            $query = $query->where('center_idFk', '=', $center);
        }
        return $query;
        }
        public static function BardanaRemainingData($chapter_dates,$division_id=false,$district_id=false,$tehsil_id=false,$center_id=false,$user_role=false,$user_division=false,$user_district=false,$user_tehsil=false,$user_center=false)
        {  
           
            $bardanaInventory = BardanaInventory::Active()
            ->selectRaw("SUM(bi_50kg_bags) as total_bi_50kg_bags,SUM(bi_100kg_bags) as total_bi_100kg_bags, godown_idFk")
            ->whereBetween('created_at', [$chapter_dates['general_start_date'], $chapter_dates['general_end_date']])
            ->groupBy('godown_idFk');  
            
            $bardanaIssuance = BardanaIssuance::Active()
            // ->leftJoin('bardana_issuance_godown_stock','bardana_issuance_idFk','=','bardana_issuance.bi_id')
            ->leftJoin('bardana_issuance_godown_stock', function($join) {
                $join->on('bardana_issuance_godown_stock.bardana_issuance_idFk', '=', 'bardana_issuance.bi_id')
                     ->where('bardana_issuance_godown_stock.bigd_status', '=', 1);
            })
            ->selectRaw("SUM(bigd_50kg_bags) as total_bigd_50kg_bags,SUM(bigd_100kg_bags) as total_bigd_100kg_bags, godown_idFk")
            ->whereBetween('bi_activity_datetime', [$chapter_dates['general_start_date'], $chapter_dates['general_end_date']])
            ->groupBy('godown_idFk');

            $bardanaReceiveGodownStock = BardanaReceiveGodownStock::Active()
            ->leftJoin('bardana_receive', 'br_id', '=', 'bardana_receive_idFK')
            ->leftJoin('bardana_transfer', 'bt_id', '=', 'bardana_transfer_idFK')
            ->selectRaw("SUM(brgs_50kg_bags_received) as total_50kg_bags, SUM(brgs_100kg_bags_received) as total_100kg_bags,godown_idFk") 
            ->whereBetween('bardana_receive_godown_stock.created_at', [$chapter_dates['general_start_date'], $chapter_dates['general_end_date']])
            ->groupBy('godown_idFk'); 
            $bardanaTransferGodownStock = BardanaTransferGodownStock::Active()
            ->leftJoin('bardana_transfer', function($join) {
                $join->on('bardana_transfer.bt_id', '=', 'bardana_transfer_godown_stock.bardana_transfer_idFK')
                     ->where('bardana_transfer.bt_status', '=', 1);
            })
            ->selectRaw("SUM(btgs_50kg_bags_transferred) as total_50kg_bags, SUM(btgs_100kg_bags_transferred) as total_100kg_bags,godown_idFk")
            ->whereBetween('bardana_transfer_godown_stock.created_at', [$chapter_dates['general_start_date'], $chapter_dates['general_end_date']])
            ->groupBy('godown_idFk');

            

            $bardanaReturnedFarmer = BardanaReturnedFarmer::Active()
            
            ->leftJoin('godowns', function($join)use($chapter_dates) {
                $join->on('godowns.g_id', '=', 'bardana_returned_farmer.godown_idFk')
                     ->where('godowns.g_status', '=', 1);
                     if($chapter_dates['general_chapter'] > 3){
                        $join = $join->where('g_chapter','>',3);
                    }else{
                        $join = $join->where('g_chapter','=',3);
                    }
            })
            ->selectRaw("SUM(brf_50kg_bags) as total_50kg_bags, SUM(brf_100kg_bags) as total_100kg_bags,godown_idFk")
            ->whereBetween('bardana_returned_farmer.created_at', [$chapter_dates['general_start_date'], $chapter_dates['general_end_date']])->groupBy('godown_idFk');  
            if(in_array($user_role, array('Division User','Deputy Director', 'Sugar Commissioner')) || $division_id != ''){
                $division = ($user_division == '' ? $division_id : $user_division);
                
                // $query = $query->where('g.division_idFk', '=', $division);
                $bardanaInventory = $bardanaInventory->where('division_idFk',$division);
                $bardanaIssuance = $bardanaIssuance->with('getFarmer')->whereHas('getFarmer', function($query)use($division){
                    $query->where('division_idFk', $division);
                });
                $bardanaReturnedFarmer = $bardanaReturnedFarmer->where('godowns.division_idFk', '=', $division);
                $bardanaTransferGodownStock = $bardanaTransferGodownStock->where('bt_by_division_id', $division);
                $bardanaReceiveGodownStock = $bardanaReceiveGodownStock->where('bt_to_division_id', $division);
                
            }
    
            if(in_array($user_role, array('District User', 'DC User')) || $district_id != ''){
                $district = ($user_district == '' ? $district_id : $user_district);
    
                $bardanaInventory = $bardanaInventory->where('district_idFk',$district);
                $bardanaIssuance = $bardanaIssuance->with('getFarmer')->whereHas('getFarmer', function($query)use($district){
                    $query->where('district_idFk', $district);
                });
                $bardanaReturnedFarmer = $bardanaReturnedFarmer->where('godowns.district_idFk', '=', $district);
                $bardanaTransferGodownStock = $bardanaTransferGodownStock->where('bt_by_district_id', $district);
                $bardanaReceiveGodownStock = $bardanaReceiveGodownStock->where('bt_to_district_id', $district);
          
                
            }
    
            if(in_array($user_role, array('Tehsil User','PLRA User')) || $tehsil_id != ''){
                $tehsil = ($user_tehsil == '' ? $tehsil_id : $user_tehsil);
               
 
                $bardanaInventory = $bardanaInventory->where('tehsil_idFk',$tehsil);
                $bardanaIssuance = $bardanaIssuance->with('getFarmer')->whereHas('getFarmer', function($query)use($tehsil){
                    $query->where('tehsil_idFk', $tehsil);
                });
                $bardanaReturnedFarmer = $bardanaReturnedFarmer->where('godowns.tehsil_idFk', '=', $tehsil);
                $bardanaTransferGodownStock = $bardanaTransferGodownStock->where('bt_by_tehsil_id', $tehsil);
                $bardanaReceiveGodownStock = $bardanaReceiveGodownStock->where('bt_to_tehsil_id', $tehsil);
                
            }
    
            if(in_array($user_role, array('Center User')) || $center_id != ''){
                $center = ($user_center == '' ? $center_id : $user_center);    
                $bardanaInventory = $bardanaInventory->where('center_idFk',$center);
                
                
                
                $bardanaIssuance = $bardanaIssuance->with('getFarmer')->whereHas('getFarmer', function($query)use($center){
                    $query->where('center_idFk', $center);
                });
          
                $bardanaReturnedFarmer = $bardanaReturnedFarmer->where('godowns.center_idFk', '=', $center);
                $bardanaTransferGodownStock = $bardanaTransferGodownStock->where('bt_by_center_id', $center);
                $bardanaReceiveGodownStock = $bardanaReceiveGodownStock->where('bt_to_center_id', $center);
              
            }
            // \DB::enableQueryLog();
            $query = DB::table('godowns as g')
            ->selectRaw("g_id,g.g_title,districts.district_name,tehsils.tehsil_name,centers.center_name, 
                        IF(inventory.total_bi_50kg_bags,inventory.total_bi_50kg_bags,0) as total_50_bardana_inventory,
                        IF(inventory.total_bi_100kg_bags,inventory.total_bi_100kg_bags,0) as total_100_bardana_inventory,
                        IF(issuance.total_bigd_50kg_bags,issuance.total_bigd_50kg_bags,0) as total_50_bardana_issuance,
                        IF(issuance.total_bigd_100kg_bags,issuance.total_bigd_100kg_bags,0) as total_100_bardana_issuance,
                        IF(received.total_50kg_bags,received.total_50kg_bags,0) as total_50_bardana_received,
                        IF(received.total_100kg_bags,received.total_100kg_bags,0) as total_100_bardana_received,
                        IF(transfer.total_50kg_bags,transfer.total_50kg_bags,0) as total_50_bardana_transfer,
                        IF(transfer.total_100kg_bags,transfer.total_100kg_bags,0) as total_100_bardana_transfer,
                        IF(returned.total_50kg_bags,returned.total_50kg_bags,0) as total_50_bardana_returned,
                        IF(returned.total_100kg_bags,returned.total_100kg_bags,0) as total_100_bardana_returned,
                        (IF(inventory.total_bi_50kg_bags, inventory.total_bi_50kg_bags, 0) 
                        - IF(issuance.total_bigd_50kg_bags, issuance.total_bigd_50kg_bags, 0) 
                        + IF(returned.total_50kg_bags, returned.total_50kg_bags, 0) 
                        - IF(transfer.total_50kg_bags, transfer.total_50kg_bags,0) 
                        + IF(received.total_50kg_bags,received.total_50kg_bags,0)) as total_50_bardana_remaining,

                        (IF(inventory.total_bi_100kg_bags, inventory.total_bi_100kg_bags, 0) 
                        - IF(issuance.total_bigd_100kg_bags, issuance.total_bigd_100kg_bags, 0) 
                        + IF(returned.total_100kg_bags, returned.total_100kg_bags, 0) 
                        - IF(transfer.total_100kg_bags, transfer.total_100kg_bags,0) 
                        + IF(received.total_100kg_bags,received.total_100kg_bags,0)) as total_100_bardana_remaining,

                        (
                            (IF(inventory.total_bi_50kg_bags, inventory.total_bi_50kg_bags, 0) * 50 +
                             IF(inventory.total_bi_100kg_bags, inventory.total_bi_100kg_bags, 0) * 100)
                            -
                            (IF(issuance.total_bigd_50kg_bags, issuance.total_bigd_50kg_bags, 0) * 50 +
                             IF(issuance.total_bigd_100kg_bags, issuance.total_bigd_100kg_bags, 0) * 100)
                            +
                            (IF(returned.total_50kg_bags, returned.total_50kg_bags, 0) * 50 +
                             IF(returned.total_100kg_bags, returned.total_100kg_bags, 0) * 100)
                            -
                            (IF(transfer.total_50kg_bags, transfer.total_50kg_bags, 0) * 50 +
                             IF(transfer.total_100kg_bags, transfer.total_100kg_bags, 0) * 100)
                            +
                            (IF(received.total_50kg_bags, received.total_50kg_bags, 0) * 50 +
                             IF(received.total_100kg_bags, received.total_100kg_bags, 0) * 100)
                          ) as total_bardana_remaining
                          
                        "  
            )
            ->leftJoinSub($bardanaInventory, 'inventory', function ($join) {
                $join->on('g.g_id', '=', 'inventory.godown_idFk');
            })
            ->leftJoinSub($bardanaIssuance, 'issuance', function ($join) { 
                $join->on('g.g_id', '=', 'issuance.godown_idFk'); 
            })
            ->leftJoinSub($bardanaReceiveGodownStock, 'received', function ($join) { 
                $join->on('g.g_id', '=', 'received.godown_idFk'); 
            })
            ->leftJoinSub($bardanaTransferGodownStock, 'transfer', function ($join) { 
                $join->on('g.g_id', '=', 'transfer.godown_idFk'); 
            })
            ->leftJoinSub($bardanaReturnedFarmer, 'returned', function ($join) { 
                $join->on('g.g_id', '=', 'returned.godown_idFk'); 
            })
             
            ->leftJoin('divisions','division_id','=','g.division_idFk')
            ->leftJoin('districts','district_id','=','g.district_idFk')
            ->leftJoin('tehsils','tehsil_id','=','g.tehsil_idFk')
            ->leftJoin('centers','center_id','=','g.center_idFk')
            ->where('g_status',1);
            if($chapter_dates['general_chapter'] > 3){
                $query = $query->where('g_chapter','>',3);
            }else{
                $query = $query->where('g_chapter','=',3);
            }
            $query = $query->groupBy('g.g_id');
            
        if(in_array($user_role, array('Division User','Deputy Director', 'Sugar Commissioner')) || $division_id != ''){
            $division = ($user_division == '' ? $division_id : $user_division);
             
            $query = $query->where('g.division_idFk', '=', $division);
        }

        if(in_array($user_role, array('District User', 'DC User')) || $district_id != ''){
            $district = ($user_district == '' ? $district_id : $user_district);
 
            $query = $query->where('g.district_idFk', '=', $district);
            
        }

        if(in_array($user_role, array('Tehsil User','PLRA User')) || $tehsil_id != ''){
            $tehsil = ($user_tehsil == '' ? $tehsil_id : $user_tehsil);
            
            $query = $query->where('g.tehsil_idFk', '=', $tehsil);
        }

        if(in_array($user_role, array('Center User')) || $center_id != ''){
            $center = ($user_center == '' ? $center_id : $user_center);    
           
            $query = $query->where('g.center_idFk', '=', $center);
        }
       
        $query = $query->get();
        // dd(\DB::getQueryLog()); // Show results of log

        return $query;
        }
        public static function OutstandingBardanaIssuanceData($chapter_dates=false,$chapter_number=false,$division_id=false,$district_id=false,$tehsil_id=false,$center_id=false,$user_role=false,$user_division=false,$user_district=false,$user_tehsil=false,$user_center=false)
        {
            $wheatprocurement = WheatProcurement::Active()
        ->selectRaw("SUM(wp_50kg_bags_returned) as total_50_procured,SUM(wp_100kg_bags_returned) as total_100_procured, farmer_idFk")
        ->where('wp_type', 0)->whereBetween('wp_activity_datetime', [$chapter_dates['general_start_date'], $chapter_dates['general_end_date']])
        ->groupBy('farmer_idFk'); 

        $bardana_returned = BardanaReturnedFarmer::Active()
        ->selectRaw("SUM(brf_50kg_bags) as total_50_returned,SUM(brf_100kg_bags) as total_100_returned, farmer_idFk")
        ->where('brf_chapter', $chapter_number)
        ->groupBy('farmer_idFk'); 
        
        $query = DB::table('bardana_issuance as bi')
        ->selectRaw("f_id, f_name, f_cnic, f_phone_number, 
                    SUM(bi_50kg_bags) as total_50_issued,SUM(bi_100kg_bags) as total_100_issued, MAX(bi_activity_datetime) as max_issued_date,
                    IF(b.total_50_procured,b.total_50_procured,0) as total_50_procured,IF(b.total_100_procured,b.total_100_procured,0) as total_100_procured,
                    IF(c.total_50_returned,c.total_50_returned,0) as total_50_returned
                    ,
                    IF(c.total_100_returned,c.total_100_returned,0) as total_100_returned"

        )
        ->leftJoinSub($wheatprocurement, 'b', function ($join) {
            $join->on('bi.farmer_idFk', '=', 'b.farmer_idFk');
        })
        ->leftJoinSub($bardana_returned, 'c', function ($join) { 
            $join->on('bi.farmer_idFk', '=', 'c.farmer_idFk'); 
        })

        ->leftJoin('farmers','f_id','=','bi.farmer_idFk')
        ->whereBetween('bi_activity_datetime', [$chapter_dates['general_start_date'], $chapter_dates['general_end_date']])
        ->groupBy('bi.farmer_idFk')
        ->havingRaw("(total_50_issued-total_50_procured-total_50_returned) > 0 OR  (total_100_issued-total_100_procured-total_100_returned) > 0");

        if(in_array($user_role, array('Division User','Deputy Director', 'Sugar Commissioner')) || $division_id != ''){
            $division = ($user_division == '' ? $division_id : $user_division);
            
            $query->where('division_idFk', '=', $division);
            
        }

        if(in_array($user_role, array('District User', 'DC User')) || $district_id != ''){
            $district = ($user_district == '' ? $district_id : $user_district);
            
            $query->where('district_idFk', '=', $district);
            
        }

        if(in_array($user_role, array('Tehsil User','PLRA User')) || $tehsil_id != ''){
            $tehsil = ($user_tehsil == '' ? $tehsil_id : $user_tehsil);
            
            $query->where('tehsil_idFk', '=', $tehsil);
            
        }

        if(in_array($user_role, array('Center User')) || $center_id != ''){
            $center = ($user_center == '' ? $center_id : $user_center);

            $query->where('center_idFk', '=', $center);
        }

        return $query;
        }
        public static function BardanaStoreIssuanceDataView($chapter_dates=false,$chapter_number=false,$farmer_id=false)
        {
             $wheatprocurement = WheatProcurement::Active()
        ->selectRaw("SUM(wp_50kg_bags_returned) as total_50_procured,SUM(wp_100kg_bags_returned) as total_100_procured, farmer_idFk")
        ->where(['wp_type'=> 0,'farmer_idFk'=> $farmer_id])->whereBetween('wp_activity_datetime', [$chapter_dates['general_start_date'], $chapter_dates['general_end_date']])
        ->groupBy('farmer_idFk');  

        $bardana_returned = BardanaReturnedFarmer::Active()
        ->selectRaw("SUM(brf_50kg_bags) as total_50_returned,SUM(brf_100kg_bags) as total_100_returned, farmer_idFk")
        ->where('brf_chapter', $chapter_number)
        ->where('farmer_idFk', $farmer_id)
        ->groupBy('farmer_idFk');

         
        $query = DB::table('bardana_issuance as bi')
        ->selectRaw("f_id, f_name, f_cnic, f_phone_number, 
                    SUM(bi_50kg_bags) as total_50_issued,SUM(bi_100kg_bags) as total_100_issued, MAX(bi_activity_datetime) as max_issued_date,
                    IF(b.total_50_procured,b.total_50_procured,0) as total_50_procured,IF(b.total_100_procured,b.total_100_procured,0) as total_100_procured,
                    IF(c.total_50_returned,c.total_50_returned,0) as total_50_returned
                    ,
                    IF(c.total_100_returned,c.total_100_returned,0) as total_100_returned"
        )
        ->leftJoinSub($wheatprocurement, 'b', function ($join) {
            $join->on('bi.farmer_idFk', '=', 'b.farmer_idFk');
        })
        ->leftJoinSub($bardana_returned, 'c', function ($join) {
            $join->on('bi.farmer_idFk', '=', 'c.farmer_idFk');
        })
        ->leftJoin('farmers','f_id','=','bi.farmer_idFk')
        ->whereBetween('bi_activity_datetime', [$chapter_dates['general_start_date'], $chapter_dates['general_end_date']])
        ->where('bi.farmer_idFk',$farmer_id)
        ->groupBy('bi.farmer_idFk')->first();
        return $query;
        }
        public static function BardanaIssuanceAuditDetails($chapter_dates,$division_id=false,$district_id=false,$tehsil_id=false,$center_id=false,$user_role=false,$user_division=false,$user_district=false,$user_tehsil=false,$user_center=false)
        {
             // $query = BardanaIssuance::Active()->with(['getFarmer','getFarmer.getDistrict','getFarmer.getTehsil','getFarmer.getCenter'])
             // ->leftJoin('audits','bardana_issuance.bi_id','=','audits.auditable_id')
             // ->where(['auditable_type'=>'App\Models\Site\BardanaIssuance','event'=>'updated']);
             $query = Audit::leftJoin('bardana_issuance','bardana_issuance.bi_id','=','audits.auditable_id')
                        ->select('bi_id','farmers.f_name as f_name','farmers.f_father_name as f_father_name','divisions.name as division_name','districts.district_name as district_name','tehsils.tehsil_name as tehsil_name','centers.center_name as center_name','farmers.f_cnic as f_cnic','farmers.f_phone_number as f_phone_number','bardana_issuance.bi_50kg_bags as bi_50kg_bags','bardana_issuance.bi_100kg_bags as bi_100kg_bags','bardana_issuance.bi_cash_deposit_receipt_number as bi_cash_deposit_receipt_number','bardana_issuance.bi_mobile as bi_mobile','audits.updated_at as acitivity_datetime_update','old_values','new_values')
                ->Leftjoin('farmers','farmers.f_id','=','bardana_issuance.farmer_idFk')
                ->Leftjoin('divisions','farmers.division_idFk','=','division_id')
                ->Leftjoin('districts','farmers.district_idFk','=','district_id')
                ->Leftjoin('tehsils','farmers.tehsil_idFk','=','tehsil_id')
                ->Leftjoin('centers','farmers.center_idFk','=','center_id') 
             ->where(['auditable_type'=>'App\Models\Site\BardanaIssuance','event'=>'updated','bardana_issuance.bi_status'=>1]); 

        if(in_array($user_role, array('Division User','Deputy Director', 'Sugar Commissioner')) || $division_id != ''){
            $division = ($user_division == '' ? $division_id : $user_division);
            $query = $query->where('farmers.division_idFk', '=', $division);
             
        }

        if(in_array($user_role, array('District User', 'DC User')) || $district_id != ''){
            $district = ($user_district == '' ? $district_id : $user_district);
            $query = $query->where('farmers.district_idFk', '=', $district);
        }

        if(in_array($user_role, array('Tehsil User','PLRA User')) || $tehsil_id != ''){
            $tehsil = ($user_tehsil == '' ? $tehsil_id : $user_tehsil);
            $query = $query->where('farmers.tehsil_idFk', '=', $tehsil);
        }

        if(in_array($user_role, array('Center User')) || $center_id != ''){
            $center = ($user_center == '' ? $center_id : $user_center);
            $query = $query->where('farmers.center_idFk', '=', $center);
        } 
        return $query;
        }

    public static function Year_wise_bardana_issued($chapter_dates=false,$user_role=false)
    {
        $user_role=Session::get('user_info.user_role'); 
        $district_where =$issuance_farmer_join = $returned_farmer_join ='';
        if($user_role == 'Division User' || $user_role == 'Deputy Director' || $user_role == 'Sugar Commissioner'){
            $issuance_farmer_join = 'left join `farmers` on bardana_issuance.farmer_idFk = farmers.f_id';
            $returned_farmer_join = 'left join `farmers` on bardana_returned_farmer.farmer_idFk = farmers.f_id';
            $district_where .= ' AND division_idFk = '.Session::get('user_info.division_id'); 
        }
        if($user_role == 'District User' || $user_role == 'DC User'){
            $issuance_farmer_join = 'left join `farmers` on bardana_issuance.farmer_idFk = farmers.f_id';
            $returned_farmer_join = 'left join `farmers` on bardana_returned_farmer.farmer_idFk = farmers.f_id'; 
            $district_where .= " AND district_idFk = ".Session::get('user_info.district_id'); 
        }
        if($user_role == 'Center User'){
             
            $district_where .= " AND center_idFk = ".Session::get('user_info.center_id'); 
        }
        $query = 'SELECT IFNULL(t1.total_wheat_procured,0) - IFNULL(t2.farmer_total_returned,0) as total_wheat_procured  , t1.wp_activity_datetime, t1.year_months,t1.month_name,t1.month_number
                            FROM (select 
                                ((SUM(bi_50kg_bags)*50+SUM(bi_100kg_bags)*100)/1000) as total_wheat_procured, 
                                  bi_activity_datetime as wp_activity_datetime,
                                  DATE_FORMAT(bi_activity_datetime, "%m")as year_months,
                                  MONTHNAME(bi_activity_datetime) as month_name,
                                  MONTH(bi_activity_datetime)as month_number
                                  from bardana_issuance '.$issuance_farmer_join.'
                                  where `bi_status` = 1 '.$district_where.' and date(`bi_activity_datetime`) >= 
                                  "'.$chapter_dates['general_start_date'].'" and date(`bi_activity_datetime`) <= 
                                  "'.$chapter_dates['general_end_date'].'"
                                  GROUP BY DATE_FORMAT(bi_activity_datetime, "%m")
                                  ORDER BY DATE_FORMAT(bi_activity_datetime, "%m") ASC) as t1
                                Left join 
                                        (select 
                                        ((SUM(brf_50kg_bags)*50+SUM(brf_100kg_bags)*100)/1000) AS farmer_total_returned,DATE_FORMAT(bardana_returned_farmer.created_at, "%m") as month_returned
                                             from bardana_returned_farmer '.$returned_farmer_join.'
                                              WHERE
                                              date(bardana_returned_farmer.created_at) >= "'.$chapter_dates['general_start_date'].'" and date(bardana_returned_farmer.created_at) <= "'.$chapter_dates['general_end_date'].'"   '.$district_where.' 
                                              GROUP BY DATE_FORMAT(bardana_returned_farmer.created_at, "%m")
                                              ORDER BY DATE_FORMAT(bardana_returned_farmer.created_at, "%m") ASC) as t2
                            on t1.year_months = t2.month_returned';  
        return $query;
    }
    public static function District_wise_bardana_issued($chapter_dates=false,$district_data_ids=false,$user_role=false,$from_date=false,$to_date=false)
    {   
        $district_where ='';
        if($user_role == 'Division User' || $user_role == 'Deputy Director' || $user_role == 'Sugar Commissioner'){
            $district_where .= ' AND division_id_Fk = '.Session::get('user_info.division_id'); 
        }
        if($user_role == 'District User' || $user_role == 'DC User'){
             
            $district_where = " AND district_id = ".Session::get('user_info.district_id'); 
        }
        if($user_role == 'Center User'){
             
            $district_where = " AND center_id = ".Session::get('user_info.center_id'); 
        }
        $where_date = $where_date_returned= '';
        if ($from_date) {
            $where_date .= " AND DATE(`bi_activity_datetime`)  >= '" . $from_date . "'";
            $where_date_returned .= " AND DATE(bardana_returned_farmer.created_at)  >= '" . $from_date . "'";
        }
        if ($to_date) {
            $where_date .= "  AND DATE(`bi_activity_datetime`)  <= '" . $to_date . "'";
            $where_date_returned .= "  AND DATE(bardana_returned_farmer.created_at) <= '" . $to_date . "'";
        }
        $query = 'select districts.district_name,districts.district_id,
                    IFNULL(SUM((farmer_total_issued)/1000),0)-IFNULL(SUM((farmer_total_returned)/1000), 0) as farmer ,
                    bi_activity_datetime as f_activity_datetime,
                    DATE_FORMAT(bi_activity_datetime, "%Y-%m")as year_months,
                    MONTHNAME(bi_activity_datetime) as month_name,
                    MONTH(bi_activity_datetime)as month_number
                    from `districts` 
                    left join `farmers` on districts.district_id = farmers.district_idFk 
                    LEFT JOIN (select farmer_idFk,(SUM(bi_50kg_bags)*50+SUM(bi_100kg_bags)*100) AS farmer_total_issued,bi_activity_datetime
                        from bardana_issuance WHERE
                        `bi_status` = 1  and date(`bi_activity_datetime`) >= 
                        "'.$chapter_dates['general_start_date'].'" and date(`bi_activity_datetime`) <= 
                        "'.$chapter_dates['general_end_date'].'" "'.$where_date.'"  GROUP BY farmer_idFk) as bi ON farmers.f_id = bi.farmer_idFk
                    LEFT JOIN (select farmer_idFk,(SUM(brf_50kg_bags)*50+SUM(brf_100kg_bags)*100) AS farmer_total_returned
                                 from bardana_returned_farmer WHERE
                                  date(bardana_returned_farmer.created_at) >= "'.$chapter_dates['general_start_date'].'" and date(bardana_returned_farmer.created_at) <= "'.$chapter_dates['general_end_date'].'" "'.$where_date_returned.'"  GROUP BY farmer_idFk) as btr ON farmers.f_id = btr.farmer_idFk  
                    WHERE '.$district_data_ids.' '.$district_where.' AND province_idFk =1 GROUP BY district_id , DATE_FORMAT(bi_activity_datetime, "%Y-%m") ORDER BY district_id, DATE_FORMAT(bi_activity_datetime, "%Y-%m") ASC';  
        return $query;
    }
    public static function Year_wise_projected_comparison_bardana_issuance($chapter_dates=false,$district_data_ids=false,$user_role=false,$where_date=false,$from_date=false,$to_date=false)
    {   
        $district_where ='';
        if($user_role == 'Division User' || $user_role == 'Deputy Director' || $user_role == 'Sugar Commissioner'){
            $district_where .= ' AND division_id_Fk = '.Session::get('user_info.division_id'); 
        }
        if($user_role == 'District User' || $user_role == 'DC User'){
             
            $district_where .= " AND district_id = ".Session::get('user_info.district_id'); 
        }
        if($user_role == 'Center User'){
             
            $district_where .= " AND center_idFk = ".Session::get('user_info.center_id'); 
        }
        if ($from_date) {
            $where_date .= " AND DATE(`bardana_returned_farmer.created_at`)  >= '" . $from_date . "'";
        }
        if ($to_date) {
            $where_date .= "  AND DATE(`bardana_returned_farmer.created_at`)  <= '" . $to_date . "'";
        }
        $query = 'select districts.district_name,districts.district_id,
                    IFNULL((SUM( total_bardana_issued)/1000),0) - IFNULL((SUM( farmer_total_returned)/1000),0)  as farmer from `districts` 
                    left join `farmers` on districts.district_id = farmers.district_idFk 
                    LEFT JOIN (select farmer_idFk, 
                    (SUM(bi_50kg_bags)*50+SUM(bi_100kg_bags)*100) AS total_bardana_issued, 
                    bi_activity_datetime 
                    from bardana_issuance 
                    where `bi_status` = 1 and date(`bi_activity_datetime`) >= 
                    "'.$chapter_dates['general_start_date'].'" and date(`bi_activity_datetime`) <= 
                    "'.$chapter_dates['general_end_date'].'" GROUP BY farmer_idFk) as wp ON farmers.f_id = wp.farmer_idFk
                    LEFT JOIN (select farmer_idFk,(SUM(brf_50kg_bags)*50+SUM(brf_100kg_bags)*100) AS farmer_total_returned
                                 from bardana_returned_farmer WHERE
                                  date(bardana_returned_farmer.created_at) >= "'.$chapter_dates['general_start_date'].'" and date(bardana_returned_farmer.created_at) <= "'.$chapter_dates['general_end_date'].'" "'.$where_date.'"  GROUP BY farmer_idFk) as btr ON farmers.f_id = btr.farmer_idFk    
                    WHERE '.$district_data_ids.' '.$district_where.' AND province_idFk =1 
                    GROUP BY district_id 
                    ORDER BY district_name ASC';
        return $query;
    }
    protected $fillable = [
        "bi_id",
        "farmer_idFk",
        "bi_50kg_bags",
        "bi_100kg_bags",
        "bi_cash_deposit_receipt_number",
        "bi_picture",
        "bi_picture_original_name",
        "bi_activity_datetime",
        "bi_lat_long",
        "bi_status",
        "bi_mobile",
        "created_at",
        "created_by",
        "updated_at",
        "updated_by",
    ];

    protected $hidden = [

    ];

    protected $dates = [
        "created_at",
        "updated_at",
    ];

    public $appends = [
    ];

    /*Relations Part Start*/

    public function CreatedBy()
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }

    public function UpdatedBy()
    {
        return $this->hasOne(User::class, 'id', 'updated_by');
    }

    public function getFarmer()
    {
        return $this->belongsTo(Farmer::class, 'farmer_idFk', 'f_id');
    }


    /*Relations Part End*/

    public function getBiMobileAttribute($attribute){
        return [
            "0"=>"Dashboard",
            "1"=>"Center App",
            "3"=>"Import",
        ][$attribute];
    }

    /*Scopes Part Start*/
    
    public function scopeActive($query){
        return $query->where('bi_status',1);
    }

    public function scopeInactive($query){
        return $query->where('bi_status',0);
    }


     /*Scopes Part End*/

}
