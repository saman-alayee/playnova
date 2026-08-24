<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PlayNova - پلتفرم مسابقات آنلاین')</title>
    <meta name="description" content="@yield('meta_description', 'پلتفرم برگزاری مسابقات آنلاین Call of Duty Mobile — رقابت، هیجان و جوایز نقدی.')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', 'PlayNova - پلتفرم مسابقات آنلاین')">
    <meta property="og:description" content="@yield('meta_description', 'پلتفرم برگزاری مسابقات آنلاین Call of Duty Mobile — رقابت، هیجان و جوایز نقدی.')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:site_name" content="PlayNova">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:image" content="{{ url('/favicon-192x192.png') }}">
    @stack('structured_data')
    <link rel="icon" href="{{ url('/favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ url('/favicon-48x48.png') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ url('/favicon-96x96.png') }}">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ url('/favicon-192x192.png') }}">
    <meta name="theme-color" content="#050505">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Vazirmatn', sans-serif; box-sizing: border-box; }
        :root {
            --bg-dark: #050505;
            --primary: #9333EA;
            --primary-light: #A855F7;
            --secondary: #3B82F6;
            --success: #22C55E;
            --danger: #EF4444;
            --text-light: #e5e7eb;
        }
        body { background: var(--bg-dark); color: var(--text-light); min-height: 100vh; overflow-x: hidden; }
        .site-header { background: rgba(0,0,0,.92); border-bottom: 1px solid rgba(147,51,234,.25); position: sticky; top: 0; z-index: 50; backdrop-filter: blur(12px); }
        .site-logo { height: 56px; width: auto; max-width: 200px; object-fit: contain; display: block; }
        .site-header-logo { display: inline-flex; align-items: center; line-height: 0; }
        .site-footer { position: relative; margin-top: 3rem; background: linear-gradient(180deg, rgba(17,17,24,0) 0%, #0a0a10 24%, #08080c 100%); border-top: 1px solid rgba(147,51,234,.22); overflow: hidden; }
        .site-footer__glow { position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: min(680px, 90vw); height: 120px; background: radial-gradient(ellipse at center, rgba(147,51,234,.18) 0%, transparent 70%); pointer-events: none; }
        .site-footer__inner { position: relative; z-index: 1; padding: 2.5rem 0 1.25rem; }
        .site-footer__grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }
        .site-footer__logo { height: 64px; width: auto; max-width: 200px; object-fit: contain; margin-bottom: .75rem; }
        .site-footer__desc { color: #9ca3af; font-size: .85rem; line-height: 1.9; max-width: 280px; }
        .site-footer__title { color: #fff; font-size: .92rem; font-weight: 800; margin-bottom: .85rem; }
        .site-footer__links { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .55rem; }
        .site-footer__links a { color: #cbd5e1; text-decoration: none; font-size: .86rem; transition: color .15s; }
        .site-footer__links a:hover { color: #c084fc; }
        .site-footer__trust { display: flex; align-items: flex-start; }
        .site-footer__bottom { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,.06); text-align: center; color: #6b7280; font-size: .82rem; }
        @media (min-width: 640px) { .site-footer__grid { grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 1.5rem; } }
        .btn-header-register { background: linear-gradient(135deg, #9333EA, #7C3AED); color: #fff; padding: .55rem 1.1rem; border-radius: 999px; font-size: .85rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .btn-header-login { color: #fff; border: 1px solid rgba(255,255,255,.35); padding: .5rem 1rem; border-radius: 999px; font-size: .85rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .btn-header-wallet { background: rgba(26,26,46,.9); border: 1px solid rgba(147,51,234,.35); color: #fff; padding: .5rem .9rem; border-radius: 999px; font-size: .82rem; font-weight: 700; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: .35rem; }
        .hamburger-btn { width: 42px; height: 42px; border-radius: 12px; border: 1px solid rgba(255,255,255,.12); background: rgba(26,26,46,.8); color: #fff; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
        .desktop-nav { display: none; align-items: center; gap: 1.25rem; flex: 1; justify-content: center; padding: 0 1rem; }
        .desktop-nav a { color: #d1d5db; text-decoration: none; font-size: .9rem; font-weight: 600; white-space: nowrap; transition: color .2s; }
        .desktop-nav a:hover { color: #fff; }
        .desktop-nav a.is-active { color: #c084fc; }
        .header-actions { display: flex; align-items: center; gap: .4rem; flex-shrink: 0; flex-wrap: nowrap; }
        @media (max-width: 767px) {
            .btn-header-register, .btn-header-login, .btn-header-wallet { font-size: .75rem; padding: .45rem .7rem; }
            .btn-header-wallet { gap: .2rem; }
        }
        .show-mobile-only { display: inline-flex; }
        .show-desktop-only { display: none; }
        .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 60; backdrop-filter: blur(2px); }
        .sidebar-panel { position: fixed; top: 0; right: 0; height: 100%; width: min(300px, 86vw); background: #111118; border-left: 1px solid rgba(147,51,234,.2); z-index: 70; transform: translateX(100%); transition: transform .25s ease; overflow-y: auto; display: flex; flex-direction: column; box-shadow: -8px 0 32px rgba(0,0,0,.45); }
        .sidebar-panel.open { transform: translateX(0); }
        .sidebar-top { display: flex; align-items: center; justify-content: space-between; padding: .85rem 1rem; border-bottom: 1px solid rgba(255,255,255,.06); flex-shrink: 0; }
        .sidebar-top__title { font-size: .95rem; font-weight: 800; color: #fff; }
        .sidebar-close { width: 36px; height: 36px; border-radius: 10px; border: 1px solid rgba(255,255,255,.1); background: rgba(255,255,255,.04); color: #fff; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
        .sidebar-menu { padding: .75rem; flex: 1; display: flex; flex-direction: column; gap: .25rem; }
        .sidebar-item { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .72rem .85rem; border-radius: 12px; color: #e5e7eb; text-decoration: none; font-size: .9rem; font-weight: 600; transition: background .15s, color .15s; border: none; background: transparent; width: 100%; cursor: pointer; text-align: right; }
        .sidebar-item:hover { background: rgba(147,51,234,.12); color: #fff; }
        .sidebar-item__left { display: flex; align-items: center; gap: .65rem; min-width: 0; }
        .sidebar-item__icon { width: 20px; height: 20px; color: #a78bfa; flex-shrink: 0; opacity: .95; }
        .sidebar-item__badge { min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: #ef4444; color: #fff; font-size: .62rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; }
        .sidebar-item--danger { color: #fca5a5; }
        .sidebar-item--danger .sidebar-item__icon { color: #f87171; }
        .sidebar-item--danger:hover { background: rgba(239,68,68,.12); color: #fecaca; }
        .sidebar-footer { padding: .75rem; border-top: 1px solid rgba(255,255,255,.06); flex-shrink: 0; }
        .sidebar-divider { height: 1px; background: rgba(255,255,255,.06); margin: .35rem .5rem; }
        .sidebar-social { padding: .85rem .75rem; border-top: 1px solid rgba(255,255,255,.08); flex-shrink: 0; }
        .sidebar-social__title { text-align: center; font-size: .78rem; color: #9ca3af; margin-bottom: .65rem; font-weight: 600; }
        .sidebar-social__grid { display: flex; justify-content: center; align-items: center; gap: 1.25rem; }
        .sidebar-social__link { display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: transform .15s, opacity .15s; }
        .sidebar-social__link:hover { transform: translateY(-2px); opacity: .9; }
        .sidebar-social__icon { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; background: none !important; border: none !important; box-shadow: none !important; border-radius: 0 !important; padding: 0; }
        .sidebar-social__icon img { width: 36px; height: 36px; object-fit: contain; display: block; }
        .sidebar-social__link.is-disabled { opacity: .35; pointer-events: none; filter: grayscale(1); }
        .card-tournament { background: rgba(18,18,28,.92); border: 1px solid rgba(147,51,234,.18); transition: all .3s ease; }
        .card-tournament:hover { border-color: rgba(147,51,234,.45); box-shadow: 0 12px 40px rgba(147,51,234,.12); }
        .league-card { background: rgba(18,18,28,.95); border: 1px solid transparent; }
        .league-card--beginner { border-color: rgba(34,197,94,.45); box-shadow: 0 0 24px rgba(34,197,94,.08); }
        .league-card--intermediate { border-color: rgba(59,130,246,.45); box-shadow: 0 0 24px rgba(59,130,246,.08); }
        .league-card--professional { border-color: rgba(239,68,68,.45); box-shadow: 0 0 24px rgba(239,68,68,.08); }
        .league-card--beginner .league-card__btn { border-color: rgba(34,197,94,.6); color: #86efac; }
        .league-card--intermediate .league-card__btn { border-color: rgba(59,130,246,.6); color: #93c5fd; }
        .league-card--professional .league-card__btn { border-color: rgba(239,68,68,.6); color: #fca5a5; }
        .special-scroll::-webkit-scrollbar { height: 6px; }
        .special-scroll::-webkit-scrollbar-thumb { background: rgba(147,51,234,.45); border-radius: 999px; }
        .hero-carousel { position: relative; border-radius: 20px; overflow: hidden; margin-bottom: 2rem; min-height: 280px; background: #0a0a12; border: 1px solid rgba(147,51,234,.2); }
        .hero-slide { position: absolute; inset: 0; opacity: 0; transition: opacity .6s ease; background-size: cover; background-position: center top; pointer-events: none; z-index: 0; }
        .hero-slide.is-active { opacity: 1; pointer-events: auto; z-index: 1; }
        .hero-slide::before { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,.92) 0%, rgba(0,0,0,.45) 45%, rgba(0,0,0,.25) 100%); }
        .hero-content { position: relative; z-index: 2; padding: 2rem 1.25rem 1.5rem; min-height: 280px; display: flex; flex-direction: column; justify-content: flex-end; text-align: center; }
        .hero-content h1 { font-size: 1.75rem; font-weight: 900; line-height: 1.35; margin-bottom: .75rem; color: #fff; text-shadow: 0 2px 16px rgba(0,0,0,.8); }
        .hero-content p { font-size: .85rem; color: #cbd5e1; line-height: 1.8; margin-bottom: 1.25rem; max-width: 28rem; margin-left: auto; margin-right: auto; }
        .hero-cta { display: block; width: 100%; max-width: 100%; padding: .85rem 1rem; border-radius: 14px; font-size: .95rem; }
        .hero-dots { position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); z-index: 3; display: flex; gap: 8px; }
        .hero-dot { width: 8px; height: 8px; border-radius: 999px; background: rgba(255,255,255,.35); border: none; cursor: pointer; padding: 0; }
        .hero-dot.is-active { background: #9333EA; width: 22px; }
        .league-card__shield { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .league-card--beginner .league-card__shield { background: rgba(34,197,94,.12); color: #4ade80; box-shadow: 0 0 20px rgba(34,197,94,.15); }
        .league-card--intermediate .league-card__shield { background: rgba(59,130,246,.12); color: #60a5fa; box-shadow: 0 0 20px rgba(59,130,246,.15); }
        .league-card--professional .league-card__shield { background: rgba(239,68,68,.12); color: #f87171; box-shadow: 0 0 20px rgba(239,68,68,.15); }
        .special-card { min-width: 260px; max-width: 300px; shrink: 0; border-radius: 16px; overflow: hidden; background: #12121c; border: 1px solid rgba(147,51,234,.2); snap-align: start; }
        .special-card__img { height: 120px; background-size: cover; background-position: center; position: relative; }
        .special-card__img::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, #12121c, transparent); }
        .special-card__body { padding: .85rem 1rem 1rem; }
        @media (min-width: 768px) {
            .hero-carousel { min-height: 360px; }
            .hero-content { min-height: 360px; padding: 3rem 2rem 2rem; }
            .hero-content h1 { font-size: 2.5rem; }
            .hero-content p { font-size: 1rem; }
            .hero-cta { max-width: 320px; margin: 0 auto; }
        }
        .btn-glow-success { background: linear-gradient(135deg, #22C55E, #16A34A) !important; color: #fff !important; border: none; cursor: pointer; font-weight: 700; text-decoration: none; display: inline-block; text-align: center; }
        .btn-glow-primary { background: linear-gradient(135deg, #9333EA, #7C3AED) !important; color: #fff !important; padding: 12px 28px; border-radius: 999px; border: none; cursor: pointer; font-weight: 700; text-decoration: none; display: inline-block; text-align: center; }
        .text-gradient { background: linear-gradient(135deg, #9333EA, #3B82F6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        input:not([type="checkbox"]):not([type="radio"]), textarea, select { background: #1a1a2e !important; color: #e5e7eb !important; border: 1px solid #374151 !important; border-radius: 8px; padding: 10px 14px; width: 100%; }
        input[type="checkbox"], input[type="radio"] { width: auto !important; padding: 0 !important; accent-color: #9333EA; flex-shrink: 0; }
        .form-check { display: flex; align-items: center; gap: .65rem; cursor: pointer; }
        .form-check__input { width: 1.1rem !important; height: 1.1rem !important; margin: 0 !important; }
        .form-check__text { font-size: .875rem; line-height: 1.65; color: #d1d5db; }
        .form-check__link { color: #a855f7; font-weight: 600; text-decoration: none; }
        .form-check__link:hover { text-decoration: underline; }
        input:focus, textarea:focus, select:focus { border-color: #9333EA !important; outline: none !important; box-shadow: 0 0 0 2px rgba(147,51,234,.25) !important; }
        .text-danger { color: #EF4444 !important; } .bg-danger { background: #EF4444 !important; }
        .text-success { color: #22C55E !important; } .bg-success { background: #22C55E !important; }
        .bg-dark-800 { background: #12121c; } .bg-dark-900 { background: #0a0a12; } .border-dark-600 { border-color: #2d2d44; }
        .tournament-capacity-fill { background: linear-gradient(90deg, var(--primary), var(--secondary)); transition: width .4s ease; }
        .tournament-capacity-fill--min { min-width: .75rem; }
        .tournament-actions { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
        .tournament-actions--single { grid-template-columns: 1fr; }
        .tournament-actions--with-desc { align-items: stretch; }
        .tournament-actions__desc {
            min-height: 42px;
            border: 1px solid #374151;
            border-radius: .75rem;
            padding: .5rem .65rem;
            background: rgba(15, 15, 26, .65);
            display: flex;
            flex-direction: column;
            gap: .25rem;
            overflow: hidden;
        }
        .tournament-actions__desc-label { font-size: .65rem; color: #6b7280; margin: 0; }
        .tournament-actions__desc-text {
            font-size: .7rem;
            color: #d1d5db;
            line-height: 1.55;
            white-space: pre-line;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .tournament-actions__desc-more {
            align-self: flex-start;
            margin-top: .15rem;
            background: transparent;
            border: none;
            color: #93c5fd;
            font-size: .65rem;
            font-weight: 700;
            cursor: pointer;
            padding: 0;
        }
        .tournament-actions__btn {
            min-height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: .75rem;
            font-weight: 700;
            line-height: 1.2;
            padding: .45rem .5rem;
            border-radius: .75rem;
            border: none;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
        }
        .tournament-actions__btn--primary {
            background: linear-gradient(135deg, #9333EA, #7C3AED);
            color: #fff;
        }
        .tournament-actions__btn--success {
            background: linear-gradient(135deg, #22C55E, #16A34A);
        }
        .tournament-actions__btn--outline {
            background: rgba(59,130,246,.08);
            color: #93c5fd;
            border: 1px solid rgba(59,130,246,.35);
        }
        .tournament-actions__btn--muted {
            background: #1f2937;
            color: #9ca3af;
            cursor: default;
        }
        .tournament-schedule { margin-bottom: .75rem; }
        .tournament-schedule__date {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding: .35rem 0;
        }
        .tournament-schedule__date-label {
            font-size: .72rem;
            color: #9ca3af;
            font-weight: 600;
        }
        .tournament-schedule__date-value {
            font-size: .78rem;
            color: #e5e7eb;
            font-weight: 700;
        }
        .tournament-schedule__time {
            margin-top: .55rem;
            padding: .7rem .85rem .75rem;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(59,130,246,.14), rgba(147,51,234,.1));
            border: 1px solid rgba(96,165,250,.35);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.04), 0 8px 24px rgba(59,130,246,.12);
            text-align: center;
        }
        .tournament-schedule__time-label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            font-size: .68rem;
            color: #93c5fd;
            font-weight: 700;
            margin-bottom: .4rem;
            letter-spacing: .02em;
        }
        .tournament-schedule__time-value {
            font-size: 1.85rem;
            font-weight: 900;
            line-height: 1;
            letter-spacing: .12em;
            font-variant-numeric: tabular-nums;
            background: linear-gradient(135deg, #93c5fd, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 0 10px rgba(147,197,253,.35));
        }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        @media (max-width: 767px) { .container { padding-left: 12px; padding-right: 12px; } }
        @media (min-width: 768px) {
            .show-mobile-only { display: none !important; }
            .show-desktop-only { display: flex !important; }
            .desktop-nav { display: flex; }
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">

    <header class="site-header">
        <div class="container mx-auto px-4 py-3 max-w-7xl">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0 shrink-0">
                    <button type="button" class="hamburger-btn" @click="sidebarOpen = true" aria-label="منو">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <a href="{{ route('home') }}" class="site-header-logo">
                        <img src="{{ \App\Models\Setting::logoUrl() }}" class="site-logo" alt="PlayNova" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                        <span class="text-lg font-black text-gradient whitespace-nowrap" style="display:none;">PlayNova</span>
                    </a>
                </div>

                <nav class="desktop-nav" aria-label="منوی اصلی">
                    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}">خانه</a>
                    <a href="{{ route('rules') }}" class="{{ request()->routeIs('rules') ? 'is-active' : '' }}">قوانین</a>
                    <a href="{{ route('leaderboard') }}" class="{{ request()->routeIs('leaderboard') ? 'is-active' : '' }}">رتبه‌بندی</a>
                    <a href="{{ route('history') }}" class="{{ request()->routeIs('history') ? 'is-active' : '' }}">تاریخچه</a>
                    @auth
                        <a href="{{ route('profile.show') }}" class="{{ request()->routeIs('profile.*') ? 'is-active' : '' }}">پروفایل</a>
                        <a href="{{ route('tickets.index') }}" class="{{ request()->routeIs('tickets.index') ? 'is-active' : '' }}">سوالات متداول</a>
                        @if(Auth::user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">پنل مدیریت</a>
                        @elseif(Auth::user()->isSeatAdmin())
                            <a href="{{ route('admin.tournament-seats.index') }}" class="{{ request()->routeIs('admin.tournament-seats.*') || request()->routeIs('admin.tournaments.seats') ? 'is-active' : '' }}">جایگاه‌های مسابقات</a>
                        @endif
                    @endauth
                </nav>

                <div class="header-actions">
                    @auth
                        <a href="{{ route('wallet.index') }}" class="btn-header-wallet">💼 کیف پول</a>
                        <span class="show-desktop-only text-xs text-success font-bold">{{ number_format(Auth::user()->wallet ?? 0) }}</span>
                        <span class="show-desktop-only text-sm text-gray-300 max-w-[120px] truncate">{{ Auth::user()->username ?? Auth::user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}" class="show-desktop-only inline">
                            @csrf
                            <button type="submit" class="text-sm text-red-400 hover:text-red-300 transition font-bold">خروج</button>
                        </form>
                    @else
                        <a href="{{ route('wallet.index') }}" class="btn-header-wallet">💼 کیف پول</a>
                        <a href="{{ route('login') }}" class="btn-header-login">ورود</a>
                        <a href="{{ route('register') }}" class="btn-header-register">ثبت‌نام</a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <div x-show="sidebarOpen" x-cloak x-transition.opacity class="sidebar-overlay" @click="sidebarOpen = false"></div>
    <aside class="sidebar-panel" :class="{ 'open': sidebarOpen }" x-cloak @click.stop>
        <div class="sidebar-top">
            <span class="sidebar-top__title">منو</span>
            <button type="button" class="sidebar-close" @click="sidebarOpen = false" aria-label="بستن منو">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <nav class="sidebar-menu">
            @auth
                <a href="{{ route('profile.show') }}" class="sidebar-item" @click="sidebarOpen=false">
                    <span class="sidebar-item__left">
                        <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span>پروفایل</span>
                    </span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <a href="{{ route('wallet.index') }}" class="sidebar-item" @click="sidebarOpen=false">
                    <span class="sidebar-item__left">
                        <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <span>کیف پول من</span>
                    </span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <a href="{{ route('home') }}#special" class="sidebar-item" @click="sidebarOpen=false">
                    <span class="sidebar-item__left">
                        <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                        <span>مسابقات من</span>
                    </span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div class="sidebar-divider"></div>
            @endauth

            @php
                $sidebarUnreadCount = 0;
                if (Auth::check()) {
                    $sidebarUnreadCount = \App\Models\Notification::where('user_id', Auth::id())->where('is_read', false)->count();
                }
            @endphp
            <a href="{{ route('notifications.index') }}" class="sidebar-item {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}" @click="sidebarOpen=false">
                <span class="sidebar-item__left">
                    <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <span>اعلانات</span>
                </span>
                <span class="flex items-center gap-2">
                    @if($sidebarUnreadCount > 0)
                        <span class="sidebar-item__badge">{{ $sidebarUnreadCount > 99 ? '99+' : $sidebarUnreadCount }}</span>
                    @endif
                    <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </span>
            </a>

            <a href="{{ route('rules') }}" class="sidebar-item" @click="sidebarOpen=false">
                <span class="sidebar-item__left">
                    <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>قوانین</span>
                </span>
                <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <a href="{{ route('leaderboard') }}" class="sidebar-item" @click="sidebarOpen=false">
                <span class="sidebar-item__left">
                    <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    <span>رتبه‌بندی</span>
                </span>
                <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <a href="{{ route('history') }}" class="sidebar-item" @click="sidebarOpen=false">
                <span class="sidebar-item__left">
                    <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>تاریخچه مسابقات</span>
                </span>
                <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <a href="{{ route('about') }}" class="sidebar-item" @click="sidebarOpen=false">
                <span class="sidebar-item__left"><svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>درباره ما</span></span>
                <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <a href="{{ route('privacy') }}" class="sidebar-item" @click="sidebarOpen=false">
                <span class="sidebar-item__left"><svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M12 11c1.657 0 3-1.343 3-3S13.657 5 12 5 9 6.343 9 8s1.343 3 3 3z"/><path stroke-linecap="round" stroke-width="1.8" d="M5 20a7 7 0 0114 0"/></svg><span>حریم خصوصی</span></span>
                <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <a href="{{ route('contact') }}" class="sidebar-item" @click="sidebarOpen=false">
                <span class="sidebar-item__left"><svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg><span>ارتباط با ما</span></span>
                <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>

            @auth
                <a href="{{ route('tickets.index') }}" class="sidebar-item" @click="sidebarOpen=false">
                    <span class="sidebar-item__left">
                        <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                        <span>سوالات متداول</span>
                    </span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <a href="{{ route('kyc.index') }}" class="sidebar-item" @click="sidebarOpen=false">
                    <span class="sidebar-item__left">
                        <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>احراز هویت</span>
                    </span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                @if(Auth::user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-item" @click="sidebarOpen=false">
                        <span class="sidebar-item__left">
                            <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>پنل مدیریت</span>
                        </span>
                        <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                @elseif(Auth::user()->isSeatAdmin())
                    <a href="{{ route('admin.tournament-seats.index') }}" class="sidebar-item" @click="sidebarOpen=false">
                        <span class="sidebar-item__left">
                            <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            <span>جایگاه‌های مسابقات</span>
                        </span>
                        <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                @endif
            @else
                <div class="sidebar-divider"></div>
                <a href="{{ route('login') }}" class="sidebar-item" @click="sidebarOpen=false">
                    <span class="sidebar-item__left">
                        <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                        <span>ورود</span>
                    </span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <a href="{{ route('register') }}" class="sidebar-item" @click="sidebarOpen=false">
                    <span class="sidebar-item__left">
                        <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        <span>ثبت‌نام</span>
                    </span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" class="opacity-40" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @endauth
        </nav>

        @php
            $social = \App\Models\Setting::socialLinks();
            $socialItems = [
                'instagram' => [
                    'icon' => url('/social-instagram.svg'),
                    'title' => 'اینستاگرام',
                    'url' => filled($social['instagram'])
                        ? (str_starts_with($social['instagram'], 'http') ? $social['instagram'] : 'https://instagram.com/' . ltrim($social['instagram'], '@'))
                        : null,
                ],
                'rubika' => [
                    'icon' => url('/social-rubika.png'),
                    'title' => 'روبیکا',
                    'url' => filled($social['rubika'])
                        ? (str_starts_with($social['rubika'], 'http') ? $social['rubika'] : 'https://rubika.ir/' . ltrim($social['rubika'], '@'))
                        : null,
                ],
                'telegram' => [
                    'icon' => url('/social-telegram.svg'),
                    'title' => 'تلگرام',
                    'url' => filled($social['telegram'])
                        ? (str_starts_with($social['telegram'], 'http') ? $social['telegram'] : 'https://t.me/' . ltrim($social['telegram'], '@'))
                        : null,
                ],
            ];
        @endphp
        <div class="sidebar-social">
            <p class="sidebar-social__title">شبکه‌های اجتماعی</p>
            <div class="sidebar-social__grid">
                @foreach($socialItems as $item)
                    <a href="{{ $item['url'] ?? '#' }}" target="_blank" rel="noopener noreferrer"
                       class="sidebar-social__link {{ $item['url'] ? '' : 'is-disabled' }}"
                       title="{{ $item['title'] }}" @if(!$item['url']) aria-disabled="true" @endif>
                        <span class="sidebar-social__icon">
                            <img src="{{ $item['icon'] }}" alt="{{ $item['title'] }}" width="36" height="36" loading="lazy">
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        @auth
            <div class="sidebar-footer">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="sidebar-item sidebar-item--danger">
                        <span class="sidebar-item__left">
                            <svg class="sidebar-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            <span>خروج</span>
                        </span>
                    </button>
                </form>
            </div>
        @endauth
    </aside>

    @if(session('success'))
        <div class="container mx-auto px-4 mt-4"><div class="bg-success/20 border border-success/50 text-success px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div></div>
    @endif
    @if(session('error'))
        <div class="container mx-auto px-4 mt-4"><div class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div></div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="container mx-auto px-4 mt-4">
            <div class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm">
                <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    <main class="container mx-auto px-4 py-6 max-w-7xl">@yield('content')</main>

    <footer class="site-footer">
        <div class="site-footer__glow"></div>
        <div class="container mx-auto px-4 max-w-7xl site-footer__inner">
            <div class="site-footer__grid">
                <div>
                    <img src="{{ \App\Models\Setting::logoUrl() }}" alt="PlayNova" class="site-footer__logo">
                    <p class="site-footer__desc">پلتفرم برگزاری مسابقات آنلاین Call of Duty Mobile — رقابت، هیجان و جوایز نقدی.</p>
                </div>

                <div>
                    <h3 class="site-footer__title">دسترسی سریع</h3>
                    <ul class="site-footer__links">
                        <li><a href="{{ route('home') }}">خانه</a></li>
                        <li><a href="{{ route('home') }}#special">مسابقات ویژه</a></li>
                        <li><a href="{{ route('leaderboard') }}">رتبه‌بندی</a></li>
                        <li><a href="{{ route('history') }}">تاریخچه مسابقات</a></li>
                        <li><a href="{{ route('rules') }}">قوانین</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="site-footer__title">حساب کاربری</h3>
                    <ul class="site-footer__links">
                        @auth
                            <li><a href="{{ route('profile.show') }}">پروفایل</a></li>
                            <li><a href="{{ route('wallet.index') }}">کیف پول</a></li>
                            <li><a href="{{ route('tickets.index') }}">سوالات متداول</a></li>
                        @else
                            <li><a href="{{ route('login') }}">ورود</a></li>
                            <li><a href="{{ route('register') }}">ثبت‌نام</a></li>
                            <li><a href="{{ route('wallet.index') }}">کیف پول</a></li>
                        @endauth
                    </ul>
                </div>

                <div>
                    <h3 class="site-footer__title">اعتماد و امنیت</h3>
                    <div class="site-footer__trust">
                        <a referrerpolicy='origin' target='_blank' href='https://trustseal.enamad.ir/?id=766546&Code=sORWoyVCo0DL6d7gLFAqrTrHvwchtiBu'><img referrerpolicy='origin' src='https://trustseal.enamad.ir/logo.aspx?id=766546&Code=sORWoyVCo0DL6d7gLFAqrTrHvwchtiBu' alt='' style='cursor:pointer' code='sORWoyVCo0DL6d7gLFAqrTrHvwchtiBu'></a>
                    </div>
                </div>
            </div>

            <div class="site-footer__bottom">
                تمامی حقوق محفوظ است © {{ date('Y') }} PlayNova.ir
            </div>
        </div>
    </footer>

    @auth
        @include('components.team-invite-banner')
    @endauth

    <div id="gameLoginModal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this) closeGameLoginModal()">
        <div style="background:#1a1a2e;border:1px solid #374151;border-radius:16px;max-width:560px;width:100%;max-height:90vh;overflow-y:auto;padding:1.5rem;">
            <h2 id="gameLoginModalTitle" style="font-size:1.15rem;font-weight:700;color:#8B5CF6;margin-bottom:1rem;">🎮 اطلاعات ورود به بازی</h2>
            <div id="gameLoginModalBody" style="background:#0f0f1a;border-radius:8px;padding:1rem;border:1px solid #374151;color:#d1d5db;font-size:.9rem;line-height:1.9;white-space:pre-line;"></div>
            <div id="gameLoginModalSeat" style="display:none;margin-top:1rem;background:#0f172a;border:1px solid #3B82F6;border-radius:8px;padding:.85rem 1rem;">
                <p style="margin:0;font-size:.75rem;color:#93c5fd;margin-bottom:.35rem;">جایگاه شما (غیرقابل تغییر)</p>
                <p id="gameLoginModalSeatValue" style="margin:0;font-size:1.35rem;font-weight:800;color:#60a5fa;text-align:center;direction:ltr;font-family:ui-monospace,monospace;"></p>
            </div>
            <button type="button" onclick="closeGameLoginModal()" style="margin-top:1rem;width:100%;background:#4b5563;color:#fff;border:none;border-radius:8px;padding:.55rem 0;font-weight:700;cursor:pointer;">بستن</button>
        </div>
    </div>

    <div id="descriptionModal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this) closeDescriptionModal()">
        <div style="background:#1a1a2e;border:1px solid #374151;border-radius:16px;max-width:560px;width:100%;max-height:85vh;overflow-y:auto;padding:1.5rem;">
            <h2 id="descriptionModalTitle" style="font-size:1.15rem;font-weight:700;color:#8B5CF6;margin-bottom:1rem;">📝 توضیحات مسابقه</h2>
            <div id="descriptionModalBody" style="background:#0f0f1a;border-radius:8px;padding:1rem;border:1px solid #374151;color:#d1d5db;font-size:.9rem;line-height:1.9;white-space:pre-line;"></div>
            <button type="button" onclick="closeDescriptionModal()" style="margin-top:1rem;width:100%;background:#4b5563;color:#fff;border:none;border-radius:8px;padding:.55rem 0;font-weight:700;cursor:pointer;">بستن</button>
        </div>
    </div>

    <script>
        window.openGameLoginModal = function (title, content, seatLabel) {
            var modal = document.getElementById('gameLoginModal');
            var titleEl = document.getElementById('gameLoginModalTitle');
            var bodyEl = document.getElementById('gameLoginModalBody');
            var seatWrap = document.getElementById('gameLoginModalSeat');
            var seatValue = document.getElementById('gameLoginModalSeatValue');
            if (!modal || !titleEl || !bodyEl) return;
            titleEl.textContent = '🎮 اطلاعات ورود — ' + title;
            bodyEl.textContent = content;
            if (seatWrap && seatValue) {
                if (seatLabel) {
                    seatWrap.style.display = 'block';
                    seatValue.textContent = seatLabel;
                } else {
                    seatWrap.style.display = 'none';
                    seatValue.textContent = '';
                }
            }
            if (modal.parentElement !== document.body) document.body.appendChild(modal);
            modal.style.display = 'flex';
        };
        window.closeGameLoginModal = function () {
            var modal = document.getElementById('gameLoginModal');
            if (modal) modal.style.display = 'none';
        };
        window.openGameLoginModalById = function (url) {
            var modal = document.getElementById('gameLoginModal');
            var titleEl = document.getElementById('gameLoginModalTitle');
            var bodyEl = document.getElementById('gameLoginModalBody');
            if (!modal || !titleEl || !bodyEl) return;
            titleEl.textContent = '🎮 در حال بارگذاری...';
            bodyEl.textContent = '';
            if (modal.parentElement !== document.body) document.body.appendChild(modal);
            modal.style.display = 'flex';
            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.error) {
                        titleEl.textContent = '🎮 خطا';
                        bodyEl.textContent = data.error;
                        return;
                    }
                    openGameLoginModal(data.title, data.content, data.seat_label || data.seat_number);
                })
                .catch(function () {
                    titleEl.textContent = '🎮 خطا';
                    bodyEl.textContent = 'بارگذاری اطلاعات ورود ممکن نشد.';
                });
        };
        window.openDescriptionModal = function (title, content) {
            var modal = document.getElementById('descriptionModal');
            var titleEl = document.getElementById('descriptionModalTitle');
            var bodyEl = document.getElementById('descriptionModalBody');
            if (!modal || !titleEl || !bodyEl) return;
            titleEl.textContent = '📝 توضیحات — ' + title;
            bodyEl.textContent = content;
            if (modal.parentElement !== document.body) document.body.appendChild(modal);
            modal.style.display = 'flex';
        };
        window.closeDescriptionModal = function () {
            var modal = document.getElementById('descriptionModal');
            if (modal) modal.style.display = 'none';
        };
    </script>
    <script>
        setTimeout(() => {
            document.querySelectorAll('.bg-success\\/20, .bg-danger\\/20').forEach(el => {
                el.style.transition = 'opacity 0.5s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 5000);
    </script>
    @stack('scripts')
</body>
</html>
