<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'IM-Master Track')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex overflow-hidden bg-gray-50">

{{-- ── Sidebar ───────────────────────────────────────────────────────────────── --}}
<aside class="w-56 shrink-0 flex flex-col bg-white border-r border-gray-200 h-full">

    {{-- Logo --}}
    <div class="px-4 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-blue-600 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900 leading-tight">IM-Master Track</p>
                <p class="text-2xs text-gray-400 capitalize leading-tight">{{ str_replace('_', ' ', auth()->user()->role) }}</p>
            </div>
        </div>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 px-2 py-3 space-y-0.5 overflow-y-auto scrollbar-thin">

        <a href="{{ route('contracts.index') }}"
           class="nav-link {{ request()->routeIs('contracts.index') ? 'active' : '' }}">
            @include('components.icons.grid')
            Contracts
        </a>

        @if(isset($contract) && !isset($project))
            <a href="{{ route('contracts.index') }}"
               class="nav-link text-gray-400 hover:text-gray-600 text-2xs">
                ← All Contracts
            </a>
            <p class="section-label">{{ Str::limit($contract->name, 20) }}</p>

            <a href="{{ route('contracts.show', $contract) }}"
               class="nav-link {{ request()->routeIs('contracts.show') ? 'active' : '' }}">
                @include('components.icons.grid')
                Projects
            </a>

            <a href="{{ route('contracts.letters', $contract) }}"
               class="nav-link {{ request()->routeIs('contracts.letters') ? 'active' : '' }}"
               x-data="{ alertCount: 0 }"
               x-init="fetch('/contracts/{{ $contract->id }}/letters-alerts', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).then(d => alertCount = d.count).catch(() => {})">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Letters
                <span x-show="alertCount > 0"
                      class="ml-auto text-2xs font-bold px-1.5 py-0.5 rounded-full bg-red-500 text-white leading-none"
                      x-text="alertCount"></span>
            </a>

            <a href="{{ route('contracts.cvs', $contract) }}"
               class="nav-link {{ request()->routeIs('contracts.cvs') ? 'active' : '' }}"
               x-data="{ alertCount: 0 }"
               x-init="fetch('/contracts/{{ $contract->id }}/cvs-alerts', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).then(d => alertCount = d.count).catch(() => {})">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                CVs
                <span x-show="alertCount > 0"
                      class="ml-auto text-2xs font-bold px-1.5 py-0.5 rounded-full bg-red-500 text-white leading-none"
                      x-text="alertCount"></span>
            </a>
        @endif

        @if(isset($project))
            {{-- Contract breadcrumb --}}
            @if($project->contract)
            <a href="{{ route('contracts.show', $project->contract) }}"
               class="nav-link text-gray-400 hover:text-gray-600 text-2xs">
                ← {{ Str::limit($project->contract->name, 22) }}
            </a>
            @endif
            <p class="section-label">{{ Str::limit($project->name, 20) }}</p>

            <a href="{{ route('projects.master', $project) }}"
               class="nav-link {{ request()->routeIs('projects.master') ? 'active' : '' }}">
                @include('components.icons.table')
                Master View
            </a>

            @if(auth()->user()->hasRole(['sap_admin','super_admin']))
            <a href="{{ route('projects.sap', $project) }}"
               class="nav-link {{ request()->routeIs('projects.sap') ? 'active' : '' }}">
                @include('components.icons.cog')
                SAP Tracking
            </a>
            @endif

            @if(auth()->user()->hasRole(['dc','super_admin']))
            <a href="{{ route('projects.dc', $project) }}"
               class="nav-link {{ request()->routeIs('projects.dc') ? 'active' : '' }}">
                @include('components.icons.document')
                DC Tracking
            </a>
            @endif

            @if(auth()->user()->hasRole(['super_admin','manager']))
            <a href="{{ route('projects.audit', $project) }}"
               class="nav-link {{ request()->routeIs('projects.audit') ? 'active' : '' }}">
                @include('components.icons.clock')
                Audit Log
            </a>
            @endif

            @if(auth()->user()->isSuperAdmin())
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

        <p class="section-label">Admin</p>

        @if(auth()->user()->isSuperAdmin())
        <a href="{{ route('users.index') }}"
           class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
            @include('components.icons.users')
            Users
        </a>
        @endif


    </nav>

    {{-- User footer --}}
    <div class="px-3 py-3 border-t border-gray-100">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center text-xs font-semibold text-blue-700 shrink-0">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}{{ strtoupper(substr(strstr(auth()->user()->name.' ', ' '), 1, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-gray-900 truncate">{{ auth()->user()->name }}</p>
                <p class="text-2xs text-gray-400 truncate">{{ auth()->user()->email }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-gray-400 hover:text-gray-600" title="Sign out">
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
<div class="flex-1 flex flex-col min-h-0 overflow-hidden">
    @yield('content')
</div>

</body>
</html>
