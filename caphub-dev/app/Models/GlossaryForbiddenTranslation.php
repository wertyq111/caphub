<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlossaryForbiddenTranslation extends Model
{
    protected $fillable = [
        'glossary_id',
        'forbidden_translation',
        'reason',
    ];

    public function glossary(): BelongsTo
    {
        return $this->belongsTo(Glossary::class);
    }
}
