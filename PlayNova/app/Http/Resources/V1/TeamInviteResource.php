<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamInviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'failure_reason' => $this->failure_reason,
            'seat_number_inviter' => $this->seat_number_inviter,
            'seat_number_invitee' => $this->seat_number_invitee,
            'is_pending' => $this->isPending(),
            'tournament' => new TournamentResource($this->whenLoaded('tournament')),
            'inviter' => new UserResource($this->whenLoaded('inviter')),
            'invitee' => new UserResource($this->whenLoaded('invitee')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
