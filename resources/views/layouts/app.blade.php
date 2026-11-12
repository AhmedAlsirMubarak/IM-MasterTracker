<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'IM-Master Track')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex overflow-hidden bg-slate-100" x-data="{ sidebarOpen: false }">

@php
    $navContract = $project->contract ?? ($contract ?? null);
    $user = auth()->user();
    $initials = strtoupper(substr($user->name, 0, 1)) . strtoupper(substr(strstr($user->name.' ', ' '), 1, 1));
@endphp

{{-- Mobile backdrop --}}
<div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
     class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden"></div>

{{-- ── Sidebar ───────────────────────────────────────────────────────────────── --}}
<aside class="fixed inset-y-0 left-0 z-50 w-60 shrink-0 flex flex-col bg-sidebar text-slate-300 h-full
              transform transition-transform duration-200 lg:static lg:translate-x-0"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

    {{-- Logo --}}
    <div class="px-4 h-16 flex items-center gap-2.5 shrink-0">
        <div class="w-8 h-8 rounded-lg bg-brand-600 flex items-center justify-center">
            <svg class="w-[18px] h-[18px] text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                    d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/>
            </svg>
        </div>
        <span class="text-[15px] font-semibold text-white tracking-tight">IM-Master Track</span>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 px-3 pb-4 space-y-0.5 overflow-y-auto scrollbar-thin scrollbar-dark" aria-label="Main">

        <a href="{{ route('contracts.index') }}"
           class="nav-link {{ request()->routeIs('contracts.index') ? 'active' : '' }}">
            @include('components.icons.grid')
            Contracts
        </a>

        {{-- Contract context --}}
        @if($navContract)
            <div class="mt-4 mx-0.5 rounded-lg bg-sidebar-raised/70 border border-sidebar-border px-3 py-2.5">
                <p class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold">Contract</p>
                <a href="{{ route('contracts.show', $navContract) }}"
                   class="block text-[13px] font-medium text-white truncate hover:text-brand-200"
                   title="{{ $navContract->name }}">{{ $navContract->name }}</a>
            </div>
        @endif

        @if(isset($contract) && !isset($project))
            <p class="section-label">Contract</p>

            <a href="{{ route('contracts.show', $contract) }}"
               class="nav-link {{ request()->routeIs('contracts.show') ? 'active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                Projects
            </a>

            <a href="{{ route('contracts.letters', $contract) }}"
               class="nav-link {{ request()->routeIs('contracts.letters') ? 'active' : '' }}"
               x-data="{ alertCount: 0 }"
               x-init="fetch('/contracts/{{ $contract->id }}/letters-alerts', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).then(d => alertCount = d.count).catch(() => {})">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Letters
                <span x-show="alertCount > 0" x-cloak
                      class="ml-auto text-[11px] font-bold px-1.5 py-0.5 rounded-full bg-orange-500 text-slate-900 leading-none"
                      x-text="alertCount"></span>
            </a>

            <a href="{{ route('contracts.cvs', $contract) }}"
               class="nav-link {{ request()->routeIs('contracts.cvs') ? 'active' : '' }}"
               x-data="{ alertCount: 0 }"
               x-init="fetch('/contracts/{{ $contract->id }}/cvs-alerts', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).then(d => alertCount = d.count).catch(() => {})">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                CVs
                <span x-show="alertCount > 0" x-cloak
                      class="ml-auto text-[11px] font-bold px-1.5 py-0.5 rounded-full bg-orange-500 text-slate-900 leading-none"
                      x-text="alertCount"></span>
            </a>
        @endif

        @if(isset($project))
            <p class="section-label truncate" title="{{ $project->name }}">Project · {{ Str::limit($project->name, 22) }}</p>

            <a href="{{ route('projects.master', $project) }}"
               class="nav-link {{ request()->routeIs('projects.master') ? 'active' : '' }}">
                @include('components.icons.table')
                Master View
            </a>

            @if($user->hasRole(['sap_admin','super_admin']))
            <a href="{{ route('projects.sap', $project) }}"
               class="nav-link {{ request()->routeIs('projects.sap') ? 'active' : '' }}">
                @include('components.icons.cog')
                SAP Tracking
            </a>
            @endif

            @if($user->hasRole(['dc','super_admin']))
            <a href="{{ route('projects.dc', $project) }}"
               class="nav-link {{ request()->routeIs('projects.dc') ? 'active' : '' }}">
                @include('components.icons.document')
                DC Tracking
            </a>
            @endif

            @if($user->hasRole(['super_admin','manager']))
            <a href="{{ route('projects.audit', $project) }}"
               class="nav-link {{ request()->routeIs('projects.audit') ? 'active' : '' }}">
                @include('components.icons.clock')
                Audit Log
            </a>
            @endif

            @if($user->isSuperAdmin())
            <a href="{{ route('projects.columns', $project) }}"
               class="nav-link {{ request()->routeIs('projects.columns') ? 'active' : '' }}">
                @include('components.icons.columns')
                Column Config
            </a>
            <a href="{{ route('projects.import', $project) }}"
               class="nav-link {{ request()->routeIs('projects.import') ? 'active' : '' }}">
                @include('components.icons.upload')
                Import Excel
            </a>
            @endif
        @endif

        @if($user->isSuperAdmin())
        <p class="section-label">Admin</p>
        <a href="{{ route('users.index') }}"
           class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
            @include('components.icons.users')
            Users
        </a>
        @endif
    </nav>

    {{-- User footer --}}
    <div class="px-3 py-3 border-t border-sidebar-border shrink-0">
        <div class="flex items-center gap-2.5 px-1">
            <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-semibold text-white shrink-0">
                {{ $initials }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-[13px] font-medium text-white truncate">{{ $user->name }}</p>
                <p class="text-xs text-slate-400 truncate capitalize">{{ str_replace('_', ' ', $user->role) }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="p-2 rounded-md text-slate-400 hover:text-white hover:bg-sidebar-raised" title="Sign out" aria-label="Sign out">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- ── Main area ────────────────────────────────────────────────────────────── --}}
