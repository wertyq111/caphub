<?php

namespace App\Enums;

enum TranslationProvider: string
{
    case OpenClaw = 'openclaw';
    case Hermes = 'hermes';

    /**
     * 返回可选的翻译提供方列表。
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $provider): string => $provider->value,
            self::cases(),
        );
    }
}
