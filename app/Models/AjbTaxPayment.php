<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjbTaxPayment extends Model
{
    protected $table = 'ajb_tax_payments';
    protected $fillable = ['ajb_case_id', 'type', 'amount', 'payment_date', 'receipt_number', 'file_path', 'status', 'notes'];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];

    public function ajbCase() { return $this->belongsTo(AjbCase::class); }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }
}
