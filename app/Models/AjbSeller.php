<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjbSeller extends Model
{
    protected $table = 'ajb_sellers';
    protected $fillable = ['ajb_case_id', 'name', 'nik', 'npwp', 'address', 'phone', 'marital_status', 'spouse_name', 'spouse_nik'];

    public function ajbCase() { return $this->belongsTo(AjbCase::class); }
}
