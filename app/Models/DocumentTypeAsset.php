<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTypeAsset extends Model
{
    protected $fillable = ['document_type_id', 'asset_type_id', 'is_required', 'sort_order'];

    protected $casts = ['is_required' => 'boolean'];

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function assetType()
    {
        return $this->belongsTo(AssetType::class);
    }
}