<?php

use App\Enums\TranslationJobStatus;
use App\Models\TranslationJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('allows authenticated admin to filter translation jobs by mode', function () {
    Sanctum::actingAs(User::factory()->create());

    $asyncJob = TranslationJob::query()->create([
        'job_uuid' => (string) Str::uuid(),
        'mode' => 'async',
        'status' => TranslationJobStatus::Pending,
        'input_type' => 'article_payload',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'source_body' => '正文',
    ]);

    TranslationJob::query()->create([
        'job_uuid' => (string) Str::uuid(),
        'mode' => 'sync',
        'status' => TranslationJobStatus::Succeeded,
        'input_type' => 'plain_text',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'source_text' => '标题',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $response = $this->getJson('/api/admin/translation-jobs?mode=async');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $asyncJob->id)
        ->assertJsonPath('data.0.mode', 'async');
});
