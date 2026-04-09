<?php

use App\Models\Glossary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication to access glossary admin apis', function () {
    $this->getJson('/api/admin/glossaries')->assertUnauthorized();
});

it('allows authenticated admin to create a glossary entry', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/admin/glossaries', [
        'term' => 'ethylene',
        'source_lang' => 'en',
        'target_lang' => 'zh',
        'standard_translation' => '乙烯',
        'domain' => 'chemical_news',
        'priority' => 90,
        'status' => 'active',
        'notes' => 'Core monomer term',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('term', 'ethylene');

    $this->assertDatabaseHas('glossaries', [
        'term' => 'ethylene',
        'source_lang' => 'en',
        'target_lang' => 'zh',
        'standard_translation' => '乙烯',
    ]);
});

it('allows authenticated admin to list glossary entries', function () {
    Sanctum::actingAs(User::factory()->create());

    Glossary::query()->create([
        'term' => 'propylene',
        'source_lang' => 'en',
        'target_lang' => 'zh',
        'standard_translation' => '丙烯',
        'domain' => 'chemical_news',
        'priority' => 100,
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/admin/glossaries');

    $response
        ->assertOk()
        ->assertJsonPath('data.0.term', 'propylene')
        ->assertJsonPath('data.0.standard_translation', '丙烯');
});

it('allows authenticated admin to update a glossary entry', function () {
    Sanctum::actingAs(User::factory()->create());

    $glossary = Glossary::query()->create([
        'term' => 'naphtha',
        'source_lang' => 'en',
        'target_lang' => 'zh',
        'standard_translation' => '石脑油',
        'domain' => 'chemical_news',
        'priority' => 100,
        'status' => 'active',
    ]);

    $response = $this->putJson("/api/admin/glossaries/{$glossary->id}", [
        'standard_translation' => '轻石脑油',
        'priority' => 80,
        'status' => 'inactive',
        'notes' => 'Legacy variant',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('standard_translation', '轻石脑油')
        ->assertJsonPath('priority', 80)
        ->assertJsonPath('status', 'inactive');

    $this->assertDatabaseHas('glossaries', [
        'id' => $glossary->id,
        'standard_translation' => '轻石脑油',
        'priority' => 80,
        'status' => 'inactive',
    ]);
});

it('allows authenticated admin to delete a glossary entry', function () {
    Sanctum::actingAs(User::factory()->create());

    $glossary = Glossary::query()->create([
        'term' => 'benzene',
        'source_lang' => 'en',
        'target_lang' => 'zh',
        'standard_translation' => '苯',
        'domain' => 'chemical_news',
        'priority' => 70,
        'status' => 'active',
    ]);

    $this->deleteJson("/api/admin/glossaries/{$glossary->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('glossaries', [
        'id' => $glossary->id,
    ]);
});
