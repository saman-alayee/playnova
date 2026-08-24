<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\TournamentResource;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;

class HistoryController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $finishedTournaments = Tournament::where('status', 'ended')
            ->with('winner:id,username')
            ->orderByDesc('end_date')
            ->paginate(10);

        return $this->paginated($finishedTournaments, TournamentResource::class);
    }
}
