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

class AdminIssuance extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    public const RULES = [

        "product_category" => 'required',
        "product_subcategory" => 'required',
        "code_no" => 'required',
        "division_id" => 'required',
        "district_id" => 'required',
        "tehsil_id" => 'required',
        "center_id" => 'required',
        "name" => 'required',
        "cnic" => 'required|min:15|max:15',
        "date" => 'required',
        "designation" => 'required',
        "contact_no" => 'required|min:12|max:12',
    ];
    public const MESSAGES = [
        "required" => 'This field is required',
        "gt" => 'Should be greater than 0.',

        "contact_no.min" => 'The Phone Number must be at least 12 characters',
        "contact_no.max" => 'The Phone Number must be at least 12 characters',
    ];
    protected $primaryKey = 'ai_id';
    protected $table = 'inventory_admin_issuance';
    protected $fillable = [
        "ai_name",
        "ai_cnic",
        "ai_contact_no",
        "product_category_idFk",
        "product_subcategory_idFk",
        "date",
        "designation",
        "code_no_idFk",
        "division_idFk",
        "district_idFk",
        "tehsil_idFk",
        "center_idFk",
        "created_at",
        "created_by",
        "updated_at",
        "updated_by",
        "ai_status",
    ];

    public static function adminIssuanceData()
    {
        $query = AdminIssuance::Active()->leftJoin('product_category', 'inventory_admin_issuance.product_category_idFk', '=', 'product_category.id')
            ->leftJoin('product_sub_category', 'inventory_admin_issuance.product_subcategory_idFk', '=', 'product_sub_category.id')
            ->leftJoin('districts', 'inventory_admin_issuance.district_idFk', '=', 'districts.district_id')
            ->leftJoin('divisions', 'inventory_admin_issuance.division_idFk', '=', 'divisions.division_id')
            ->leftJoin('tehsils', 'inventory_admin_issuance.tehsil_idFk', '=', 'tehsils.tehsil_id')
            ->leftJoin('centers', 'inventory_admin_issuance.center_idFk', '=', 'centers.center_id')
            ->leftJoin('inventory_admin_code_product', 'inventory_admin_issuance.code_no_idFk', '=', 'inventory_admin_code_product.aicp_id')
            ->select('inventory_admin_issuance.ai_name'
                , 'inventory_admin_issuance.ai_id'
                , 'inventory_admin_issuance.ai_cnic'
                , 'inventory_admin_issuance.date'
                , 'inventory_admin_issuance.designation'
                , 'inventory_admin_issuance.ai_status'
                , 'inventory_admin_issuance.created_at'
                , 'inventory_admin_issuance.ai_contact_no'
                , 'product_category.pc_name'
                , 'product_sub_category.pc_sub_name'
                , 'divisions.name AS division_name'
                , 'districts.district_name'
                , 'tehsils.tehsil_name'
                , 'centers.center_name'
                , 'inventory_admin_code_product.code_no');
        return $query;
    }

    public function scopeActive($query)
    {
        return $query->where('ai_status', 1);
    }

    public function scopeInactive($query)
    {
        return $query->where('ai_status', 0);
    }
}
