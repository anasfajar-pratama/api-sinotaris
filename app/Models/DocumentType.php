<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sla_days', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function documents()
    {
        return $this->hasMany(Document::class, 'type_id');
    }
}
