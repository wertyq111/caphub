<?php

namespace App\Clients\Ai\OpenClaw;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenClawClient
{
    protected const OUTPUT_SCHEMA_VERSION = 'v1';

    protected const DEFAULT_RETRY_TIMES = 1;

    /**
     * 初始化 OpenClaw 客户端依赖，参数：$payloadBuilder 翻译请求载荷构建器。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function __construct(
        protected TranslationAgentPayloadBuilder $payloadBuilder,
    ) {}

    /**
     * 执行翻译请求并返回结构化结果，参数：输入文档、术语项与约束条件。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function translate(array $inputDocument, array $glossaryEntries = [], array $constraints = []): array
    {
        $payload = $this->payloadBuilder->build($inputDocument, $glossaryEntries, $constraints);

        return $this->sendTranslationPayload($payload);
    }

    /**
     * 执行宽松翻译请求，仅校验结构合法性，允许调用方自行处理字段级语言残留。
     * @since 2026-04-03
     * @author zhouxufeng
     */
    public function translateLenient(array $inputDocument, array $glossaryEntries = [], array $constraints = []): array
    {
        $payload = $this->payloadBuilder->build($inputDocument, $glossaryEntries, $constraints);

        return $this->sendTranslationPayload($payload, false, true);
    }

    /**
     * 发送翻译载荷到 OpenClaw 并校验响应，参数：$payload 翻译请求体。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function sendTranslationPayload(
        array $payload,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array
    {
        $requestPayload = $this->openAiRequestPayload($payload);
        $attempt = 0;
        $maxAttempts = $this->retryTimes() + 1;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                $response = $this->request()
                    ->post($this->translationPath(), $requestPayload)
                    ->throw()
                    ->json() ?? [];

                return $this->validateTranslationResponse(
                    $this->normalizeResponsePayload($response),
                    $payload,
                    $enforceTargetLanguage,
                    $allowPartialTranslatedDocument,
                );
            } catch (RuntimeException $exception) {
                $lastException = $exception;
                $attempt++;

                if (! $this->shouldRetryAfter($exception) || $attempt >= $maxAttempts) {
                    throw $exception;
                }
            }
        }

        throw $lastException ?? new RuntimeException('OpenClaw translation request failed.');
    }

    /**
     * 创建 HTTP 请求实例，参数：无（内部读取配置）。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function request(): PendingRequest
    {
        $baseUrl = $this->baseUrl();
        $apiKey = $this->apiKey();
        $timeout = $this->timeout();

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->withToken($apiKey);
    }

    /**
     * 获取翻译接口路径，参数：无。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function translationPath(): string
    {
        return '/v1/chat/completions';
    }

    /**
     * 读取并校验 OpenClaw 基础地址，参数：无。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function baseUrl(): string
    {
        $baseUrl = trim((string) config('services.openclaw.base_url', ''));

        if ($baseUrl === '') {
            throw new RuntimeException('OpenClaw base URL is not configured.');
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * 读取并校验 OpenClaw 鉴权 Token，参数：无。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function apiKey(): string
    {
        $apiKey = trim((string) config('services.openclaw.api_key', ''));

        if ($apiKey === '') {
            throw new RuntimeException('OpenClaw API key is not configured.');
        }

        return $apiKey;
    }

    /**
     * 获取请求超时时间，参数：无。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function timeout(): int
    {
        return max(1, (int) config('services.openclaw.timeout', 30));
    }

    /**
     * 获取解析/校验类失败时的重试次数，参数：无。
     * @since 2026-04-03
     * @author zhouxufeng
     */
    protected function retryTimes(): int
    {
        return max(0, (int) config('services.openclaw.retry_times', self::DEFAULT_RETRY_TIMES));
    }

