<?php

namespace App\Http\Resources\V1;

use App\Support\IranDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'priority' => $this->priority,
            'priority_label' => $this->priorityLabel(),
            'messages_count' => $this->whenCounted('messages'),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_display' => IranDate::formatString($this->created_at),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'updated_at_display' => IranDate::formatString($this->updated_at),
            'user' => new UserResource($this->whenLoaded('user')),
            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),
        ];
    }

    protected function statusLabel(): string
    {
        return match ($this->status) {
            'open' => 'باز',
            'in_progress' => 'در حال بررسی',
            'resolved' => 'حل شده',
            'closed' => 'بسته شده',
            default => (string) $this->status,
        };
    }

    protected function priorityLabel(): string
    {
        return match ($this->priority) {
            'low' => 'کم',
            'medium' => 'متوسط',
            'high' => 'بالا',
            default => (string) $this->priority,
        };
    }
}
