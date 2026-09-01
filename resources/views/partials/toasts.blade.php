<div
    x-data="osToasts()"
    x-on:toast.window="push($event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex flex-col items-center gap-2 px-4 sm:right-4 sm:bottom-4 sm:left-auto sm:items-end sm:px-0"
    role="status"
    aria-live="polite"
>
    <template x-for="toast in items" :key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 sm:translate-x-3 sm:translate-y-0"
            x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-2.5 rounded-xl border bg-raised px-3.5 py-3 shadow-lg"
            :class="{
                'border-success-line': toast.tone === 'success',
                'border-danger-line': toast.tone === 'danger',
                'border-warn-line': toast.tone === 'warn',
                'border-line': toast.tone === 'info' || ! toast.tone,
            }"
        >
            <span
                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full"
                :class="{
                    'text-success': toast.tone === 'success',
                    'text-danger': toast.tone === 'danger',
                    'text-warn': toast.tone === 'warn',
                    'text-info': toast.tone === 'info' || ! toast.tone,
                }"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="m8.5 12.2 2.4 2.4 4.6-5" x-show="toast.tone !== 'danger' && toast.tone !== 'warn'" />
                    <path d="M12 8v4.6M12 16v.4" x-show="toast.tone === 'danger' || toast.tone === 'warn'" />
                </svg>
            </span>

            <p class="min-w-0 flex-1 text-sm text-ink" x-text="toast.message"></p>

            <button
                type="button"
                x-on:click="dismiss(toast.id)"
                class="-mt-0.5 -mr-1 flex size-6 shrink-0 items-center justify-center rounded-md text-faint transition-colors hover:bg-subtle hover:text-ink"
                aria-label="Dismiss"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-3.5" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
        </div>
    </template>
</div>
