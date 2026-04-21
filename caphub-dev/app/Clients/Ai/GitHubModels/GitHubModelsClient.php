<?php

namespace App\Clients\Ai\GitHubModels;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GitHubModelsClient
{
    protected const OUTPUT_SCHEMA_VERSION = 'v1';

    protected const RETRYABLE_STATUS_CODES = [429, 502, 503, 504];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendTranslationPayload(
        array $payload,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array {
        $requestPayload = $this->bridgeRequestPayload(
            $this->translationPrompt($payload, $allowPartialTranslatedDocument),
        );
        $attempt = 0;
        $maxAttempts = $this->retryTimes() + 1;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                $response = $this->request()
                    ->post($this->completionPath(), $requestPayload)
                    ->throw();

                return $this->withRetryCount(
                    $this->attachTransportMeta(
                        $this->validateTranslationResponse(
                            $this->decodeJsonFromText(
                                $this->extractCompletionContent($response->json() ?? []),
                            ),
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

                if (! $this->shouldRetryAfter($throwable) || $attempt >= $maxAttempts) {
                    throw $lastException;
                }
            }
        }

        throw $lastException ?? new RuntimeException('Copilot bridge translation request failed.');
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

        $results = [];

        foreach ($requests as $key => $request) {
            try {
                $results[$key] = [
                    'response' => $this->sendTranslationPayload(
                        (array) ($request['payload'] ?? []),
                        (bool) ($request['enforce_target_language'] ?? true),
                        (bool) ($request['allow_partial_translated_document'] ?? false),
                    ),
                ];
            } catch (Throwable $throwable) {
                $results[$key] = [
                    'exception' => $this->toRuntimeException($throwable),
                ];
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function translationPrompt(array $payload, bool $allowPartialTranslatedDocument): string
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
            'Return strict JSON only without markdown or explanation.',
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

        $instructions[] = 'Payload to translate:';
        $instructions[] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return implode("\n", $instructions);
    }

    /**
     * @param  string  $prompt
     * @return array<string, mixed>
     */
    protected function bridgeRequestPayload(string $prompt): array
    {
        return [
            'model' => $this->copilotModel(),
            'prompt' => $prompt,
            'timeout' => $this->timeout(),
        ];
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->withToken($this->apiKey());
    }

    protected function completionPath(): string
    {
        return '/v1/completions';
    }

    protected function baseUrl(): string
    {
        $baseUrl = trim((string) config('services.github_models.base_url', ''));

        if ($baseUrl === '') {
            throw new RuntimeException('Copilot bridge base URL is not configured.');
        }

        return rtrim($baseUrl, '/');
    }

    protected function apiKey(): string
    {
        $apiKey = trim((string) config('services.github_models.api_key', ''));

        if ($apiKey === '') {
            throw new RuntimeException('Copilot bridge API key is not configured.');
        }

        return $apiKey;
    }

    protected function timeout(): int
    {
        return max(1, (int) config('services.github_models.timeout', 45));
    }

    protected function retryTimes(): int
    {
        return max(0, (int) config('services.github_models.retry_times', 0));
    }

    protected function providerModel(): string
    {
        $model = trim((string) config('services.github_models.model', 'openai/gpt-5-mini'));

        if ($model === '') {
            throw new RuntimeException('GitHub Models model is not configured.');
        }

        return $model;
    }

    protected function copilotModel(): string
    {
        $model = $this->providerModel();

        if (str_starts_with($model, 'openai/')) {
            return substr($model, strlen('openai/')) ?: $model;
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
     * @param  array<string, mixed>  $response
     * @return string
     */
    protected function extractCompletionContent(array $response): string
    {
        $content = data_get($response, 'content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Copilot bridge completion content is empty.');
        }

        return $content;
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
            'bridge_duration_ms' => is_numeric(data_get($httpResponse->json() ?? [], 'duration_ms'))
                ? (int) data_get($httpResponse->json() ?? [], 'duration_ms')
                : null,
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

    protected function shouldRetryAfter(Throwable $throwable): bool
    {
        if ($throwable instanceof ConnectionException) {
            return true;
        }

        if ($throwable instanceof RequestException) {
            return in_array($throwable->response?->status(), self::RETRYABLE_STATUS_CODES, true);
        }

        return false;
    }

    protected function normalizeException(Throwable $throwable): RuntimeException
    {
        if ($throwable instanceof RequestException) {
            $message = trim((string) data_get($throwable->response?->json() ?? [], 'message', ''));

            if ($message !== '') {
                return new RuntimeException($message, (int) ($throwable->response?->status() ?? 0), $throwable);
            }
        }

        if ($throwable instanceof ConnectionException) {
            return new RuntimeException('Copilot bridge connection failed: '.$throwable->getMessage(), 0, $throwable);
        }

        return $this->toRuntimeException($throwable);
    }

    protected function toRuntimeException(Throwable $throwable): RuntimeException
    {
        if ($throwable instanceof RuntimeException) {
            return $throwable;
        }

        return new RuntimeException($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
    }

    /**
     * @param  string  $text
     * @return array<string, mixed>
     */
    protected function decodeJsonFromText(string $text): array
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            throw new RuntimeException('Copilot bridge completion content is empty.');
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
            throw new RuntimeException('Copilot bridge completion content is not valid JSON.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function validateTranslationResponse(
        array $response,
        array $payload,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array {
        foreach (['translated_document', 'glossary_hits', 'risk_flags', 'notes', 'meta'] as $key) {
            if (! array_key_exists($key, $response)) {
                throw new RuntimeException(sprintf('Copilot bridge response payload is missing required key [%s].', $key));
            }
        }

        if (! is_array($response['translated_document'])
            || ! is_array($response['glossary_hits'])
            || ! is_array($response['risk_flags'])
            || ! is_array($response['notes'])
            || ! is_array($response['meta'])) {
            throw new RuntimeException('Copilot bridge response payload has an invalid schema.');
        }

        if (! array_key_exists('schema_version', $response['meta'])) {
            throw new RuntimeException('Copilot bridge response meta is missing [schema_version].');
        }

        foreach (array_keys((array) ($payload['input_document'] ?? [])) as $key) {
            if (! array_key_exists($key, $response['translated_document'])) {
                if ($allowPartialTranslatedDocument) {
                    continue;
                }

                throw new RuntimeException(sprintf('Copilot bridge translated_document is missing required key [%s].', $key));
            }

            if (! is_string($response['translated_document'][$key])) {
                throw new RuntimeException(sprintf('Copilot bridge translated_document key [%s] must be a string.', $key));
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
                'Copilot bridge translated_document key [%s] contains Chinese characters for English target output.',
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
}
