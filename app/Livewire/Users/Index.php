<?php

namespace App\Livewire\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Users')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $is_active = true;

    /** @var array<int, int> */
    public array $selectedRoles = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizePermission('users.create');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $userId): void
    {
        $this->authorizePermission('users.update');

        $user = User::query()->with('roles')->findOrFail($userId);

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->is_active = $user->is_active;
        $this->selectedRoles = $user->roles->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        $isCreating = $this->editingUserId === null;

        $this->authorizePermission($isCreating ? 'users.create' : 'users.update');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingUserId),
            ],
            'is_active' => ['boolean'],
            'selectedRoles' => ['array'],
            'selectedRoles.*' => ['integer', 'exists:roles,id'],
            'password' => $isCreating
                ? ['required', 'string', Password::defaults()]
                : ['nullable', 'string', Password::defaults()],
        ];

        $validated = $this->validate($rules);

        if ($isCreating) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_active' => $validated['is_active'],
            ]);
        } else {
            $user = User::query()->findOrFail($this->editingUserId);
            $user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_active' => $validated['is_active'],
            ]);

            if (filled($validated['password'] ?? null)) {
                $user->password = $validated['password'];
            }

            $user->save();
        }

        $sync = collect($this->selectedRoles)
            ->mapWithKeys(fn (int $roleId) => [$roleId => ['project_id' => null]])
            ->all();

        $user->roles()->sync($sync);

        // Authorisation lookups are memoised per instance, so an admin editing
        // their own roles would otherwise render this request with the old set.
        $user->flushPermissionCache();
        Auth::user()?->flushPermissionCache();

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: $isCreating ? 'User created.' : 'User updated.', tone: 'success');
    }

    public function toggleActive(int $userId): void
    {
        $this->authorizePermission('users.deactivate');

        $user = User::query()->findOrFail($userId);

        if ($user->id === Auth::id()) {
            $this->dispatch('toast', message: 'You cannot deactivate your own account.', tone: 'danger');

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);

        $this->dispatch('toast', message: $user->is_active ? 'User activated.' : 'User deactivated.', tone: 'success');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingUserId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->is_active = true;
        $this->selectedRoles = [];
        $this->resetValidation();
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(Auth::user()?->hasPermission($permission), 403);
    }

    public function render()
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('label')->get(),
        ]);
    }
}
