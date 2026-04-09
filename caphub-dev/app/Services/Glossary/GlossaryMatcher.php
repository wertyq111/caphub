<?php

namespace App\Services\Glossary;

use App\Models\Glossary;
use App\Models\GlossaryAlias;
use App\Models\TranslationGlossaryHit;
use InvalidArgumentException;

class GlossaryMatcher
{
    public function __construct(
        private readonly GlossaryPreselector $preselector,
    ) {}

    /**
     * @return array<int, TranslationGlossaryHit>
     */
    public function match(string $text, string $sourceLang, string $targetLang, ?string $domain = null, ?int $translationJobId = null): array
    {
        return $this->preselector->preselect($sourceLang, $targetLang, $domain)
            ->flatMap(function (Glossary $entry) use ($text, $translationJobId): array {
                return $this->matchEntry($entry, $text, $translationJobId);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, TranslationGlossaryHit>
     */
    protected function matchEntry(Glossary $entry, string $text, ?int $translationJobId = null): array
    {
        $candidates = collect([[
            'text' => $entry->term,
            'match_type' => 'exact',
        ]])
            ->merge($entry->aliases->map(static fn (GlossaryAlias $alias): array => [
                'text' => $alias->alias,
                'match_type' => $alias->match_type ?? 'exact',
            ]))
            ->filter(static fn (array $candidate): bool => $candidate['text'] !== '')
            ->unique()
            ->values();

        $hits = [];

        foreach ($candidates as $candidate) {
            foreach ($this->findMatchPositions($text, $candidate['text'], $candidate['match_type']) as [$matchStart, $matchEnd]) {
                $hits[] = new TranslationGlossaryHit([
                    'job_id' => $translationJobId,
                    'glossary_id' => $entry->id,
                    'source_term' => $entry->term,
                    'chosen_translation' => $entry->standard_translation,
                    'match_text' => mb_substr($text, $matchStart, $matchEnd - $matchStart),
                    'match_position' => [
                        'start' => $matchStart,
                        'end' => $matchEnd,
                    ],
                    'hit_source' => 'system',
                ]);
            }
        }

        return $hits;
    }

    /**
     * @return array<int, array{0:int,1:int}>
     */
    protected function findMatchPositions(string $text, string $candidate, string $matchType): array
    {
        return match ($matchType) {
            'regex-lite' => $this->findRegexMatchPositions($text, $candidate),
            'phrase' => $this->findLiteralMatchPositions($text, $candidate),
            default => $this->findExactMatchPositions($text, $candidate),
        };
    }

    /**
     * @return array<int, array{0:int,1:int}>
     */
    protected function findLiteralMatchPositions(string $text, string $candidate): array
    {
        $positions = [];
        $offset = 0;
        $length = mb_strlen($candidate);

        while (($position = mb_stripos($text, $candidate, $offset)) !== false) {
            $positions[] = [$position, $position + $length];
            $offset = $position + $length;
        }

        return $positions;
    }

    /**
     * @return array<int, array{0:int,1:int}>
     */
    protected function findExactMatchPositions(string $text, string $candidate): array
    {
        if (! preg_match('/^[A-Za-z0-9 _-]+$/', $candidate)) {
            return $this->findLiteralMatchPositions($text, $candidate);
        }

        return $this->findRegexMatchPositions($text, '(?<![\pL\pN])'.preg_quote($candidate, '/').'(?![\pL\pN])');
    }

    /**
     * @return array<int, array{0:int,1:int}>
     */
    protected function findRegexMatchPositions(string $text, string $pattern): array
    {
        $matches = [];
        $positions = [];
        $regex = $this->compileRegexPattern($pattern);

        if (preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        foreach ($matches[0] as [$matchText, $byteOffset]) {
            $matchStart = $this->byteOffsetToCharacterOffset($text, $byteOffset);
            $matchEnd = $matchStart + mb_strlen($matchText);

            $positions[] = [$matchStart, $matchEnd];
        }

        return $positions;
    }

    protected function byteOffsetToCharacterOffset(string $text, int $byteOffset): int
    {
        return mb_strlen(substr($text, 0, $byteOffset));
    }

    protected function compileRegexPattern(string $pattern): string
    {
        $regex = '~'.$pattern.'~iu';

        if (@preg_match($regex, '') === false) {
            throw new InvalidArgumentException(sprintf(
                'Invalid regex-lite glossary alias pattern [%s].',
                $pattern,
            ));
        }

        return $regex;
    }
}
