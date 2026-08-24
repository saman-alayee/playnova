<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\V1\TournamentResource;
use App\Http\Resources\V1\TransactionResource;
use App\Http\Resources\V1\UserResource;
use App\Models\KycSubmission;
use App\Models\Ticket;
use App\Models\Tournament;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        $stats = Cache::remember('admin:dashboard:stats', 300, function () {
            $totalEntryFees = Transaction::where('type', 'entry_fee')->where('status', 'completed')->sum('amount');
            $totalPrizesPaid = Transaction::where('type', 'prize')->where('status', 'completed')->sum('amount');

            return [
                'total_users' => User::count(),
                'total_tournaments' => Tournament::count(),
                'active_tournaments' => Tournament::where('status', 'active')->count(),
                'total_deposits' => Transaction::where('type', 'deposit')->where('status', 'completed')->sum('amount'),
                'total_withdraws_completed' => Transaction::where('type', 'withdraw')->where('status', 'completed')->sum('amount'),
                'pending_withdraws' => Transaction::where('type', 'withdraw')->where('status', 'pending')->sum('amount'),
                'total_entry_fees' => $totalEntryFees,
                'total_prizes_paid' => $totalPrizesPaid,
                'net_revenue' => $totalEntryFees - $totalPrizesPaid,
                'open_tickets' => Ticket::where('status', 'open')->count(),
            ];
        });

        return $this->success($stats);
    }

    protected function authorizeAdmin(): void
    {
        $user = request()->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'دسترسی ادمین لازم است.');
        }
    }
}
