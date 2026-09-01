<?php

use App\Enums\DistributionStatus;
use App\Enums\PartnerLedgerType;
use App\Enums\RevenueSource;
use App\Models\PartnerLedgerEntry;
use App\Models\Project;
use App\Models\Revenue;
use App\Models\User;
use App\Services\DistributionService;
use App\Services\PartnerLedgerService;
use App\Services\ProjectOwnershipService;
use App\Support\Currency;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function distUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function distRevenue(Project $project, int $paisa, string $month, User $actor): void
{
    Revenue::query()->create([
        'project_id' => $project->id,
        'period_month' => $month.'-01',
        'source' => RevenueSource::Adsense,
        'amount_usd_cents' => 0,
        'fx_rate_e6' => 0,
        'amount_pkr_paisa' => $paisa,
        'currency_input' => Currency::code(),
        'created_by' => $actor->id,
    ]);
}

it('splits an odd amount without crediting more than the profit', function () {
    $admin = distUser('admin');
    $partner = distUser('partner');

    $project = Project::factory()->create(['domain' => 'odd-split.test', 'status' => 'monetized']);
    app(ProjectOwnershipService::class)->sync($project, [
        ['user_id' => $admin->id, 'share_bps' => 5000],
        ['user_id' => $partner->id, 'share_bps' => 5000],
    ]);

    // 101 paisa split 50/50 cannot divide evenly: half-up on each share
    // independently would hand out 51 + 51 = 102 and invent a paisa.
    distRevenue($project, 101, '2026-07', $admin);

    $run = app(DistributionService::class)->createDraft('2026-07', $admin, holdbackBps: 0);

    $lines = $run->lines;
    $net = (int) $lines->first()->net_profit_paisa;

    expect($net)->toBe(101)
        ->and((int) $lines->sum('gross_share_paisa'))->toBe(101)
        ->and((int) $lines->sum('credited_paisa') + (int) $lines->sum('holdback_paisa'))->toBe(101);
});

it('keeps every project split summing to that project net profit', function () {
    $admin = distUser('admin');
    $b = distUser('partner');
    $c = distUser('partner');

    $project = Project::factory()->create(['domain' => 'thirds.test', 'status' => 'monetized']);
    app(ProjectOwnershipService::class)->sync($project, [
        ['user_id' => $admin->id, 'share_bps' => 3333],
        ['user_id' => $b->id, 'share_bps' => 3333],
        ['user_id' => $c->id, 'share_bps' => 3334],
    ]);

    distRevenue($project, 1_000_01, '2026-08', $admin);

    $run = app(DistributionService::class)->createDraft('2026-08', $admin, holdbackBps: 0);

    expect((int) $run->lines->sum('gross_share_paisa'))->toBe(1_000_01)
        ->and((int) $run->total_credited_paisa)->toBe(1_000_01);
});

it('credits partners exactly the distributed total, to the paisa', function () {
    $admin = distUser('admin');
    $partner = distUser('partner');

    $project = Project::factory()->create(['domain' => 'exact.test', 'status' => 'monetized']);
    app(ProjectOwnershipService::class)->sync($project, [
        ['user_id' => $admin->id, 'share_bps' => 5000],
        ['user_id' => $partner->id, 'share_bps' => 5000],
    ]);

    distRevenue($project, 999, '2026-09', $admin);

    $dist = app(DistributionService::class);
    $approved = $dist->approve($dist->createDraft('2026-09', $admin, holdbackBps: 0), $admin);

    $credited = (int) PartnerLedgerEntry::query()
        ->where('distribution_run_id', $approved->id)
        ->where('type', PartnerLedgerType::ProfitCredit->value)
        ->sum('amount_paisa');

    expect($credited)->toBe(999)
        ->and((int) $approved->total_credited_paisa)->toBe(999);
});

it('keeps the approved ownership snapshot matching the shares the money used', function () {
    $admin = distUser('admin');
    $partner = distUser('partner');
    $latecomer = distUser('partner');

    $project = Project::factory()->create(['domain' => 'snapshot.test', 'status' => 'monetized']);
    app(ProjectOwnershipService::class)->sync($project, [
        ['user_id' => $admin->id, 'share_bps' => 6000],
        ['user_id' => $partner->id, 'share_bps' => 4000],
    ]);

    distRevenue($project, 100_000_00, '2026-10', $admin);

    $dist = app(DistributionService::class);
    $run = $dist->createDraft('2026-10', $admin, holdbackBps: 0);

    // Ownership changes after the draft is computed but before it is approved.
    app(ProjectOwnershipService::class)->sync($project, [
        ['user_id' => $admin->id, 'share_bps' => 3000],
        ['user_id' => $partner->id, 'share_bps' => 3000],
        ['user_id' => $latecomer->id, 'share_bps' => 4000],
    ]);

    $approved = $dist->approve($run->fresh(), $admin);

    // The snapshot is the provenance of the amounts, so it has to describe the
    // shares the lines were actually computed from — not today's ownership.
    $snapshot = collect($approved->ownership_snapshot[(string) $project->id]);

    expect($snapshot->pluck('share_bps')->sort()->values()->all())->toBe([4000, 6000])
        ->and($snapshot->pluck('user_id')->contains($latecomer->id))->toBeFalse();

    // And nobody who joined after the draft gets paid from this run.
    expect(PartnerLedgerEntry::query()
        ->where('distribution_run_id', $approved->id)
        ->where('user_id', $latecomer->id)
        ->exists())->toBeFalse();
});

it('will not credit the ledger twice when approve is submitted twice', function () {
    $admin = distUser('admin');
    $partner = distUser('partner');

    $project = Project::factory()->create(['domain' => 'double.test', 'status' => 'monetized']);
    app(ProjectOwnershipService::class)->sync($project, [
        ['user_id' => $admin->id, 'share_bps' => 5000],
        ['user_id' => $partner->id, 'share_bps' => 5000],
    ]);

    distRevenue($project, 50_000_00, '2026-11', $admin);

    $dist = app(DistributionService::class);
    $run = $dist->createDraft('2026-11', $admin, holdbackBps: 0);
    $approved = $dist->approve($run, $admin);

    // A double-submit re-runs approve against a run the caller still believes is
    // a draft; it must not post a second set of credits.
    expect(fn () => $dist->approve($run, $admin))->toThrow(RuntimeException::class);

    expect((int) PartnerLedgerEntry::query()
        ->where('distribution_run_id', $approved->id)
        ->where('type', PartnerLedgerType::ProfitCredit->value)
        ->sum('amount_paisa'))->toBe(50_000_00)
        ->and(app(PartnerLedgerService::class)->balanceFor($partner->id))->toBe(25_000_00);
});

it('refuses to approve a run that is no longer a draft', function () {
    $admin = distUser('admin');
    $project = Project::factory()->create(['domain' => 'status.test', 'status' => 'monetized']);
    app(ProjectOwnershipService::class)->sync($project, [
        ['user_id' => $admin->id, 'share_bps' => 10000],
    ]);

    distRevenue($project, 10_000_00, '2026-12', $admin);

    $dist = app(DistributionService::class);
    $run = $dist->createDraft('2026-12', $admin, holdbackBps: 0);
    $approved = $dist->approve($run, $admin);

    expect($approved->status)->toBe(DistributionStatus::Approved);
    expect(fn () => $dist->approve($approved->fresh(), $admin))->toThrow(RuntimeException::class);
});
