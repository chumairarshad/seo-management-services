<?php

use App\Http\Controllers\MediaDownloadController;
use App\Http\Controllers\OpsController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Ai\Ask as AiAsk;
use App\Livewire\Ai\DraftNotes as AiDraftNotes;
use App\Livewire\Approvals\Queue as ApprovalQueue;
use App\Livewire\Articles\Index as ArticlesIndex;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Dashboard;
use App\Livewire\Links\Index as LinksIndex;
use App\Livewire\Money\DistributionShow;
use App\Livewire\Money\DistributionsIndex;
use App\Livewire\Money\ExpensesIndex;
use App\Livewire\Money\PartnersIndex;
use App\Livewire\Money\PartnerStatement;
use App\Livewire\Money\ProfitLoss;
use App\Livewire\Money\RevenuesIndex;
use App\Livewire\People\AttendanceIndex;
use App\Livewire\People\LoginHistoryIndex;
use App\Livewire\People\ScorecardShow;
use App\Livewire\People\WorkLogsIndex;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Projects\Show as ProjectShow;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Livewire\Settings\TaskTemplates;
use App\Livewire\Tasks\Index as TasksIndex;
use App\Livewire\Tasks\Show as TaskShow;
use App\Livewire\Users\Index as UsersIndex;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Hostinger ops (no SSH) — disabled when OPS_TOKEN is empty
|--------------------------------------------------------------------------
*/
Route::get('/_ops/{action}', OpsController::class)
    ->where('action', 'migrate|storage-link|cache-clear|optimize|livewire-assets')
    ->middleware('throttle:'.max(1, (int) config('ops.throttle', 5)).',1')
    ->name('ops');

/*
|--------------------------------------------------------------------------
| Public storage fallback when symlink(/public/storage) fails
|--------------------------------------------------------------------------
*/
Route::get('/media/public/{path}', PublicStorageController::class)
    ->where('path', '.*')
    ->name('media.public');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');

    // Attachments live on the private disk; these are the only way to read one back.
    Route::get('/media/{media}', [MediaDownloadController::class, 'media'])->name('media.download');
    Route::get('/money/expenses/{expense}/receipt', [MediaDownloadController::class, 'receipt'])
        ->name('expenses.receipt');

    // Milestone 7 — routes always registered; components 404 when AI key is missing
    Route::get('/ai', AiAsk::class)->name('ai.ask');
    Route::get('/ai/drafts', AiDraftNotes::class)->name('ai.drafts');

    Route::get('/users', UsersIndex::class)
        ->middleware(EnsurePermission::class.':users.view')
        ->name('users.index');

    Route::get('/settings', SettingsIndex::class)
        ->middleware(EnsurePermission::class.':settings.view')
        ->name('settings.index');

    Route::get('/settings/task-templates', TaskTemplates::class)
        ->middleware(EnsurePermission::class.':task_templates.manage')
        ->name('settings.task-templates');

    Route::get('/projects', ProjectsIndex::class)
        ->middleware(EnsurePermission::class.':projects.view')
        ->name('projects.index');

    Route::get('/projects/{project}', ProjectShow::class)
        ->middleware(EnsurePermission::class.':projects.view')
        ->name('projects.show');

    Route::get('/tasks', TasksIndex::class)
        ->middleware(EnsurePermission::class.':tasks.view')
        ->name('tasks.index');

    Route::get('/tasks/{task}', TaskShow::class)
        ->middleware(EnsurePermission::class.':tasks.view')
        ->name('tasks.show');

    Route::get('/articles', ArticlesIndex::class)
        ->middleware(EnsurePermission::class.':articles.view')
        ->name('articles.index');

    Route::get('/links', LinksIndex::class)
        ->middleware(EnsurePermission::class.':links.view')
        ->name('links.index');

    Route::get('/approvals', ApprovalQueue::class)
        ->name('approvals.queue');

    // Milestone 4 — People
    Route::get('/people/login-history', LoginHistoryIndex::class)
        ->middleware(EnsurePermission::class.':login_history.view')
        ->name('people.login-history');

    Route::get('/people/attendance', AttendanceIndex::class)
        ->middleware(EnsurePermission::class.':attendance.view')
        ->name('people.attendance');

    Route::get('/people/work-logs', WorkLogsIndex::class)
        ->middleware(EnsurePermission::class.':work_logs.view')
        ->name('people.work-logs');

    Route::get('/people/scorecard', ScorecardShow::class)
        ->middleware(EnsurePermission::class.':scorecards.view')
        ->name('people.scorecard');

    // Milestone 5 — Money
    Route::get('/money/revenues', RevenuesIndex::class)
        ->middleware(EnsurePermission::class.':revenue.view')
        ->name('money.revenues');

    Route::get('/money/expenses', ExpensesIndex::class)
        ->middleware(EnsurePermission::class.':expenses.view')
        ->name('money.expenses');

    Route::get('/money/pnl', ProfitLoss::class)
        ->middleware(EnsurePermission::class.':pnl.view')
        ->name('money.pnl');

    Route::get('/money/distributions', DistributionsIndex::class)
        ->middleware(EnsurePermission::class.':distributions.view')
        ->name('money.distributions');

    Route::get('/money/distributions/{run}', DistributionShow::class)
        ->middleware(EnsurePermission::class.':distributions.view')
        ->name('money.distributions.show');

    Route::get('/money/partners', PartnersIndex::class)
        ->middleware(EnsurePermission::class.':partners.view')
        ->name('money.partners');

    Route::get('/money/partners/statement/{user?}', PartnerStatement::class)
        ->middleware(EnsurePermission::class.':partners.statement')
        ->name('money.partners.statement');
});
