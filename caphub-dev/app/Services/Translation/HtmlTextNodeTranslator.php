<?php

namespace App\Services\Translation;

use Throwable;

class HtmlTextNodeTranslator
{
    /**
     * 需要跳过文本翻译的标签集合。
     *
     * @var array<int, string>
     */
    protected array $rawTextTags = ['script', 'style', 'noscript'];

    /**
     * 语义分段时视为块级边界的标签集合。
     *
     * @var array<int, string>
     */
    protected array $segmentBoundaryTags = [
        'article',
        'aside',
        'blockquote',
        'br',
        'div',
        'footer',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'header',
        'li',
        'main',
        'nav',
        'ol',
        'p',
        'section',
        'table',
        'tbody',
        'td',
        'tfoot',
        'th',
        'thead',
        'tr',
        'ul',
    ];

    /**
     * 判断文本是否包含 HTML 标签结构，参数：$text 原始文本。
     * @since 2026-04-03
     * @author zhouxufeng
     */
    public function looksLikeHtml(string $text): bool
    {
        return preg_match('/<\s*[a-zA-Z][^>]*>/u', $text) === 1;
    }

    /**
     * 保留 HTML 结构并翻译可见文本节点，参数：$html HTML 内容，$translator 节点翻译回调。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  callable(string): string  $translator
     * @return array{html: string, translated_text_nodes: int, fallback_text_nodes: int}
     */
    public function translate(string $html, callable $translator): array
    {
        $compiled = $this->compile($html);
        $translatedTextNodes = 0;
        $fallbackTextNodes = 0;
        $translatedNodeTexts = [];

        foreach ($compiled['nodes'] as $index => $node) {
            $translatedCoreText = null;

            for ($attempt = 0; $attempt < 2; $attempt++) {
                try {
                    $translatedCoreText = (string) $translator($node['core_text']);
                    break;
                } catch (Throwable) {
                    continue;
                }
            }

            $result = $this->hydrateNodeText($node, $translatedCoreText);
            $translatedNodeTexts[$index] = $result['text'];
            $translatedTextNodes += $result['translated'] ? 1 : 0;
            $fallbackTextNodes += $result['fallback'] ? 1 : 0;
        }

        return [
            'html' => $this->render($compiled['parts'], $translatedNodeTexts),
            'translated_text_nodes' => $translatedTextNodes,
            'fallback_text_nodes' => $fallbackTextNodes,
        ];
    }

    /**
     * 预编译 HTML 结构，提取可翻译文本节点并保留原始标签片段，参数：$html HTML 内容。
     * @since 2026-04-03
     * @author zhouxufeng
     * @return array{
     *     parts: array<int, array{type: string, value?: string, node_index?: int}>,
     *     nodes: array<int, array{
     *         original: string,
     *         leading_whitespace: string,
     *         core_text: string,
     *         trailing_whitespace: string,
     *         entity_map: array<string, string>
     *     }>
     * }
     */
    public function compile(string $html): array
    {
        $parts = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $rawTagStack = [];
        $compiledParts = [];
        $nodes = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if ($this->isHtmlTag($part)) {
                $this->updateRawTagStack($rawTagStack, $part);
                $compiledParts[] = [
                    'type' => 'raw',
                    'value' => $part,
                ];

                continue;
            }

            if ($rawTagStack !== []) {
                $compiledParts[] = [
                    'type' => 'raw',
                    'value' => $part,
                ];

                continue;
            }

            $node = $this->createTextNode($part);

            if ($node === null) {
                $compiledParts[] = [
                    'type' => 'raw',
                    'value' => $part,
                ];

                continue;
            }

            $nodeIndex = count($nodes);
            $nodes[] = $node;
            $compiledParts[] = [
                'type' => 'node',
                'node_index' => $nodeIndex,
            ];
        }

