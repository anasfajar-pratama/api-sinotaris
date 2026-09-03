<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentFile extends Model
{
    protected $fillable = ['document_id', 'filename', 'original_name', 'path', 'type', 'size', 'uploaded_by'];

    protected $appends = ['url'];

    public function document() { return $this->belongsTo(Document::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }
}
