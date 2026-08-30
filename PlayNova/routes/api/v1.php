<?php

use App\Http\Controllers\Api\V1\Admin\ContentAdminController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\KycAdminController;
use App\Http\Controllers\Api\V1\Admin\ResourceController as AdminResourceController;
use App\Http\Controllers\Api\V1\Admin\RoleAdminController;
use App\Http\Controllers\Api\V1\Admin\SettingsAdminController;
use App\Http\Controllers\Api\V1\Admin\TournamentAdminController;
use App\Http\Controllers\Api\V1\Admin\TournamentResultController as AdminTournamentResultController;
use App\Http\Controllers\Api\V1\Admin\TournamentPrizeAdminController;
use App\Http\Controllers\Api\V1\Admin\TournamentSeatAdminController;
use App\Http\Controllers\Api\V1\Admin\UserAdminController;
use App\Http\Controllers\Api\V1\Admin\WithdrawalAdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HistoryController;
use App\Http\Controllers\Api\V1\KycController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\TeamInviteController;
use App\Http\Controllers\Api\V1\TournamentController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('forgot-password', [AuthController::class, 'sendResetCode']);
    Route::get('reset-password/{token}', [AuthController::class, 'showResetPasswordVerify']);
    Route::post('reset-password/{token}', [AuthController::class, 'resetPassword']);
    Route::post('reset-password/{token}/resend', [AuthController::class, 'resendResetCode']);
    Route::get('register/verify/{token}', [AuthController::class, 'showRegisterVerify']);
    Route::post('register/verify/{token}', [AuthController::class, 'verifyRegister']);
    Route::post('register/verify/{token}/resend', [AuthController::class, 'resendRegisterVerify']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::get('settings', [SettingsController::class, 'index'])->middleware('api.cache.public');

Route::get('home', [TournamentController::class, 'home'])->middleware('api.cache.public');
Route::get('leaderboard', [TournamentController::class, 'leaderboard'])->middleware('api.cache.public');
Route::get('rules', [TournamentController::class, 'rules'])->middleware('api.cache.public');
Route::get('history', [HistoryController::class, 'index'])->middleware('api.cache.public');
Route::get('tournaments/{tournament}', [TournamentController::class, 'show']);

Route::prefix('pages')->middleware('api.cache.public')->group(function () {
    Route::get('privacy', [PageController::class, 'privacy']);
    Route::get('about', [PageController::class, 'about']);
    Route::get('contact', [PageController::class, 'contact']);
    Route::get('faq', [PageController::class, 'faq']);
});

Route::get('wallet/callback', [WalletController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::patch('profile', [ProfileController::class, 'update']);

    Route::get('wallet', [WalletController::class, 'index']);
    Route::post('wallet/deposit', [WalletController::class, 'deposit']);
    Route::post('wallet/withdraw', [WalletController::class, 'withdraw']);
    Route::get('wallet/callback-info', [WalletController::class, 'callbackInfo']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/popup', [NotificationController::class, 'popup']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('notifications/{id}', [NotificationController::class, 'delete']);

    Route::get('kyc', [KycController::class, 'index']);
    Route::post('kyc', [KycController::class, 'store']);

    Route::get('team-invites', [TeamInviteController::class, 'index']);
    Route::post('tournaments/{tournament}/team-invite', [TeamInviteController::class, 'store']);
    Route::post('team-invites/{invite}/accept', [TeamInviteController::class, 'accept']);
    Route::post('team-invites/{invite}/decline', [TeamInviteController::class, 'decline']);
    Route::post('team-invites/{invite}/cancel', [TeamInviteController::class, 'cancel']);

    Route::post('tournaments/{tournament}/register', [TournamentController::class, 'register']);
    Route::post('tournaments/{tournament}/cancel-pending', [TournamentController::class, 'cancelPending']);
    Route::get('tournaments/{tournament}/select-seat', [TournamentController::class, 'selectSeat']);
    Route::post('tournaments/{tournament}/select-seat', [TournamentController::class, 'storeSeat']);
    Route::get('tournaments/{tournament}/game-login', [TournamentController::class, 'gameLoginInfo']);

    Route::get('admin/tournament-seats', [TournamentSeatAdminController::class, 'index']);
    Route::get('admin/tournament-seats/{tournament}', [TournamentSeatAdminController::class, 'show']);

    Route::prefix('admin')->middleware(['admin', 'admin.cache.invalidate'])->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index']);
        Route::get('tournaments', [AdminResourceController::class, 'tournaments']);
        Route::post('tournaments', [TournamentAdminController::class, 'store']);
        Route::get('tournaments/{tournament}', [TournamentAdminController::class, 'show']);
        Route::put('tournaments/{tournament}', [TournamentAdminController::class, 'update']);
        Route::delete('tournaments/{tournament}', [TournamentAdminController::class, 'destroy']);
        Route::put('tournaments/{tournament}/status', [TournamentAdminController::class, 'updateStatus']);
        Route::post('tournaments/{tournament}/result', [TournamentAdminController::class, 'recordResult']);
        Route::post('tournaments/{tournament}/pay-prize', [TournamentAdminController::class, 'payPrize']);
        Route::get('tournaments/{tournament}/prize-status', [TournamentAdminController::class, 'prizeStatus']);
        Route::get('tournaments/{tournament}/participants', [TournamentAdminController::class, 'participants']);
        Route::post('tournaments/{tournament}/result-ai/analyze', [AdminTournamentResultController::class, 'analyze']);
        Route::post('tournaments/{tournament}/result-ai/apply', [AdminTournamentResultController::class, 'apply']);
        Route::get('tournaments/{tournament}/result-ai/config', [AdminTournamentResultController::class, 'config']);
        Route::get('tournaments/{tournament}/prizes', [TournamentPrizeAdminController::class, 'show']);
        Route::put('tournaments/{tournament}/prizes', [TournamentPrizeAdminController::class, 'update']);
        Route::post('tournaments/{tournament}/prizes/approve', [TournamentPrizeAdminController::class, 'approve']);

        Route::get('users', [AdminResourceController::class, 'users']);
        Route::put('users/{user}/cod-id', [UserAdminController::class, 'updateCodId']);
        Route::put('users/{user}/kills', [UserAdminController::class, 'updateKills']);
        Route::put('users/{user}/wallet', [UserAdminController::class, 'adjustWallet']);
        Route::get('users/{user}/activity', [UserAdminController::class, 'activityHistory']);
        Route::delete('users/{user}', [UserAdminController::class, 'destroy']);

        Route::get('withdrawals', [AdminResourceController::class, 'withdrawals']);
        Route::put('withdrawals/{transaction}', [WithdrawalAdminController::class, 'update']);

        Route::get('kyc', [AdminResourceController::class, 'kyc']);
        Route::put('kyc/{submission}', [KycAdminController::class, 'updateStatus']);
        Route::get('kyc/{submission}/document/{side}', [KycAdminController::class, 'document']);

        Route::get('settings/site', [AdminResourceController::class, 'siteSettings']);
        Route::put('settings/site', [AdminResourceController::class, 'updateSiteSettings']);
        Route::get('settings/logo', [SettingsAdminController::class, 'logo']);
        Route::post('settings/logo', [SettingsAdminController::class, 'updateLogo']);
        Route::delete('settings/logo', [SettingsAdminController::class, 'deleteLogo']);
        Route::get('settings/payment-gateway', [SettingsAdminController::class, 'paymentGateway']);
        Route::put('settings/payment-gateway', [SettingsAdminController::class, 'updatePaymentGateway']);
        Route::post('settings/payment-gateway/test', [SettingsAdminController::class, 'testPaymentGateway']);
        Route::get('settings/sms', [SettingsAdminController::class, 'smsSettings']);
        Route::put('settings/sms', [SettingsAdminController::class, 'updateSmsSettings']);
        Route::get('settings/ai', [SettingsAdminController::class, 'aiSettings']);
        Route::put('settings/ai', [SettingsAdminController::class, 'updateAiSettings']);
        Route::post('settings/ai/test', [SettingsAdminController::class, 'testAiSettings']);
        Route::get('settings/referral', [SettingsAdminController::class, 'referralSettings']);
        Route::put('settings/referral', [SettingsAdminController::class, 'updateReferralSettings']);

        Route::get('discounts', [ContentAdminController::class, 'discounts']);
        Route::post('discounts', [ContentAdminController::class, 'storeDiscount']);
        Route::delete('discounts/{discount}', [ContentAdminController::class, 'deleteDiscount']);

        Route::get('news', [ContentAdminController::class, 'news']);
        Route::post('news', [ContentAdminController::class, 'storeNews']);
        Route::delete('news/{news}', [ContentAdminController::class, 'deleteNews']);

        Route::post('broadcast', [ContentAdminController::class, 'broadcast']);
        Route::get('broadcasts', [ContentAdminController::class, 'broadcasts']);
        Route::put('broadcasts/{notification}', [ContentAdminController::class, 'updateBroadcast']);
        Route::delete('broadcasts/{notification}', [ContentAdminController::class, 'deleteBroadcast']);

        Route::get('rules', [ContentAdminController::class, 'rules']);
        Route::post('rules', [ContentAdminController::class, 'storeRule']);
        Route::put('rules/{rule}', [ContentAdminController::class, 'updateRule']);
        Route::delete('rules/{rule}', [ContentAdminController::class, 'deleteRule']);

        Route::get('admins', [RoleAdminController::class, 'admins']);
        Route::post('admins', [RoleAdminController::class, 'addAdmin']);
        Route::delete('admins/{user}', [RoleAdminController::class, 'removeAdmin']);

        Route::get('seat-admins', [RoleAdminController::class, 'seatAdmins']);
        Route::post('seat-admins', [RoleAdminController::class, 'addSeatAdmin']);
        Route::delete('seat-admins/{user}', [RoleAdminController::class, 'removeSeatAdmin']);
    });
});
