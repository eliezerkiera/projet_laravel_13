<?php

use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('it returns English message by default', function () {
    $response = $this->postJson('/api/v2/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'secret',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'These credentials do not match our records.',
        ]);
});

test('it returns French message when Accept-Language is set to fr', function () {
    $response = $this->withHeader('Accept-Language', 'fr')
        ->postJson('/api/v2/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'secret',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
        ]);
});
