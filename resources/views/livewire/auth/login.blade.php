<div>
    <div class="mb-6">
        <p class="font-mono text-eyebrow text-faint uppercase">Sign in</p>
        <h2 class="mt-1 font-display text-xl font-semibold tracking-tight text-ink">Welcome back</h2>
        <p class="mt-1 text-sm text-muted">Use your work email and password.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 flex items-start gap-2 rounded-lg border border-success-line bg-success-soft px-3 py-2.5 text-sm text-success" role="status">
            <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 flex items-start gap-2 rounded-lg border border-danger-line bg-danger-soft px-3 py-2.5 text-sm text-danger" role="alert">
            <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div
        wire:offline
        class="mb-4 flex items-start gap-2 rounded-lg border border-danger-line bg-danger-soft px-3 py-2.5 text-sm text-danger"
        role="alert"
    >
        <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
        <span>You are offline. Reconnect to sign in.</span>
    </div>

    <noscript>
        <div class="mb-4 rounded-lg border border-danger-line bg-danger-soft px-3 py-2.5 text-sm text-danger" role="alert">
            JavaScript is required to sign in. Enable it and reload this page.
        </div>
    </noscript>

    <form wire:submit="login" class="space-y-4" novalidate>
        <x-input
            label="Email"
            type="email"
            wire:model="email"
            autocomplete="username"
            autofocus
            :error="$errors->first('email')"
        />

        <x-input
            label="Password"
            type="password"
            wire:model="password"
            autocomplete="current-password"
            :error="$errors->first('password')"
        />

        <div class="flex items-center justify-between gap-3">
            <x-checkbox label="Remember me" wire:model="remember" class="text-sm" />

            <a href="{{ route('password.request') }}" wire:navigate class="rounded text-sm font-medium text-accent hover:underline">
                Forgot password?
            </a>
        </div>

        <x-button type="submit" size="lg" target="login" class="w-full">
            <span wire:loading.remove wire:target="login">Sign in</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </x-button>
    </form>
</div>
