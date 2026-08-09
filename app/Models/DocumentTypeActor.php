<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTypeActor extends Model
{
    protected $fillable = ['document_type_id', 'actor_type_id', 'is_required', 'sort_order', 'label_override'];

    protected $casts = ['is_required' => 'boolean'];

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function actorType()
    {
        return $this->belongsTo(ActorType::class);
    }

    public function fields()
    {
        return $this->hasMany(DocumentTypeActorField::class, 'document_type_actor_id')->orderBy('sort_order');
    }

    public function documents()
    {
        return $this->hasMany(DocumentTypeActorDocument::class, 'document_type_actor_id')->orderBy('sort_order');
    }
}