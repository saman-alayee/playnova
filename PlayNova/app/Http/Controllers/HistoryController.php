<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index()
    {
        $finishedTournaments = Tournament::where('status', 'ended')
            ->with('winner:id,username')
            ->orderByDesc('end_date')
            ->paginate(10);

        return view('history', compact('finishedTournaments'));
    }
}