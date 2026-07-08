<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjbCertificate extends Model
{
    protected $table = 'ajb_certificates';
    protected $fillable = ['ajb_case_id', 'cert_number', 'cert_type', 'land_area', 'address', 'verified_at', 'verified_by', 'notes'];

    protected $casts = ['verified_at' => 'datetime'];

    public function ajbCase() { return $this->belongsTo(AjbCase::class); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
}
