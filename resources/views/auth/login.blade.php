<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — IM-Master Track</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-50 flex items-center justify-center px-4">

<div class="w-full max-w-sm">
    <div class="text-center mb-8">
        <div class="inline-flex w-12 h-12 rounded-xl bg-blue-600 items-center justify-center mb-4">
            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/>
            </svg>
        </div>
        <h1 class="text-2xl font-semibold text-gray-900">IM-Master Track</h1>
        <p class="text-sm text-gray-500 mt-1">Sign in to your account</p>
    </div>

    <div class="card">
        <div class="card-body space-y-4">
            @if ($errors->any())
                <div class="alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="label">Email address</label>
                    <input name="email" type="email" autocomplete="email" required
                        value="{{ old('email') }}"
                        class="input" placeholder="you@company.com" />
                </div>

                <div>
                    <label class="label">Password</label>
                    <input name="password" type="password" autocomplete="current-password" required
                        class="input" />
                </div>

                <button type="submit" class="btn-primary w-full justify-center py-2">
                    Sign in
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
