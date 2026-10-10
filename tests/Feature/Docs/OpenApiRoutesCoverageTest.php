<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

test('openapi.yaml documents exactly the registered api/v2 routes', function () {
    $specPath = base_path('openapi.yaml');
    expect(File::exists($specPath))->toBeTrue('The OpenAPI file openapi.yaml does not exist.');

    $spec = Yaml::parseFile($specPath);
    $baseUrl = $spec['servers'][0]['url'] ?? '/api/v2';
    $baseUrl = '/'.ltrim($baseUrl, '/');

    $httpMethods = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];

    $documented = [];
    foreach (($spec['paths'] ?? []) as $path => $operations) {
        foreach ($httpMethods as $method) {
            if (isset($operations[$method])) {
                if (strtoupper($method) === 'HEAD') {
                    continue;
                }
                $documented[] = strtoupper($method).' '.$baseUrl.$path;
            }
        }
    }
    sort($documented);

    $registered = [];
    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();
        if (! str_starts_with($uri, 'api/v2')) {
            continue;
        }

        foreach ($route->methods() as $method) {
            if ($method === 'HEAD') {
                continue;
            }
            $registered[] = $method.' /'.$uri;
        }
    }
    sort($registered);

    $missingFromOpenApi = array_values(array_diff($registered, $documented));
    $notRegistered = array_values(array_diff($documented, $registered));

    expect($missingFromOpenApi)->toBeEmpty(
        "Routes registered in api/v2 but missing from openapi.yaml:\n".implode("\n", $missingFromOpenApi)
    )->and($notRegistered)->toBeEmpty(
        "Paths documented in openapi.yaml but not registered as routes:\n".implode("\n", $notRegistered)
    );
});
