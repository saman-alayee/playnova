<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RuleSaveController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TournamentController::class, 'home'])->name('home');
Route::get('/leaderboard', [TournamentController::class, 'leaderboard'])->name('leaderboard');
Route::get('/rules', [TournamentController::class, 'rules'])->name('rules');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');
Route::get('/history', [App\Http\Controllers\HistoryController::class, 'index'])->name('history');
Route::get('/notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
    Route::get('/register/verify-mobile/{token}', [AuthController::class, 'showRegisterVerify'])->name('register.verify');
    Route::post('/register/verify-mobile/{token}', [AuthController::class, 'verifyRegister'])->name('register.verify.submit');
    Route::post('/register/verify-mobile/{token}/resend', [AuthController::class, 'resendRegisterVerify'])->name('register.verify.resend');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetCode'])->name('password.send-code');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordVerify'])->name('password.reset.verify');
    Route::post('/reset-password/{token}', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('/reset-password/{token}/resend', [AuthController::class, 'resendResetCode'])->name('password.reset.resend');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/wallet/callback', [WalletController::class, 'callback'])->name('wallet.callback');

Route::get('/tickets', [FaqController::class, 'index'])->name('tickets.index');
Route::redirect('/tickets/create', '/tickets');
Route::get('/tickets/{ticket}', fn () => redirect()->route('tickets.index'))->whereNumber('ticket');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/deposit', [WalletController::class, 'deposit'])->name('wallet.deposit');
    Route::post('/wallet/withdraw', [WalletController::class, 'requestWithdraw'])->name('wallet.withdraw');
    Route::post('/tournaments/{tournament}/register', [TournamentController::class, 'register'])->name('tournaments.register');
    Route::post('/tournaments/{tournament}/team-invite', [App\Http\Controllers\TeamInviteController::class, 'store'])->name('tournaments.team-invite');
    Route::get('/team-invites/banner', [App\Http\Controllers\TeamInviteController::class, 'banner'])->name('team-invites.banner');
    Route::post('/team-invites/{invite}/accept', [App\Http\Controllers\TeamInviteController::class, 'accept'])->name('team-invites.accept');
    Route::post('/team-invites/{invite}/decline', [App\Http\Controllers\TeamInviteController::class, 'decline'])->name('team-invites.decline');
    Route::post('/team-invites/{invite}/cancel', [App\Http\Controllers\TeamInviteController::class, 'cancel'])->name('team-invites.cancel');
    Route::get('/tournaments/{tournament}/select-seat', [TournamentController::class, 'selectSeat'])->name('tournaments.select-seat');
    Route::post('/tournaments/{tournament}/select-seat', [TournamentController::class, 'storeSeat'])->name('tournaments.select-seat.store');
    Route::post('/tournaments/{tournament}/cancel-pending', [TournamentController::class, 'cancelPendingRegistration'])->name('tournaments.cancel-pending');
    Route::get('/tournaments/{tournament}/game-login', [TournamentController::class, 'gameLoginInfo'])->name('tournaments.game-login');
    Route::post('/tickets', fn () => redirect()->route('tickets.index'));
    Route::post('/tickets/{ticket}/reply', fn () => redirect()->route('tickets.index'))->whereNumber('ticket');
    Route::get('/kyc', [KycController::class, 'index'])->name('kyc.index');
    Route::post('/kyc', [KycController::class, 'store'])->name('kyc.store');
    Route::get('/ticket-attachments/{message}', [TicketAttachmentController::class, 'ticketAttachment'])->name('tickets.attachment');

    Route::post('/notifications/{id}/mark-read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::delete('/notifications/{id}', [App\Http\Controllers\NotificationController::class, 'delete'])->name('notifications.delete');
});

require __DIR__.'/admin.php';

Route::middleware(['auth', 'admin'])->post('/save-rules', [RuleSaveController::class, 'save'])->name('rules.save');
