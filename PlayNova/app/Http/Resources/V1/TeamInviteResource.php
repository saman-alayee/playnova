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
            'expires_at' => $this->expires_at?->toIso8601String(),
            'seconds_remaining' => $this->secondsRemaining(),
            'team_group_id' => $this->team_group_id,
            'tournament_title' => $this->whenLoaded('tournament', fn () => $this->tournament?->title),
            'inviter_username' => $this->whenLoaded('inviter', fn () => $this->inviter?->username),
            'invitee_username' => $this->whenLoaded('invitee', fn () => $this->invitee?->username),
            'inviter_cod_id' => $this->whenLoaded('inviter', fn () => $this->inviter?->cod_id),
            'invitee_cod_id' => $this->whenLoaded('invitee', fn () => $this->invitee?->cod_id),
            'tournament' => new TournamentResource($this->whenLoaded('tournament')),
            'inviter' => new UserResource($this->whenLoaded('inviter')),
            'invitee' => new UserResource($this->whenLoaded('invitee')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
