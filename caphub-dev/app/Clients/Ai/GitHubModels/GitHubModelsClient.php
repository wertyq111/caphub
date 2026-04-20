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

    protected const API_VERSION = '2022-11-28';

    /**
     * 通过 GitHub Models 执行翻译并返回结构化结果。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendTranslationPayload(
        array $payload,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array {
        try {
            $response = $this->request()
                ->post('/chat/completions', $this->openAiRequestPayload($payload))
                ->throw()
                ->json() ?? [];

            return $this->withRetryCount($this->validateTranslationResponse(
                $this->normalizeResponsePayload($response),
                $payload,
                $enforceTargetLanguage,
                $allowPartialTranslatedDocument,
            ), 0);
        } catch (Throwable $throwable) {
            throw $this->normalizeException($throwable);
        }
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
                'request_payload' => $this->openAiRequestPayload($payload),
                'enforce_target_language' => (bool) ($request['enforce_target_language'] ?? true),
                'allow_partial_translated_document' => (bool) ($request['allow_partial_translated_document'] ?? false),
            ];
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
                    throw new RuntimeException('GitHub Models concurrent translation request did not return a response.');
                }

                $responsePayload = $response->throw()->json() ?? [];

                $results[$key] = [
                    'response' => $this->withRetryCount($this->validateTranslationResponse(
                        $this->normalizeResponsePayload($responsePayload),
                        $descriptor['payload'],
                        $descriptor['enforce_target_language'],
                        $descriptor['allow_partial_translated_document'],
                    ), 0),
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
            ->asJson()
            ->timeout($this->timeout())
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => self::API_VERSION,
            ])
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
                    ->asJson()
                    ->timeout($timeout)
                    ->withHeaders([
                        'Accept' => 'application/vnd.github+json',
                        'X-GitHub-Api-Version' => self::API_VERSION,
                    ])
                    ->withToken($apiKey)
                    ->post($translationUrl, $descriptor['request_payload']);
            }
        }, max(1, $concurrency));
    }

    protected function withRetryCount(array $response, int $retryCount): array
    {
        $response['meta'] = array_merge((array) ($response['meta'] ?? []), [
            'retry_count' => max(0, $retryCount),
        ]);

        return $response;
    }

    protected function normalizeException(Throwable $throwable): RuntimeException
    {
        $exception = $this->toRuntimeException($throwable);

        if ($this->isTransientUpstreamFailure($throwable)) {
            return new RuntimeException('upstream_timeout: '.$exception->getMessage(), (int) $exception->getCode(), $throwable);
        }

        return $exception;
    }

    protected function baseUrl(): string
    {
        $baseUrl = trim((string) config('services.github_models.base_url', ''));

        if ($baseUrl === '') {
            throw new RuntimeException('GitHub Models base URL is not configured.');
        }

        return rtrim($baseUrl, '/');
    }

    protected function apiKey(): string
    {
        $apiKey = trim((string) config('services.github_models.api_key', ''));

        if ($apiKey === '') {
            throw new RuntimeException('GitHub Models API key is not configured.');
        }

        return $apiKey;
    }

    protected function timeout(): int
    {
        return max(1, (int) config('services.github_models.timeout', 45));
    }

    protected function providerModel(): string
    {
        $model = trim((string) config('services.github_models.model', 'openai/gpt-5-mini'));

        if ($model === '') {
            throw new RuntimeException('GitHub Models model is not configured.');
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function openAiRequestPayload(array $payload): array
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
            'translated_document must preserve exactly the same keys as input_document.',
            'JSON schema:',
            json_encode([
                'translated_document' => $translatedDocumentSchema,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => self::OUTPUT_SCHEMA_VERSION,
                    'provider_model' => $this->providerModel(),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

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
            throw new RuntimeException('GitHub Models response payload must be a JSON object.');
        }

        foreach (['translated_document', 'glossary_hits', 'risk_flags', 'notes', 'meta'] as $key) {
            if (! array_key_exists($key, $response)) {
                throw new RuntimeException(sprintf('GitHub Models response payload is missing required key [%s].', $key));
            }
        }

        if (! is_array($response['translated_document'])
            || ! is_array($response['glossary_hits'])
            || ! is_array($response['risk_flags'])
            || ! is_array($response['notes'])
            || ! is_array($response['meta'])) {
            throw new RuntimeException('GitHub Models response payload has an invalid schema.');
        }

        if (! array_key_exists('schema_version', $response['meta'])) {
            throw new RuntimeException('GitHub Models response meta is missing [schema_version].');
        }

        foreach (array_keys((array) ($payload['input_document'] ?? [])) as $key) {
            if (! array_key_exists($key, $response['translated_document'])) {
                if ($allowPartialTranslatedDocument) {
                    continue;
                }

                throw new RuntimeException(sprintf('GitHub Models translated_document is missing required key [%s].', $key));
            }

            if (! is_string($response['translated_document'][$key])) {
                throw new RuntimeException(sprintf('GitHub Models translated_document key [%s] must be a string.', $key));
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
     * @param  mixed  $response
     * @return array<string, mixed>
     */
    protected function normalizeResponsePayload(mixed $response): array
    {
        if (! is_array($response)) {
            throw new RuntimeException('GitHub Models response payload must be a JSON object.');
        }

        if (array_key_exists('translated_document', $response)) {
            return $response;
        }

        $content = (string) data_get($response, 'choices.0.message.content', '');
        $decoded = $this->decodeJsonFromText($content);

        if (! is_array($decoded)) {
            throw new RuntimeException('GitHub Models completion content must be a JSON object.');
        }

        $translatedDocument = data_get($decoded, 'translated_document');
        if (! is_array($translatedDocument)) {
            throw new RuntimeException('GitHub Models completion content is missing [translated_document].');
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
     * @return array<string, mixed>
     */
    protected function decodeJsonFromText(string $text): array
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            throw new RuntimeException('GitHub Models completion content is empty.');
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
            throw new RuntimeException('GitHub Models completion content is not valid JSON.');
        }

        return $decoded;
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
                'GitHub Models translated_document key [%s] contains Chinese characters for English target output.',
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

    protected function toRuntimeException(Throwable $throwable): RuntimeException
    {
        if ($throwable instanceof RuntimeException) {
            return $throwable;
        }

        return new RuntimeException($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
    }
}
