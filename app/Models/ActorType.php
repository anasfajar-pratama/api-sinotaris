<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActorType extends Model
{
    protected $fillable = ['key', 'label', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function documentTypeActors()
    {
        return $this->hasMany(DocumentTypeActor::class);
    }

    public function orderActors()
    {
        return $this->hasMany(OrderActor::class);
    }
}