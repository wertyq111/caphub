<?php

namespace App\Clients\Ai\GitHubModels;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GitHubModelsClient
{
    protected const OUTPUT_SCHEMA_VERSION = 'v1';

    protected const RETRYABLE_STATUS_CODES = [429, 502, 503, 504];

    protected const DEFAULT_RETRY_TIMES = 2;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendTranslationPayload(
        array $payload,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array {
        $requestPayload = $this->openAiRequestPayload($payload, $allowPartialTranslatedDocument);
        $attempt = 0;
        $maxAttempts = $this->retryTimes() + 1;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                $response = $this->request()
                    ->post('/chat/completions', $requestPayload)
                    ->throw();

                return $this->withRetryCount(
                    $this->attachTransportMeta(
                        $this->validateTranslationResponse(
                            $this->normalizeResponsePayload($response->json() ?? []),
                            $payload,
                            $enforceTargetLanguage,
                            $allowPartialTranslatedDocument,
                        ),
                        $response,
                    ),
                    $attempt,
                );
            } catch (Throwable $throwable) {
                $lastException = $this->normalizeException($throwable);
                $attempt++;

                if (! $this->shouldRetryAfter($throwable, $attempt, $maxAttempts)) {
                    throw $lastException;
                }

                usleep($this->retryDelayInMicroseconds($throwable, $attempt));
            }
        }

