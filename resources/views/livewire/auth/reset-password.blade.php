<div>
    <div class="mb-6">
        <p class="font-mono text-eyebrow text-faint uppercase">Account recovery</p>
        <h2 class="mt-1 font-display text-xl font-semibold tracking-tight text-ink">Set a new password</h2>
        <p class="mt-1 text-sm text-muted">Choose something you don’t use anywhere else.</p>
    </div>

    <form wire:submit="resetPassword" class="space-y-4">
        <x-input
            label="Email"
            type="email"
            wire:model="email"
            autocomplete="username"
            :error="$errors->first('email')"
        />

        <x-input
            label="New password"
            type="password"
            wire:model="password"
            autocomplete="new-password"
            :error="$errors->first('password')"
        />

        <x-input
            label="Confirm password"
            type="password"
            wire:model="password_confirmation"
            autocomplete="new-password"
        />

        <x-button type="submit" size="lg" target="resetPassword" class="w-full">
            <span wire:loading.remove wire:target="resetPassword">Reset password</span>
            <span wire:loading wire:target="resetPassword">Saving…</span>
        </x-button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        <a href="{{ route('login') }}" wire:navigate class="rounded font-medium text-accent hover:underline">Back to sign in</a>
    </p>
</div>
