<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\TransactionResource;
use App\Http\Resources\V1\UserResource;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ZibalGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletController extends BaseApiController
{
    public function __construct(protected ZibalGatewayService $zibal)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $transactions = $user->transactions()
            ->where(function ($query) {
                $query->where('type', '!=', 'deposit')
                    ->orWhere('status', 'completed');
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->paginated($transactions, TransactionResource::class);
    }

    public function deposit(Request $request): JsonResponse
    {
        $user = $request->user();
        $kycVerified = $user->isKycVerified();
        $walletCap = $user->kycWalletCap();

        $rules = [
            'amount' => 'required|numeric|min:10000',
        ];

        if (! $kycVerified) {
            $remaining = max(0, $walletCap - (int) $user->wallet);
            if ($remaining <= 0) {
                return $this->error('تا زمان تأیید احراز هویت، سقف موجودی کیف پول ۱,۰۰۰,۰۰۰ تومان است. لطفاً از بخش احراز هویت مدارک را ارسال کنید.', 422);
            }
            $rules['amount'] .= '|max:' . $remaining;
        } else {
            $rules['amount'] .= '|max:50000000';
        }

        $request->validate($rules, [
            'amount.max' => $kycVerified
                ? 'حداکثر مبلغ هر واریز ۵۰,۰۰۰,۰۰۰ تومان است.'
                : 'تا قبل از تأیید احراز هویت، حداکثر موجودی کیف پول ۱,۰۰۰,۰۰۰ تومان است. مبلغ واریز را کاهش دهید یا احراز هویت را تکمیل کنید.',
        ]);

        $amount = (int) $request->amount;
        $referenceId = 'DEP-' . Str::upper(Str::random(10));

        if (Setting::isPaymentGatewayActive() && ! Setting::isZibalConfigured()) {
            return $this->error('درگاه پرداخت فعال است ولی مرچنت کد زیبال تنظیم نشده است.', 422);
        }

        if (! $this->zibal->isEnabled()) {
            DB::transaction(function () use ($user, $amount, $referenceId) {
                $isFirstDeposit = ! $user->first_deposit_done;

                $user->wallet = round($user->wallet + $amount, 2);
                $user->first_deposit_done = true;
                $user->save();

                $user->transactions()->create([
                    'type' => 'deposit',
                    'amount' => $amount,
                    'balance_after' => $user->wallet,
                    'description' => 'شارژ کیف پول (حالت شبیه‌سازی)',
                    'reference_id' => $referenceId,
                    'status' => 'completed',
                ]);

                if ($isFirstDeposit && $user->referred_by) {
                    $referrer = User::find($user->referred_by);
                    if ($referrer) {
                        $bonusPercent = (float) Setting::get('referral_bonus_percent', 5);
                        $bonus = round($amount * ($bonusPercent / 100), 2);
                        if ($bonus > 0) {
                            $referrer->creditWallet(
                                $bonus,
                                'referral_bonus',
                                'پاداش معرفی کاربر: ' . $user->username,
                                'referral_' . $user->id
                            );
                        }
                    }
                }
            });

            $user->refresh();

            return $this->success([
                'user' => new UserResource($user),
                'simulated' => true,
            ], 'کیف پول شما با موفقیت شارژ شد (حالت شبیه‌سازی).');
        }

        $payment = $this->zibal->requestPayment(
            $amount,
            $this->zibal->callbackUrl(),
            'شارژ کیف پول PlayNova - ' . $referenceId,
            $user->mobile,
            $referenceId
        );

        if (! ($payment['ok'] ?? false)) {
            return $this->error($payment['message'] ?? 'اتصال به درگاه پرداخت زیبال ناموفق بود.', 422);
        }

        cache()->put('wallet_deposit_' . $payment['track_id'], [
            'user_id' => $user->id,
            'amount' => $amount,
            'order_id' => $referenceId,
        ], now()->addHours(3));

        return $this->success([
            'redirect_url' => $payment['redirect_url'],
            'track_id' => $payment['track_id'],
            'reference_id' => $referenceId,
        ], 'در حال انتقال به درگاه پرداخت.');
    }

    public function withdraw(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:50000',
            'bank_card_confirm' => ['required', 'string', 'max:24', 'regex:/^[0-9\-]*$/'],
        ]);

        $user = $request->user();
        $amount = (float) $request->amount;

        if (empty($user->bank_card_number)) {
            return $this->error('ابتدا در پروفایل شماره کارت بانکی خود را ثبت کنید.', 422);
        }

        $savedCard = preg_replace('/\D+/', '', (string) $user->bank_card_number);
        $confirmedCard = preg_replace('/\D+/', '', (string) $request->bank_card_confirm);

        if ($savedCard === '' || $confirmedCard !== $savedCard) {
            return $this->error('شماره کارت وارد شده با کارت ثبت‌شده در پروفایل مطابقت ندارد.', 422, [
                'bank_card_confirm' => ['شماره کارت وارد شده با کارت ثبت‌شده در پروفایل مطابقت ندارد.'],
            ]);
        }

        if ($user->wallet < $amount) {
            return $this->error('موجودی کیف پول شما کافی نیست.', 422);
        }

        $transaction = null;

        DB::transaction(function () use ($user, $amount, &$transaction) {
            $user->wallet = round($user->wallet - $amount, 2);
            $user->save();

            $cardNumber = preg_replace('/\D+/', '', (string) $user->bank_card_number);
            $accountName = trim((string) ($user->bank_account_name ?? ''));
            $description = 'درخواست برداشت وجه';
            if ($cardNumber !== '') {
                $description .= ' | کارت: ' . $cardNumber;
            }
            if ($accountName !== '') {
                $description .= ' | صاحب حساب: ' . $accountName;
            }

            $transaction = $user->transactions()->create([
                'type' => 'withdraw',
                'amount' => $amount,
                'balance_after' => $user->wallet,
                'description' => $description,
                'reference_id' => 'WD-' . Str::upper(Str::random(10)),
                'status' => 'pending',
            ]);
        });

        $user->refresh();

        return $this->success([
            'user' => new UserResource($user),
            'transaction' => new TransactionResource($transaction),
        ], 'درخواست برداشت شما ثبت شد و پس از بررسی پرداخت خواهد شد.', 201);
    }

    public function callbackInfo(): JsonResponse
    {
        return $this->success([
            'callback_url' => $this->zibal->callbackUrl(),
            'gateway_enabled' => $this->zibal->isEnabled(),
            'payment_gateway_active' => Setting::isPaymentGatewayActive(),
            'zibal_configured' => Setting::isZibalConfigured(),
        ]);
    }

    public function callback(Request $request): JsonResponse
    {
        $trackId = $request->query('trackId') ?? $request->query('track_id');
        $orderId = $request->query('orderId') ?? $request->query('order_id');
        $success = (string) ($request->query('success') ?? '');

        Log::info('Zibal wallet callback (API)', [
            'trackId' => $trackId,
            'orderId' => $orderId,
            'success' => $success,
            'query' => $request->query(),
        ]);

        if (! $trackId || ! in_array($success, ['1', 'true'], true)) {
            $this->clearAbandonedDeposit((string) $trackId, $orderId ? (string) $orderId : null);

            return $this->error('پرداخت توسط کاربر لغو شد یا ناموفق بود.', 422);
        }

        $pending = cache()->get('wallet_deposit_' . $trackId);

        $transaction = Transaction::where('reference_id', (string) $trackId)
            ->where('type', 'deposit')
            ->first();

        if (! $transaction && ! $pending && $orderId) {
            $pending = cache()->get('wallet_deposit_' . $orderId);
            $transaction = Transaction::where('type', 'deposit')
                ->where('status', 'pending')
                ->where(function ($query) use ($orderId) {
                    $query->where('reference_id', (string) $orderId)
                        ->orWhere('description', 'like', '%' . $orderId . '%');
                })
                ->orderByDesc('id')
                ->first();
        }

        if (! $pending && ! $transaction) {
            return $this->error('تراکنش یافت نشد.', 404);
        }

        if ($transaction && $transaction->status === 'completed') {
            cache()->forget('wallet_deposit_' . $trackId);

            return $this->success(null, 'کیف پول شما قبلاً شارژ شده است.');
        }

        $result = $this->zibal->verify((string) $trackId);

        if (! ($result['ok'] ?? false)) {
            $this->clearAbandonedDeposit((string) $trackId, $orderId ? (string) $orderId : null);

            return $this->error($result['message'] ?? 'پرداخت تأیید نشد.', 422);
        }

        $userId = $pending['user_id'] ?? $transaction?->user_id;
        $amount = (int) ($pending['amount'] ?? $transaction?->amount ?? 0);
        $user = $userId ? User::find($userId) : null;

        if (! $user || $amount <= 0) {
            $this->clearAbandonedDeposit((string) $trackId, $orderId ? (string) $orderId : null);

            return $this->error('اطلاعات پرداخت نامعتبر است.', 422);
        }

        DB::transaction(function () use ($user, $amount, $trackId, $transaction, $result, $pending) {
            if ($transaction && $transaction->status === 'completed') {
                return;
            }

            if ($transaction && $transaction->status === 'pending') {
                $transaction->delete();
            }

            $isFirstDeposit = ! $user->first_deposit_done;

            $user->wallet = round($user->wallet + $amount, 2);
            $user->first_deposit_done = true;
            $user->save();

            $refNumber = $result['ref_number'] ?? null;
            $description = 'شارژ کیف پول از طریق زیبال';
            if ($refNumber) {
                $description .= ' | ref: ' . $refNumber;
            }

            $user->transactions()->create([
                'type' => 'deposit',
                'amount' => $amount,
                'balance_after' => $user->wallet,
                'description' => $description,
                'reference_id' => (string) $trackId,
                'status' => 'completed',
            ]);

            if ($isFirstDeposit && $user->referred_by) {
                $referrer = User::find($user->referred_by);
                if ($referrer) {
                    $bonusPercent = (float) Setting::get('referral_bonus_percent', 5);
                    $bonus = round($amount * ($bonusPercent / 100), 2);
                    if ($bonus > 0) {
                        $referrer->creditWallet(
                            $bonus,
                            'referral_bonus',
                            'پاداش معرفی کاربر: ' . $user->username,
                            'referral_' . $user->id
                        );
                    }
                }
            }

            if ($pending) {
                cache()->forget('wallet_deposit_' . $trackId);
                if (! empty($pending['order_id'])) {
                    cache()->forget('wallet_deposit_' . $pending['order_id']);
                }
            }
        });

        $user->refresh();

        return $this->success(new UserResource($user), 'کیف پول شما با موفقیت شارژ شد.');
    }

    protected function clearAbandonedDeposit(?string $trackId, ?string $orderId = null): void
    {
        if ($trackId) {
            cache()->forget('wallet_deposit_' . $trackId);
        }
        if ($orderId) {
            cache()->forget('wallet_deposit_' . $orderId);
        }

        $query = Transaction::query()
            ->where('type', 'deposit')
            ->whereIn('status', ['pending', 'failed', 'cancelled']);

        if ($trackId || $orderId) {
            $query->where(function ($q) use ($trackId, $orderId) {
                if ($trackId) {
                    $q->orWhere('reference_id', $trackId);
                }
                if ($orderId) {
                    $q->orWhere('reference_id', $orderId)
                        ->orWhere('description', 'like', '%' . $orderId . '%');
                }
            });
        }

        $query->delete();
    }
}
