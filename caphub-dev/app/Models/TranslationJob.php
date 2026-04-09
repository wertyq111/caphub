<?php

namespace App\Models;

use App\Enums\TranslationJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TranslationJob extends Model
{
    protected $fillable = [
        'job_uuid',
        'mode',
        'status',
        'failure_reason',
        'input_type',
        'document_type',
        'source_lang',
        'target_lang',
        'source_text',
        'source_title',
        'source_summary',
        'source_body',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TranslationJobStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function result(): HasOne
    {
        return $this->hasOne(TranslationResult::class);
    }
}
