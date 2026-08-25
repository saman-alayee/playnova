<?php

namespace Tests\Unit;

use App\Services\TournamentEntryFeeService;
use Tests\TestCase;

class TournamentEntryFeeServiceTest extends TestCase
{
    public function test_fee_reference_is_unique_per_tournament_and_user(): void
    {
        $service = app(TournamentEntryFeeService::class);

        $this->assertSame('tournament_fee_12_34', $service->feeReference(12, 34));
        $this->assertSame('tournament_refund_12_34', $service->refundReference(12, 34));
        $this->assertNotSame($service->feeReference(12, 34), $service->feeReference(13, 34));
    }
}
