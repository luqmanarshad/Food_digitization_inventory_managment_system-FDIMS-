<?php

namespace App\Models\Site;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use App\Models\Site\User;
use App\Models\Site\Farmer;
use OwenIt\Auditing\Contracts\Auditable;

class BardanaIssuanceFormula extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable; 
    protected $primaryKey = 'bif_id';
    protected $table = 'bardana_issuance_formula';
    // protected $guarded = ['bi_id'];

    protected $fillable = [
        "bif_id",
        "bif_formula_type",
        "bif_year",
        "bif_status",
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

    

    /*Relations Part End*/


    /*Scopes Part Start*/
    
    public function scopeActive($query){
        return $query->where('bif_status',1);
    }

    public function scopeInactive($query){
        return $query->where('bif_status',0);
    }


     /*Scopes Part End*/

}
