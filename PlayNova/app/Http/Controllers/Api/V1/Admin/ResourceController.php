<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
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
    public function tournaments(): JsonResponse
    {
        $this->authorizeAdmin();

        $tournaments = Tournament::with('winner')->orderByDesc('start_date')->paginate(30);

        return $this->paginated($tournaments, TournamentResource::class);
    }

    public function users(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $query = User::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('cod_id', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('created_at')->paginate(30);

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

        $withdrawals = $query->orderByDesc('created_at')->paginate(30);

        return $this->paginated($withdrawals, TransactionResource::class);
    }

    public function kyc(): JsonResponse
    {
        $this->authorizeAdmin();

        $submissions = KycSubmission::with('user')->orderByDesc('created_at')->paginate(20);

        return $this->paginated($submissions);
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

    protected function authorizeAdmin(): void
    {
        $user = request()->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'دسترسی ادمین لازم است.');
        }
    }
}
