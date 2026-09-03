<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentStagePic extends Model
{
    protected $fillable = ['stage_id', 'user_id', 'assigned_by', 'action', 'note', 'assigned_at'];

    protected $casts = ['assigned_at' => 'datetime'];

    public function stage()
    {
        return $this->belongsTo(DocumentStage::class, 'stage_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
