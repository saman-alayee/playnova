<?php

namespace Tests\Feature\Wallet;

use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TournamentEntryFeeTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_lists_zero_entry_fee_after_confirming_a_free_tournament_seat(): void
    {
        $user = User::factory()->create(['wallet' => 12000]);
        $tournament = Tournament::create([
            'title' => 'کاستوم رایگان',
            'game' => 'Call of Duty Mobile',
            'description' => 'test',
            'entry_fee' => 0,
            'prize_pool' => 0,
            'capacity' => 40,
            'seat_mode' => 1,
            'registered_count' => 0,
            'start_date' => now()->addHour(),
            'status' => 'active',
        ]);

        Registration::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'status' => 'waiting',
            'reservation_type' => 'solo',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/tournaments/{$tournament->id}/select-seat", [
            'seat_number' => 7,
        ])->assertOk()->assertJsonPath('success', true);

        $wallet = $this->getJson('/api/v1/wallet')->assertOk();

        $transactions = $wallet->json('data.transactions');
        $this->assertIsArray($transactions);
        $this->assertCount(1, $transactions);
        $this->assertSame('fee', $transactions[0]['type']);
        $this->assertSame(0, (int) $transactions[0]['amount']);
        $this->assertSame('completed', $transactions[0]['status']);
        $this->assertStringContainsString('کاستوم رایگان', (string) $transactions[0]['description']);
        $this->assertEquals(12000, (float) $wallet->json('data.balance'));
    }
}
