<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderActorDocument extends Model
{
    protected $fillable = ['order_actor_id', 'document_catalog_id', 'filename', 'original_name', 'path', 'size', 'uploaded_by'];

    public function orderActor()
    {
        return $this->belongsTo(OrderActor::class);
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