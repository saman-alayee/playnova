<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tournament = $this->relationLoaded('tournament') ? $this->tournament : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'tournament_id' => $this->tournament_id,
            'status' => $this->status,
            'reservation_type' => $this->reservation_type ?? 'solo',
            'seat_number' => $this->seat_number,
            'seat_label' => $tournament && $this->seat_number
                ? $tournament->seatDisplayLabel((int) $this->seat_number)
                : null,
            'rank' => $this->rank,
            'has_confirmed_seat' => $this->hasConfirmedSeat(),
            'user' => new UserResource($this->whenLoaded('user')),
            'tournament' => new TournamentResource($this->whenLoaded('tournament')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
