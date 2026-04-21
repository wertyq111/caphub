<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class CaphubProjectKnowledge
{
    /**
     * Build a stable project knowledge snapshot for the Hermes chat assistant.
     */
    public function build(): string
    {
        $workspaceRoot = $this->resolveWorkspaceRoot();
        $files = $this->knowledgeFiles($workspaceRoot);
        $cacheKey = 'hermes-chat-project-knowledge:'.md5(implode('|', array_map(
            fn (string $path): string => $path.':'.filemtime($path),
            $files,
        )));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($files): string {
            $sections = [
                'Treat every section below as the current source of truth for CapHub.',
                'Do not invent pages, APIs, roles, or capabilities that are not supported by these files.',
                'If the answer is not grounded in these files, say you are unsure.',
                '',
            ];

            foreach ($files as $label => $path) {
                $sections[] = "## {$label}";
                $sections[] = $this->formatFile($path);
                $sections[] = '';
            }

            return trim(implode("\n", $sections));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function knowledgeFiles(string $workspaceRoot): array
    {
        return [
            'project-info.json' => $this->requireFile($workspaceRoot.'/project-info.json'),
            'README.md' => $this->requireFile($workspaceRoot.'/README.md'),
            'caphub-dev/README.md' => $this->requireFile($workspaceRoot.'/caphub-dev/README.md'),
            'caphub-dev/routes/api.php' => $this->requireFile($workspaceRoot.'/caphub-dev/routes/api.php'),
            'caphub-ui/README.md' => $this->requireFile($workspaceRoot.'/caphub-ui/README.md'),
            'caphub-ui/src/router/index.js' => $this->requireFile($workspaceRoot.'/caphub-ui/src/router/index.js'),
        ];
    }

    protected function resolveWorkspaceRoot(): string
    {
        $configured = trim((string) config('services.hermes.workspace_root', ''));

        if ($configured !== '') {
            return $this->normalizeRoot($configured);
        }

        $candidates = [
            dirname(base_path()),
            base_path(),
        ];

        foreach ($candidates as $candidate) {
            $root = $this->normalizeRoot($candidate, false);

            if (is_file($root.'/project-info.json') && is_file($root.'/README.md')) {
                return $root;
            }
        }

        throw new RuntimeException('CapHub workspace root is not configured or readable.');
    }

    protected function normalizeRoot(string $path, bool $mustExist = true): string
    {
        $candidate = $path;

        if (! str_starts_with($candidate, DIRECTORY_SEPARATOR)) {
            $candidate = base_path($candidate);
        }

        $resolved = realpath($candidate);

        if ($resolved === false || ! is_dir($resolved)) {
            if ($mustExist) {
                throw new RuntimeException(sprintf('CapHub workspace root [%s] does not exist.', $candidate));
            }

            return $candidate;
        }

        return $resolved;
    }

    protected function requireFile(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(sprintf('CapHub knowledge file [%s] is missing or unreadable.', $path));
        }

        return $path;
    }

    protected function formatFile(string $path): string
    {
        $content = file_get_contents($path);

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException(sprintf('CapHub knowledge file [%s] is empty.', $path));
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", trim($content));

        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'json' => "```json\n{$normalized}\n```",
            'php' => "```php\n{$normalized}\n```",
            'js' => "```js\n{$normalized}\n```",
            default => "```md\n{$normalized}\n```",
        };
    }
}
