<?php

namespace App\Http\Resources\V1;

use App\Support\IranDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'cod_id' => $this->cod_id,
            'cod_id_changed' => (bool) $this->cod_id_changed,
            'wallet' => (float) $this->wallet,
            'kills' => (int) $this->kills,
            'wins' => (int) $this->wins,
            'losses' => (int) $this->losses,
            'referral_code' => $this->referral_code,
            'kyc_verified' => $this->isKycVerified(),
            'kyc_submission_status' => $this->latestKycSubmission?->status,
            'kyc_wallet_cap' => $this->kycWalletCap(),
            'bank_card_number' => $this->bank_card_number,
            'bank_account_name' => $this->bank_account_name,
            'first_deposit_done' => (bool) $this->first_deposit_done,
            'is_admin' => (bool) $this->is_admin,
            'is_seat_admin' => (bool) $this->is_seat_admin,
            'unread_notifications_count' => (int) ($this->unread_notifications_count ?? 0),
            'registrations_count' => $this->whenCounted('registrations'),
            'referrer_username' => $this->whenLoaded('referrer', fn () => $this->referrer?->username),
            'active_seats' => RegistrationResource::collection($this->whenLoaded('registrations')),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_display' => IranDate::formatString($this->created_at),
        ];
    }
}
