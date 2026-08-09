<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderActor extends Model
{
    protected $fillable = ['document_id', 'actor_type_id', 'data', 'sort_order'];

    protected $casts = ['data' => 'array'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function actorType()
    {
        return $this->belongsTo(ActorType::class);
    }

    public function documents()
    {
        return $this->hasMany(OrderActorDocument::class);
    }
}