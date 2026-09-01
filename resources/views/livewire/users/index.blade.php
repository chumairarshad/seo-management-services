@php
    $canCreate = (bool) auth()->user()?->hasPermission('users.create');
    $canUpdate = (bool) auth()->user()?->hasPermission('users.update');
    $canDeactivate = (bool) auth()->user()?->hasPermission('users.deactivate');
@endphp

<div class="space-y-6">
    <x-page-header
        title="Users"
        subtitle="Accounts, role assignments and sign-in access. Permissions are the union of every role a person holds."
        :breadcrumbs="[['label' => 'Workspace']]"
    >
        <x-slot:actions>
            @if ($canCreate)
                <x-button icon="plus" wire:click="create">New user</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar target="search">
        <x-input
            type="search"
            size="sm"
            icon="search"
            data-page-search
            class="min-w-[12rem] flex-1 sm:max-w-xs"
            wire:model.live.debounce.300ms="search"
            placeholder="Search name or email…"
            aria-label="Search users"
        />

        <x-slot:trailing>
            {{ $users->total() }} {{ \Illuminate\Support\Str::plural('user', $users->total()) }}
        </x-slot:trailing>
    </x-filter-bar>

    <x-modal
        :show="$showForm"
        :title="$editingUserId ? 'Edit user' : 'Create user'"
        subtitle="Roles decide what this person can see and do. A user can hold several at once."
        close="cancel"
        size="lg"
    >
        <form wire:submit="save" id="user-form" class="grid gap-4 sm:grid-cols-2">
            <x-input label="Name" wire:model="name" :error="$errors->first('name')" required />
            <x-input label="Email" type="email" wire:model="email" :error="$errors->first('email')" required />
            <x-input
                label="Password"
                type="password"
                wire:model="password"
                :error="$errors->first('password')"
                :hint="$editingUserId ? 'Leave blank to keep the current password.' : null"
                :required="! $editingUserId"
            />

            <div class="space-y-1.5">
                <span class="block text-xs font-medium text-ink-soft">Status</span>
                <x-checkbox wire:model="is_active" label="Active (can sign in)" />
            </div>

            <div class="sm:col-span-2">
                <span class="mb-2 block text-xs font-medium text-ink-soft">Global roles</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($roles as $role)
                        <x-checkbox
                            class="items-center rounded-lg border border-line px-3 py-2 hover:border-line-strong"
                            value="{{ $role->id }}"
                            wire:model="selectedRoles"
                            :label="$role->label"
                            wire:key="role-{{ $role->id }}"
                        />
                    @endforeach
                </div>
                @error('selectedRoles') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
        </form>

        <x-slot:footer>
            <x-button variant="ghost" wire:click="cancel">Cancel</x-button>
            <x-button type="submit" form="user-form" target="save">Save user</x-button>
        </x-slot:footer>
    </x-modal>

    <div wire:loading.delay.long.flex wire:target="search" class="hidden">
        <x-skeleton variant="table" :rows="6" :cols="4" class="w-full" />
    </div>

    @if ($users->isEmpty())
        <x-empty-state
            icon="users"
            title="No users match"
            description="Create a teammate account and give it roles, or clear the search to see everyone."
        >
            @if ($canCreate)
                <x-button icon="plus" wire:click="create">New user</x-button>
            @endif
        </x-empty-state>
    @else
        <div wire:loading.class="opacity-60" wire:target="search" class="transition-opacity duration-150">
            <x-table :headers="['Person', 'Roles', 'Status', ['label' => 'Actions', 'align' => 'right']]">
                @foreach ($users as $user)
                    <x-table.row wire:key="user-{{ $user->id }}">
                        <x-table.cell>
                            <span class="flex items-center gap-2.5">
                                <x-avatar :name="$user->name" size="md" />
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-ink">{{ $user->name }}</span>
                                    <span class="block truncate font-mono text-xs text-muted">{{ $user->email }}</span>
                                </span>
                            </span>
                        </x-table.cell>

                        <x-table.cell>
                            <span class="flex flex-wrap gap-1">
                                @forelse ($user->roles as $role)
                                    <x-badge size="sm" tone="accent">{{ $role->label }}</x-badge>
                                @empty
                                    <span class="text-xs text-faint">No roles</span>
                                @endforelse
                            </span>
                        </x-table.cell>

                        <x-table.cell nowrap>
                            @if ($user->is_active)
                                <x-badge size="sm" tone="success" dot>Active</x-badge>
                            @else
                                <x-badge size="sm" tone="danger" dot>Inactive</x-badge>
                            @endif
                        </x-table.cell>

                        <x-table.cell align="right" nowrap>
                            <span class="flex justify-end gap-0.5">
                                @if ($canUpdate)
                                    <x-tooltip text="Edit">
                                        <x-button size="sm" square variant="ghost" wire:click="edit({{ $user->id }})" aria-label="Edit {{ $user->name }}">
                                            <x-icon name="pencil" class="size-3.5" />
                                        </x-button>
                                    </x-tooltip>
                                @endif

                                @if ($canDeactivate && $user->id !== auth()->id())
                                    <x-tooltip :text="$user->is_active ? 'Deactivate' : 'Activate'">
                                        <x-button
                                            size="sm"
                                            square
                                            :variant="$user->is_active ? 'danger-ghost' : 'ghost'"
                                            wire:click="toggleActive({{ $user->id }})"
                                            wire:confirm="{{ $user->is_active ? 'Deactivate this user? They will be blocked from signing in immediately.' : 'Reactivate this user?' }}"
                                            aria-label="{{ $user->is_active ? 'Deactivate' : 'Activate' }} {{ $user->name }}"
                                        >
                                            <x-icon :name="$user->is_active ? 'eye-off' : 'check-circle'" class="size-3.5" />
                                        </x-button>
                                    </x-tooltip>
                                @endif
                            </span>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        {{ $users->links() }}
    @endif
</div>
