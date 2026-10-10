<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

test('api v2 documentation guide covers all registered api v2 routes in section 7', function () {
    $guidePath = base_path('docs/api/guide_api_v2.md');
    expect(File::exists($guidePath))->toBeTrue('The guide file docs/api/guide_api_v2.md does not exist.');

    $guideContent = File::get($guidePath);

    // Isolate Section 7 from the documentation
    preg_match('/## 7\. Référence des endpoints(.*?)## 8\./s', $guideContent, $matches);
    expect($matches)->not->toBeEmpty('Section 7 was not found in docs/api/guide_api_v2.md.');
    $section7 = $matches[1];

    $routes = Route::getRoutes();
    $missingRoutes = [];
    $inspectedCount = 0;

    foreach ($routes as $route) {
        $uri = $route->uri();

        // Target all api/v2 routes (ignore non-v2 routes or fallback routes)
        if (! str_starts_with($uri, 'api/v2')) {
            continue;
        }

        $fullPath = '/'.$uri;
        // Ignore HEAD method (HEAD is standard companion for GET)
        $methods = array_filter($route->methods(), fn ($m) => $m !== 'HEAD');

        foreach ($methods as $method) {
            $inspectedCount++;
            $pattern = "`{$method}` | `{$fullPath}`";
            if (! str_contains($section7, $pattern)) {
                $missingRoutes[] = "{$method} {$fullPath}";
            }
        }
    }

    expect($inspectedCount)->toBeGreaterThan(0)
        ->and($missingRoutes)->toBeEmpty(
            "The following API v2 routes are missing from Section 7 of docs/api/guide_api_v2.md:\n".implode("\n", $missingRoutes)
        );
});
