<?php

namespace App\Http\Controllers\Admin;

use App\Models\Ticket;
use App\Models\Tournament;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardController extends BaseAdminController
{
    public function index()
    {
        $stats = Cache::remember('admin:dashboard:stats', 300, function () {
            $totalEntryFees = Transaction::query()
                ->whereIn('type', ['fee', 'entry_fee'])
                ->where('status', 'completed')
                ->sum('amount');
            $totalPrizesPaid = Transaction::where('type', 'prize')->where('status', 'completed')->sum('amount');

            return [
                'totalUsers' => User::count(),
                'totalTournaments' => Tournament::count(),
                'activeTournaments' => Tournament::where('status', 'active')->count(),
                'totalDeposits' => Transaction::where('type', 'deposit')->where('status', 'completed')->sum('amount'),
                'totalWithdrawsCompleted' => Transaction::where('type', 'withdraw')->where('status', 'completed')->sum('amount'),
                'pendingWithdraws' => Transaction::where('type', 'withdraw')->where('status', 'pending')->sum('amount'),
                'totalEntryFees' => $totalEntryFees,
                'totalPrizesPaid' => $totalPrizesPaid,
                'netRevenue' => $totalEntryFees - $totalPrizesPaid,
                'openTickets' => Ticket::where('status', 'open')->count(),
            ];
        });

        return view('admin.dashboard', $stats);
    }
}
