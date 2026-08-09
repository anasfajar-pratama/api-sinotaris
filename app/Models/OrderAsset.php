<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAsset extends Model
{
    protected $fillable = ['document_id', 'asset_type_id', 'data', 'sort_order'];

    protected $casts = ['data' => 'array'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function assetType()
    {
        return $this->belongsTo(AssetType::class);
    }

    public function documents()
    {
        return $this->hasMany(OrderAssetDocument::class);
    }
}