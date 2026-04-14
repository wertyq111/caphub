<?php

use App\Enums\TranslationJobStatus;
use App\Models\AiInvocation;
use App\Models\TranslationJob;
use App\Models\TranslationResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns the actual translation provider and agent for job detail', function () {
    Sanctum::actingAs(User::factory()->create());

    $job = TranslationJob::query()->create([
        'job_uuid' => (string) Str::uuid(),
        'mode' => 'async',
        'status' => TranslationJobStatus::Succeeded,
        'input_type' => 'plain_text',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'source_text' => '原文',
        'started_at' => now()->subSeconds(4),
        'finished_at' => now(),
    ]);

    TranslationResult::query()->create([
        'translation_job_id' => $job->id,
        'translated_document_json' => ['text' => 'Translated text'],
        'risk_payload' => [],
        'notes_payload' => [],
        'meta_payload' => [
            'schema_version' => 'v1',
            'provider_model' => 'gpt-5-mini',
        ],
    ]);

    AiInvocation::query()->create([
        'job_id' => $job->id,
        'agent_name' => 'chemical-news-translator',
        'request_payload' => [
            'execution_context' => [
                'provider' => 'hermes',
            ],
        ],
        'response_payload_summary' => [
            'meta' => [
                'provider_model' => 'gpt-5-mini',
            ],
        ],
        'status' => 'success',
        'duration_ms' => 1280,
    ]);

    $this->getJson("/api/admin/translation-jobs/{$job->id}")
        ->assertOk()
        ->assertJsonPath('id', $job->id)
        ->assertJsonPath('translation_provider', 'hermes')
        ->assertJsonPath('translation_agent', 'chemical-news-translator')
        ->assertJsonPath('result.meta_payload.provider_model', 'gpt-5-mini');
});
