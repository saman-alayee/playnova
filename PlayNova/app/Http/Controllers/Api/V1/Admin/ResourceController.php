<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\V1\KycSubmissionResource;
use App\Http\Resources\V1\TournamentResource;
use App\Http\Resources\V1\TransactionResource;
use App\Http\Resources\V1\UserResource;
use App\Modules\Content\Services\ContentCacheService;
use App\Models\KycSubmission;
use App\Models\Setting;
use App\Models\Tournament;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends BaseApiController
{
    use AuthorizesAdmin;
    public function tournaments(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $query = Tournament::with('winner');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('game', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $status = (string) $request->query('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $sort = (string) $request->query('sort', 'newest');
        if ($sort === 'start_date') {
            $query->orderByDesc('start_date');
        } elseif ($sort === 'entry_fee') {
            $query->orderByDesc('entry_fee')->orderByDesc('id');
        } elseif ($sort === 'capacity') {
            $query->orderByDesc('capacity')->orderByDesc('id');
        } else {
            $query->orderByDesc('created_at');
        }

        $tournaments = $query->paginate(30);

        return $this->paginated($tournaments, TournamentResource::class);
    }

    public function users(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $query = User::query()
            ->with([
                'latestKycSubmission' => fn ($q) => $q->select(
                    'kyc_submissions.id',
                    'kyc_submissions.user_id',
                    'kyc_submissions.status',
                    'kyc_submissions.created_at',
                ),
                'referrer:id,username',
                'registrations' => function ($q) {
                    $q->whereNotNull('seat_number')
                        ->with('tournament:id,title,status')
                        ->orderByDesc('updated_at');
                },
            ])
            ->withCount('registrations');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('cod_id', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $role = (string) $request->query('role', 'all');
        if ($role === 'admin') {
            $query->where('is_admin', true);
        } elseif ($role === 'seat_admin') {
            $query->where('is_seat_admin', true)->where('is_admin', false);
        } elseif ($role === 'regular') {
            $query->where('is_admin', false)->where('is_seat_admin', false);
        }

        $kyc = (string) $request->query('kyc', 'all');
        if ($kyc === 'verified') {
            $query->whereNotNull('kyc_verified_at');
        } elseif ($kyc === 'pending') {
            $query->whereNull('kyc_verified_at')
                ->whereHas('latestKycSubmission', fn ($q) => $q->where('status', 'pending'));
        } elseif ($kyc === 'unverified') {
            $query->whereNull('kyc_verified_at')
                ->whereDoesntHave('latestKycSubmission', fn ($q) => $q->where('status', 'pending'));
        }

        $deposit = (string) $request->query('deposit', 'all');
        if ($deposit === 'done') {
            $query->where('first_deposit_done', true);
        } elseif ($deposit === 'not_done') {
            $query->where('first_deposit_done', false);
        }

        $sort = (string) $request->query('sort', 'newest');
        if ($sort === 'wallet') {
            $query->orderByDesc('wallet')->orderByDesc('id');
        } elseif ($sort === 'kills') {
            $query->orderByDesc('kills')->orderByDesc('id');
        } elseif ($sort === 'username') {
            $query->orderBy('username');
        } else {
            $query->orderByDesc('created_at');
        }

        $users = $query->paginate(30);

        return $this->paginated($users, UserResource::class);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $query = Transaction::with('user')->where('type', 'withdraw');
        $status = $request->query('status', 'pending');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('user_search')) {
            $search = trim((string) $request->user_search);
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('cod_id', 'like', "%{$search}%");
            });
        }

        $withdrawals = $query->orderByDesc('created_at')->paginate(30);

        $userIds = $withdrawals->getCollection()->pluck('user_id')->filter()->unique()->values();
        $userTransactions = $userIds->isEmpty()
            ? collect()
            : Transaction::with('user')
                ->whereIn('user_id', $userIds)
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('user_id')
                ->map(fn ($items) => TransactionResource::collection($items)->resolve());

        $pendingWithdraws = (float) Transaction::where('type', 'withdraw')->where('status', 'pending')->sum('amount');
        $totalWithdrawsCompleted = (float) Transaction::where('type', 'withdraw')->where('status', 'completed')->sum('amount');
        $totalWallets = (float) User::sum('wallet');

        return response()->json([
            'success' => true,
            'data' => TransactionResource::collection($withdrawals->getCollection()),
            'meta' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
            ],
            'links' => [
                'first' => $withdrawals->url(1),
                'last' => $withdrawals->url($withdrawals->lastPage()),
                'prev' => $withdrawals->previousPageUrl(),
                'next' => $withdrawals->nextPageUrl(),
            ],
            'financial_summary' => [
                'pending_withdraws' => $pendingWithdraws,
                'pending_withdrawals_count' => Transaction::where('type', 'withdraw')->where('status', 'pending')->count(),
                'total_withdraws_completed' => $totalWithdrawsCompleted,
                'total_wallets' => $totalWallets,
            ],
            'user_transactions' => $userTransactions,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $query = Transaction::with('user')->orderByDesc('created_at');

        if ($request->filled('user_search')) {
            $search = trim((string) $request->user_search);
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('cod_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tx_type') && $request->tx_type !== 'all') {
            $query->where('type', $request->tx_type);
        }

        $transactions = $query->paginate(40);

        return $this->paginated($transactions, TransactionResource::class);
    }

    public function kyc(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $query = KycSubmission::with('user');

        $status = (string) $request->query('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->orWhere('user_id', (int) $search);
                }

                $q->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('username', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        $submissions = $query->orderByDesc('created_at')->paginate(20);

        return $this->paginated($submissions, KycSubmissionResource::class);
    }

    public function siteSettings(): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->success([
            'privacy_content' => Setting::get('privacy_content', ''),
            'about_content' => Setting::get('about_content', ''),
            'contact_email' => Setting::get('contact_email', ''),
            'contact_phone' => Setting::get('contact_phone', ''),
            'contact_address' => Setting::get('contact_address', ''),
            'social_telegram' => Setting::get('social_telegram', ''),
            'social_rubika' => Setting::get('social_rubika', ''),
            'social_instagram' => Setting::get('social_instagram', ''),
            'results_telegram' => Setting::get('results_telegram', ''),
            'results_rubika' => Setting::get('results_rubika', ''),
        ]);
    }

    public function updateSiteSettings(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'privacy_content' => 'nullable|string',
            'about_content' => 'nullable|string',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_address' => 'nullable|string|max:500',
            'social_telegram' => 'nullable|string|max:255',
            'social_rubika' => 'nullable|string|max:255',
            'social_instagram' => 'nullable|string|max:255',
            'results_telegram' => 'nullable|string|max:255',
            'results_rubika' => 'nullable|string|max:255',
        ]);

        foreach ([
            'privacy_content', 'about_content', 'contact_email', 'contact_phone', 'contact_address',
            'social_telegram', 'social_rubika', 'social_instagram',
            'results_telegram', 'results_rubika',
        ] as $key) {
            Setting::set($key, $request->input($key, ''));
        }

        ContentCacheService::forgetAll();

        return $this->success(null, 'تنظیمات سایت ذخیره شد.');
    }
}
