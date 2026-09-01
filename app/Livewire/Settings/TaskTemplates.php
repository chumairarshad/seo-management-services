<?php

namespace App\Livewire\Settings;

use App\Enums\TaskTemplateCategory;
use App\Models\TaskTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Task templates')]
class TaskTemplates extends Component
{
    use AuthorizesRequests;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $description = '';

    public string $category = 'on_page_seo';

    public string $sort_order = '0';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('viewAny', TaskTemplate::class);
    }

    public function create(): void
    {
        $this->authorize('create', TaskTemplate::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $template = TaskTemplate::query()->findOrFail($id);
        $this->authorize('update', $template);

        $this->editingId = $template->id;
        $this->title = $template->title;
        $this->description = (string) $template->description;
        $this->category = $template->category->value;
        $this->sort_order = (string) $template->sort_order;
        $this->is_active = $template->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        if ($this->editingId) {
            $template = TaskTemplate::query()->findOrFail($this->editingId);
            $this->authorize('update', $template);
        } else {
            $this->authorize('create', TaskTemplate::class);
        }

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(TaskTemplateCategory::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            TaskTemplate::query()->findOrFail($this->editingId)->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?: null,
                'category' => $validated['category'],
                'sort_order' => (int) $validated['sort_order'],
                'is_active' => $this->is_active,
            ]);
        } else {
            $base = Str::slug($validated['title']);
            $key = $base;
            $i = 1;
            while (TaskTemplate::query()->where('key', $key)->exists()) {
                $key = $base.'-'.$i++;
            }

            TaskTemplate::query()->create([
                'key' => $key,
                'title' => $validated['title'],
                'description' => $validated['description'] ?: null,
                'category' => $validated['category'],
                'sort_order' => (int) $validated['sort_order'],
                'is_active' => $this->is_active,
            ]);
        }

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Template saved.', tone: 'success');
    }

    public function toggleActive(int $id): void
    {
        $template = TaskTemplate::query()->findOrFail($id);
        $this->authorize('update', $template);
        $template->update(['is_active' => ! $template->is_active]);
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->description = '';
        $this->category = TaskTemplateCategory::OnPageSeo->value;
        $this->sort_order = '0';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.settings.task-templates', [
            'templates' => TaskTemplate::query()->orderBy('sort_order')->orderBy('id')->get(),
            'categoryOptions' => [
                TaskTemplateCategory::OnPageSeo->value => TaskTemplateCategory::OnPageSeo->label(),
                TaskTemplateCategory::TechnicalSeo->value => TaskTemplateCategory::TechnicalSeo->label(),
            ],
        ]);
    }
}
