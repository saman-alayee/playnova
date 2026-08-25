<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Modules\Content\Services\ContentCacheService;
use App\Modules\Tournament\Services\TournamentListingService;
use App\Services\TournamentEntryFeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TournamentController extends Controller
{
    public function home(TournamentListingService $listing)
    {
        extract($listing->homePayload());

        return view('index', compact('activeTournaments', 'leagues', 'news'));
    }

    public function show(Tournament $tournament)
    {
        $registration = null;
        $isRegistered = false;
        $pendingSeat = false;
        if (Auth::check()) {
            $registration = Registration::where('user_id', Auth::id())
                ->where('tournament_id', $tournament->id)
                ->first();

            if ($registration) {
                if ($registration->seat_number === null) {
                    $pendingSeat = true;

                    return redirect()->route('tournaments.select-seat', $tournament);
                }
                $isRegistered = true;
            }
        }

        $players = $tournament->registrations()
            ->whereNotNull('seat_number')
            ->with('user')
            ->get();
        $occupiedSeats = $players->keyBy('seat_number');

        return view('tournaments.show', compact('tournament', 'isRegistered', 'registration', 'pendingSeat', 'players', 'occupiedSeats'));
    }

    public function register(Request $request, Tournament $tournament)
    {
        $user = Auth::user();

        if (! $tournament->acceptsRegistration()) {
            return back()->with('error', 'ثبت‌نام این مسابقه بسته شده است.');
        }

        if ($tournament->isFull()) {
            return back()->with('error', 'ظرفیت مسابقه تکمیل شده است.');
        }

        $existing = Registration::where('user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->first();

        if ($existing) {
            if ($existing->seat_number === null) {
                return redirect()->route('tournaments.select-seat', $tournament)
                    ->with('info', 'ابتدا جایگاه خود را انتخاب و تأیید کنید.');
            }

            return back()->with('error', 'شما قبلاً در این مسابقه ثبت‌نام کرده‌اید.');
        }

        if ($user->wallet < $tournament->entry_fee) {
            return back()->with('error', 'موجودی کیف پول کافی نیست.');
        }

        Registration::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'status' => 'waiting',
            'reservation_type' => 'solo',
        ]);

        return redirect()
            ->route('tournaments.select-seat', $tournament)
            ->with('info', 'برای تکمیل ثبت‌نام، جایگاه خود را انتخاب و تأیید کنید.');
    }

    public function selectSeat(Tournament $tournament)
    {
        $user = Auth::user();
        $registration = Registration::where('user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->first();

        if (! $registration) {
            return redirect()->route('tournaments.show', $tournament)
                ->with('error', 'ابتدا در این مسابقه ثبت‌نام کنید.');
        }

        if ($registration->seat_number === null && ($registration->reservation_type ?? 'solo') === 'team') {
            return redirect()->route('home')
                ->with('info', 'درخواست رزرو تیمی شما در انتظار تأیید هم‌تیمی است.');
        }

        if ($registration->seat_number !== null) {
            return redirect()->route('home')
                ->with('info', 'جایگاه شما (' . $tournament->seatDisplayLabel((int) $registration->seat_number) . ') قبلاً ثبت شده است.');
        }

        $occupiedSeats = Registration::where('tournament_id', $tournament->id)
            ->whereNotNull('seat_number')
            ->with('user')
            ->get()
            ->keyBy('seat_number');

        return response()
            ->view('tournaments.select-seat', compact('tournament', 'registration', 'occupiedSeats'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function storeSeat(Request $request, Tournament $tournament, TournamentEntryFeeService $fees)
    {
        $request->validate([
            'seat_number' => 'required|integer|min:1|max:' . max(1, (int) $tournament->capacity),
        ]);

        if (! $tournament->acceptsRegistration()) {
            return back()->with('error', 'ثبت‌نام این مسابقه بسته شده است.');
        }

        $user = Auth::user();
        $seatNumber = (int) $request->seat_number;

        try {
            DB::transaction(function () use ($user, $tournament, $seatNumber, $fees) {
                $lockedTournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();

                if (! $lockedTournament->acceptsRegistration()) {
                    throw new \RuntimeException('registration_closed');
                }

                $registration = Registration::where('user_id', $user->id)
                    ->where('tournament_id', $tournament->id)
                    ->lockForUpdate()
                    ->first();

                if (! $registration) {
                    throw new \RuntimeException('not_registered');
                }

                if ($registration->seat_number !== null) {
                    throw new \RuntimeException('already_selected');
                }

                $taken = Registration::where('tournament_id', $tournament->id)
                    ->where('seat_number', $seatNumber)
                    ->lockForUpdate()
                    ->exists();

                if ($taken) {
                    throw new \RuntimeException('seat_taken');
                }

                if ($lockedTournament->isFull()) {
                    throw new \RuntimeException('tournament_full');
                }

                if (! $fees->hasPaid($user, $lockedTournament)) {
                    try {
                        $fees->charge($user, $lockedTournament);
                    } catch (\InvalidArgumentException) {
                        throw new \RuntimeException('insufficient_wallet');
                    }

                    $lockedTournament->increment('registered_count');
                }

                $registration->update([
                    'seat_number' => $seatNumber,
                    'status' => 'confirmed',
                ]);
            });
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'registration_closed' => back()->with('error', 'ثبت‌نام این مسابقه بسته شده است.'),
                'not_registered' => back()->with('error', 'ابتدا در این مسابقه ثبت‌نام کنید.'),
                'already_selected' => redirect()->route('home')
                    ->with('info', 'جایگاه شما قبلاً انتخاب شده است.'),
                'seat_taken' => back()->with('error', 'این جایگاه قبلاً گرفته شده. جایگاه دیگری انتخاب کنید.'),
                'insufficient_wallet' => back()->with('error', 'موجودی کیف پول کافی نیست.'),
                'tournament_full' => back()->with('error', 'ظرفیت مسابقه تکمیل شده است.'),
                default => back()->with('error', 'انتخاب جایگاه ناموفق بود.'),
            };
        }

        TournamentListingService::forgetHomeCache();

        return redirect()
            ->route('home')
            ->with('success', 'ثبت‌نام شما با موفقیت تکمیل شد. جایگاه ' . $tournament->seatDisplayLabel($seatNumber) . ' برای شما ثبت شد.');
    }

    public function cancelPendingRegistration(Tournament $tournament, TournamentEntryFeeService $fees)
    {
        $user = Auth::user();

        try {
            DB::transaction(function () use ($user, $tournament, $fees) {
                $registration = Registration::where('user_id', $user->id)
                    ->where('tournament_id', $tournament->id)
                    ->whereNull('seat_number')
                    ->lockForUpdate()
                    ->first();

                if (! $registration) {
                    throw new \RuntimeException('no_pending');
                }

                if ($fees->refundIfPaid($user, $tournament) && $tournament->registered_count > 0) {
                    $tournament->decrement('registered_count');
                }

                $registration->delete();
            });
        } catch (\RuntimeException $e) {
            return redirect()->route('home')->with('error', 'ثبت‌نام ناتمامی برای لغو یافت نشد.');
        }

        TournamentListingService::forgetHomeCache();

        return redirect()->route('home')
            ->with('info', 'ثبت‌نام ناتمام لغو شد.');
    }

    public function leaderboard(TournamentListingService $listing)
    {
        $topPlayers = $listing->leaderboard();

        return view('leaderboard', compact('topPlayers'));
    }

    public function gameLoginInfo(Tournament $tournament)
    {
        $user = Auth::user();

        if (! $user) {
            if (request()->expectsJson()) {
                return response()->json(['error' => 'لطفاً وارد شوید.'], 401);
            }

            return redirect()->route('login');
        }

        $registration = Registration::where('user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('seat_number')
            ->first(['id', 'seat_number']);

        if (! $registration) {
            if (request()->expectsJson()) {
                return response()->json(['error' => 'شما در این مسابقه ثبت‌نام نکرده‌اید.'], 403);
            }

            return back()->with('error', 'شما در این مسابقه ثبت‌نام نکرده‌اید.');
        }

        if (! $tournament->allowsGameLogin()) {
            if (request()->expectsJson()) {
                return response()->json(['error' => 'اطلاعات ورود برای این مسابقه در دسترس نیست.'], 403);
            }

            return back()->with('error', 'اطلاعات ورود برای این مسابقه در دسترس نیست.');
        }

        $payload = [
            'title' => $tournament->title,
            'content' => $tournament->gameLoginMessage(),
            'has_info' => $tournament->hasPublishedGameLogin(),
            'seat_number' => $registration?->seat_number,
            'seat_label' => $registration && $registration->seat_number
                ? $tournament->seatDisplayLabel((int) $registration->seat_number)
                : null,
        ];

        if (request()->expectsJson()) {
            return response()->json($payload);
        }

        return back()
            ->with('game_login_info', $payload['content'])
            ->with('game_login_title', $payload['title']);
    }

    public function rules(ContentCacheService $content)
    {
        $ruleSections = $content->rules();

        return view('rules', compact('ruleSections'));
    }
}
