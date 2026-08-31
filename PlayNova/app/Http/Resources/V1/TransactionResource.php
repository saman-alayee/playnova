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
            'user_id' => $this->user_id,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'amount' => abs((float) $this->amount),
            'balance_after' => (float) $this->balance_after,
            'description' => $this->description,
            'reference_id' => $this->reference_id,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'rejection_reason' => $this->rejectionReason(),
            'displayed_at' => $this->displayedAt()?->toIso8601String(),
            'displayed_at_display' => IranDate::formatString($this->displayedAt()),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_display' => IranDate::formatString($this->created_at),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'mobile' => $this->user->mobile,
                'cod_id' => $this->user->cod_id,
                'wallet' => (float) $this->user->wallet,
                'bank_card_number' => $this->user->bank_card_number,
                'bank_account_name' => $this->user->bank_account_name,
            ]),
        ];
    }
}
