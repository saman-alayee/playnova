<?php

require __DIR__ . '/../PlayNova/vendor/autoload.php';

$app = require __DIR__ . '/../PlayNova/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admin = App\Models\User::where('is_admin', true)->orderBy('id')->first();

if (! $admin) {
    echo json_encode(['error' => 'no admin user'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

$admin->tokens()->where('name', 'result-ai-test')->delete();
$token = $admin->createToken('result-ai-test')->plainTextToken;

$tournament = App\Models\Tournament::query()
    ->where('status', '!=', 'cancelled')
    ->orderByDesc('id')
    ->first();

echo json_encode([
    'admin_username' => $admin->username,
    'token' => $token,
    'tournament_id' => $tournament?->id,
    'tournament_title' => $tournament?->title,
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
