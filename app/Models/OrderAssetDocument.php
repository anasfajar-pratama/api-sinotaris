<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAssetDocument extends Model
{
    protected $fillable = ['order_asset_id', 'document_catalog_id', 'filename', 'original_name', 'path', 'size', 'uploaded_by'];

    protected $appends = ['url'];

    public function orderAsset()
    {
        return $this->belongsTo(OrderAsset::class);
    }

    public function documentCatalog()
    {
        return $this->belongsTo(DocumentCatalog::class);
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