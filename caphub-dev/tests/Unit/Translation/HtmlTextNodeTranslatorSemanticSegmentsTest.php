<?php

use App\Services\Translation\HtmlTextNodeTranslator;

it('groups inline html text nodes into one semantic segment and splits block boundaries', function () {
    $translator = app(HtmlTextNodeTranslator::class);
    $compiled = $translator->compileSemanticSegments(
        '<p><span>第一段内容</span><strong>第二段内容</strong></p><p><em>第三段内容</em></p>',
        300,
        600,
    );

    expect($compiled['segments'])->toHaveCount(2);
    expect($compiled['segments'][0]['node_indexes'])->toBe([0, 1]);
    expect($compiled['segments'][1]['node_indexes'])->toBe([2]);
});

it('encodes, decodes, and renders semantic segment translations without breaking html structure', function () {
    $translator = app(HtmlTextNodeTranslator::class);
    $compiled = $translator->compileSemanticSegments(
        '<p><span>第一段内容</span><strong>第二段内容</strong></p>',
        300,
        600,
    );

    $segment = $compiled['segments'][0];
    $encoded = $translator->encodeSegmentNodeTexts($segment, [
        'First paragraph content',
        'Second paragraph content',
    ]);

    $decoded = $translator->decodeSegmentNodeTexts($segment, $encoded);

    $translatedNodeTexts = [];
    foreach ($decoded as $nodeIndex => $translatedText) {
        $translatedNodeTexts[$nodeIndex] = $translator->hydrateNodeText(
            $compiled['nodes'][$nodeIndex],
            $translatedText,
        )['text'];
    }

    expect($translator->render($compiled['parts'], $translatedNodeTexts))
        ->toBe('<p><span>First paragraph content</span><strong>Second paragraph content</strong></p>');
});
