<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Glossary extends Model
{
    protected $fillable = [
        'term',
        'source_lang',
        'target_lang',
        'standard_translation',
        'domain',
        'priority',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
        ];
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(GlossaryAlias::class);
    }

    public function forbiddenTranslations(): HasMany
    {
        return $this->hasMany(GlossaryForbiddenTranslation::class);
    }

    public function translationGlossaryHits(): HasMany
    {
        return $this->hasMany(TranslationGlossaryHit::class);
    }
}
