<?php

test('get api v2 test returns ok status', function () {
    $response = $this->getJson('/api/v2/test');

    $response->assertOk()
        ->assertExactJson([
            'status' => 'ok',
        ]);
});
