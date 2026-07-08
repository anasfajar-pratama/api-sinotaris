<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjbBuyer extends Model
{
    protected $table = 'ajb_buyers';
    protected $fillable = ['ajb_case_id', 'name', 'nik', 'npwp', 'address', 'phone'];

    public function ajbCase() { return $this->belongsTo(AjbCase::class); }
}
