<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $users = App\Models\User::query()
        ->with([
            'latestKycSubmission' => function ($q) {
                $q->select(
                    'kyc_submissions.id',
                    'kyc_submissions.user_id',
                    'kyc_submissions.status',
                    'kyc_submissions.created_at',
                );
            },
            'referrer:id,username',
            'registrations' => function ($q) {
                $q->whereNotNull('seat_number')
                    ->with('tournament:id,title,status')
                    ->orderByDesc('updated_at');
            },
        ])
        ->withCount('registrations')
        ->paginate(5);

    $json = App\Http\Resources\V1\UserResource::collection($users)->toJson();
    echo "OK\n";
    echo substr($json, 0, 500) . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}
