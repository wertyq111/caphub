<?php

use App\Models\AiInvocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
