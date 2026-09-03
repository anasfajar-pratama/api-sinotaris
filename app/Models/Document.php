<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'doc_number',
        'type_id',
        'client_id',
        'created_by',
        'title',
        'description',
        'status',
        'current_stage',
        'priority',
        'deadline',
        'notes',
        'tracking_code',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_REVIEW = 'review';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    public static function generateDocNumber(): string
    {
        $prefix = 'DOC';
        $year = now()->year;
        $month = str_pad(now()->month, 2, '0', STR_PAD_LEFT);
        $count = self::withTrashed()->whereYear('created_at', $year)->whereMonth('created_at', now()->month)->count() + 1;

        do {
            $docNumber = "{$prefix}/{$year}/{$month}/" . str_pad($count, 4, '0', STR_PAD_LEFT);
            $exists = self::withTrashed()->where('doc_number', $docNumber)->exists();
            if ($exists) $count++;
        } while ($exists);

        return $docNumber;
    }

    public static function generateTrackingCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid()), 0, 10));
        } while (self::withTrashed()->where('tracking_code', $code)->exists());
        return $code;
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'type_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files()
    {
        return $this->hasMany(DocumentFile::class);
    }

    public function stages()
    {
        return $this->hasMany(DocumentStage::class)->orderBy('stage_number');
    }

    public function ajbCase()
    {
        return $this->hasOne(AjbCase::class);
    }

    public function actors()
    {
        return $this->hasMany(OrderActor::class)->orderBy('sort_order');
    }

    public function assets()
    {
        return $this->hasMany(OrderAsset::class)->orderBy('sort_order');
    }

    public function orderDocuments()
    {
        return $this->hasMany(OrderDocument::class)->orderBy('id');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'in_progress' => 'blue',
            'review' => 'yellow',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
