<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$blade = $app->make('blade.compiler');
$files = glob(resource_path('views/tagore/**/*.blade.php'), GLOB_BRACE);

if ($files === false || $files === []) {
    throw new RuntimeException('No Tagore Blade views were found.');
}

foreach ($files as $file) {
    $blade->compile($file);
}

echo "Compiled " . count($files) . " Tagore Blade views.\n";
