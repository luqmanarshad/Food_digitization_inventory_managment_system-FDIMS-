<?php

namespace App\Models\Site;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $primaryKey = 'al_id';
    protected $table = 'api_logs';
    protected $guarded = ['al_id'];

    protected $fillable = [
        "al_json",
        "al_route",
        "created_at",
    ];

    protected $hidden = [

    ];

    protected $dates = [
        "created_at",
    ];

}
