<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTypeDocument extends Model
{
    protected $fillable = ['document_type_id', 'document_catalog_id', 'is_required', 'sort_order'];

    protected $casts = ['is_required' => 'boolean'];

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function documentCatalog()
    {
        return $this->belongsTo(DocumentCatalog::class);
    }
}