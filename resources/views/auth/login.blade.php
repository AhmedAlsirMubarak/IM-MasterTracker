<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — IM-Master Track</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100">

<div class="min-h-full flex">
    {{-- Brand panel --}}
    <div class="hidden lg:flex lg:w-[44%] bg-sidebar text-slate-300 flex-col justify-between p-12">
        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-lg bg-brand-600 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                        d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">IM-Master Track</span>
        </div>
        <div class="max-w-md">
            <h2 class="text-3xl font-semibold text-white leading-tight">Contracts, wells and correspondence in one place.</h2>
            <p class="mt-4 text-slate-400">Master View, SAP and DC tracking, letters and CVs for every contract.</p>
        </div>
        <p class="text-xs text-slate-500">&copy; {{ date('Y') }} IM-Master Track</p>
    </div>

    {{-- Form --}}
    <div class="flex-1 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <div class="lg:hidden flex items-center gap-2.5 mb-8">
                <div class="w-9 h-9 rounded-lg bg-brand-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                            d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/>
                    </svg>
                </div>
                <span class="text-base font-semibold text-slate-900">IM-Master Track</span>
            </div>

            <h1 class="text-2xl font-semibold text-slate-900">Sign in</h1>
            <p class="text-sm text-slate-500 mt-1 mb-6">Use your work email and password.</p>

            <div class="card">
                <div class="card-body space-y-4 py-6">
                    @if ($errors->any())
                        <div class="alert-danger">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label class="label" for="email">Email address</label>
                            <input id="email" name="email" type="email" autocomplete="email" required
                                value="{{ old('email') }}"
                                class="input" placeholder="you@company.com" />
                        </div>

                        <div>
                            <label class="label" for="password">Password</label>
                            <input id="password" name="password" type="password" autocomplete="current-password" required
                                class="input" />
                        </div>

                        <button type="submit" class="btn-primary w-full justify-center py-2.5">
                            Sign in
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
