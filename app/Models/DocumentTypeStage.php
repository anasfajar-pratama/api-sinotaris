<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTypeStage extends Model
{
    protected $fillable = ['document_type_id', 'stage_number', 'stage_name', 'sla_days'];

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }
}