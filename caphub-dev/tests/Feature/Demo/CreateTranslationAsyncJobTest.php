<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a pending async translation job', function () {
    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => ['text' => '乙烯价格上涨。'],
    ]);

    $response->assertAccepted();
    $response->assertJsonStructure([
        'job_id',
        'job_uuid',
        'status',
    ]);

    $this->assertDatabaseHas('translation_jobs', [
        'input_type' => 'plain_text',
        'status' => 'pending',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'source_text' => '乙烯价格上涨。',
    ]);
});

it('rejects async translation requests that do not include any source content', function () {
    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => [],
    ]);

    $response->assertUnprocessable();

    $this->assertDatabaseCount('translation_jobs', 0);
});
