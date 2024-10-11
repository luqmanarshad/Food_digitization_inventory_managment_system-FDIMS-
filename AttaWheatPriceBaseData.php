<?php

namespace App\Models\Site;

use Illuminate\Database\Eloquent\Model;

class AttaWheatPriceBaseData extends Model
{

    protected $primaryKey = 'awpbd_id';
    protected $table = 'atta_wheat_price_base_data';

    protected $fillable = [
        "awpbd_id",
        "atta_price_idFk",
        "created_by",
        "updated_by",
    ];

    
   
}
