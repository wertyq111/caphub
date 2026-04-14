<?php

namespace App\Services\Translation;

use App\Clients\Ai\Hermes\HermesTranslationGateway;
use App\Clients\Ai\OpenClaw\OpenClawTranslationGateway;
use App\Enums\TranslationProvider;
use RuntimeException;

class TranslationGatewayRouter
{
    public function __construct(
        protected TranslationProviderSettings $settings,
        protected OpenClawTranslationGateway $openClawGateway,
        protected HermesTranslationGateway $hermesGateway,
    ) {}

    public function translate(array $inputDocument, array $glossaryEntries = [], array $constraints = [], ?int $jobId = null): array
    {
        return $this->activeGateway()->translate($inputDocument, $glossaryEntries, $constraints, $jobId);
    }

    public function translateLenient(array $inputDocument, array $glossaryEntries = [], array $constraints = [], ?int $jobId = null): array
    {
        return $this->activeGateway()->translateLenient($inputDocument, $glossaryEntries, $constraints, $jobId);
    }

    public function translatePayload(
        array $payload,
        ?int $jobId = null,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array {
        return $this->translatePayloadForProvider(
            $this->activeProvider(),
            $payload,
            $jobId,
            $enforceTargetLanguage,
            $allowPartialTranslatedDocument,
        );
    }

    public function translatePayloadForProvider(
        TranslationProvider $provider,
        array $payload,
        ?int $jobId = null,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array {
        return $this->gatewayForProvider($provider)->translatePayload(
            $payload,
            $jobId,
            $enforceTargetLanguage,
            $allowPartialTranslatedDocument,
        );
    }

    /**
     * @param  array<array-key, array{
     *     payload: array<string, mixed>,
     *     job_id?: int|null,
     *     context?: array<string, mixed>,
     *     enforce_target_language?: bool,
     *     allow_partial_translated_document?: bool
     * }>  $requests
     * @return array<array-key, array{response?: array<string, mixed>, exception?: \RuntimeException}>
     */
    public function translateDocumentsConcurrently(
        array $requests,
        int $concurrency = 4,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array {
        return $this->activeGateway()->translateDocumentsConcurrently(
            $requests,
            $concurrency,
            $enforceTargetLanguage,
            $allowPartialTranslatedDocument,
        );
    }

    public function activeProvider(): TranslationProvider
    {
        return $this->settings->current();
    }

    public function activeAgent(): string
    {
        return $this->agentForProvider($this->activeProvider());
    }

    public function agentForProvider(TranslationProvider $provider): string
    {
        return $this->gatewayForProvider($provider)->translationAgent();
    }

    public function timeout(?TranslationProvider $provider = null): int
    {
        return $this->gatewayForProvider($provider ?? $this->activeProvider())->timeout();
    }

    /**
     * @param  array<string, mixed>  $normalizedRequest
     */
    public function syncProvider(array $normalizedRequest): TranslationProvider
    {
        if (! $this->shouldUseSyncShortTextProvider($normalizedRequest)) {
            return $this->activeProvider();
        }

        $providerValue = trim((string) config('services.translation.sync_short_text_provider', ''));

        if ($providerValue === '') {
            return $this->activeProvider();
        }

        $provider = TranslationProvider::tryFrom($providerValue);

        if (! $provider) {
            throw new RuntimeException(sprintf(
                'Sync short text provider [%s] is invalid.',
                $providerValue,
            ));
        }

        if (! $this->settings->isConfigured($provider)) {
            throw new RuntimeException(sprintf(
                'Sync short text provider [%s] is not configured.',
                $provider->value,
            ));
        }

        return $provider;
    }

    protected function activeGateway(): OpenClawTranslationGateway|HermesTranslationGateway
    {
        return $this->gatewayForProvider($this->settings->current());
    }

    protected function gatewayForProvider(TranslationProvider $provider): OpenClawTranslationGateway|HermesTranslationGateway
    {
        return match ($provider) {
            TranslationProvider::OpenClaw => $this->openClawGateway,
            TranslationProvider::Hermes => $this->hermesGateway,
        };
    }

    /**
     * @param  array<string, mixed>  $normalizedRequest
     */
    protected function shouldUseSyncShortTextProvider(array $normalizedRequest): bool
    {
        if (($normalizedRequest['input_type'] ?? null) !== 'plain_text') {
            return false;
        }

        $text = $normalizedRequest['source_text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            return false;
        }

        return mb_strlen($text) <= max(1, (int) config('services.translation.sync_short_text_max_length', 3));
    }
}
