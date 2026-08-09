<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileField extends Model
{
    protected $fillable = ['key', 'label', 'data_type', 'options', 'is_active'];

    protected $casts = ['options' => 'array', 'is_active' => 'boolean'];
}