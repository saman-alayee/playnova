<?php

namespace App\Jobs;

use App\Services\MelipayamakSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOtpSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public string $mobile,
        public int $code,
        public string $purpose = 'register',
    ) {}

    public function handle(MelipayamakSmsService $sms): array
    {
        $result = $sms->sendOtp($this->mobile, $this->code, $this->purpose);

        if (! $result['ok']) {
            Log::warning('SendOtpSmsJob failed', [
                'mobile' => $this->mobile,
                'purpose' => $this->purpose,
                'message' => $result['message'] ?? null,
            ]);
        }

        return $result;
    }

    /** Run inline when caller must know SMS result immediately (register/reset). */
    public static function sendNow(string $mobile, int $code, string $purpose = 'register'): array
    {
        return static::dispatchSync($mobile, $code, $purpose);
    }
}
