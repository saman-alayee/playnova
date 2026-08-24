@extends('layouts.app')
@section('title', 'داشبورد مدیریت | PlayNova')

@section('content')
<h1 class="text-2xl font-bold mb-6">پنل مدیریت</h1>

@include('admin._nav')

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
        <p class="text-xs text-gray-400">تعداد کاربران</p>
        <p class="text-2xl font-black">{{ number_format($totalUsers) }}</p>
    </div>
    <div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
        <p class="text-xs text-gray-400">تعداد مسابقات</p>
        <p class="text-2xl font-black">{{ number_format($totalTournaments) }}</p>
    </div>
    <div class="bg-dark-800 border border-primary/40 rounded-xl p-4">
        <p class="text-xs text-gray-400">مسابقات فعال</p>
        <p class="text-2xl font-black text-primary">{{ number_format($activeTournaments) }}</p>
    </div>
    <div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
        <p class="text-xs text-gray-400">تیکت‌های باز</p>
        <p class="text-2xl font-black">{{ number_format($openTickets) }}</p>
    </div>
</div>

<h2 class="font-bold mb-4">گزارش مالی</h2>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <div class="bg-dark-800 border border-green-500/40 rounded-xl p-4">
        <p class="text-xs text-gray-400">مجموع شارژها</p>
        <p class="text-xl font-bold text-green-400">{{ number_format($totalDeposits) }} تومان</p>
    </div>
    <div class="bg-dark-800 border border-yellow-500/40 rounded-xl p-4">
        <p class="text-xs text-gray-400">برداشت‌های در انتظار</p>
        <p class="text-xl font-bold text-yellow-400">{{ number_format($pendingWithdraws) }} تومان</p>
    </div>
    <div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
        <p class="text-xs text-gray-400">برداشت‌های پرداخت شده</p>
        <p class="text-xl font-bold">{{ number_format($totalWithdrawsCompleted) }} تومان</p>
    </div>
    <div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
        <p class="text-xs text-gray-400">مجموع ورودی مسابقات</p>
        <p class="text-xl font-bold">{{ number_format($totalEntryFees) }} تومان</p>
    </div>
    <div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
        <p class="text-xs text-gray-400">مجموع جوایز پرداخت شده</p>
        <p class="text-xl font-bold">{{ number_format($totalPrizesPaid) }} تومان</p>
    </div>
    <div class="bg-dark-800 border border-secondary/40 rounded-xl p-4">
        <p class="text-xs text-gray-400">درآمد خالص (ورودی - جایزه)</p>
        <p class="text-xl font-bold text-secondary">{{ number_format($netRevenue) }} تومان</p>
    </div>
</div>
@endsection
