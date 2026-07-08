<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjbBpnSubmission extends Model
{
    protected $table = 'ajb_bpn_submissions';
    protected $fillable = ['ajb_case_id', 'spa_number', 'submission_date', 'sps_number', 'sps_amount', 'payment_date', 'status', 'notes'];

    protected $casts = [
        'submission_date' => 'date',
        'payment_date'    => 'date',
        'sps_amount'      => 'decimal:2',
    ];

    public function ajbCase() { return $this->belongsTo(AjbCase::class); }
}
