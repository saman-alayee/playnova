<?php

namespace App\Http\Controllers\Admin;

use App\Models\Registration;
use App\Models\Tournament;

class TournamentSeatController extends BaseSeatAdminController
{
    public function tournamentSeatsIndex()
    {
        $tournaments = Tournament::whereIn('status', ['active', 'ongoing', 'upcoming'])
            ->orderByDesc('start_date')
            ->get();

        return view('admin.tournament-seats-index', compact('tournaments'));
    }

    public function tournamentSeats(Tournament $tournament)
    {
        $occupiedSeats = Registration::where('tournament_id', $tournament->id)
            ->whereNotNull('seat_number')
            ->with('user')
            ->get()
            ->keyBy('seat_number');

        return view('admin.tournament-seats', compact('tournament', 'occupiedSeats'));
    }
}
