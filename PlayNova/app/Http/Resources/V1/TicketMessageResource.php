<?php

namespace App\Http\Resources\V1;

use App\Support\IranDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'body' => $this->body,
            'is_admin' => (bool) $this->is_admin,
            'has_attachment' => ! empty($this->attachment_path),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_display' => IranDate::formatString($this->created_at),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
