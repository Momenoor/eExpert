<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Contracts\Console\Kernel;

echo "=== PAGES ===\n";
foreach (FilamentShield::getPages() as $p) {
    print_r($p);
}

echo "\n=== WIDGETS ===\n";
foreach (FilamentShield::getWidgets() as $w) {
    print_r($w);
}