    /**
     * 读取并校验翻译 Agent 名称，参数：无。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function translationAgent(): string
    {
        $translationAgent = trim((string) config('services.openclaw.translation_agent', 'chemical-news-translator'));

        if ($translationAgent === '') {
            throw new RuntimeException('OpenClaw translation agent is not configured.');
        }

        return $translationAgent;
    }

    /**
     * 校验翻译响应结构完整性，参数：$response 响应体，$payload 请求体。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function validateTranslationResponse(
        mixed $response,
        array $payload,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array
    {
        if (! is_array($response)) {
            throw new RuntimeException('OpenClaw response payload must be a JSON object.');
        }

        $requiredKeys = [
            'translated_document',
            'glossary_hits',
            'risk_flags',
            'notes',
            'meta',
        ];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $response)) {
                throw new RuntimeException(sprintf(
                    'OpenClaw response payload is missing required key [%s].',
                    $key,
                ));
            }
        }

        if (! is_array($response['translated_document'])
            || ! is_array($response['glossary_hits'])
            || ! is_array($response['risk_flags'])
            || ! is_array($response['notes'])
            || ! is_array($response['meta'])
        ) {
            throw new RuntimeException('OpenClaw response payload has an invalid schema.');
        }

        if (! array_key_exists('schema_version', $response['meta'])) {
            throw new RuntimeException('OpenClaw response meta is missing [schema_version].');
        }

        $expectedDocumentKeys = array_keys((array) ($payload['input_document'] ?? []));

        foreach ($expectedDocumentKeys as $key) {
            if (! array_key_exists($key, $response['translated_document'])) {
                if ($allowPartialTranslatedDocument) {
                    continue;
                }

                throw new RuntimeException(sprintf(
                    'OpenClaw translated_document is missing required key [%s].',
                    $key,
                ));
            }

            if (! is_string($response['translated_document'][$key])) {
                throw new RuntimeException(sprintf(
                    'OpenClaw translated_document key [%s] must be a string.',
                    $key,
                ));
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
     * 归一化 OpenClaw 响应为内部标准结构，参数：$response 原始响应体。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function normalizeResponsePayload(mixed $response): array
    {
        if (! is_array($response)) {
            throw new RuntimeException('OpenClaw response payload must be a JSON object.');
        }

        if (array_key_exists('translated_document', $response)) {
            return $response;
        }

        $content = (string) data_get($response, 'choices.0.message.content', '');
        $decoded = $this->decodeJsonFromText($content);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenClaw completion content must be a JSON object.');
        }

        $translatedDocument = data_get($decoded, 'translated_document');
        if (! is_array($translatedDocument)) {
            throw new RuntimeException('OpenClaw completion content is missing [translated_document].');
        }

        return [
            'translated_document' => $translatedDocument,
            'glossary_hits' => is_array(data_get($decoded, 'glossary_hits')) ? data_get($decoded, 'glossary_hits') : [],
            'risk_flags' => is_array(data_get($decoded, 'risk_flags')) ? data_get($decoded, 'risk_flags') : [],
            'notes' => is_array(data_get($decoded, 'notes')) ? data_get($decoded, 'notes') : [],
            'meta' => [
                'schema_version' => data_get($decoded, 'meta.schema_version', self::OUTPUT_SCHEMA_VERSION),
                'provider_model' => data_get($response, 'model'),
            ],
        ];
    }

    /**
     * 组装 OpenAI 兼容请求体，参数：$payload 翻译业务载荷。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function openAiRequestPayload(array $payload): array
    {
        $documentKeys = array_keys((array) ($payload['input_document'] ?? []));
        $translatedDocumentSchema = [];

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
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        return [
            'model' => $this->translationAgent(),
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
     * 将模型输出文本解析为 JSON 对象，参数：$text 模型原始文本。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function decodeJsonFromText(string $text): array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            throw new RuntimeException('OpenClaw completion content is empty.');
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
            throw new RuntimeException('OpenClaw completion content is not valid JSON.');
        }

        return $decoded;
    }

    /**
     * 校验翻译正文语言是否符合目标语言要求，参数：$translatedDocument 翻译文档，$targetLang 目标语言。
     * @since 2026-04-03
     * @author zhouxufeng
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
                'OpenClaw translated_document key [%s] contains Chinese characters for English target output.',
                $key,
            ));
        }
    }

    /**
     * 判断目标语言是否要求英文输出，参数：$targetLang 目标语言代码。
     * @since 2026-04-03
     * @author zhouxufeng
     */
    protected function expectsEnglishOutput(string $targetLang): bool
    {
        $normalized = strtolower(trim($targetLang));

        return $normalized === 'en' || str_starts_with($normalized, 'en-');
    }

    /**
     * 判断文本中是否包含中文字符，参数：$value 待检测文本。
     * @since 2026-04-03
     * @author zhouxufeng
     */
    protected function containsChineseCharacters(string $value): bool
    {
        return preg_match('/\p{Han}/u', $value) === 1;
    }

    /**
     * 判断当前异常是否适合重试，参数：$exception 当前运行时异常。
     * @since 2026-04-03
     * @author zhouxufeng
     */
    protected function shouldRetryAfter(RuntimeException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'OpenClaw completion content is not valid JSON.')
            || str_contains($message, 'OpenClaw completion content must be a JSON object.')
            || str_contains($message, 'OpenClaw response payload must be a JSON object.');
    }
}
