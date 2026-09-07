<?php

namespace Tests\Feature;

use App\Jobs\ExpireTeamInviteJob;
use App\Models\Registration;
use App\Models\TeamInvite;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TeamInviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamInviteExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_pending_invite_is_expired_even_after_the_deadline(): void
    {
        [$inviter, $invite] = $this->createOverdueInvite();

        (new ExpireTeamInviteJob($invite->id))->handle(app(TeamInviteService::class));

        $this->assertSame(TeamInvite::STATUS_EXPIRED, $invite->fresh()->status);
        $this->assertNotNull(
            Registration::query()
                ->where('user_id', $inviter->id)
                ->where('tournament_id', $invite->tournament_id)
                ->whereNull('seat_number')
                ->first()
        );
    }

    public function test_home_stops_showing_pending_team_after_the_invite_deadline(): void
    {
        [$inviter, $invite] = $this->createOverdueInvite();

        Sanctum::actingAs($inviter);

        $home = $this->getJson('/api/v1/home')->assertOk();
        $tournament = $this->findHomeTournament($home->json('data'), $invite->tournament_id);

        $this->assertNotNull($tournament);
        $this->assertFalse((bool) ($tournament['pending_team'] ?? false));
        $this->assertTrue((bool) ($tournament['pending_seat'] ?? false));
        $this->assertSame(TeamInvite::STATUS_EXPIRED, $invite->fresh()->status);
    }

    public function test_select_seat_is_allowed_again_after_the_invite_deadline(): void
    {
        [$inviter, $invite] = $this->createOverdueInvite();

        Sanctum::actingAs($inviter);

        $this->getJson("/api/v1/tournaments/{$invite->tournament_id}/select-seat")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(TeamInvite::STATUS_EXPIRED, $invite->fresh()->status);
    }

    public function test_future_invite_stays_pending_team(): void
    {
        $inviter = User::factory()->create(['username' => 'inviter_live']);
        $invitee = User::factory()->create(['username' => 'invitee_live']);
        $tournament = $this->createActiveTournament();

        Registration::create([
            'user_id' => $inviter->id,
            'tournament_id' => $tournament->id,
            'status' => 'waiting',
            'reservation_type' => 'team',
        ]);

        $invite = TeamInvite::create([
            'tournament_id' => $tournament->id,
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
            'status' => TeamInvite::STATUS_PENDING,
            'expires_at' => now()->addSeconds(30),
        ]);

        Sanctum::actingAs($inviter);

        $home = $this->getJson('/api/v1/home')->assertOk();
        $payload = $this->findHomeTournament($home->json('data'), $tournament->id);

        $this->assertNotNull($payload);
        $this->assertTrue((bool) ($payload['pending_team'] ?? false));
        $this->assertFalse((bool) ($payload['pending_seat'] ?? false));
        $this->assertSame(TeamInvite::STATUS_PENDING, $invite->fresh()->status);
    }

    /**
     * @return array{0: User, 1: TeamInvite}
     */
    private function createOverdueInvite(): array
    {
        $inviter = User::factory()->create(['username' => 'inviter_wait']);
        $invitee = User::factory()->create(['username' => 'invitee_wait']);
        $tournament = $this->createActiveTournament();

        Registration::create([
            'user_id' => $inviter->id,
            'tournament_id' => $tournament->id,
            'status' => 'waiting',
            'reservation_type' => 'team',
        ]);

        $invite = TeamInvite::create([
            'tournament_id' => $tournament->id,
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
            'status' => TeamInvite::STATUS_PENDING,
            'expires_at' => now()->subSeconds(5),
        ]);

        return [$inviter, $invite];
    }

    private function createActiveTournament(): Tournament
    {
        return Tournament::create([
            'title' => 'کاستوم دو نفره',
            'game' => 'Call of Duty Mobile',
            'description' => 'test',
            'entry_fee' => 0,
            'prize_pool' => 0,
            'capacity' => 40,
            'seat_mode' => 2,
            'registered_count' => 0,
            'start_date' => now()->addHour(),
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function findHomeTournament(array $data, int $tournamentId): ?array
    {
        $groups = [
            $data['active_tournaments'] ?? [],
            $data['leagues']['beginner'] ?? [],
            $data['leagues']['intermediate'] ?? [],
            $data['leagues']['professional'] ?? [],
        ];

        foreach ($groups as $tournaments) {
            foreach ($tournaments as $tournament) {
                if ((int) ($tournament['id'] ?? 0) === $tournamentId) {
                    return $tournament;
                }
            }
        }

        return null;
    }
}
