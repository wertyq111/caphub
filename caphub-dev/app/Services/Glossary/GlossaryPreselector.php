<?php

namespace App\Services\Glossary;

use App\Models\Glossary;
use Illuminate\Database\Eloquent\Collection;

class GlossaryPreselector
{
    public function preselect(string $sourceLang, string $targetLang, ?string $domain = null): Collection
    {
        return Glossary::query()
            ->with(['aliases', 'forbiddenTranslations'])
            ->where('source_lang', $sourceLang)
            ->where('target_lang', $targetLang)
            ->where('status', 'active')
            ->when($domain !== null, static function ($query) use ($domain): void {
                $query->where('domain', $domain);
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }
}
