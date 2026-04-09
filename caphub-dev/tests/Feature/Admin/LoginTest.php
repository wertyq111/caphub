<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admin to log in and returns an api token with user payload', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $response = $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email'],
        ]);
});

it('rejects login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $response = $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertUnauthorized()
        ->assertExactJson([
            'message' => 'Invalid credentials.',
        ]);
});
