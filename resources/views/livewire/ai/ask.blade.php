@php
    $modes = [
        'ask' => 'Ask',
        'meta' => 'Meta title',
        'rejections' => 'Rejection themes',
        'brief' => 'Task brief',
    ];

    $figureValue = function ($value) {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if ($value === null) {
            return '—';
        }

        return is_scalar($value)
            ? (string) $value
            : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    };

    // Nested figure maps read far better as one row per number than as a JSON blob.
    $flattenFigures = function (array $data, string $prefix = '') use (&$flattenFigures) {
        $rows = [];

        foreach ($data as $key => $value) {
            $label = trim($prefix.' · '.str_replace('_', ' ', (string) $key), ' ·');
            $isMap = is_array($value) && $value !== [] && array_keys($value) !== range(0, count($value) - 1);

            if ($isMap) {
                $rows = array_merge($rows, $flattenFigures($value, $label));
            } else {
                $rows[] = ['label' => $label, 'value' => $value];
            }
        }

        return $rows;
    };
@endphp

<div class="{{ $compact ? '' : 'space-y-6' }}">
    @if ($compact)
        <x-card title="Ask your data" subtitle="AI-generated · scoped to your permissions" icon="ai">
            <x-slot:actions>
                <x-button size="sm" variant="ghost" iconRight="arrow-right" href="{{ route('ai.ask') }}" wire:navigate>Open full console</x-button>
            </x-slot:actions>

            <form wire:submit="ask" class="space-y-3">
                <x-textarea
                    label="Question"
                    wire:model="question"
                    rows="2"
                    placeholder="e.g. Which sites dropped revenue this month vs prior?"
                    :error="$errors->first('question')"
                />

                <div class="flex justify-end">
                    <x-button type="submit" size="sm" target="ask" icon="ai">Ask</x-button>
                </div>
            </form>

            @if ($error)
                <p class="mt-3 flex items-start gap-1.5 rounded-lg border border-danger-line bg-danger-soft px-3 py-2 text-xs text-danger">
                    <x-icon name="alert" class="mt-px size-3.5 shrink-0" />
                    {{ $error }}
                </p>
            @endif

            @if ($answer !== '')
                <div class="mt-4 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge size="sm" tone="accent">AI-generated</x-badge>
                        @if ($cached)
                            <x-badge size="sm" tone="warn">Cached</x-badge>
                        @endif
                        @if ($reportTitle)
                            <span class="font-mono text-[10px] text-muted uppercase">{{ $reportTitle }}</span>
                        @endif
                    </div>

                    <p class="rounded-lg border border-line bg-subtle/60 px-3 py-2.5 text-sm whitespace-pre-wrap text-ink-soft">{{ $answer }}</p>

                    @if ($sourceFigures !== [])
                        <details class="rounded-lg border border-line px-3 py-2">
                            <summary class="cursor-pointer text-xs font-medium text-muted">Source figures</summary>
                            <dl class="mt-2 space-y-1">
                                @foreach ($flattenFigures($sourceFigures) as $figure)
                                    <div class="flex items-baseline justify-between gap-3 text-[11px]">
                                        <dt class="font-mono text-muted">{{ $figure['label'] }}</dt>
                                        <dd class="min-w-0 truncate font-mono text-ink-soft tabular-nums">{{ $figureValue($figure['value']) }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </details>
                    @endif
                </div>
            @endif
        </x-card>
    @else
        <x-page-header
            title="Ask your data"
            subtitle="Plain-English questions about your scoped portfolio. Every answer maps to a fixed read-only report — never generated SQL."
            :breadcrumbs="[['label' => 'Workspace']]"
        >
            <x-slot:meta>
                <x-badge tone="accent" size="sm" dot>Read-only reports</x-badge>
                <x-badge tone="neutral" size="sm">{{ count($supported) }} supported</x-badge>
            </x-slot:meta>
            <x-slot:actions>
                @if (auth()->user()?->isAdmin() || auth()->user()?->hasPermission('settings.view'))
                    <x-button variant="secondary" icon="inbox" href="{{ route('ai.drafts') }}" wire:navigate>Draft notes</x-button>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <x-card flush class="overflow-hidden">
                    <div class="flex flex-wrap items-center gap-1 border-b border-line bg-subtle/50 px-3 py-2">
                        @foreach ($modes as $key => $label)
                            <x-button
                                size="sm"
                                target=""
                                :variant="$mode === $key ? 'subtle' : 'ghost'"
                                wire:click="$set('mode', '{{ $key }}')"
                                aria-pressed="{{ $mode === $key ? 'true' : 'false' }}"
                            >{{ $label }}</x-button>
                        @endforeach
                    </div>

                    <div class="p-4 sm:p-6">
                        @if ($mode === 'ask')
                            <form wire:submit="ask" class="space-y-3">
                                <x-textarea
                                    label="Question"
                                    wire:model="question"
                                    rows="3"
                                    placeholder="e.g. Which sites dropped revenue this month vs prior?"
                                    :error="$errors->first('question')"
                                    hint="Questions are matched to a whitelisted report before anything is sent."
                                />

                                <div class="flex justify-end">
                                    <x-button type="submit" target="ask" icon="ai">Ask</x-button>
                                </div>
                            </form>
                        @elseif ($mode === 'meta' || $mode === 'brief')
                            <form wire:submit="runHelper" class="space-y-3">
                                <x-input
                                    :label="$mode === 'meta' ? 'Title / topic' : 'Task title'"
                                    wire:model="helperTitle"
                                    :error="$errors->first('helperTitle')"
                                />

                                <x-textarea
                                    label="Notes (optional)"
                                    wire:model="helperNotes"
                                    rows="3"
                                    :error="$errors->first('helperNotes')"
                                />

                                <div class="flex justify-end">
                                    <x-button type="submit" target="runHelper" icon="ai">Generate</x-button>
                                </div>
                            </form>
                        @else
                            <form wire:submit="runHelper" class="space-y-3">
                                <p class="text-sm text-muted">Summarise recent rejection / revision reasons in your scope.</p>

                                <div class="flex justify-end">
                                    <x-button type="submit" target="runHelper" icon="ai">Summarise</x-button>
                                </div>
                            </form>
                        @endif
                    </div>
                </x-card>

                @if ($error)
                    <p class="flex items-start gap-2 rounded-xl border border-danger-line bg-danger-soft px-4 py-3 text-sm text-danger">
                        <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
                        {{ $error }}
                    </p>
                @endif

                <div wire:loading.delay.long.flex wire:target="ask,runHelper" class="hidden">
                    <x-card class="w-full">
                        <x-skeleton :lines="4" />
                    </x-card>
                </div>

                @if ($answer !== '')
                    <x-card title="Answer" :subtitle="$reportTitle ? 'Report: '.$reportTitle : null" icon="ai">
                        <x-slot:actions>
                            <x-badge tone="accent">AI-generated</x-badge>
                            @if ($cached)
                                <x-badge tone="warn">Cached</x-badge>
                            @endif
                        </x-slot:actions>

                        <p class="text-sm whitespace-pre-wrap text-ink-soft">{{ $answer }}</p>
                    </x-card>

                    @if ($sourceFigures !== [])
                        <x-card title="Source figures" subtitle="The exact scoped numbers the answer was written from." icon="pnl" flush class="overflow-hidden">
                            <x-table flush :headers="['Figure', ['label' => 'Value', 'align' => 'right']]">
                                @foreach ($flattenFigures($sourceFigures) as $index => $figure)
                                    @php $scalar = is_scalar($figure['value']) || $figure['value'] === null; @endphp
                                    <x-table.row wire:key="figure-{{ $index }}">
                                        <x-table.cell mono muted nowrap>{{ $figure['label'] }}</x-table.cell>
                                        <x-table.cell mono :align="$scalar ? 'right' : 'left'">
                                            @if ($scalar)
                                                <span class="tabular-nums">{{ $figureValue($figure['value']) }}</span>
                                            @else
                                                <pre class="max-w-full overflow-x-auto text-[10px] text-muted">{{ $figureValue($figure['value']) }}</pre>
                                            @endif
                                        </x-table.cell>
                                    </x-table.row>
                                @endforeach
                            </x-table>
                        </x-card>
                    @endif
                @endif
            </div>

            <aside class="space-y-4">
                <x-card title="Spend this month" subtitle="Estimated from token use." icon="expenses">
                    <p class="font-mono text-figure font-medium text-ink tabular-nums">${{ number_format($spentCents / 100, 2) }}</p>

                    <x-progress
                        class="mt-3"
                        :value="$spentCents"
                        :max="max(1, $budgetCents)"
                        label="Monthly cap"
                        caption="${{ number_format($remainingCents / 100, 2) }} left"
                        :tone="$remainingCents <= 0 ? 'danger' : ($spentCents > ($budgetCents * 0.8) ? 'warn' : 'accent')"
                    />

                    <p class="mt-2 text-xs text-muted">Cap ${{ number_format($budgetCents / 100, 2) }} · set in Settings.</p>
                </x-card>

                <x-card title="Supported reports" subtitle="Anything outside this list is refused." icon="pnl">
                    <ul class="space-y-2.5">
                        @foreach ($supported as $key => $label)
                            <li class="flex gap-2">
                                <span class="mt-0.5 shrink-0 font-mono text-[10px] text-accent uppercase">{{ str_replace('_', ' ', $key) }}</span>
                                <span class="text-xs text-muted">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            </aside>
        </div>
    @endif
</div>
