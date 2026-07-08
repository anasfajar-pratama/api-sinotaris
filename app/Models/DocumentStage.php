<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentStage extends Model
{
    protected $fillable = ['document_id', 'stage_number', 'stage_name', 'status', 'notes', 'handled_by', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];

    public function document() { return $this->belongsTo(Document::class); }
    public function handler() { return $this->belongsTo(User::class, 'handled_by'); }
}
