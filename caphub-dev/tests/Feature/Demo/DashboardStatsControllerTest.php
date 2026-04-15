<?php

use App\Models\AiInvocation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts legacy success invocations and normalizes recent log statuses', function () {
    AiInvocation::query()->create([
        'agent_name' => 'chemical-news-translator',
        'request_payload' => [
            'execution_context' => [
                'provider' => 'openclaw',
            ],
        ],
        'response_payload_summary' => null,
        'status' => 'success',
        'duration_ms' => 900,
        'created_at' => now(),
    ]);

    AiInvocation::query()->create([
        'agent_name' => 'chemical-news-translator',
        'request_payload' => [
            'execution_context' => [
                'provider' => 'openclaw',
            ],
        ],
        'response_payload_summary' => null,
        'status' => 'succeeded',
        'duration_ms' => 1100,
        'created_at' => now()->subMinute(),
    ]);

    AiInvocation::query()->create([
        'agent_name' => 'chemical-news-translator',
        'request_payload' => [
            'execution_context' => [
                'provider' => 'openclaw',
            ],
        ],
        'response_payload_summary' => null,
        'status' => 'failed',
        'duration_ms' => 1300,
        'created_at' => now()->subMinutes(2),
    ]);

    AiInvocation::query()->create([
        'agent_name' => 'chemical-news-translator',
        'request_payload' => [
            'execution_context' => [
                'provider' => 'hermes',
            ],
        ],
        'response_payload_summary' => null,
        'status' => 'success',
        'duration_ms' => 1500,
        'created_at' => now()->subMinutes(3),
    ]);

    $this->getJson('/api/demo/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('agents.0.key', 'openclaw')
        ->assertJsonPath('agents.0.stats_24h.total_calls', 3)
        ->assertJsonPath('agents.0.stats_24h.succeeded', 2)
        ->assertJsonPath('agents.0.stats_24h.failed', 1)
        ->assertJsonPath('agents.1.key', 'hermes')
        ->assertJsonPath('agents.1.stats_24h.total_calls', 1)
        ->assertJsonPath('agents.1.stats_24h.succeeded', 1)
        ->assertJsonPath('recent_logs.0.status', 'succeeded');
});
