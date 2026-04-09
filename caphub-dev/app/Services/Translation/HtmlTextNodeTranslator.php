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
