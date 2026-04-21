<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class CaphubProjectKnowledge
{
    /**
     * Build a compact project snapshot for the Hermes chat assistant.
     */
    public function build(): string
    {
        $workspaceRoot = $this->resolveWorkspaceRoot();
        $files = $this->knowledgeFiles($workspaceRoot);
        $cacheKey = 'hermes-chat-project-knowledge:'.md5(implode('|', array_map(
            fn (string $path): string => $path.':'.filemtime($path),
            $files,
        )));

        return Cache::remember($cacheKey, now()->addMinutes(10), fn (): string => $this->buildSnapshot($files));
    }

    /**
     * @return array<string, string>
     */
    protected function knowledgeFiles(string $workspaceRoot): array
    {
        return array_filter([
            'project-info.json' => $this->optionalFile($workspaceRoot.'/project-info.json'),
            'README.md' => $this->requireFile($workspaceRoot.'/README.md'),
            'caphub-dev/README.md' => $this->requireFile($workspaceRoot.'/caphub-dev/README.md'),
            'caphub-dev/routes/api.php' => $this->requireFile($workspaceRoot.'/caphub-dev/routes/api.php'),
            'caphub-ui/README.md' => $this->requireFile($workspaceRoot.'/caphub-ui/README.md'),
            'caphub-ui/src/router/index.js' => $this->requireFile($workspaceRoot.'/caphub-ui/src/router/index.js'),
        ], fn (?string $path): bool => is_string($path));
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

            if (
                is_file($root.'/README.md')
                && is_file($root.'/caphub-dev/README.md')
                && is_file($root.'/caphub-ui/README.md')
            ) {
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

    protected function optionalFile(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        return $path;
    }

    /**
     * @param  array<string, string>  $files
     */
    protected function buildSnapshot(array $files): string
    {
        $projectInfo = isset($files['project-info.json']) ? $this->readJson($files['project-info.json']) : [];
        $rootReadme = $this->readFile($files['README.md']);
        $backendReadme = $this->readFile($files['caphub-dev/README.md']);
        $apiRoutes = $this->readFile($files['caphub-dev/routes/api.php']);
        $frontendReadme = $this->readFile($files['caphub-ui/README.md']);
        $router = $this->readFile($files['caphub-ui/src/router/index.js']);

        $pagePaths = array_values(array_unique(array_merge(
            $this->extractRouterPaths($router),
            $this->extractMarkdownPaths($rootReadme),
            $this->extractMarkdownPaths($frontendReadme),
        )));

        $apiPaths = $this->extractApiRoutes($apiRoutes);

        $lines = array_filter([
            'CapHub Project Snapshot',
            '- Ground answers only in the facts below. If a fact is missing, say you are unsure.',
            '- Product: '.$this->projectName($projectInfo, $rootReadme),
            '- Purpose: '.$this->projectPurpose($rootReadme),
            '- Backend: '.$this->backendSummary($projectInfo, $backendReadme),
            '- Frontend: '.$this->frontendSummary($projectInfo, $frontendReadme),
            '- Demo Pages: '.$this->joinLimited($this->filterPathsByPrefix($pagePaths, '/demo'), 6),
            '- Admin Pages: '.$this->joinLimited($this->filterPathsByPrefix($pagePaths, '/admin'), 6),
            '- Demo APIs: '.$this->joinLimited($this->filterPathsByPrefix($apiPaths, 'POST /api/demo', 'GET /api/demo', 'PUT /api/demo', 'DELETE /api/demo'), 6),
            '- Admin APIs: '.$this->joinLimited($this->filterPathsByPrefix($apiPaths, 'POST /api/admin', 'GET /api/admin', 'PUT /api/admin', 'DELETE /api/admin'), 8),
            in_array('/demo/translate', $pagePaths, true) ? '- Translation workbench: /demo/translate' : null,
        ]);

        return implode("\n", $lines);
    }

    protected function readFile(string $path): string
    {
        $content = file_get_contents($path);

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException(sprintf('CapHub knowledge file [%s] is empty.', $path));
        }

        return str_replace(["\r\n", "\r"], "\n", trim($content));
    }

    /**
     * @return array<string, mixed>
     */
    protected function readJson(string $path): array
    {
        $decoded = json_decode($this->readFile($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $projectInfo
     */
    protected function projectName(array $projectInfo, string $rootReadme): string
    {
        $name = trim((string) ($projectInfo['name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        if (preg_match('/^#\s+(.+)$/m', $rootReadme, $matches) === 1) {
            return trim($matches[1]);
        }

        return 'CapHub';
    }

    protected function projectPurpose(string $rootReadme): string
    {
        foreach (preg_split('/\n+/', $rootReadme) ?: [] as $line) {
            $clean = trim($line);

            if ($clean === '' || str_starts_with($clean, '#') || str_starts_with($clean, '|')) {
                continue;
            }

            return $this->stripMarkdown($clean);
        }

        return '面向化工资讯翻译和术语治理的工作区。';
    }

    /**
     * @param  array<string, mixed>  $projectInfo
     */
    protected function backendSummary(array $projectInfo, string $backendReadme): string
    {
        $backend = $projectInfo['stack']['backend'] ?? [];
        $parts = [];

        foreach (['framework', 'language', 'runtime_requirement', 'database', 'queue'] as $key) {
            $value = $backend[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        if (! empty($parts)) {
            return implode(', ', array_unique($parts));
        }

        return $this->extractTechSummary($backendReadme, ['Laravel', 'PHP', 'MySQL', 'Redis', 'SQLite', 'Queue']);
    }

    /**
     * @param  array<string, mixed>  $projectInfo
     */
    protected function frontendSummary(array $projectInfo, string $frontendReadme): string
    {
        $frontend = $projectInfo['stack']['frontend'] ?? [];
        $parts = [];

        foreach (['framework', 'build_tool', 'state', 'router'] as $key) {
            $value = $frontend[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        foreach (($frontend['ui'] ?? []) as $ui) {
            if (is_string($ui) && trim($ui) !== '') {
                $parts[] = trim($ui);
            }
        }

        if (! empty($parts)) {
            return implode(', ', array_unique($parts));
        }

        return $this->extractTechSummary($frontendReadme, ['Vue', 'Vite', 'Pinia', 'Vue Router', 'Element Plus', 'Tailwind']);
    }

    /**
     * @return array<int, string>
     */
    protected function extractRouterPaths(string $router): array
    {
        preg_match_all('/path:\s*[\'"]([^\'"]+)[\'"]/', $router, $matches);

        return array_values(array_unique(array_map('trim', $matches[1] ?? [])));
    }

    /**
     * @return array<int, string>
     */
    protected function extractMarkdownPaths(string $markdown): array
    {
        preg_match_all('/`(\/[^`]+)`/', $markdown, $matches);

        return array_values(array_unique(array_map('trim', $matches[1] ?? [])));
    }

    /**
     * @return array<int, string>
     */
    protected function extractApiRoutes(string $routesFile): array
    {
        preg_match_all('/Route::(get|post|put|delete|patch)\(\s*[\'"]([^\'"]+)[\'"]/', $routesFile, $matches, PREG_SET_ORDER);

        $routes = [];

        foreach ($matches as $match) {
            $path = $match[2];
            $path = str_starts_with($path, '/') ? $path : '/'.$path;
            $routes[] = strtoupper($match[1]).' /api'.$path;
        }

        return array_values(array_unique($routes));
    }

    protected function extractTechSummary(string $content, array $keywords): string
    {
        $parts = [];

        foreach ($keywords as $keyword) {
            if (preg_match('/'.preg_quote($keyword, '/').'[^\n,]*/iu', $content, $matches) === 1) {
                $parts[] = trim($this->stripMarkdown($matches[0]));
            }
        }

        return implode(', ', array_unique($parts));
    }

    /**
     * @param  array<int, string>  $paths
     * @param  string  ...$prefixes
     * @return array<int, string>
     */
    protected function filterPathsByPrefix(array $paths, string ...$prefixes): array
    {
        return array_values(array_filter($paths, function (string $path) use ($prefixes): bool {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  array<int, string>  $items
     */
    protected function joinLimited(array $items, int $limit): string
    {
        if ($items === []) {
            return '未标注';
        }

        return implode(', ', array_slice($items, 0, $limit));
    }

    protected function stripMarkdown(string $text): string
    {
        $normalized = preg_replace('/[`*_>#\[\]\(\)\|]/u', '', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }
}
