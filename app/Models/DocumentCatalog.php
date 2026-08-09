<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentCatalog extends Model
{
    protected $fillable = ['key', 'label', 'category', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}