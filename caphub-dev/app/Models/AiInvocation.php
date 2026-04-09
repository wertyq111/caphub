<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInvocation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'job_id',
        'agent_name',
        'skill_version',
        'request_payload',
        'response_payload_summary',
        'status',
        'duration_ms',
        'token_usage_estimate',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'job_id' => 'integer',
            'request_payload' => 'array',
            'response_payload_summary' => 'array',
            'duration_ms' => 'integer',
            'token_usage_estimate' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function translationJob(): BelongsTo
    {
        return $this->belongsTo(TranslationJob::class, 'job_id');
    }
}
