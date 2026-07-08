<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'nik',
        'name',
        'phone',
        'email',
        'address',
        'npwp',
        'birth_date',
        'gender',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function getActiveDocumentsCountAttribute(): int
    {
        return $this->documents()->whereIn('status', ['draft', 'in_progress', 'review'])->count();
    }

    public function getCompletedDocumentsCountAttribute(): int
    {
        return $this->documents()->where('status', 'completed')->count();
    }
}
