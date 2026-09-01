<div>
    <div class="mb-6">
        <p class="font-mono text-eyebrow text-faint uppercase">Account recovery</p>
        <h2 class="mt-1 font-display text-xl font-semibold tracking-tight text-ink">Forgot password</h2>
        <p class="mt-1 text-sm text-muted">We’ll email a reset link if the account exists.</p>
    </div>

    @if ($status)
        <div class="mb-4 flex items-start gap-2 rounded-lg border border-success-line bg-success-soft px-3 py-2.5 text-sm text-success" role="status">
            <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0" />
            <span>{{ $status }}</span>
        </div>
    @endif

    <form wire:submit="sendResetLink" class="space-y-4">
        <x-input
            label="Email"
            type="email"
            wire:model="email"
            autocomplete="username"
            autofocus
            :error="$errors->first('email')"
        />

        <x-button type="submit" size="lg" target="sendResetLink" class="w-full">
            <span wire:loading.remove wire:target="sendResetLink">Send reset link</span>
            <span wire:loading wire:target="sendResetLink">Sending…</span>
        </x-button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        <a href="{{ route('login') }}" wire:navigate class="rounded font-medium text-accent hover:underline">Back to sign in</a>
    </p>
</div>
