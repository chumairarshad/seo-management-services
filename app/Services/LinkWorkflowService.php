<?php

namespace App\Services;

use App\Enums\LinkLiveStatus;
use App\Enums\LinkWorkflowStatus;
use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkWorkflowService
{
    public function __construct(
        protected ExpenseService $expenses,
    ) {}

    /**
     * @return array{link: Link, domain_warning: string|null}
     */
    public function create(array $data, User $actor): array
    {
        $domain = Link::extractDomain($data['source_url']);
        $warning = $this->duplicateDomainWarning((int) $data['project_id'], $domain);

        $link = Link::query()->create([
            ...$data,
            'source_domain' => $domain,
            'live_status' => $data['live_status'] ?? LinkLiveStatus::Live,
            'workflow_status' => $data['workflow_status'] ?? LinkWorkflowStatus::Pending,
            'created_by' => $actor->id,
        ]);

        return ['link' => $link, 'domain_warning' => $warning];
    }

    /**
     * @return array{link: Link, domain_warning: string|null}
     */
    public function update(Link $link, array $data): array
    {
        if (isset($data['source_url'])) {
            $data['source_domain'] = Link::extractDomain($data['source_url']);
        }

        $domain = $data['source_domain'] ?? $link->source_domain;
        $warning = $this->duplicateDomainWarning((int) ($data['project_id'] ?? $link->project_id), $domain, $link->id);

        $link->update($data);

        return ['link' => $link->fresh(), 'domain_warning' => $warning];
    }

    public function submit(Link $link): Link
    {
        $this->assertTransition($link, [LinkWorkflowStatus::Pending, LinkWorkflowStatus::Rejected], LinkWorkflowStatus::Submitted);

        $link->update([
            'workflow_status' => LinkWorkflowStatus::Submitted,
            'submitted_at' => now(),
            'rejection_reason' => null,
        ]);

        return $link->fresh();
    }

    public function approve(Link $link, User $actor): Link
    {
        return DB::transaction(function () use ($link, $actor) {
            $this->assertTransition($link, [LinkWorkflowStatus::Submitted, LinkWorkflowStatus::Pending], LinkWorkflowStatus::Approved);

            $link->update([
                'workflow_status' => LinkWorkflowStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $link = $link->fresh();
            $this->expenses->createFromLinkApproval($link, $actor);

            return $link->fresh();
        });
    }

    public function reject(Link $link, string $reason): Link
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is required.',
            ]);
        }

        $this->assertTransition($link, [LinkWorkflowStatus::Submitted, LinkWorkflowStatus::Pending], LinkWorkflowStatus::Rejected);

        $link->update([
            'workflow_status' => LinkWorkflowStatus::Rejected,
            'rejection_reason' => $reason,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return $link->fresh();
    }

    public function duplicateDomainWarning(int $projectId, string $domain, ?int $ignoreId = null): ?string
    {
        $query = Link::query()
            ->where('project_id', $projectId)
            ->where('source_domain', $domain);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            return "Warning: source domain “{$domain}” is already logged on this project.";
        }

        return null;
    }

    /**
     * @param  array<int, LinkWorkflowStatus>  $from
     */
    protected function assertTransition(Link $link, array $from, LinkWorkflowStatus $to): void
    {
        if (! in_array($link->workflow_status, $from, true)) {
            throw ValidationException::withMessages([
                'workflow_status' => "Cannot move link from {$link->workflow_status->label()} to {$to->label()}.",
            ]);
        }
    }
}
