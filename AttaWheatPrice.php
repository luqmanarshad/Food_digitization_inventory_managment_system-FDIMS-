<?php

namespace App\Models\Site;

use Illuminate\Database\Eloquent\Model;

class AttaWheatPrice extends Model
{

    protected $primaryKey = 'awp_id';
    protected $table = 'atta_wheat_prices';

    protected $fillable = [
        "awp_id",
        "awp_date",
        "division_id_Fk",
        "district_idFk",
        "mill_idFk",
        "awp_commodity_type",
        "awp_type",
        "awp_wheat_price",
        "awp_wheat_price_mandi",
        "awp_10kg_atta_price",
        "awp_15kg_atta_price",
        "awp_20kg_atta_price",
        "awp_79kg_atta_price",
        "awp_80kg_maida_price",
        "awp_50kg_suji_price",
        "awp_34kg_bran_price",
        "awp_brand",
        "atta_wheat_price_base_id",
        "status",
        "created_by",
        "updated_by",
    ];

    public const RULES = [
        "commodity"  => '',
        "type"  => 'required',
        "date"  => 'required',
        
     
    ];
    
    public static function wheatPriceData($division=false,$district=false,$commodity=false,$type=false,$user_role=false,$user_district=false,$user_division=false )
    {
        //4th parameter in following call sent after added f_chapter in farmers table, now chapter_dates has no work in this function call
        $query = AttaWheatPrice::Select('atta_wheat_prices.*','districts.district_name','divisions.name', 'fm_name')
        ->leftjoin('divisions','division_id_Fk','division_id')
        ->leftjoin('districts','district_idFk','district_id')
        ->leftjoin('flour_mills','mill_idFk','fm_id')
        ->where('atta_wheat_prices.status',1);

        
        if(in_array($user_role, array('Division User','Deputy Director', 'Sugar Commissioner')) || ($division != '' && $division > 0)){
            $division = ($user_division == '' ? $division : $user_division);  
            $query = $query->where('atta_wheat_prices.division_id_Fk', '=', $division);
        }
        if(in_array($user_role, array('DC User','District User')) || ($district != '' && $district >0)){
            $district = ($user_district == '' ? $district : $user_district);
            $query = $query->where('atta_wheat_prices.district_idFk', '=', $district);
        }
         
        if($commodity != ''){ 
            $query =$query->where('awp_commodity_type',$commodity);
        }
        if($type != ''){
            $query =$query->where('awp_type',$type);
        }
        
        return $query;
    }

    public const MESSAGES = [
                "required" => 'This field is required',
                 
    ];
    public function division()
    {
        return $this->belongsTo(Division::class);
    }
    public function district()
    {
        return $this->belongsTo(District::class);
    }
    public function flourMill()
    {
        return $this->belongsTo(FlourMill::class, 'mill_idFk', 'fm_id');
    }
    

    public function scopeActive($query){
        return $query->where('status',1);
    }

    public function scopeInactive($query){
        return $query->where('status',0);
    }
    
    public function getAwpCommodityTypeAttribute($attribute){
        return [
            "0"=>"Atta",
            "1"=>"Wheat",
            "2"=>"Maida",
            "3"=>"Suji",
            "4"=>"Bran"
        ][$attribute];
    }

    public function getAwpTypeAttribute($attribute){
        return [
            "0"=>"Government",
            "1"=>"Private"
        ][$attribute];
    }
}
