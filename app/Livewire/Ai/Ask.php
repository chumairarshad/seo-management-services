<?php

namespace App\Livewire\Ai;

use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiBudgetService;
use App\Services\Ai\AiHelperService;
use App\Support\AiAvailability;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

class Ask extends Component
{
    public bool $compact = false;

    public string $question = '';

    public string $mode = 'ask'; // ask | meta | rejections | brief

    public string $answer = '';

    public ?string $reportKey = null;

    public ?string $reportTitle = null;

    /** @var array<string, mixed> */
    public array $sourceFigures = [];

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public bool $aiGenerated = false;

    public bool $cached = false;

    public string $error = '';

    public string $helperTitle = '';

    public string $helperNotes = '';

    public function mount(bool $compact = false): void
    {
        abort_unless(AiAvailability::enabled(), 404);
        abort_unless(Auth::check(), 403);
        $this->compact = $compact;
    }

    public function ask(AiAssistantService $assistant): void
    {
        $this->resetOutput();
        $this->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $result = $assistant->ask(Auth::user(), $this->question);
            $this->applyResult($result);
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function runHelper(AiHelperService $helpers): void
    {
        $this->resetOutput();
        $user = Auth::user();

        try {
            $result = match ($this->mode) {
                'meta' => $helpers->suggestMeta($user, null, null, $this->helperTitle ?: null, $this->helperNotes ?: null),
                'rejections' => $helpers->summariseRejections($user),
                'brief' => (function () use ($helpers, $user) {
                    $this->validate([
                        'helperTitle' => ['required', 'string', 'max:255'],
                        'helperNotes' => ['nullable', 'string', 'max:2000'],
                    ]);

                    return $helpers->draftTaskBrief($user, $this->helperTitle, $this->helperNotes ?: null);
                })(),
                default => throw new RuntimeException('Unknown helper.'),
            };
            $this->applyResult($result);
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * @param  array{answer: string, report_key?: string|null, report_title?: string|null, source_figures?: array<string, mixed>, rows?: list<array<string, mixed>>, cached?: bool, ai_generated?: bool}  $result
     */
    protected function applyResult(array $result): void
    {
        $this->answer = (string) ($result['answer'] ?? '');
        $this->reportKey = $result['report_key'] ?? null;
        $this->reportTitle = $result['report_title'] ?? null;
        $this->sourceFigures = $result['source_figures'] ?? [];
        $this->rows = $result['rows'] ?? [];
        $this->cached = (bool) ($result['cached'] ?? false);
        $this->aiGenerated = (bool) ($result['ai_generated'] ?? true);
    }

    protected function resetOutput(): void
    {
        $this->answer = '';
        $this->reportKey = null;
        $this->reportTitle = null;
        $this->sourceFigures = [];
        $this->rows = [];
        $this->cached = false;
        $this->aiGenerated = false;
        $this->error = '';
    }

    public function render(AiAssistantService $assistant, AiBudgetService $budget)
    {
        $view = view('livewire.ai.ask', [
            'supported' => $assistant->supportedReports(),
            'budgetCents' => AiAvailability::monthlyBudgetCents(),
            'spentCents' => $budget->spentThisMonthCents(),
            'remainingCents' => $budget->remainingCents(),
        ]);

        // Nested dashboard embed must not re-apply the app layout.
        if ($this->compact) {
            return $view;
        }

        return $view->layout('layouts.app')->title('AI assistant');
    }
}
