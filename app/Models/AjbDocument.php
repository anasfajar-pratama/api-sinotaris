<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjbDocument extends Model
{
    protected $table = 'ajb_documents';
    protected $fillable = ['ajb_case_id', 'doc_type', 'filename', 'path', 'uploaded_by'];

    public function ajbCase() { return $this->belongsTo(AjbCase::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }
}
