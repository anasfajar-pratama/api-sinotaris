<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTypeActorField extends Model
{
    protected $fillable = ['document_type_actor_id', 'document_type_id', 'actor_type_id', 'profile_field_id', 'is_required', 'sort_order'];

    protected $casts = ['is_required' => 'boolean'];

    public function documentTypeActor()
    {
        return $this->belongsTo(DocumentTypeActor::class, 'document_type_actor_id');
    }

    public function profileField()
    {
        return $this->belongsTo(ProfileField::class);
    }
}