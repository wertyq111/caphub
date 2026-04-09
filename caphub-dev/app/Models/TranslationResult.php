<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslationResult extends Model
{
    protected $fillable = [
        'translation_job_id',
        'translated_document_json',
        'risk_payload',
        'notes_payload',
        'meta_payload',
    ];

    protected function casts(): array
    {
        return [
            'translated_document_json' => 'array',
            'risk_payload' => 'array',
            'notes_payload' => 'array',
            'meta_payload' => 'array',
        ];
    }

    public function translationJob(): BelongsTo
    {
        return $this->belongsTo(TranslationJob::class);
    }
}
