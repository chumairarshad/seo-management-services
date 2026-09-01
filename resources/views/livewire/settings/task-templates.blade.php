<div class="space-y-6">
    <x-page-header
        title="Task templates"
        subtitle="On-page and technical SEO checklist items auto-copied onto every new project."
        :breadcrumbs="[['label' => 'Workspace'], ['label' => 'Task templates']]"
    >
        <x-slot:meta>
            <x-badge tone="neutral">{{ $templates->count() }} templates</x-badge>
            <x-badge tone="success" dot>{{ $templates->where('is_active', true)->count() }} active</x-badge>
        </x-slot:meta>

        <x-slot:actions>
            <x-button icon="plus" wire:click="create">Add template</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($templates->isEmpty())
        <x-empty-state
            icon="templates"
            title="No checklist templates yet"
            description="Templates become the setup task list on every project you create. Add the first one to start the checklist."
        >
            <x-button icon="plus" wire:click="create">Add template</x-button>
        </x-empty-state>
    @else
        <x-table :headers="[
            ['label' => 'Order', 'align' => 'right', 'width' => 'w-20'],
            'Title',
            'Category',
            'Active',
            ['label' => 'Actions', 'sr' => true, 'align' => 'right', 'width' => 'relative'],
        ]">
            @foreach ($templates as $template)
                <x-table.row wire:key="template-{{ $template->id }}">
                    <x-table.cell numeric muted>{{ $template->sort_order }}</x-table.cell>
                    <x-table.cell>
                        <p class="font-medium text-ink">{{ $template->title }}</p>
                        @if ($template->description)
                            <p class="mt-0.5 line-clamp-1 text-xs text-muted">{{ $template->description }}</p>
                        @endif
                    </x-table.cell>
                    <x-table.cell muted>{{ $template->category->label() }}</x-table.cell>
                    <x-table.cell>
                        <x-badge :tone="$template->is_active ? 'success' : 'neutral'" dot>
                            {{ $template->is_active ? 'Yes' : 'No' }}
                        </x-badge>
                    </x-table.cell>
                    <x-table.cell align="right" nowrap>
                        <div class="flex items-center justify-end gap-1">
                            <x-tooltip text="Edit template">
                                <x-button size="sm" variant="ghost" square icon="pencil" wire:click="edit({{ $template->id }})" aria-label="Edit {{ $template->title }}" />
                            </x-tooltip>
                            <x-tooltip :text="$template->is_active ? 'Exclude from new projects' : 'Include on new projects'">
                                <x-button
                                    size="sm"
                                    variant="ghost"
                                    square
                                    :icon="$template->is_active ? 'eye-off' : 'eye'"
                                    wire:click="toggleActive({{ $template->id }})"
                                    aria-label="Toggle {{ $template->title }}"
                                />
                            </x-tooltip>
                        </div>
                    </x-table.cell>
                </x-table.row>
            @endforeach
        </x-table>
    @endif

    <x-modal
        :show="$showForm"
        :title="$editingId ? 'Edit template' : 'New template'"
        subtitle="Sort order controls where the item lands in the setup checklist."
        close="cancel"
        size="md"
    >
        <form id="template-form" wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <x-input label="Title" wire:model="title" :error="$errors->first('title')" class="sm:col-span-2" required />
            <x-textarea label="Description" wire:model="description" rows="2" :error="$errors->first('description')" class="sm:col-span-2" />

            <x-select label="Category" wire:model="category" :error="$errors->first('category')">
                @foreach ($categoryOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-select>

            <x-input label="Sort order" type="number" min="0" wire:model="sort_order" :error="$errors->first('sort_order')" />

            <x-checkbox
                class="sm:col-span-2"
                label="Active"
                hint="Included on new projects."
                wire:model="is_active"
            />
        </form>

        <x-slot:footer>
            <x-button variant="ghost" wire:click="cancel">Cancel</x-button>
            <x-button type="submit" form="template-form" target="save">Save template</x-button>
        </x-slot:footer>
    </x-modal>
</div>
