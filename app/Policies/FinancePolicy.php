<?php

namespace App\Policies;

use App\Models\User;

/**
 * Generic money permission gates used by Livewire (model policies mirror these).
 */
class FinancePolicy
{
    public static function viewRevenue(User $user): bool
    {
        return $user->hasAnyPermission('revenue.view', 'revenue.manage');
    }

    public static function manageRevenue(User $user): bool
    {
        return $user->hasPermission('revenue.manage');
    }

    public static function viewExpenses(User $user): bool
    {
        return $user->hasAnyPermission('expenses.view', 'expenses.manage');
    }

    public static function manageExpenses(User $user): bool
    {
        return $user->hasPermission('expenses.manage');
    }

    public static function viewPnl(User $user): bool
    {
        return $user->hasPermission('pnl.view');
    }

    public static function viewDistributions(User $user): bool
    {
        return $user->hasAnyPermission('distributions.view', 'distributions.manage', 'distributions.approve');
    }

    public static function manageDistributions(User $user): bool
    {
        return $user->hasPermission('distributions.manage');
    }

    public static function approveDistributions(User $user): bool
    {
        return $user->hasPermission('distributions.approve');
    }

    public static function viewPartners(User $user): bool
    {
        return $user->hasAnyPermission('partners.view', 'partners.manage', 'partners.statement');
    }

    public static function managePartners(User $user): bool
    {
        return $user->hasPermission('partners.manage');
    }

    public static function export(User $user): bool
    {
        return $user->hasPermission('finance.export')
            || self::viewRevenue($user)
            || self::viewExpenses($user);
    }
}