        return [
            'parts' => $compiledParts,
            'nodes' => $nodes,
        ];
    }

    /**
     * 将 HTML 文本节点按块级边界与长度限制编译为语义段。
     * @since 2026-04-10
     * @author zhouxufeng
     * @return array{
     *     parts: array<int, array{type: string, value?: string, node_index?: int}>,
     *     nodes: array<int, array{
     *         original: string,
     *         leading_whitespace: string,
     *         core_text: string,
     *         trailing_whitespace: string,
     *         entity_map: array<string, string>
     *     }>,
     *     segments: array<int, array{
     *         index: int,
     *         node_indexes: array<int, int>,
     *         visible_length: int,
     *         source_text: string
     *     }>
     * }
     */
    public function compileSemanticSegments(string $html, int $targetTextLength = 300, int $maxTextLength = 600): array
    {
        $compiled = $this->compile($html);
        $segments = [];
        $buffer = [];
        $bufferLength = 0;

        $flushBuffer = function () use (&$segments, &$buffer, &$bufferLength, $compiled): void {
            if ($buffer === []) {
                return;
            }

            $segmentIndex = count($segments);
            $segments[] = [
                'index' => $segmentIndex,
                'node_indexes' => array_values($buffer),
                'visible_length' => $bufferLength,
                'source_text' => $this->encodeSegmentNodeTexts([
                    'node_indexes' => array_values($buffer),
                ], array_map(
                    fn (int $nodeIndex): string => (string) ($compiled['nodes'][$nodeIndex]['core_text'] ?? ''),
                    $buffer,
                )),
            ];

            $buffer = [];
            $bufferLength = 0;
        };

        foreach ($compiled['parts'] as $part) {
            if (($part['type'] ?? 'raw') === 'node') {
                $nodeIndex = $part['node_index'] ?? null;

                if (! is_int($nodeIndex) || ! array_key_exists($nodeIndex, $compiled['nodes'])) {
                    continue;
                }

                $nodeLength = mb_strlen((string) ($compiled['nodes'][$nodeIndex]['core_text'] ?? ''));

                if ($buffer !== [] && $bufferLength + $nodeLength > $maxTextLength) {
                    $flushBuffer();
                }

                $buffer[] = $nodeIndex;
                $bufferLength += $nodeLength;

                continue;
            }

            if (! $this->isSemanticSegmentBoundaryTag((string) ($part['value'] ?? ''))) {
                continue;
            }

            if ($buffer !== [] && $bufferLength >= max(1, $targetTextLength)) {
                $flushBuffer();
                continue;
            }

            $flushBuffer();
        }

        $flushBuffer();

        return [
            'parts' => $compiled['parts'],
            'nodes' => $compiled['nodes'],
            'segments' => $segments,
        ];
    }

    /**
     * 将一段语义段的节点文本编码为占位符串，参数：$segment 段定义，$nodeTexts 节点文本。
     * @since 2026-04-10
     * @author zhouxufeng
     * @param  array{node_indexes: array<int, int>}  $segment
     * @param  array<int|string, string>  $nodeTexts
     */
    public function encodeSegmentNodeTexts(array $segment, array $nodeTexts): string
    {
        $encoded = '';

        foreach (array_values((array) ($segment['node_indexes'] ?? [])) as $localIndex => $nodeIndex) {
            $nodeText = $nodeTexts[$localIndex] ?? $nodeTexts[$nodeIndex] ?? '';
            $encoded .= sprintf(
                '[[NODE_%d_START]]%s[[NODE_%d_END]]',
                $localIndex,
                $nodeText,
                $localIndex,
            );
        }

        return $encoded;
    }

    /**
     * 将段级占位符译文解码回原始节点索引到节点文本的映射。
     * @since 2026-04-10
     * @author zhouxufeng
     * @param  array{node_indexes: array<int, int>}  $segment
     * @return array<int, string>
     */
    public function decodeSegmentNodeTexts(array $segment, string $encodedText): array
    {
        $nodeIndexes = array_values((array) ($segment['node_indexes'] ?? []));

        if ($nodeIndexes === []) {
            return [];
        }

        $matches = [];
        preg_match_all('/\[\[NODE_(\d+)_START\]\](.*?)\[\[NODE_\1_END\]\]/us', $encodedText, $matches, PREG_SET_ORDER);

        if ($matches === [] && count($nodeIndexes) === 1) {
            return [$nodeIndexes[0] => $encodedText];
        }

        $decoded = [];

        foreach ($matches as $match) {
            $localIndex = (int) ($match[1] ?? -1);

            if (! array_key_exists($localIndex, $nodeIndexes)) {
                continue;
            }

            $decoded[$nodeIndexes[$localIndex]] = (string) ($match[2] ?? '');
        }

        return $decoded;
    }

    /**
     * 获取语义段内所有占位符标记，参数：$segment 段定义。
     * @since 2026-04-10
     * @author zhouxufeng
     * @param  array{node_indexes: array<int, int>}  $segment
     * @return array<int, string>
     */
    public function segmentPlaceholderTokens(array $segment): array
    {
        $tokens = [];

        foreach (array_values((array) ($segment['node_indexes'] ?? [])) as $localIndex => $unusedNodeIndex) {
            $tokens[] = sprintf('[[NODE_%d_START]]', $localIndex);
            $tokens[] = sprintf('[[NODE_%d_END]]', $localIndex);
        }

        return $tokens;
    }

    /**
     * 根据编译片段和文本节点渲染最终 HTML，参数：$parts 编译片段，$translatedNodeTexts 节点文本。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array<int, array{type: string, value?: string, node_index?: int}>  $parts
     * @param  array<int, string>  $translatedNodeTexts
     */
    public function render(array $parts, array $translatedNodeTexts): string
    {
        $output = '';

        foreach ($parts as $part) {
            if (($part['type'] ?? 'raw') === 'node') {
                $nodeIndex = $part['node_index'] ?? null;

                if (is_int($nodeIndex) && array_key_exists($nodeIndex, $translatedNodeTexts)) {
                    $output .= $translatedNodeTexts[$nodeIndex];
                }

                continue;
            }

            $output .= (string) ($part['value'] ?? '');
        }

        return $output;
    }

    /**
     * 将节点翻译核心文本还原成完整 HTML 文本节点，参数：$node 节点信息，$translatedCoreText 翻译核心文本。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array{
     *     original: string,
     *     leading_whitespace: string,
     *     core_text: string,
     *     trailing_whitespace: string,
     *     entity_map: array<string, string>
     * }  $node
     * @return array{text: string, translated: bool, fallback: bool}
     */
    public function hydrateNodeText(array $node, ?string $translatedCoreText): array
    {
        if ($translatedCoreText === null) {
            return [
                'text' => $node['original'],
                'translated' => false,
                'fallback' => true,
            ];
        }

        return [
            'text' => $node['leading_whitespace'].$this->restoreEntities($translatedCoreText, $node['entity_map']).$node['trailing_whitespace'],
            'translated' => true,
            'fallback' => false,
        ];
    }

    /**
     * 解析单个 HTML 文本节点为可翻译结构，参数：$text 文本节点原文。
     * @since 2026-04-03
     * @author zhouxufeng
     * @return array{
     *     original: string,
     *     leading_whitespace: string,
     *     core_text: string,
     *     trailing_whitespace: string,
     *     entity_map: array<string, string>
     * }|null
     */
    protected function createTextNode(string $text): ?array
    {
        if (trim($text) === '') {
            return null;
        }

        preg_match('/^(\s*)(.*?)(\s*)$/us', $text, $matches);
        $leadingWhitespace = $matches[1] ?? '';
        $coreText = $matches[2] ?? $text;
        $trailingWhitespace = $matches[3] ?? '';

        if ($coreText === '') {
            return null;
        }

        $entityMap = [];
        $placeholderText = preg_replace_callback(
            '/&(?:#\d+|#x[0-9A-Fa-f]+|[A-Za-z][A-Za-z0-9]+);/u',
            function (array $matches) use (&$entityMap): string {
                $placeholder = '__HTML_ENTITY_'.count($entityMap).'__';
                $entityMap[$placeholder] = $matches[0];

                return $placeholder;
            },
            $coreText,
        ) ?? $coreText;

        return [
            'original' => $text,
            'leading_whitespace' => $leadingWhitespace,
            'core_text' => $placeholderText,
            'trailing_whitespace' => $trailingWhitespace,
            'entity_map' => $entityMap,
        ];
    }

    /**
     * 将实体占位符恢复为原始 HTML 实体，参数：$text 翻译文本，$entityMap 实体映射。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array<string, string>  $entityMap
     */
    protected function restoreEntities(string $text, array $entityMap): string
    {
        return strtr($text, $entityMap);
    }

    /**
     * 判断片段是否为 HTML 标签，参数：$part 待判断片段。
     * @since 2026-04-03
     * @author zhouxufeng
     */
    protected function isHtmlTag(string $part): bool
    {
        return str_starts_with($part, '<') && str_ends_with($part, '>');
    }

    /**
     * 判断标签是否应作为语义段边界，参数：$tag HTML 标签文本。
     * @since 2026-04-10
     * @author zhouxufeng
     */
    protected function isSemanticSegmentBoundaryTag(string $tag): bool
    {
        if (! $this->isHtmlTag($tag)) {
            return false;
        }

        if (preg_match('/^<\s*\/?\s*([a-zA-Z0-9:-]+)/u', $tag, $matches) !== 1) {
            return false;
        }

        return in_array(strtolower((string) $matches[1]), $this->segmentBoundaryTags, true);
    }

    /**
     * 维护当前原样保留标签栈，参数：&$rawTagStack 栈引用，$tag 标签文本。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array<int, string>  $rawTagStack
     */
    protected function updateRawTagStack(array &$rawTagStack, string $tag): void
    {
        if (preg_match('/^<\s*\/\s*([a-zA-Z0-9:-]+)/u', $tag, $matches) === 1) {
            $tagName = strtolower($matches[1]);
            $lastIndex = array_key_last($rawTagStack);

            if ($lastIndex !== null && $rawTagStack[$lastIndex] === $tagName) {
                array_pop($rawTagStack);
            }

            return;
        }

        if (preg_match('/^<\s*([a-zA-Z0-9:-]+)/u', $tag, $matches) !== 1) {
            return;
        }

        $tagName = strtolower($matches[1]);

        if (! in_array($tagName, $this->rawTextTags, true) || preg_match('/\/\s*>$/u', $tag) === 1) {
            return;
        }

        $rawTagStack[] = $tagName;
    }
}
