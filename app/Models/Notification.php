<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = ['user_id', 'document_id', 'type', 'title', 'message', 'is_read', 'sent_at'];

    protected $casts = ['is_read' => 'boolean', 'sent_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function document() { return $this->belongsTo(Document::class); }
}
