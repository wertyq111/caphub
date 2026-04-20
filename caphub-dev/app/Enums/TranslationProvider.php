<?php

namespace App\Enums;

enum TranslationProvider: string
{
    case OpenClaw = 'openclaw';
    case Hermes = 'hermes';
    case GitHubModels = 'github_models';

    /**
     * 返回后台允许人工切换的长文本提供方列表。
     *
     * @return array<int, self>
     */
    public static function manualCases(): array
    {
        return [
            self::OpenClaw,
            self::Hermes,
        ];
    }

    /**
     * 返回后台允许人工切换的提供方 key 列表。
     *
     * @return array<int, string>
     */
    public static function manualValues(): array
    {
        return array_map(
            static fn (self $provider): string => $provider->value,
            self::manualCases(),
        );
    }

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

    public function displayName(): string
    {
        return match ($this) {
            self::OpenClaw => 'OpenClaw',
            self::Hermes => 'Hermes',
            self::GitHubModels => 'GitHub Models',
        };
    }
}
