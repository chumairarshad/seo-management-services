<?php

namespace App\Console\Commands;

use App\Models\Credential;
use App\Models\User;
use App\Notifications\CredentialExpiringNotification;
use App\Support\AppSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class CheckCredentialExpiry extends Command
{
    protected $signature = 'credentials:check-expiry {--notify : Send notifications for matching thresholds}';

    protected $description = 'List credentials expiring within configured thresholds (30/14/7 by default)';

    public function handle(): int
    {
        $thresholds = AppSettings::get('credential_expiry_thresholds', [30, 14, 7]);
        if (! is_array($thresholds) || $thresholds === []) {
            $thresholds = [30, 14, 7];
        }
        $thresholds = array_values(array_unique(array_map('intval', $thresholds)));
        rsort($thresholds);

        $credentials = Credential::query()
            ->with('project')
            ->expiringWithin($thresholds)
            ->orderBy('expires_on')
            ->get();

        if ($credentials->isEmpty()) {
            $this->info('No credentials expiring within '.implode('/', $thresholds).' days.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Project', 'Label', 'Type', 'Expires', 'Days'],
            $credentials->map(fn (Credential $c) => [
                $c->id,
                $c->project?->domain ?? '—',
                $c->label,
                $c->type->value,
                $c->expires_on?->toDateString(),
                $c->daysUntilExpiry(),
            ])->all(),
        );

        if ($this->option('notify')) {
            $this->sendNotifications($credentials, $thresholds);
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Credential>  $credentials
     * @param  array<int, int>  $thresholds
     */
    protected function sendNotifications($credentials, array $thresholds): void
    {
        $emails = AppSettings::get('credential_expiry_notify_emails', []);
        if (! is_array($emails)) {
            $emails = [];
        }

        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($emails) {
                $q->whereHas('roles', fn ($r) => $r->where('name', 'admin'));
                if ($emails !== []) {
                    $q->orWhereIn('email', $emails);
                }
            })
            ->get()
            ->unique('id');

        if ($recipients->isEmpty()) {
            $this->warn('No notification recipients found.');

            return;
        }

        // Notify once per exact threshold day match (avoid spam); always include overdue
        $toNotify = $credentials->filter(function (Credential $c) use ($thresholds) {
            $days = $c->daysUntilExpiry();
            if ($days === null) {
                return false;
            }
            if ($days < 0) {
                return true;
            }

            return in_array($days, $thresholds, true);
        });

        if ($toNotify->isEmpty()) {
            $this->info('Nothing matched exact threshold days for notification.');

            return;
        }

        Notification::send($recipients, new CredentialExpiringNotification($toNotify, $thresholds));

        $this->info('Notified '.$recipients->count().' recipient(s) about '.$toNotify->count().' credential(s).');
    }
}
