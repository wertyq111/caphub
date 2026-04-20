<?php

namespace App\Services\Translation;

use App\Clients\Ai\GitHubModels\GitHubModelsTranslationGateway;
use App\Clients\Ai\Hermes\HermesTranslationGateway;
use App\Clients\Ai\OpenClaw\OpenClawTranslationGateway;
use App\Enums\TranslationProvider;

class TranslationGatewayRouter
{
    protected ?TranslationProvider $overrideProvider = null;

    public function __construct(
        protected TranslationProviderSettings $settings,
        protected OpenClawTranslationGateway $openClawGateway,
        protected HermesTranslationGateway $hermesGateway,
        protected GitHubModelsTranslationGateway $githubModelsGateway,
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
        return $this->overrideProvider ?? $this->settings->current();
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

    /**
     * 在单次翻译流程里临时强制指定 provider，避免短文本和长文本共享同一全局设置。
     *
     * @template TReturn
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function usingProvider(TranslationProvider $provider, callable $callback): mixed
    {
        $previousProvider = $this->overrideProvider;
        $this->overrideProvider = $provider;

        try {
            return $callback();
        } finally {
            $this->overrideProvider = $previousProvider;
        }
    }

    protected function activeGateway(): OpenClawTranslationGateway|HermesTranslationGateway|GitHubModelsTranslationGateway
    {
        return match ($this->activeProvider()) {
            TranslationProvider::OpenClaw => $this->openClawGateway,
            TranslationProvider::Hermes => $this->hermesGateway,
            TranslationProvider::GitHubModels => $this->githubModelsGateway,
        };
    }
}
