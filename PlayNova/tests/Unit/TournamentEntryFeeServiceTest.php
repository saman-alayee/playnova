<?php

namespace Tests\Unit;

use App\Models\Registration;
use App\Models\Tournament;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Tournament\Services\TournamentRegistrationService;
use App\Services\TournamentEntryFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TournamentEntryFeeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fee_reference_is_unique_per_tournament_and_user(): void
    {
        $service = app(TournamentEntryFeeService::class);

        $this->assertSame('tournament_fee_12_34', $service->feeReference(12, 34));
        $this->assertSame('tournament_refund_12_34', $service->refundReference(12, 34));
        $this->assertNotSame($service->feeReference(12, 34), $service->feeReference(13, 34));
    }

    public function test_zero_entry_fee_still_records_a_completed_fee_transaction(): void
    {
        $user = User::factory()->create(['wallet' => 15000]);
        $tournament = $this->makeTournament(0);
        $service = app(TournamentEntryFeeService::class);

        DB::transaction(fn () => $service->charge($user->fresh(), $tournament));

        $tx = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'fee')
            ->where('reference_id', $service->feeReference($tournament->id, $user->id))
            ->first();

        $this->assertNotNull($tx);
        $this->assertSame('completed', $tx->status);
        $this->assertEquals(0, (float) $tx->amount);
        $this->assertEquals(15000, (float) $user->fresh()->wallet);
        $this->assertTrue($service->hasPaid($user->fresh(), $tournament));
    }

    public function test_zero_entry_fee_charge_is_idempotent(): void
    {
        $user = User::factory()->create(['wallet' => 0]);
        $tournament = $this->makeTournament(0);
        $service = app(TournamentEntryFeeService::class);

        DB::transaction(function () use ($service, $user, $tournament) {
            $service->charge($user->fresh(), $tournament);
            $service->charge($user->fresh(), $tournament);
        });

        $this->assertSame(1, Transaction::query()->where('user_id', $user->id)->where('type', 'fee')->count());
    }

    public function test_paid_entry_fee_still_debits_wallet_and_records_transaction(): void
    {
        $user = User::factory()->create(['wallet' => 20000]);
        $tournament = $this->makeTournament(5000);
        $service = app(TournamentEntryFeeService::class);

        DB::transaction(fn () => $service->charge($user->fresh(), $tournament));

        $tx = Transaction::query()->where('user_id', $user->id)->where('type', 'fee')->first();

        $this->assertNotNull($tx);
        $this->assertEquals(5000, (float) $tx->amount);
        $this->assertEquals(15000, (float) $user->fresh()->wallet);
    }

    public function test_confirming_a_free_tournament_seat_records_the_entry_fee_transaction(): void
    {
        $user = User::factory()->create(['wallet' => 8000]);
        $tournament = $this->makeTournament(0);

        Registration::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'status' => 'waiting',
            'reservation_type' => 'solo',
        ]);

        $registration = app(TournamentRegistrationService::class)
            ->confirmSoloSeat($user->fresh(), $tournament, 1);

        $this->assertSame(1, $registration->seat_number);
        $this->assertSame('confirmed', $registration->status);

        $tx = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'fee')
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(0, (float) $tx->amount);
        $this->assertStringContainsString($tournament->title, (string) $tx->description);
        $this->assertEquals(8000, (float) $user->fresh()->wallet);
    }

    protected function makeTournament(int $entryFee): Tournament
    {
        return Tournament::create([
            'title' => 'Free Custom Cup',
            'game' => 'Call of Duty Mobile',
            'description' => 'test',
            'entry_fee' => $entryFee,
            'prize_pool' => 0,
            'capacity' => 40,
            'seat_mode' => 1,
            'registered_count' => 0,
            'start_date' => now()->addHour(),
            'status' => 'active',
        ]);
    }
}
