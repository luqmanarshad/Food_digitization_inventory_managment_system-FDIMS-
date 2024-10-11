<?php

namespace App\Models\Site;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use DB;
class AdminInventoryCodeProduct extends Model
{
     protected $primaryKey = 'aicp_id';
    protected $table = 'inventory_admin_code_product';
    public const MESSAGES = [
        "required" => 'This field is required',
        "unique" => "This code has already been taken",
        "different" => "This code must be different",
    ];
    public const RULES = [
//        "product_category" => 'required',
//        "product_subcategory" => 'required',
//        "code_no" => 'required'
    ] ;
    protected $fillable = [
        "aicp_id",
        "ai_idFK",
        "product_category_idFk",
        "product_subcategory_idFk",
        "code_no",
        "product_description",
        "aicp_status",
        "created_at",
        "created_by",
        "updated_at",
        "updated_by",
    ];

     public function scopeActive($query){
        return $query->where('aicp_status',1);
    }

    public function scopeInactive($query){
        return $query->where('aicp_status',0);
    }
}
