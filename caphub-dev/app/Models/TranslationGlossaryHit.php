<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslationGlossaryHit extends Model
{
    protected $fillable = [
        'job_id',
        'glossary_id',
        'source_term',
        'chosen_translation',
        'match_text',
        'match_position',
        'hit_source',
    ];

    protected function casts(): array
    {
        return [
            'job_id' => 'integer',
            'glossary_id' => 'integer',
            'match_position' => 'array',
        ];
    }

    public function glossary(): BelongsTo
    {
        return $this->belongsTo(Glossary::class);
    }

    public function translationJob(): BelongsTo
    {
        return $this->belongsTo(TranslationJob::class, 'job_id');
    }
}
