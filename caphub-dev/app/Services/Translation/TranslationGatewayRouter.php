<?php

namespace App\Services\Translation;

use App\Clients\Ai\Hermes\HermesTranslationGateway;
use App\Clients\Ai\OpenClaw\OpenClawTranslationGateway;
use App\Enums\TranslationProvider;

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
        return $this->activeGateway()->translatePayload(
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
        return $this->activeGateway()->translationAgent();
    }

    public function timeout(): int
    {
        return $this->activeGateway()->timeout();
    }

    public function htmlParallelism(): int
    {
        return $this->activeGateway()->htmlParallelism();
    }

    protected function activeGateway(): OpenClawTranslationGateway|HermesTranslationGateway
    {
        return match ($this->settings->current()) {
            TranslationProvider::OpenClaw => $this->openClawGateway,
            TranslationProvider::Hermes => $this->hermesGateway,
        };
    }
}
