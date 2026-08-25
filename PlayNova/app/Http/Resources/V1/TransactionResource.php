<?php

namespace App\Http\Resources\V1;

use App\Support\IranDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'balance_after' => (float) $this->balance_after,
            'description' => $this->description,
            'reference_id' => $this->reference_id,
            'status' => $this->status,
            'rejection_reason' => $this->rejectionReason(),
            'displayed_at' => $this->displayedAt()?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_display' => IranDate::formatString($this->created_at),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