        throw $lastException ?? new RuntimeException('GitHub Copilot translation request failed.');
    }

    /**
     * @param  array<array-key, array{
     *     payload: array<string, mixed>,
     *     enforce_target_language?: bool,
     *     allow_partial_translated_document?: bool
     * }>  $requests
     * @return array<array-key, array{response?: array<string, mixed>, exception?: RuntimeException}>
     */
    public function sendTranslationPayloadsConcurrently(array $requests, int $concurrency = 2): array
    {
        if ($requests === []) {
            return [];
        }

        $descriptors = [];

        foreach ($requests as $key => $request) {
            $payload = (array) ($request['payload'] ?? []);

            $descriptors[$key] = [
                'payload' => $payload,
                'request_payload' => $this->openAiRequestPayload(
                    $payload,
                    (bool) ($request['allow_partial_translated_document'] ?? false),
                ),
                'enforce_target_language' => (bool) ($request['enforce_target_language'] ?? true),
                'allow_partial_translated_document' => (bool) ($request['allow_partial_translated_document'] ?? false),
            ];
        }

        if ($concurrency <= 1) {
            $results = [];

            foreach ($descriptors as $key => $descriptor) {
                try {
                    $results[$key] = [
                        'response' => $this->sendTranslationPayload(
                            $descriptor['payload'],
                            $descriptor['enforce_target_language'],
                            $descriptor['allow_partial_translated_document'],
                        ),
                    ];
                } catch (Throwable $throwable) {
                    $results[$key] = [
                        'exception' => $this->normalizeException($throwable),
                    ];
                }
            }

            return $results;
        }

        $responses = $this->dispatchConcurrentRequests($descriptors, $concurrency);
        $results = [];

        foreach ($descriptors as $key => $descriptor) {
            $response = $responses[(string) $key] ?? $responses[$key] ?? null;

            try {
                if ($response instanceof Throwable) {
                    throw $response;
                }

                if (! $response instanceof Response) {
                    throw new RuntimeException('GitHub Copilot concurrent translation request did not return a response.');
                }

                $results[$key] = [
                    'response' => $this->withRetryCount(
                        $this->attachTransportMeta(
                            $this->validateTranslationResponse(
                                $this->normalizeResponsePayload($response->throw()->json() ?? []),
                                $descriptor['payload'],
                                $descriptor['enforce_target_language'],
                                $descriptor['allow_partial_translated_document'],
                            ),
                            $response,
                        ),
                        0,
                    ),
                ];
            } catch (Throwable $throwable) {
                $results[$key] = [
                    'exception' => $this->normalizeException($throwable),
                ];
            }
        }

        return $results;
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->withToken($this->apiKey());
    }

    /**
     * @param  array<array-key, array{request_payload: array<string, mixed>}>  $descriptors
     * @return array<array-key, Response|Throwable>
     */
    protected function dispatchConcurrentRequests(array $descriptors, int $concurrency): array
    {
        $translationUrl = $this->baseUrl().'/chat/completions';
        $apiKey = $this->apiKey();
        $timeout = $this->timeout();

        return Http::pool(function (Pool $pool) use ($descriptors, $translationUrl, $apiKey, $timeout): void {
            foreach ($descriptors as $key => $descriptor) {
                $pool->as((string) $key)
                    ->acceptJson()
                    ->asJson()
                    ->timeout($timeout)
                    ->withToken($apiKey)
                    ->post($translationUrl, $descriptor['request_payload']);
            }
        }, max(1, $concurrency));
    }

    protected function baseUrl(): string
    {
        $baseUrl = trim((string) config('services.github_models.base_url', ''));

        if ($baseUrl === '') {
            throw new RuntimeException('GitHub Copilot base URL is not configured.');
        }

        return rtrim($baseUrl, '/');
    }

    protected function apiKey(): string
    {
        $apiKey = trim((string) config('services.github_models.api_key', ''));

        if ($apiKey === '') {
            throw new RuntimeException('GitHub Copilot API key is not configured.');
        }

        return $apiKey;
    }

    protected function timeout(): int
    {
        return max(1, (int) config('services.github_models.timeout', 120));
    }

    protected function retryTimes(): int
    {
        return max(0, (int) config('services.github_models.retry_times', self::DEFAULT_RETRY_TIMES));
    }

    protected function providerModel(): string
    {
        $model = trim((string) config('services.github_models.model', 'gpt-4o'));

        if ($model === '') {
            throw new RuntimeException('GitHub Copilot model is not configured.');
        }

        return $model;
    }

    protected function withRetryCount(array $response, int $retryCount): array
    {
        $response['meta'] = array_merge((array) ($response['meta'] ?? []), [
            'retry_count' => max(0, $retryCount),
        ]);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function openAiRequestPayload(array $payload, bool $allowPartialTranslatedDocument): array
    {
        $documentKeys = array_keys((array) ($payload['input_document'] ?? []));
        $translatedDocumentSchema = [];
        $preservePlaceholders = array_values(array_filter(
            (array) data_get($payload, 'context.constraints.preserve_placeholders', []),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        foreach ($documentKeys as $key) {
            $translatedDocumentSchema[$key] = 'string';
        }

        $instructions = [
            'You are a professional chemical industry translation assistant.',
            'Translate input_document from source_lang to target_lang.',
            'Translate every input_document value fully into the target language.',
            'When target_lang is English, translated_document must not contain any Chinese characters.',
            'Do not leave partial source-language fragments in the translation output.',
            'Return strict JSON only without markdown.',
        ];

        if ($allowPartialTranslatedDocument) {
            $instructions[] = 'If a translated_document key cannot be translated safely, you may omit that key instead of returning invalid content.';
        } else {
            $instructions[] = 'translated_document must preserve exactly the same keys as input_document.';
        }

        $instructions[] = 'JSON schema:';
        $instructions[] = json_encode([
            'translated_document' => $translatedDocumentSchema,
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => self::OUTPUT_SCHEMA_VERSION,
                'provider_model' => $this->providerModel(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($preservePlaceholders !== []) {
            $instructions[] = 'Preserve every placeholder token exactly as provided in the source text.';
            $instructions[] = 'Do not translate, remove, reorder, or alter placeholder tokens in any way.';
            $instructions[] = 'Placeholder tokens: '.implode(', ', $preservePlaceholders);
        }

        return [
            'model' => $this->providerModel(),
            'stream' => false,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => implode("\n", $instructions),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
        ];
    }

    /**
     * @param  mixed  $response
     * @return array<string, mixed>
     */
    protected function normalizeResponsePayload(mixed $response): array
    {
        if (! is_array($response)) {
            throw new RuntimeException('GitHub Copilot response payload must be a JSON object.');
        }

        if (array_key_exists('translated_document', $response)) {
            return $response;
        }

        $content = (string) data_get($response, 'choices.0.message.content', '');
        $decoded = $this->decodeJsonFromText($content);

        if (! is_array($decoded)) {
            throw new RuntimeException('GitHub Copilot completion content must be a JSON object.');
        }

        $translatedDocument = data_get($decoded, 'translated_document');

        if (! is_array($translatedDocument)) {
            throw new RuntimeException('GitHub Copilot completion content is missing [translated_document].');
        }

        return [
            'translated_document' => $translatedDocument,
            'glossary_hits' => is_array(data_get($decoded, 'glossary_hits')) ? data_get($decoded, 'glossary_hits') : [],
            'risk_flags' => is_array(data_get($decoded, 'risk_flags')) ? data_get($decoded, 'risk_flags') : [],
            'notes' => is_array(data_get($decoded, 'notes')) ? data_get($decoded, 'notes') : [],
            'meta' => [
                'schema_version' => data_get($decoded, 'meta.schema_version', self::OUTPUT_SCHEMA_VERSION),
                'provider_model' => data_get($decoded, 'meta.provider_model', data_get($response, 'model', $this->providerModel())),
            ],
        ];
    }

    /**
     * @param  string  $text
     * @return array<string, mixed>
     */
    protected function decodeJsonFromText(string $text): array
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            throw new RuntimeException('GitHub Copilot completion content is empty.');
        }

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        $decoded = json_decode($trimmed, true);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        if (! is_array($decoded)) {
            $start = strpos($trimmed, '{');
            $end = strrpos($trimmed, '}');

            if ($start !== false && $end !== false && $end > $start) {
                $slice = substr($trimmed, $start, $end - $start + 1);
                $decoded = json_decode($slice, true);

                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }
            }
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('GitHub Copilot completion content is not valid JSON.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function validateTranslationResponse(
        mixed $response,
        array $payload,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array {
        if (! is_array($response)) {
            throw new RuntimeException('GitHub Copilot response payload must be a JSON object.');
        }

        foreach (['translated_document', 'glossary_hits', 'risk_flags', 'notes', 'meta'] as $key) {
            if (! array_key_exists($key, $response)) {
                throw new RuntimeException(sprintf('GitHub Copilot response payload is missing required key [%s].', $key));
            }
        }

        if (! is_array($response['translated_document'])
            || ! is_array($response['glossary_hits'])
            || ! is_array($response['risk_flags'])
            || ! is_array($response['notes'])
            || ! is_array($response['meta'])) {
            throw new RuntimeException('GitHub Copilot response payload has an invalid schema.');
        }

        if (! array_key_exists('schema_version', $response['meta'])) {
            throw new RuntimeException('GitHub Copilot response meta is missing [schema_version].');
        }

        foreach (array_keys((array) ($payload['input_document'] ?? [])) as $key) {
            if (! array_key_exists($key, $response['translated_document'])) {
                if ($allowPartialTranslatedDocument) {
                    continue;
                }

                throw new RuntimeException(sprintf('GitHub Copilot translated_document is missing required key [%s].', $key));
            }

            if (! is_string($response['translated_document'][$key])) {
                throw new RuntimeException(sprintf('GitHub Copilot translated_document key [%s] must be a string.', $key));
            }
        }

        if ($enforceTargetLanguage) {
            $this->validateTranslatedDocumentLanguage(
                (array) $response['translated_document'],
                (string) data_get($payload, 'context.target_lang', ''),
            );
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function attachTransportMeta(array $response, Response $httpResponse): array
    {
        $stats = $httpResponse->handlerStats();

        $response['meta'] = array_merge((array) ($response['meta'] ?? []), array_filter([
            'upstream_http_status' => $httpResponse->status(),
            'upstream_connect_time_ms' => $this->handlerStatMillis($stats, 'connect_time'),
            'upstream_starttransfer_time_ms' => $this->handlerStatMillis($stats, 'starttransfer_time'),
            'upstream_total_time_ms' => $this->handlerStatMillis($stats, 'total_time'),
        ], static fn (mixed $value): bool => $value !== null));

        return $response;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    protected function handlerStatMillis(array $stats, string $key): ?int
    {
        $value = $stats[$key] ?? null;

        if (! is_numeric($value)) {
            return null;
        }

        return (int) round(((float) $value) * 1000);
    }

    /**
     * @param  array<string, mixed>  $translatedDocument
     */
    protected function validateTranslatedDocumentLanguage(array $translatedDocument, string $targetLang): void
    {
        if (! $this->expectsEnglishOutput($targetLang)) {
            return;
        }

        foreach ($translatedDocument as $key => $value) {
            if (! is_string($value) || ! $this->containsChineseCharacters($value)) {
                continue;
            }

            throw new RuntimeException(sprintf(
                'GitHub Copilot translated_document key [%s] contains Chinese characters for English target output.',
                $key,
            ));
        }
    }

    protected function expectsEnglishOutput(string $targetLang): bool
    {
        $normalized = strtolower(trim($targetLang));

        return $normalized === 'en' || str_starts_with($normalized, 'en-');
    }

    protected function containsChineseCharacters(string $value): bool
    {
        return preg_match('/\p{Han}/u', $value) === 1;
    }

    protected function normalizeException(Throwable $throwable): RuntimeException
    {
        $exception = $this->toRuntimeException($throwable);

        if ($this->isTransientUpstreamFailure($throwable)) {
            return new RuntimeException('upstream_timeout: '.$exception->getMessage(), (int) $exception->getCode(), $throwable);
        }

        return $exception;
    }

    protected function toRuntimeException(Throwable $throwable): RuntimeException
    {
        if ($throwable instanceof RuntimeException) {
            return $throwable;
        }

        return new RuntimeException($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
    }

    protected function isTransientUpstreamFailure(Throwable $throwable): bool
    {
        if ($throwable instanceof ConnectionException) {
            return str_contains(strtolower($throwable->getMessage()), 'curl error 28');
        }

        if ($throwable instanceof RequestException) {
            return in_array($throwable->response?->status(), self::RETRYABLE_STATUS_CODES, true);
        }

        return false;
    }

    protected function shouldRetryAfter(Throwable $throwable, int $attempt, int $maxAttempts): bool
    {
        return $attempt < $maxAttempts && $this->isTransientUpstreamFailure($throwable);
    }

    protected function retryDelayInMicroseconds(Throwable $throwable, int $attempt): int
    {
        if ($throwable instanceof RequestException) {
            $retryAfter = $throwable->response?->header('Retry-After');

            if (is_string($retryAfter) && $retryAfter !== '') {
                if (ctype_digit($retryAfter)) {
                    return max(0, (int) $retryAfter) * 1_000_000;
                }

                $retryAt = strtotime($retryAfter);

                if ($retryAt !== false) {
                    return max(0, $retryAt - time()) * 1_000_000;
                }
            }
        }

        return max(1, min(5, $attempt * 2)) * 1_000_000;
    }
}
