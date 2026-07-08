<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjbStep extends Model
{
    protected $table = 'ajb_steps';
    protected $fillable = ['ajb_case_id', 'step_number', 'step_name', 'status', 'notes', 'completed_by', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];

    public function ajbCase() { return $this->belongsTo(AjbCase::class); }
    public function completedBy() { return $this->belongsTo(User::class, 'completed_by'); }
}
