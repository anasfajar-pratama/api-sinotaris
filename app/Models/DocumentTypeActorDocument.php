<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTypeActorDocument extends Model
{
    protected $fillable = ['document_type_actor_id', 'document_type_id', 'actor_type_id', 'document_catalog_id', 'is_required', 'sort_order'];

    protected $casts = ['is_required' => 'boolean'];

    public function documentTypeActor()
    {
        return $this->belongsTo(DocumentTypeActor::class, 'document_type_actor_id');
    }

    public function documentCatalog()
    {
        return $this->belongsTo(DocumentCatalog::class);
    }
}