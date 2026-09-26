<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$routes = $app->make('router')->getRoutes();
$checked = 0;

foreach ($routes as $route) {
    $uri = ltrim($route->uri(), '/');

    if (!str_starts_with($uri, 'tagore')) {
        continue;
    }

    $checked++;
    $action = $route->getAction('controller');

    if (is_string($action)) {
        if (str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);
        } else {
            $class = $action;
            $method = '__invoke';
        }
    } elseif (is_array($action) && isset($action[0])) {
        $class = $action[0];
        $method = $action[1] ?? '__invoke';
    } else {
        continue;
    }

    if (!class_exists($class)) {
        throw new RuntimeException("Tagore route [{$uri}] references missing controller [{$class}].");
    }

    if (!method_exists($class, $method)) {
        throw new RuntimeException("Tagore route [{$uri}] references missing method [{$class}@{$method}].");
    }
}

if ($checked === 0) {
    throw new RuntimeException('No Tagore routes were registered.');
}

echo "Validated {$checked} Tagore routes and controller actions.\n";
