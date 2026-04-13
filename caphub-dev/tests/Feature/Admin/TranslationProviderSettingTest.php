<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 30,
    ]);

    config()->set('services.hermes', [
        'base_url' => 'http://127.0.0.1:8643/v1',
        'api_key' => 'hermes-test-key',
        'profile' => 'chemical-news-translator',
        'model' => 'gpt-5-mini',
        'timeout' => 120,
    ]);
});

it('returns the current translation provider for authenticated admins', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/admin/system/translation-provider');

    $response
        ->assertOk()
        ->assertJsonPath('provider', 'openclaw')
        ->assertJsonPath('providers.0.key', 'openclaw')
        ->assertJsonPath('providers.0.configured', true)
        ->assertJsonPath('providers.1.key', 'hermes')
        ->assertJsonPath('providers.1.configured', true);
});

it('updates the active translation provider to hermes', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->putJson('/api/admin/system/translation-provider', [
        'provider' => 'hermes',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('provider', 'hermes');

    $this->assertDatabaseHas('system_settings', [
        'key' => 'translation.active_provider',
        'value' => 'hermes',
    ]);
});

it('rejects switching to an unconfigured translation provider', function () {
    Sanctum::actingAs(User::factory()->create());

    config()->set('services.hermes.base_url', '');

    $response = $this->putJson('/api/admin/system/translation-provider', [
        'provider' => 'hermes',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('message', 'Translation provider [hermes] is not configured.');

    expect(SystemSetting::query()->where('key', 'translation.active_provider')->exists())->toBeFalse();
});
