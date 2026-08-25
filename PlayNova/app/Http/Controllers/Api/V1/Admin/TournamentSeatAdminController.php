<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Models\Registration;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;

class TournamentSeatAdminController extends BaseApiController
{
    use AuthorizesAdmin;

    public function index(): JsonResponse
    {
        $this->authorizeSeatAdmin();

        $tournaments = Tournament::whereIn('status', ['active', 'ongoing', 'upcoming'])
            ->orderByDesc('start_date')
            ->get();

        return $this->success($tournaments);
    }

    public function show(Tournament $tournament): JsonResponse
    {
        $this->authorizeSeatAdmin();

        $occupiedSeats = Registration::where('tournament_id', $tournament->id)
            ->whereNotNull('seat_number')
            ->with('user:id,username,cod_id')
            ->get()
            ->mapWithKeys(fn ($reg) => [
                $reg->seat_number => [
                    'seat_number' => $reg->seat_number,
                    'user_id' => $reg->user_id,
                    'username' => $reg->user?->username,
                    'cod_id' => $reg->user?->cod_id,
                ],
            ]);

        return $this->success([
            'tournament' => $tournament,
            'occupied_seats' => $occupiedSeats,
            'capacity' => $tournament->capacity,
            'seat_mode' => $tournament->seatMode(),
        ]);
    }
}
