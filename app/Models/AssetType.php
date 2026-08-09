<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetType extends Model
{
    protected $fillable = ['key', 'label', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function documentTypeAssets()
    {
        return $this->hasMany(DocumentTypeAsset::class);
    }

    public function orderAssets()
    {
        return $this->hasMany(OrderAsset::class);
    }
}