<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StageDocument extends Model
{
    protected $fillable = ['stage_id', 'filename', 'original_name', 'path', 'size', 'uploaded_by'];

    protected $appends = ['url'];

    public function stage()
    {
        return $this->belongsTo(DocumentStage::class, 'stage_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }
}
