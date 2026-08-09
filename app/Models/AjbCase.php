<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AjbCase extends Model
{
    use HasFactory;

    protected $table = 'ajb_cases';

    protected $fillable = [
        'document_id',
        'case_number',
        'source_type',
        'current_step',
        'status',
        'notes',
    ];

    const SOURCE_BANK = 'bank';
    const SOURCE_NOTARIS = 'notaris';
    const SOURCE_WALK_IN = 'walk_in';

    const STEPS = [
        1 => 'Data Masuk',
        2 => 'Cek Sertifikat',
        3 => 'Pembayaran Pajak (BPHTB & SSP)',
        4 => 'Pembuatan Akta Jual Beli',
        5 => 'Input Akta AJB ke Sistem BPN',
        6 => 'Pengiriman Dokumen ke BPN',
        7 => 'Proses di BPN',
        8 => 'Pembayaran SPS & Selesai',
    ];

    public static function generateCaseNumber(): string
    {
        $prefix = 'AJB';
        $year = now()->year;
        $month = str_pad(now()->month, 2, '0', STR_PAD_LEFT);
        $count = self::whereYear('created_at', $year)->count() + 1;
        return "{$prefix}/{$year}/{$month}/" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function taxPayments()
    {
        return $this->hasMany(AjbTaxPayment::class);
    }

    public function documents()
    {
        return $this->hasMany(AjbDocument::class);
    }

    public function steps()
    {
        return $this->hasMany(AjbStep::class)->orderBy('step_number');
    }

    public function bpnSubmission()
    {
        return $this->hasOne(AjbBpnSubmission::class);
    }

    public function getProgressPercentageAttribute(): int
    {
        return (int) round(($this->current_step / 8) * 100);
    }
}
