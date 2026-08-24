<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
Illuminate\Support\Facades\Artisan::call('config:clear');
Illuminate\Support\Facades\Artisan::call('route:clear');
Illuminate\Support\Facades\Artisan::call('view:clear');
Illuminate\Support\Facades\Artisan::call('config:cache');
Illuminate\Support\Facades\Artisan::call('route:cache');
Illuminate\Support\Facades\Artisan::call('view:cache');
echo "cache ok\n";