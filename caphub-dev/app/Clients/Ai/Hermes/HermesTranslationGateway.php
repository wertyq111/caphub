<?php

namespace App\Clients\Ai\Hermes;

use App\Clients\Ai\OpenClaw\AiInvocationLogger;
use App\Clients\Ai\OpenClaw\TranslationAgentPayloadBuilder;
use RuntimeException;
use Throwable;

class HermesTranslationGateway
{
    public function __construct(
        protected HermesClient $client,
        protected TranslationAgentPayloadBuilder $payloadBuilder,
        protected AiInvocationLogger $logger,
    ) {}

    public function translate(array $inputDocument, array $glossaryEntries = [], array $constraints = [], ?int $jobId = null): array
    {
        $payload = $this->payloadBuilder->build($inputDocument, $glossaryEntries, $constraints);

        return $this->translatePayload($payload, $jobId);
    }

    public function translateLenient(array $inputDocument, array $glossaryEntries = [], array $constraints = [], ?int $jobId = null): array
    {
        $payload = $this->payloadBuilder->build($inputDocument, $glossaryEntries, $constraints);

        return $this->translatePayload($payload, $jobId, false, true);
    }

    public function translatePayload(
        array $payload,
        ?int $jobId = null,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array {
        $startedAt = microtime(true);

        try {
            $response = $this->decorateResponse(
                $this->client->sendTranslationPayload(
                    $payload,
                    $enforceTargetLanguage,
                    $allowPartialTranslatedDocument,
                ),
                $this->durationInMs($startedAt),
                'single',
            );

            $this->safeLogTranslation(
                agentName: $this->translationAgent(),
                requestPayload: $payload,
                responsePayload: $response,
                status: 'succeeded',
                durationMs: $this->durationInMs($startedAt),
                jobId: $jobId,
                context: ['provider' => 'hermes'],
            );

            return $response;
        } catch (Throwable $throwable) {
            $this->safeLogTranslation(
                agentName: $this->translationAgent(),
                requestPayload: $payload,
                responsePayload: null,
                status: 'failed',
                durationMs: $this->durationInMs($startedAt),
                jobId: $jobId,
                errorMessage: $throwable->getMessage(),
                context: ['provider' => 'hermes'],
            );

            throw $throwable;
        }
    }

    /**
     * @param  array<array-key, array{
     *     payload: array<string, mixed>,
     *     job_id?: int|null,
     *     context?: array<string, mixed>,
     *     enforce_target_language?: bool,
     *     allow_partial_translated_document?: bool
     * }>  $requests
     * @return array<array-key, array{response?: array<string, mixed>, exception?: RuntimeException}>
     */
    public function translateDocumentsConcurrently(
        array $requests,
        int $concurrency = 4,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array {
        if ($requests === []) {
            return [];
        }

        $startedAt = [];
        $clientRequests = [];

        foreach ($requests as $key => $request) {
            $startedAt[$key] = microtime(true);
            $clientRequests[$key] = [
                'payload' => (array) ($request['payload'] ?? []),
                'enforce_target_language' => (bool) ($request['enforce_target_language'] ?? $enforceTargetLanguage),
                'allow_partial_translated_document' => (bool) ($request['allow_partial_translated_document'] ?? $allowPartialTranslatedDocument),
            ];
        }

        $results = $this->client->sendTranslationPayloadsConcurrently($clientRequests, $concurrency);

        foreach ($results as $key => $result) {
            $request = $requests[$key] ?? [];
            $payload = (array) ($request['payload'] ?? []);
            $jobId = array_key_exists('job_id', $request) ? $request['job_id'] : null;
            $context = (array) ($request['context'] ?? []);
            $dispatchMode = $concurrency > 1 ? 'bounded_concurrent' : 'single';

            if (isset($result['response']) && is_array($result['response'])) {
                $result['response'] = $this->decorateResponse(
                    $result['response'],
                    $this->durationInMs($startedAt[$key] ?? microtime(true)),
                    $dispatchMode,
                );
                $results[$key] = $result;

                $this->safeLogTranslation(
                    agentName: $this->translationAgent(),
                    requestPayload: $payload,
                    responsePayload: $result['response'],
                    status: 'succeeded',
                    durationMs: $this->durationInMs($startedAt[$key] ?? microtime(true)),
                    jobId: $jobId,
                    context: array_merge($context, ['provider' => 'hermes']),
                );

                continue;
            }

            $exception = $result['exception'] ?? new RuntimeException('Hermes concurrent translation request failed.');

            $this->safeLogTranslation(
                agentName: $this->translationAgent(),
                requestPayload: $payload,
                responsePayload: null,
                status: 'failed',
                durationMs: $this->durationInMs($startedAt[$key] ?? microtime(true)),
                jobId: $jobId,
                errorMessage: $exception instanceof Throwable ? $exception->getMessage() : (string) $exception,
                context: array_merge($context, ['provider' => 'hermes']),
            );
        }

        return $results;
    }

    public function timeout(): int
    {
        return max(1, (int) config('services.hermes.timeout', 120));
    }

    public function htmlParallelism(): int
    {
        return max(1, (int) config('services.hermes.html_parallelism', 2));
    }

    public function translationAgent(): string
    {
        return (string) config('services.hermes.profile', 'chemical-news-translator');
    }

    protected function durationInMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    protected function decorateResponse(array $response, int $durationMs, string $dispatchMode): array
    {
        $response['meta'] = array_merge((array) ($response['meta'] ?? []), [
            'provider_latency_ms' => max(0, $durationMs),
            'provider_dispatch_mode' => $dispatchMode,
        ]);

        return $response;
    }

    protected function safeLogTranslation(
        string $agentName,
        array $requestPayload,
        ?array $responsePayload,
        string $status,
        int $durationMs,
        ?int $jobId = null,
        ?string $errorMessage = null,
        ?array $context = null,
    ): void {
        try {
            $this->logger->logTranslation(
                agentName: $agentName,
                requestPayload: $requestPayload,
                responsePayload: $responsePayload,
                status: $status,
                durationMs: $durationMs,
                jobId: $jobId,
                errorMessage: $errorMessage,
                context: $context,
            );
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
