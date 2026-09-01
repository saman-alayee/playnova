<?php

require __DIR__ . '/../PlayNova/vendor/autoload.php';
$app = require __DIR__ . '/../PlayNova/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$t = \App\Models\Tournament::updateOrCreate(
    ['title' => 'مسابقه تست تک‌نفره'],
    [
        'game' => 'Call of Duty Mobile',
        'league' => 'beginner',
        'description' => 'مسابقه تست برای بررسی ثبت‌نام تک‌نفره — ورودی رایگان',
        'entry_fee' => 0,
        'prize_pool' => 100000,
        'prize_ranks' => [1 => 100000.0],
        'capacity' => 20,
        'seat_mode' => 1,
        'registered_count' => 0,
        'start_date' => now()->addDays(2),
        'status' => 'active',
    ]
);

\App\Modules\Tournament\Services\TournamentListingService::forgetHomeCache();

echo json_encode([
    'id' => $t->id,
    'title' => $t->title,
    'status' => $t->status,
    'seat_mode' => $t->seat_mode,
    'entry_fee' => $t->entry_fee,
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
