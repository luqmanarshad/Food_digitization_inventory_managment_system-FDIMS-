<?php
/*
@author:Luqman Arshad
@email: luqmanarshad469@gmail.com
*/
namespace App\Models\Site;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Models\Audit;
use DB;
class BardanaInventory extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;  
     protected $primaryKey = 'bi_id';
    protected $table = 'bardana_inventory';

    public const RULES = [
                
                "fifty_kg_bags"     => 'required',
                "hundred_kg_bags"   => 'required',
                "division_idFk"     => 'required',
                "district_idFk"     => 'required',
                "tehsil_idFk"       => 'required',
                "center_idFk"       => 'required',
    ];

    public const MESSAGES = [
       "required" => 'This field is required', 
       "gt" => 'Should be greater than 0.',   
    ];

    protected $fillable = [
        "godown_idFk",
        "division_idFk",
        "district_idFk",
        "tehsil_idFk",
        "center_idFk",
        "bi_50kg_bags",
        "bi_100kg_bags",
        "bi_source",
        "created_at",
        "created_by",
        "updated_at",
        "updated_by",
       
    ];
    public static function BardanaInventoryData($division_id=false,$district_id=false,$tehsil_id=false,$center_id=false,$godown_id=false,$user_role=false,$user_center=false,$user_district=false,$user_division=false,$chapter_dates=false )
    {
        $query = BardanaInventory::Active()
            ->Leftjoin('godowns','godowns.g_id','=','bardana_inventory.godown_idFk')
            ->Leftjoin('divisions','godowns.division_idFk','=','division_id')
            ->Leftjoin('districts','godowns.district_idFk','=','district_id')
            ->Leftjoin('tehsils','godowns.tehsil_idFk','=','tehsil_id')
            ->Leftjoin('centers','godowns.center_idFk','=','center_id')
            ->select('bi_id','godowns.g_title as g_title','divisions.name as division_name','districts.district_name as district_name','tehsils.tehsil_name as tehsil_name','centers.center_name as center_name','bi_50kg_bags','bi_100kg_bags','bardana_inventory.created_at as acitivity_datetime')
            ->whereBetween('bardana_inventory.created_at', [$chapter_dates['general_start_date'], $chapter_dates['general_end_date']]);
        if(in_array($user_role, array('Center User')) || $center_id != ''){
            $center = ($user_center == '' ? $center_id : $user_center);
             
             $query = $query->where('godowns.center_idFk', '=', $center);
        }
        if(in_array($user_role, array('DC User','District User')) || $district_id != ''){
            $district = ($user_district == '' ? $district_id : $user_district);
            $query = $query->where('godowns.district_idFk', '=', $district);
        }
        if(in_array($user_role, array('Division User','Deputy Director', 'Sugar Commissioner')) || $division_id != ''){
            $division = ($user_division == '' ? $division_id : $user_division);
            $query = $query->where('godowns.division_idFk', '=', $division);
        }
        if($division_id != '' && $division_id >0)
        {
                $query = $query->where('divisions.division_id',$division_id);
        }  
 
        if($tehsil_id != '' && $tehsil_id >0)
        {
                $query = $query->where('tehsils.tehsil_id',$tehsil_id);
        }  
    
         if($godown_id != '' && $godown_id >0)
        {
                $query = $query->where('godowns.g_id',$godown_id);
        } 
       
        return $query;
    }
    public static function BardanaInventoryAuditData($division_id=false,$district_id=false,$tehsil_id=false,$center_id=false,$godown_id=false,$user_role=false,$user_center=false,$user_district=false,$user_division=false )
    {
        $query = Audit::leftJoin('bardana_inventory','bardana_inventory.bi_id','=','audits.auditable_id')
            ->Leftjoin('godowns','godowns.g_id','=','bardana_inventory.godown_idFk')
            ->Leftjoin('divisions','godowns.division_idFk','=','division_id')
            ->Leftjoin('districts','godowns.district_idFk','=','district_id')
            ->Leftjoin('tehsils','godowns.tehsil_idFk','=','tehsil_id')
            ->Leftjoin('centers','godowns.center_idFk','=','center_id')
            ->select('bi_id','godowns.g_title as g_title','divisions.name as division_name','districts.district_name as district_name','tehsils.tehsil_name as tehsil_name','centers.center_name as center_name','bi_50kg_bags','bi_100kg_bags','audits.updated_at as acitivity_datetime','old_values','new_values')
            ->where(['auditable_type'=>'App\Models\Site\BardanaInventory','event'=>'updated','bardana_inventory.bi_status'=>1]);
        if(in_array($user_role, array('Center User')) || $center_id != ''){
            $center = ($user_center == '' ? $center_id : $user_center);
             
             $query = $query->where('godowns.center_idFk', '=', $center);
        }
        if(in_array($user_role, array('DC User','District User')) || $district_id != ''){
            $district = ($user_district == '' ? $district_id : $user_district);
            $query = $query->where('godowns.district_idFk', '=', $district);
        }
        if(in_array($user_role, array('Division User','Deputy Director', 'Sugar Commissioner')) || $division_id != ''){
            $division = ($user_division == '' ? $division_id : $user_division);
            $query = $query->where('godowns.division_idFk', '=', $division);
        }
        if($division_id != '' && $division_id >0)
        {
                $query = $query->where('divisions.division_id',$division_id);
        }  
 
        if($tehsil_id != '' && $tehsil_id >0)
        {
                $query = $query->where('tehsils.tehsil_id',$tehsil_id);
        }  
    
         if($godown_id != '' && $godown_id >0)
        {
                $query = $query->where('godowns.g_id',$godown_id);
        } 
       
        return $query;
    }
     public static function BardanaInventoryFilterData($division_id=false,$district_id=false,$tehsil_id=false,$center_id=false,$godown_id=false,$user_role=false,$user_center=false,$user_district=false,$user_division=false,$chapter_dates=false)
    {
 
        $BardanaInventory =BardanaInventory::Active()->select(DB::raw('SUM(bi_50kg_bags) as total_50kg'),DB::raw('SUM(bi_100kg_bags) as total_100kg'))
        ->Leftjoin('godowns','godowns.g_id','=','bardana_inventory.godown_idFk')
        ->Leftjoin('divisions','godowns.division_idFk','=','division_id')
            ->Leftjoin('districts','godowns.district_idFk','=','district_id')
            ->Leftjoin('tehsils','godowns.tehsil_idFk','=','tehsil_id')
            ->Leftjoin('centers','godowns.center_idFk','=','center_id')
            ->whereBetween('bardana_inventory.created_at', [$chapter_dates['general_start_date'], $chapter_dates['general_end_date']]);
            if(in_array($user_role, array('Center User'))  ){
            $center = ($user_center == '' ? $center_id : $user_center);
             
             $BardanaInventory = $BardanaInventory->where('godowns.center_idFk', '=', $center);
        }
       
        if(in_array($user_role, array('DC User','District User'))  ){
            $district = ($user_district == '' ? $district_id : $user_district);
            $BardanaInventory = $BardanaInventory->where('godowns.district_idFk', '=', $district);
        }
        if($division_id || $district_id || $tehsil_id || $center_id){
        if($division_id != '' && $division_id >0){
            $BardanaInventory = $BardanaInventory->where('divisions.division_id',$division_id);
          

        }  
        if($district_id != '' && $district_id >0){
            $BardanaInventory = $BardanaInventory->where('districts.district_id',$district_id); 
        } 
        if($tehsil_id != '' && $tehsil_id >0){
            $BardanaInventory = $BardanaInventory->where('tehsils.tehsil_id',$tehsil_id);
        }  
        if($center_id != '' && $center_id >0){
            $BardanaInventory = $BardanaInventory->where('centers.center_id',$center_id);
        }
        if($godown_id != '' && $godown_id >0){

            $BardanaInventory = $BardanaInventory->where('godowns.g_id',$godown_id);
             
        }
       
    }
     

        return $BardanaInventory;
    }

    public static function BardanaInventoryEditData($id=false,$user_role=false,$user_center=false)
    {
        $bardana_inventory = BardanaInventory::Active()
        ->Leftjoin('godowns','godowns.g_id','=','bardana_inventory.godown_idFk')
        ->Leftjoin('divisions','godowns.division_idFk','=','division_id')
        ->Leftjoin('districts','godowns.district_idFk','=','district_id')
        ->Leftjoin('tehsils','godowns.tehsil_idFk','=','tehsil_id')
        ->Leftjoin('centers','godowns.center_idFk','=','center_id')
        ->select('bi_id','godowns.g_title as g_title','divisions.division_id as division_id','divisions.name as division_name','districts.district_name as district_name','districts.district_id as district_id','tehsils.tehsil_name as tehsil_name','tehsils.tehsil_id as tehsil_id','centers.center_name as center_name','centers.center_id as center_id','bi_50kg_bags','bi_100kg_bags','godowns.g_id')
        ->where('bi_id',$id)->get();
        
        return $bardana_inventory;
    }


     public function scopeActive($query){
        return $query->where('bi_status',1);
    }

    public function scopeInactive($query){
        return $query->where('bi_status',0);
    }
}
