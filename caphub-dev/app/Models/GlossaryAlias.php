<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlossaryAlias extends Model
{
    protected $fillable = [
        'glossary_id',
        'alias',
        'match_type',
    ];

    public function glossary(): BelongsTo
    {
        return $this->belongsTo(Glossary::class);
    }
}