<div class="flex-1 flex flex-col min-h-0 min-w-0 overflow-hidden">

    {{-- Top bar: breadcrumb --}}
    <header class="h-14 shrink-0 flex items-center gap-3 px-4 lg:px-6 bg-white border-b border-slate-200">
        <button type="button" @click="sidebarOpen = true"
                class="lg:hidden -ml-1 p-2 rounded-md text-slate-500 hover:bg-slate-100" aria-label="Open menu">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-[13px] text-slate-500 min-w-0">
            <a href="{{ route('contracts.index') }}" class="hover:text-slate-900 shrink-0">Contracts</a>
            @if($navContract)
                <span class="text-slate-300">/</span>
                <a href="{{ route('contracts.show', $navContract) }}" class="hover:text-slate-900 truncate {{ isset($project) ? '' : 'text-slate-900 font-medium' }}">{{ $navContract->name }}</a>
            @endif
            @if(!$navContract && !request()->routeIs('contracts.index'))
                <span class="text-slate-300">/</span>
                <span class="text-slate-900 font-medium truncate">@yield('title')</span>
            @endif
            @isset($project)
                <span class="text-slate-300">/</span>
                <span class="text-slate-900 font-medium truncate">{{ $project->name }}</span>
            @endisset
        </nav>

        <div class="flex-1"></div>

        <span class="hidden sm:inline-flex badge-gray capitalize">{{ str_replace('_', ' ', $user->role) }}</span>
        <div class="w-8 h-8 rounded-full bg-brand-600 flex items-center justify-center text-xs font-semibold text-white" title="{{ $user->name }}">
            {{ $initials }}
        </div>
    </header>

    <div class="flex-1 flex flex-col min-h-0 overflow-hidden">
        @yield('content')
    </div>
</div>

@include('partials.confirm-dialog')

</body>
</html>
