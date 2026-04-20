<?php

namespace App\Services\Translation;

use App\Enums\TranslationProvider;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class TranslationProviderSettings
{
    protected const ACTIVE_PROVIDER_KEY = 'translation.active_provider';

    protected const CACHE_KEY = 'system-setting:translation.active_provider';

    public function current(): TranslationProvider
    {
        $value = Cache::rememberForever(self::CACHE_KEY, function (): string {
            return (string) (SystemSetting::query()
                ->where('key', self::ACTIVE_PROVIDER_KEY)
                ->value('value') ?? TranslationProvider::OpenClaw->value);
        });

        $provider = TranslationProvider::tryFrom($value);

        if ($provider instanceof TranslationProvider && $this->isManualSelectable($provider)) {
            return $provider;
        }

        return $this->defaultManualProvider();
    }

    public function setCurrent(TranslationProvider $provider): TranslationProvider
    {
        if (! $this->isManualSelectable($provider)) {
            throw new RuntimeException(sprintf(
                'Translation provider [%s] is not available for async long text jobs.',
                $provider->value,
            ));
        }

        if (! $this->isConfigured($provider)) {
            throw new RuntimeException(sprintf(
                'Translation provider [%s] is not configured.',
                $provider->value,
            ));
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => self::ACTIVE_PROVIDER_KEY],
            ['value' => $provider->value],
        );

        Cache::forever(self::CACHE_KEY, $provider->value);

        return $provider;
    }

    /**
     * @return array<int, array{key: string, configured: bool}>
     */
    public function providers(): array
    {
        return array_map(function (TranslationProvider $provider): array {
            return [
                'key' => $provider->value,
                'configured' => $this->isConfigured($provider),
            ];
        }, TranslationProvider::manualCases());
    }

    public function isConfigured(TranslationProvider $provider): bool
    {
        return match ($provider) {
            TranslationProvider::OpenClaw => $this->isOpenClawConfigured(),
            TranslationProvider::Hermes => $this->isHermesConfigured(),
            TranslationProvider::GitHubModels => $this->isGitHubModelsConfigured(),
        };
    }

    protected function isOpenClawConfigured(): bool
    {
        return trim((string) config('services.openclaw.base_url', '')) !== ''
            && trim((string) config('services.openclaw.api_key', '')) !== ''
            && trim((string) config('services.openclaw.translation_agent', '')) !== '';
    }

    protected function isHermesConfigured(): bool
    {
        return trim((string) config('services.hermes.base_url', '')) !== ''
            && trim((string) config('services.hermes.api_key', '')) !== ''
            && trim((string) config('services.hermes.profile', '')) !== ''
            && trim((string) config('services.hermes.model', '')) !== '';
    }

    protected function isGitHubModelsConfigured(): bool
    {
        return trim((string) config('services.github_models.base_url', '')) !== ''
            && trim((string) config('services.github_models.api_key', '')) !== ''
            && trim((string) config('services.github_models.model', '')) !== '';
    }

    protected function isManualSelectable(TranslationProvider $provider): bool
    {
        return in_array($provider, TranslationProvider::manualCases(), true);
    }

    protected function defaultManualProvider(): TranslationProvider
    {
        foreach (TranslationProvider::manualCases() as $provider) {
            if ($this->isConfigured($provider)) {
                return $provider;
            }
        }

        return TranslationProvider::OpenClaw;
    }
}
