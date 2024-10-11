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

class AdminInventory extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    public const RULES = [

        "vendor_name" => 'required',
        "vendor_cnic" => 'required|min:15|max:15',
        "vendor_contact_no" => 'required|min:12|max:12',
        "product_category" => 'required',
        "product_subcategory" => 'required',
        "warehouse_location" => 'required',
    ];
    public const MESSAGES = [
        "required" => 'This field is required',
        "gt" => 'Should be greater than 0.',
        "vendor_contact_no.min" => 'The Phone Number must be at least 12 characters',
        "vendor_contact_no.max" => 'The Phone Number must be at least 12 characters',
    ];
    protected $primaryKey = 'ai_id';
    protected $table = 'inventory_admin_inventory';
    protected $fillable = [
        "ai_vendor_name",
        "ai_vendor_cnic",
        "ai_vendor_contact_no",
        "product_category_idFk",
        "product_subcategory_idFk",
        "ai_warehouse_location",
        "created_at",
        "created_by",
        "updated_at",
        "updated_by",
        "ai_status",
    ];

    public static function adminInventoryData()
    {
        $query = AdminInventory::Active()->leftJoin('product_category', 'inventory_admin_inventory.product_category_idFk', '=', 'product_category.id')
            ->leftJoin('product_sub_category', 'inventory_admin_inventory.product_subcategory_idFk', '=', 'product_sub_category.id')
            ->leftJoin('inventory_admin_code_product', 'inventory_admin_inventory.ai_id', '=', 'inventory_admin_code_product.ai_idFK')
            ->select('inventory_admin_code_product.code_no', 'inventory_admin_code_product.product_description', 'ai_vendor_contact_no', 'ai_vendor_name', 'ai_vendor_cnic', 'ai_warehouse_location', 'product_category.pc_name', 'product_sub_category.pc_sub_name', 'inventory_admin_inventory.created_at', 'ai_id');
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
