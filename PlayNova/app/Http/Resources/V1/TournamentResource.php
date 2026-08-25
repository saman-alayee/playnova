<?php

namespace App\Http\Resources\V1;

use App\Support\IranDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'game' => $this->game,
            'league' => $this->league,
            'description' => $this->description,
            'entry_fee' => (int) $this->entry_fee,
            'prize_pool' => (int) $this->prize_pool,
            'capacity' => (int) $this->capacity,
            'registered_count' => (int) $this->registered_count,
            'remaining_capacity' => $this->remaining_capacity,
            'seat_mode' => $this->seatMode(),
            'seat_mode_label' => $this->seatModeLabel(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'accepts_registration' => $this->acceptsRegistration(),
            'allows_game_login' => $this->allowsGameLogin(),
            'has_published_game_login' => $this->hasPublishedGameLogin(),
            'start_date' => $this->start_date?->toIso8601String(),
            'start_date_display' => IranDate::formatString($this->start_date),
            'end_date' => $this->end_date?->toIso8601String(),
            'end_date_display' => IranDate::formatString($this->end_date),
            'winner' => new UserResource($this->whenLoaded('winner')),
            'registrations_count' => $this->when(
                isset($this->registrations_count),
                (int) $this->registrations_count
            ),
            'is_registered' => $this->when(isset($this->is_registered), (bool) $this->is_registered),
            'pending_seat' => $this->when(isset($this->pending_seat), (bool) $this->pending_seat),
            'pending_team' => $this->when(isset($this->pending_team), (bool) $this->pending_team),
            'teams_grid' => $this->when(
                $request->routeIs('*.select-seat') || $request->boolean('include_teams_grid'),
                fn () => $this->teamsForGrid()
            ),
        ];
    }
}
