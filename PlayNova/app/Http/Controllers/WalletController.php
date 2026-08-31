<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\ZibalGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function __construct(protected ZibalGatewayService $zibal)
    {
    }

    public function index()
    {
        $user = Auth::user();

        $transactions = $user->transactions()
            ->where(function ($query) {
                $query->where('type', '!=', 'deposit')
                    ->orWhere('status', 'completed');
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('wallet', compact('transactions'));
    }

    public function deposit(Request $request)
    {
        $user = Auth::user();
        $kycVerified = $user->isKycVerified();
        $walletCap = $user->kycWalletCap();

        $rules = [
            'amount' => 'required|numeric|min:10000',
        ];

        if (! $kycVerified) {
            $remaining = max(0, $walletCap - (int) $user->wallet);
            if ($remaining <= 0) {
                return back()->with('error', 'تا زمان تأیید احراز هویت، سقف واریز ۱,۰۰۰,۰۰۰ تومان است. لطفاً از بخش احراز هویت مدارک را ارسال کنید.');
            }
            $rules['amount'] .= '|max:' . $remaining;
        } else {
            $rules['amount'] .= '|max:50000000';
        }

        $request->validate($rules, [
            'amount.max' => $kycVerified
                ? 'حداکثر مبلغ هر واریز ۵۰,۰۰۰,۰۰۰ تومان است.'
                : 'تا قبل از تأیید احراز هویت، سقف واریز ۱,۰۰۰,۰۰۰ تومان است. مبلغ واریز را کاهش دهید یا احراز هویت را تکمیل کنید.',
        ]);

        $amount = (int) $request->amount;
        $referenceId = 'DEP-' . Str::upper(Str::random(10));

        if (\App\Models\Setting::isPaymentGatewayActive() && ! \App\Models\Setting::isZibalConfigured()) {
            return back()->with('error', 'درگاه پرداخت فعال است ولی مرچنت کد زیبال تنظیم نشده است.');
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
                    $referrer = \App\Models\User::find($user->referred_by);
                    if ($referrer) {
                        $bonusPercent = (float) \App\Models\Setting::get('referral_bonus_percent', 5);
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

            return redirect()->route('wallet.index')->with('success', 'کیف پول شما با موفقیت شارژ شد (حالت شبیه‌سازی).');
        }

        $payment = $this->zibal->requestPayment(
            $amount,
            $this->zibal->callbackUrl(),
            'شارژ کیف پول PlayNova - ' . $referenceId,
            $user->mobile,
            $referenceId
        );

        if (! ($payment['ok'] ?? false)) {
            return back()->with('error', $payment['message'] ?? 'اتصال به درگاه پرداخت زیبال ناموفق بود.');
        }

        cache()->put('wallet_deposit_' . $payment['track_id'], [
            'user_id' => $user->id,
            'amount' => $amount,
            'order_id' => $referenceId,
        ], now()->addHours(3));

        return redirect()->away($payment['redirect_url']);
    }

    public function callback(Request $request)
    {
        $trackId = $request->query('trackId') ?? $request->query('track_id');
        $orderId = $request->query('orderId') ?? $request->query('order_id');
        $success = (string) ($request->query('success') ?? '');

        Log::info('Zibal wallet callback', [
            'trackId' => $trackId,
            'orderId' => $orderId,
            'success' => $success,
            'query' => $request->query(),
        ]);

        if (! $trackId || ! in_array($success, ['1', 'true'], true)) {
            $this->clearAbandonedDeposit((string) $trackId, $orderId ? (string) $orderId : null);

            return $this->callbackRedirect('error', 'پرداخت توسط کاربر لغو شد یا ناموفق بود.');
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
            return $this->callbackRedirect('error', 'تراکنش یافت نشد.');
        }

        if ($transaction && $transaction->status === 'completed') {
            cache()->forget('wallet_deposit_' . $trackId);

            return $this->callbackRedirect('success', 'کیف پول شما قبلاً شارژ شده است.');
        }

        $result = $this->zibal->verify((string) $trackId);

        if (! ($result['ok'] ?? false)) {
            $this->clearAbandonedDeposit((string) $trackId, $orderId ? (string) $orderId : null);

            return $this->callbackRedirect('error', $result['message'] ?? 'پرداخت تأیید نشد.');
        }

        $userId = $pending['user_id'] ?? $transaction?->user_id;
        $amount = (int) ($pending['amount'] ?? $transaction?->amount ?? 0);
        $user = $userId ? \App\Models\User::find($userId) : null;

        if (! $user || $amount <= 0) {
            $this->clearAbandonedDeposit((string) $trackId, $orderId ? (string) $orderId : null);

            return $this->callbackRedirect('error', 'اطلاعات پرداخت نامعتبر است.');
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
                $referrer = \App\Models\User::find($user->referred_by);
                if ($referrer) {
                    $bonusPercent = (float) \App\Models\Setting::get('referral_bonus_percent', 5);
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

        return $this->callbackRedirect('success', 'کیف پول شما با موفقیت شارژ شد.');
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

    protected function callbackRedirect(string $type, string $message)
    {
        if (Auth::check()) {
            return redirect()->route('wallet.index')->with($type, $message);
        }

        return redirect()->route('login')->with($type, $message);
    }

    public function requestWithdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:50000',
            'bank_card_confirm' => ['required', 'string', 'max:24', 'regex:/^[0-9\-]*$/'],
        ]);

        $user = Auth::user();
        $amount = (float) $request->amount;

        if (empty($user->bank_card_number)) {
            return back()->with('error', 'ابتدا در پروفایل شماره کارت بانکی خود را ثبت کنید.');
        }

        $savedCard = preg_replace('/\D+/', '', (string) $user->bank_card_number);
        $confirmedCard = preg_replace('/\D+/', '', (string) $request->bank_card_confirm);

        if ($savedCard === '' || $confirmedCard !== $savedCard) {
            return back()
                ->withInput($request->only('amount', 'bank_card_confirm'))
                ->withErrors(['bank_card_confirm' => 'شماره کارت وارد شده با کارت ثبت‌شده در پروفایل مطابقت ندارد.']);
        }

        if ($user->wallet < $amount) {
            return back()->with('error', 'موجودی کیف پول شما کافی نیست.');
        }

        DB::transaction(function () use ($user, $amount) {
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

            $user->transactions()->create([
                'type' => 'withdraw',
                'amount' => $amount,
                'balance_after' => $user->wallet,
                'description' => $description,
                'reference_id' => 'WD-' . Str::upper(Str::random(10)),
                'status' => 'pending',
            ]);
        });

        return back()->with('success', 'درخواست برداشت شما ثبت شد و پس از بررسی پرداخت خواهد شد.');
    }
}
