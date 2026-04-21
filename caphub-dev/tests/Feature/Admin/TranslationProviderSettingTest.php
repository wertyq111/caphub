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

    config()->set('services.github_models', [
        'base_url' => 'https://api.githubcopilot.com',
        'api_key' => 'copilot-api-test-key',
        'model' => 'gpt-4o',
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
        ->assertJsonPath('providers.1.configured', true)
        ->assertJsonCount(2, 'providers');
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

it('normalizes a legacy github models selection back to openclaw for the long-text provider view', function () {
    Sanctum::actingAs(User::factory()->create());

    SystemSetting::query()->create([
        'key' => 'translation.active_provider',
        'value' => 'github_models',
    ]);

    $this->getJson('/api/admin/system/translation-provider')
        ->assertOk()
        ->assertJsonPath('provider', 'openclaw')
        ->assertJsonCount(2, 'providers');
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

it('rejects switching the async long-text provider to github models', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->putJson('/api/admin/system/translation-provider', [
        'provider' => 'github_models',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['provider']);

    expect(SystemSetting::query()->where('key', 'translation.active_provider')->exists())->toBeFalse();
});
