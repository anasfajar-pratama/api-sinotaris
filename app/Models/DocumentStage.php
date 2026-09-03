<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentStage extends Model
{
    protected $fillable = ['document_id', 'stage_number', 'stage_name', 'status', 'notes', 'handled_by', 'pic_id', 'completed_at', 'started_at', 'sla_days'];

    protected $casts = ['completed_at' => 'datetime', 'started_at' => 'datetime'];

    public function document() { return $this->belongsTo(Document::class); }
    public function handler() { return $this->belongsTo(User::class, 'handled_by'); }
    public function pic() { return $this->belongsTo(User::class, 'pic_id'); }
    public function documents() { return $this->hasMany(StageDocument::class, 'stage_id')->orderBy('id'); }
    public function picHistory() { return $this->hasMany(DocumentStagePic::class, 'stage_id')->with(['user', 'assigner'])->orderBy('assigned_at')->orderBy('id'); }
}
