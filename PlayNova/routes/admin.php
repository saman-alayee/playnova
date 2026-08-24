<?php

use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\BroadcastController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\KycController;
use App\Http\Controllers\Admin\LogoController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\ReferralSettingsController;
use App\Http\Controllers\Admin\RuleController;
use App\Http\Controllers\Admin\SeatAdminController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\SmsSettingsController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\TournamentController as AdminTournamentController;
use App\Http\Controllers\Admin\TournamentSeatController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin', 'admin.cache.invalidate'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/tournaments', [AdminTournamentController::class, 'tournaments'])->name('tournaments');
    Route::post('/tournaments', [AdminTournamentController::class, 'storeTournament'])->name('tournaments.store');
    Route::put('/tournaments/{tournament}/status', [AdminTournamentController::class, 'updateTournamentStatus'])->name('tournaments.status');
    Route::post('/tournaments/{tournament}/result', [AdminTournamentController::class, 'recordResult'])->name('tournaments.result');
    Route::post('/tournaments/{tournament}/pay-prize', [AdminTournamentController::class, 'payTournamentPrize'])->name('tournaments.pay-prize');
    Route::delete('/tournaments/{id}', [AdminTournamentController::class, 'deleteTournament'])->name('tournaments.delete');
    Route::get('/tournaments/{id}/edit', [AdminTournamentController::class, 'editTournamentForm'])->name('tournaments.edit');
    Route::put('/tournaments/{id}', [AdminTournamentController::class, 'updateTournament'])->name('tournaments.update');

    Route::get('/users', [AdminUserController::class, 'users'])->name('users');
    Route::put('/users/{user}/kills', [AdminUserController::class, 'updateUserKills'])->name('users.kills');
    Route::put('/users/{user}/cod-id', [AdminUserController::class, 'updateUserCodId'])->name('users.cod-id');
    Route::put('/users/{user}/wallet', [AdminUserController::class, 'adjustUserWallet'])->name('users.wallet');
    Route::delete('/users/{user}', [AdminUserController::class, 'deleteUser'])->name('users.delete');

    Route::get('/withdrawals', [WithdrawalController::class, 'withdrawals'])->name('withdrawals');
    Route::put('/withdrawals/{transaction}', [WithdrawalController::class, 'updateWithdrawal'])->name('withdrawals.update');

    Route::get('/kyc', [KycController::class, 'kycList'])->name('kyc');
    Route::put('/kyc/{submission}', [KycController::class, 'kycUpdateStatus'])->name('kyc.update');
    Route::get('/kyc/{submission}/document/{side}', [KycController::class, 'kycDocument'])->name('kyc.document');

    Route::get('/site-settings', [SiteSettingsController::class, 'siteSettingsForm'])->name('site-settings');
    Route::put('/site-settings', [SiteSettingsController::class, 'updateSiteSettings'])->name('site-settings.update');

    Route::get('/discounts', [DiscountController::class, 'discounts'])->name('discounts');
    Route::post('/discounts', [DiscountController::class, 'storeDiscount'])->name('discounts.store');
    Route::delete('/discounts/{discount}', [DiscountController::class, 'deleteDiscount'])->name('discounts.delete');

    Route::get('/news', [NewsController::class, 'news'])->name('news');
    Route::post('/news', [NewsController::class, 'storeNews'])->name('news.store');
    Route::delete('/news/{news}', [NewsController::class, 'deleteNews'])->name('news.delete');

    Route::get('/broadcast', [BroadcastController::class, 'broadcastForm'])->name('broadcast');
    Route::post('/broadcast', [BroadcastController::class, 'broadcast'])->name('broadcast.send');
    Route::get('/broadcast-manage', [BroadcastController::class, 'manageBroadcast'])->name('broadcast.manage');
    Route::get('/broadcast/{id}/edit', [BroadcastController::class, 'editBroadcast'])->name('broadcast.edit');
    Route::put('/broadcast/{id}', [BroadcastController::class, 'updateBroadcast'])->name('broadcast.update');
    Route::delete('/broadcast/{id}', [BroadcastController::class, 'deleteBroadcast'])->name('broadcast.delete');

    Route::get('/tickets', [AdminTicketController::class, 'tickets'])->name('tickets');
    Route::put('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.status');
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');

    Route::get('/rules/edit', [RuleController::class, 'editRulesForm'])->name('rules.legacy.edit');
    Route::put('/rules', [RuleController::class, 'updateRules'])->name('rules.legacy.update');

    Route::get('/rules/manage', [RuleController::class, 'manageRules'])->name('rules.manage');
    Route::post('/rules', [RuleController::class, 'storeRule'])->name('rules.store');
    Route::get('/rules/{id}/edit', [RuleController::class, 'editRule'])->name('rules.edit');
    Route::put('/rules/{id}', [RuleController::class, 'updateRule'])->name('rules.update');
    Route::delete('/rules/{id}', [RuleController::class, 'deleteRule'])->name('rules.delete');

    Route::get('/logo', [LogoController::class, 'logoForm'])->name('logo');
    Route::put('/logo', [LogoController::class, 'updateLogo'])->name('logo.update');
    Route::delete('/logo', [LogoController::class, 'deleteLogo'])->name('logo.delete');

    Route::get('/payment-gateway', [PaymentGatewayController::class, 'paymentGatewayForm'])->name('payment-gateway');
    Route::put('/payment-gateway', [PaymentGatewayController::class, 'updatePaymentGateway'])->name('payment-gateway.update');
    Route::post('/payment-gateway/test', [PaymentGatewayController::class, 'testPaymentGateway'])->name('payment-gateway.test');

    Route::get('/admins', [AdminRoleController::class, 'admins'])->name('admins');
    Route::post('/admins', [AdminRoleController::class, 'addAdmin'])->name('admins.store');
    Route::delete('/admins/{user}', [AdminRoleController::class, 'removeAdmin'])->name('admins.remove');

    Route::get('/seat-admins', [SeatAdminController::class, 'seatAdmins'])->name('seat-admins');
    Route::post('/seat-admins', [SeatAdminController::class, 'addSeatAdmin'])->name('seat-admins.store');
    Route::delete('/seat-admins/{user}', [SeatAdminController::class, 'removeSeatAdmin'])->name('seat-admins.remove');

    Route::get('/sms-settings', [SmsSettingsController::class, 'smsSettingsForm'])->name('sms.settings');
    Route::put('/sms-settings', [SmsSettingsController::class, 'updateSmsSettings'])->name('sms.settings.update');

    Route::get('/referral-settings', [ReferralSettingsController::class, 'referralSettingsForm'])->name('referral.settings');
    Route::put('/referral-settings', [ReferralSettingsController::class, 'updateReferralSettings'])->name('referral.settings.update');
});

Route::middleware(['auth', 'seat_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/tournament-seats', [TournamentSeatController::class, 'tournamentSeatsIndex'])->name('tournament-seats.index');
    Route::get('/tournaments/{tournament}/seats', [TournamentSeatController::class, 'tournamentSeats'])->name('tournaments.seats');
});
