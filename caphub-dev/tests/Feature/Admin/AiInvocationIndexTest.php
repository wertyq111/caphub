<?php

use App\Models\AiInvocation;
use App\Models\TranslationJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('normalizes legacy success statuses in the admin invocation list', function () {
    Sanctum::actingAs(User::factory()->create());

    AiInvocation::query()->create([
        'agent_name' => 'chemical-news-translator',
        'request_payload' => [
            'execution_context' => [
                'provider' => 'openclaw',
            ],
        ],
        'response_payload_summary' => [
            'meta' => [
                'provider_model' => 'chemical-news-translator',
            ],
        ],
        'status' => 'success',
        'duration_ms' => 1280,
        'created_at' => now(),
    ]);

    $this->getJson('/api/admin/ai-invocations?per_page=5')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'succeeded');
});

it('returns text bytes from the related translation job source content', function () {
    Sanctum::actingAs(User::factory()->create());

    $job = TranslationJob::query()->create([
        'job_uuid' => (string) Str::uuid(),
        'mode' => 'sync',
        'status' => 'processing',
        'input_type' => 'plain_text',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'source_title' => '标题',
        'source_body' => '正文ABC',
    ]);

    AiInvocation::query()->create([
        'job_id' => $job->id,
        'agent_name' => 'chemical-news-translator',
        'request_payload' => [],
        'status' => 'succeeded',
        'duration_ms' => 1280,
        'created_at' => now(),
    ]);

    $expectedBytes = strlen('标题') + strlen('正文ABC');

    $this->getJson('/api/admin/ai-invocations?per_page=5')
        ->assertOk()
        ->assertJsonPath('data.0.text_bytes', $expectedBytes);
});

it('falls back to stored request payload byte lengths when no translation job content exists', function () {
    Sanctum::actingAs(User::factory()->create());

    AiInvocation::query()->create([
        'agent_name' => 'chemical-news-translator',
        'request_payload' => [
            'document_byte_lengths' => [
                'title' => 12,
                'body' => 345,
            ],
        ],
        'status' => 'succeeded',
        'duration_ms' => 860,
        'created_at' => now(),
    ]);

    $this->getJson('/api/admin/ai-invocations?per_page=5')
        ->assertOk()
        ->assertJsonPath('data.0.text_bytes', 357);
});
