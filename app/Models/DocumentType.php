<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sla_days', 'is_active', 'category'];

    protected $casts = ['is_active' => 'boolean'];

    public function documents()
    {
        return $this->hasMany(Document::class, 'type_id');
    }

    public function actorDefinitions()
    {
        return $this->hasMany(DocumentTypeActor::class)->orderBy('sort_order');
    }

    public function assetDefinitions()
    {
        return $this->hasMany(DocumentTypeAsset::class)->orderBy('sort_order');
    }

    public function stages()
    {
        return $this->hasMany(DocumentTypeStage::class)->orderBy('stage_number');
    }

    public function requiredDocuments()
    {
        return $this->hasMany(DocumentTypeDocument::class)->orderBy('sort_order');
    }
}
