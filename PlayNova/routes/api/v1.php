<?php

use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\ResourceController as AdminResourceController;
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
Route::get('history', [HistoryController::class, 'index']);
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

    Route::prefix('admin')->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index']);
        Route::get('tournaments', [AdminResourceController::class, 'tournaments']);
        Route::get('users', [AdminResourceController::class, 'users']);
        Route::get('withdrawals', [AdminResourceController::class, 'withdrawals']);
        Route::get('kyc', [AdminResourceController::class, 'kyc']);
        Route::get('settings/site', [AdminResourceController::class, 'siteSettings']);
        Route::put('settings/site', [AdminResourceController::class, 'updateSiteSettings']);
    });
});
