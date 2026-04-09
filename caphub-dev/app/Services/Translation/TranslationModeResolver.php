<?php

namespace App\Services\Translation;

class TranslationModeResolver
{
    /**
     * 解析翻译执行模式，参数：$normalizedRequest 标准化请求数据。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, mixed>  $normalizedRequest
     */
    public function resolve(array $normalizedRequest): string
    {
        return (string) ($normalizedRequest['mode'] ?? 'sync');
    }
}
