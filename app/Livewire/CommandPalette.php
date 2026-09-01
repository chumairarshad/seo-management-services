<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\Link;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Cmd/Ctrl-K console.
 *
 * Navigation commands render on first paint (filtered instantly in Alpine) so
 * the palette never waits on the network to be useful. Record search only runs
 * once the user has typed, and every query is permission scoped.
 */
class CommandPalette extends Component
{
    public string $q = '';

    public function clear(): void
    {
        $this->q = '';
    }

    public function render()
    {
        $user = Auth::user();
        $term = trim($this->q);
        $results = [];

        if ($user && mb_strlen($term) >= 2) {
            $like = '%'.$term.'%';

            if ($user->hasPermission('projects.view')) {
                $results[] = [
                    'group' => 'Projects',
                    'icon' => 'projects',
                    'rows' => Project::query()
                        ->accessibleBy($user)
                        ->where('domain', 'like', $like)
                        ->orderBy('domain')
                        ->limit(5)
                        ->get()
                        ->map(fn (Project $project) => [
                            'label' => $project->domain,
                            'meta' => $project->status->label(),
                            'href' => route('projects.show', $project),
                        ])->all(),
                ];
            }

            if ($user->hasPermission('tasks.view')) {
                $results[] = [
                    'group' => 'Tasks',
                    'icon' => 'tasks',
                    'rows' => Task::query()
                        ->with('project')
                        ->accessibleBy($user)
                        ->where('title', 'like', $like)
                        ->orderByDesc('updated_at')
                        ->limit(5)
                        ->get()
                        ->map(fn (Task $task) => [
                            'label' => $task->title,
                            'meta' => $task->project?->domain,
                            'href' => route('tasks.show', $task),
                        ])->all(),
                ];
            }

            if ($user->hasPermission('articles.view')) {
                $results[] = [
                    'group' => 'Articles',
                    'icon' => 'articles',
                    'rows' => Article::query()
                        ->with('project')
                        ->accessibleBy($user)
                        ->where('title', 'like', $like)
                        ->orderByDesc('updated_at')
                        ->limit(5)
                        ->get()
                        ->map(fn (Article $article) => [
                            'label' => $article->title,
                            'meta' => $article->project?->domain,
                            'href' => route('articles.index', ['search' => $article->title]),
                        ])->all(),
                ];
            }

            if ($user->hasPermission('links.view')) {
                $results[] = [
                    'group' => 'Links',
                    'icon' => 'links',
                    'rows' => Link::query()
                        ->with('project')
                        ->accessibleBy($user)
                        ->where('source_domain', 'like', $like)
                        ->orderByDesc('updated_at')
                        ->limit(5)
                        ->get()
                        ->map(fn (Link $link) => [
                            'label' => $link->source_domain,
                            'meta' => $link->project?->domain,
                            'href' => route('links.index', ['search' => $link->source_domain]),
                        ])->all(),
                ];
            }

            if ($user->hasAnyPermission('users.view', 'partners.view')) {
                $canSeePartners = $user->hasPermission('partners.view');

                $results[] = [
                    'group' => 'People',
                    'icon' => 'people',
                    'rows' => User::query()
                        ->where('name', 'like', $like)
                        ->orderBy('name')
                        ->limit(5)
                        ->get()
                        ->map(fn (User $person) => [
                            'label' => $person->name,
                            'meta' => $person->email,
                            'href' => $canSeePartners
                                ? route('money.partners.statement', $person)
                                : route('users.index', ['search' => $person->name]),
                        ])->all(),
                ];
            }
        }

        return view('livewire.command-palette', [
            'commands' => Navigation::flat($user),
            'quickCreate' => Navigation::quickCreate($user),
            'results' => collect($results)->filter(fn (array $group) => $group['rows'] !== [])->values(),
            'term' => $term,
        ]);
    }
}
